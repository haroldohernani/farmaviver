<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoEscala;
use App\Models\EscalaDiaria;
use App\Models\EscalaPeriodo;
use App\Models\Funcionario;
use App\Models\TrocaEscala;
use App\Models\TurnoHorario;
use App\Services\DatabaseProcedureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EscalaApiController extends Controller
{
    public function __construct(
        protected DatabaseProcedureService $dbService
    ) {}

    protected function empId(Request $request): int
    {
        return (int) ($request->input('emp_id') ?: $request->session()->get('emp_id', 1));
    }

    /**
     * Extrai mensagem amigável de exceções SQLSTATE '45000' (Triggers e Stored Procedures).
     */
    protected function extrairMensagemBanco(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (preg_match('/1644\s+(.+?)(\s+\(Connection:|$)/i', $msg, $matches)) {
            return trim($matches[1]);
        }
        return $msg;
    }

    // =========================================================================
    // 1. DASHBOARD / VISÃO DO DIA
    // =========================================================================
    public function dashboardDia(Request $request): JsonResponse
    {
        $empId = $this->empId($request);
        $dataConsulta = $request->query('data');

        if (!$dataConsulta) {
            $hoje = now()->toDateString();
            $temHoje = EscalaDiaria::where('esd_data', $hoje)
                ->whereHas('periodo', fn ($q) => $q->where('emp_id', $empId))
                ->exists();
            if ($temHoje) {
                $dataConsulta = $hoje;
            } else {
                // Caso hoje não esteja no período ativo (ex.: 31/10 e escala de Novembro), usa o 1º dia com escala da empresa
                $primeiraData = EscalaDiaria::whereHas('periodo', fn ($q) => $q->where('emp_id', $empId))
                    ->orderBy('esd_data')
                    ->value('esd_data');
                $dataConsulta = $primeiraData ? substr((string) $primeiraData, 0, 10) : $hoje;
            }
        }

        $registrosDia = $this->dbService->consultarEscalaDiariaCompleta([
            'emp_id' => $empId,
            'data' => $dataConsulta,
        ]);

        $trabalhando = collect($registrosDia)->where('esd_tipo_dia', 'trabalho')->values();
        $folga = collect($registrosDia)->whereIn('esd_tipo_dia', ['folga', 'dsr'])->values();
        $feriado = collect($registrosDia)->where('esd_tipo_dia', 'feriado')->values();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'data_selecionada' => $dataConsulta,
                'totais' => [
                    'total_escalados' => count($registrosDia),
                    'trabalhando' => $trabalhando->count(),
                    'folga' => $folga->count(),
                    'feriado' => $feriado->count(),
                ],
                'registros' => $registrosDia,
            ],
        ]);
    }

    // =========================================================================
    // 2. VISUALIZADOR DE ESCALA (MENSAL / SEMANAL) E AUDITORIA CLT ART. 386
    // =========================================================================
    public function visualizador(Request $request): JsonResponse
    {
        $empId = $this->empId($request);
        $espId = (int) $request->query('esp_id', 0);

        $periodo = $espId > 0
            ? EscalaPeriodo::find($espId)
            : EscalaPeriodo::where('emp_id', $empId)->orderByDesc('esp_data_inicio')->first();

        if (!$periodo) {
            return response()->json([
                'status' => 'ok',
                'data' => [
                    'periodo' => null,
                    'matriz' => [],
                    'colunas_dias' => [],
                    'resumo_mensal' => [],
                    'auditoria_mulheres' => [],
                    'diaria_completa' => [],
                ],
            ]);
        }

        try {
            $matriz = $this->dbService->relatorioEscalaMatriz($periodo->esp_id);
        } catch (\Throwable) {
            $matriz = [];
        }

        $colunasDias = [];
        if (!empty($matriz)) {
            foreach (array_keys($matriz[0]) as $col) {
                if (str_starts_with($col, 'D_')) {
                    $colunasDias[] = $col;
                }
            }
        }

        $filtrosDiaria = [
            'emp_id' => $empId,
            'data_inicio' => $request->query('data_inicio') ?: $periodo->esp_data_inicio->format('Y-m-d'),
            'data_fim' => $request->query('data_fim') ?: $periodo->esp_data_fim->format('Y-m-d'),
        ];

        if ($request->filled('fun_id')) {
            $filtrosDiaria['fun_id'] = (int) $request->query('fun_id');
        }

        $diariaCompleta = $this->dbService->consultarEscalaDiariaCompleta($filtrosDiaria);
        $resumoMensal = $this->dbService->consultarResumoMensalEscalas($periodo->esp_id);
        $auditoriaMulheres = $this->dbService->consultarAuditoriaDomingosMulheres($periodo->esp_id);

        return response()->json([
            'status' => 'ok',
            'data' => [
                'periodo' => [
                    'esp_id' => $periodo->esp_id,
                    'esp_descricao' => $periodo->esp_descricao,
                    'esp_data_inicio' => $periodo->esp_data_inicio->format('Y-m-d'),
                    'esp_data_fim' => $periodo->esp_data_fim->format('Y-m-d'),
                    'esp_status' => $periodo->esp_status,
                ],
                'matriz' => $matriz,
                'colunas_dias' => $colunasDias,
                'resumo_mensal' => $resumoMensal,
                'auditoria_mulheres' => $auditoriaMulheres,
                'diaria_completa' => $diariaCompleta,
            ],
        ]);
    }

    // =========================================================================
    // 3. GERADOR AUTOMÁTICO DE ESCALA 5x2 (sp_gerar_escala_5x2_funcionario)
    // =========================================================================
    public function gerarEscala5x2(Request $request): JsonResponse
    {
        $empId = $this->empId($request);

        $validated = $request->validate([
            'esp_id' => ['required', 'integer', 'exists:escalas_periodos,esp_id'],
            'cfg_id' => ['required', 'integer', 'min:0'],
            'modo' => ['required', Rule::in(['individual', 'todos'])],
            'fun_id' => ['nullable', 'integer', 'exists:funcionarios,fun_id'],
            'tur_id' => ['nullable', 'integer', 'exists:turnohorarios,tur_id'],
            'feriado_modo' => ['nullable', Rule::in(['expediente', 'folga'])],
        ]);

        $espId = (int) $validated['esp_id'];
        $cfgId = (int) $validated['cfg_id'];
        $turId = !empty($validated['tur_id']) ? (int) $validated['tur_id'] : null;
        $feriadoModo = $validated['feriado_modo'] ?? 'expediente';

        if ($cfgId > 0 && !ConfiguracaoEscala::where('cfg_id', $cfgId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'A regra 5x2 selecionada não foi encontrada.',
            ], 422);
        }

        // Garante que a empresa possua os turnos de Sábado (08h-20h) e Domingo/Feriado (08h-13h)
        // e as regras de revezamento 5x2 com folgas em dias de semana para cobertura do fim de semana
        $this->garantirEstruturaTurnosERegras($empId);

        if ($validated['modo'] === 'individual') {
            if (empty($validated['fun_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Selecione o funcionário para geração individual da escala 5x2.',
                ], 422);
            }
            $funcIndividual = Funcionario::with('cargo')->find((int) $validated['fun_id']);
            if ($funcIndividual && $funcIndividual->isAdministradorForaEscala()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Regra de Negócio: Funcionário(a) com Cargo Administrador, Perfil Administrador e Status Ativo não pode entrar na escala 5x2.',
                ], 422);
            }
            $funcionarios = collect($funcIndividual ? [$funcIndividual] : []);
        } else {
            $funcionarios = Funcionario::with('cargo')
                ->where('emp_id', $empId)
                ->where('fun_status', 'ativo')
                ->orderBy('fun_id')
                ->get()
                ->reject(fn (Funcionario $f) => $f->isAdministradorForaEscala())
                ->values();
        }

        if ($funcionarios->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nenhum funcionário ativo encontrado para gerar a escala.',
            ], 422);
        }

        // Busca configurações 5x2 de revezamento (folgas na semana) para cobrir Sábados (08h-20h) e Domingos/Feriados (08h-13h)
        $todasConfigs = ConfiguracaoEscala::where('emp_id', $empId)
            ->where('cfg_ativo', 1)
            ->orderBy('cfg_id')
            ->get();

        $configsRotacao = $todasConfigs->filter(function (ConfiguracaoEscala $c) {
            $f1 = (int) $c->cfg_dia_folga_1;
            $f2 = (int) $c->cfg_dia_folga_2;
            return !(in_array($f1, [1, 7], true) && in_array($f2, [1, 7], true));
        })->pluck('cfg_id')->values()->all();

        if (empty($configsRotacao)) {
            $configsRotacao = $todasConfigs->pluck('cfg_id')->all();
        }

        $cfgSelecionadaObj = $cfgId > 0 ? $todasConfigs->firstWhere('cfg_id', $cfgId) : null;
        $cfgSelecionadaEhSabDom = $cfgSelecionadaObj
            && in_array((int) $cfgSelecionadaObj->cfg_dia_folga_1, [1, 7], true)
            && in_array((int) $cfgSelecionadaObj->cfg_dia_folga_2, [1, 7], true);

        $gerados = [];
        try {
            DB::statement('SET @fv_feriado_modo = ?', [$feriadoModo]);

            foreach ($funcionarios as $idx => $func) {
                if ($cfgId === 0 || ($validated['modo'] === 'todos' && $cfgSelecionadaEhSabDom)) {
                    // Revezamento automático entre os pares de folga na semana para garantir cobertura aos Sábados e Domingos
                    $indiceRotacao = $validated['modo'] === 'todos' ? $idx : ((int) $func->fun_id);
                    $cfgAplicada = (int) $configsRotacao[$indiceRotacao % count($configsRotacao)];
                } else {
                    $cfgAplicada = $cfgId;
                }

                // Se modo 'todos' e não foi forçado turno único, usa o turno padrão de cada colaborador
                $turnoAplicado = $turId ?? $func->tur_id_padrao;

                $this->dbService->gerarEscala5x2Funcionario(
                    $espId,
                    (int) $func->fun_id,
                    $cfgAplicada,
                    $turnoAplicado ? (int) $turnoAplicado : null
                );

                $gerados[] = $func->fun_nome;
            }

            // Sugestão C (11/11/2026): Verificação pós-geração de cobertura obrigatória aos Sábados (08h-20h) e Domingos/Feriados (08h-13h)
            $this->garantirCoberturaFimDeSemanaEFeriados($espId, $empId, $feriadoModo);

            $auditoriaMulheres = $this->dbService->consultarAuditoriaDomingosMulheres($espId);

            return response()->json([
                'status' => 'ok',
                'message' => 'Escala 5x2 gerada com sucesso para ' . count($gerados) . ' colaborador(es) (Sábado: 08h às 20h | Domingo/Feriado: 08h às 13h | CLT Art. 386)!',
                'data' => [
                    'colaboradores_processados' => $gerados,
                    'auditoria_mulheres' => $auditoriaMulheres,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $this->extrairMensagemBanco($e),
            ], 422);
        }
    }

    /**
     * Garante que a empresa tenha os turnos especiais de Sábado (08h-20h) e Domingo/Feriado (08h-13h)
     * e as regras de revezamento 5x2 em dias de semana para cobertura contínua da farmácia.
     */
    protected function garantirEstruturaTurnosERegras(int $empId): void
    {
        $temSabado = TurnoHorario::where('emp_id', $empId)
            ->where('tur_entrada', '08:00:00')
            ->where('tur_saida', '20:00:00')
            ->exists();

        if (!$temSabado) {
            TurnoHorario::create([
                'emp_id' => $empId,
                'tur_descricao' => 'Expediente Sábado (08h às 20h)',
                'tur_entrada' => '08:00:00',
                'tur_saida_intervalo' => '12:00:00',
                'tur_retorno_intervalo' => '14:00:00',
                'tur_saida' => '20:00:00',
                'tur_tolerancia_minutos' => 10,
                'tur_ativo' => true,
            ]);
        }

        $temDomingo = TurnoHorario::where('emp_id', $empId)
            ->where('tur_entrada', '08:00:00')
            ->where('tur_saida', '13:00:00')
            ->exists();

        if (!$temDomingo) {
            TurnoHorario::create([
                'emp_id' => $empId,
                'tur_descricao' => 'Domingo e Feriado (08h às 13h)',
                'tur_entrada' => '08:00:00',
                'tur_saida_intervalo' => '10:30:00',
                'tur_retorno_intervalo' => '10:30:00',
                'tur_saida' => '13:00:00',
                'tur_tolerancia_minutos' => 10,
                'tur_ativo' => true,
            ]);
        }

        $regrasSemana = [
            ['cfg_nome' => '5x2 Revezamento Equipe A (Segunda e Terça)', 'cfg_tipo' => 'revezamento', 'f1' => 2, 'f2' => 3],
            ['cfg_nome' => '5x2 Revezamento Equipe B (Quarta e Quinta)', 'cfg_tipo' => 'revezamento', 'f1' => 4, 'f2' => 5],
            ['cfg_nome' => '5x2 Revezamento Equipe C (Quinta e Sexta)', 'cfg_tipo' => 'revezamento', 'f1' => 5, 'f2' => 6],
        ];

        foreach ($regrasSemana as $r) {
            $existePar = ConfiguracaoEscala::where('emp_id', $empId)
                ->where(function ($q) use ($r) {
                    $q->where(function ($q1) use ($r) {
                        $q1->where('cfg_dia_folga_1', $r['f1'])->where('cfg_dia_folga_2', $r['f2']);
                    })->orWhere(function ($q2) use ($r) {
                        $q2->where('cfg_dia_folga_1', $r['f2'])->where('cfg_dia_folga_2', $r['f1']);
                    });
                })
                ->exists();

            if (!$existePar) {
                ConfiguracaoEscala::create([
                    'emp_id' => $empId,
                    'cfg_nome' => $r['cfg_nome'],
                    'cfg_tipo' => $r['cfg_tipo'],
                    'cfg_dia_folga_1' => $r['f1'],
                    'cfg_dia_folga_2' => $r['f2'],
                    'cfg_ativo' => true,
                ]);
            }
        }
    }

    /**
     * Garante que nenhum Sábado (08h-20h), Domingo (08h-13h) ou Feriado com expediente (08h-13h)
     * fique com 0 colaboradores trabalhando no período, preservando o Art. 386 da CLT.
     */
    protected function garantirCoberturaFimDeSemanaEFeriados(int $espId, int $empId, string $feriadoModo = 'expediente'): void
    {
        $turSabado = TurnoHorario::where('emp_id', $empId)
            ->where('tur_ativo', 1)
            ->where('tur_entrada', '08:00:00')
            ->where('tur_saida', '20:00:00')
            ->value('tur_id');

        $turDomFer = TurnoHorario::where('emp_id', $empId)
            ->where('tur_ativo', 1)
            ->where('tur_entrada', '08:00:00')
            ->where('tur_saida', '13:00:00')
            ->value('tur_id');

        if (!$turSabado || !$turDomFer) {
            return;
        }

        // 1. Garante que todo registro de TRABALHO aos Sábados use o turno 08h-20h
        // e todo registro de TRABALHO aos Domingos ou Feriados use o turno 08h-13h
        DB::statement("
            UPDATE escalas_diarias ed
            LEFT JOIN feriados fer ON fer.fer_data = ed.esd_data AND (fer.emp_id IS NULL OR fer.emp_id = ?)
            SET ed.tur_id = ?,
                ed.esd_observacao = 'Expediente Sábado (08h às 20h)'
            WHERE ed.esp_id = ?
              AND ed.esd_tipo_dia = 'trabalho'
              AND DAYOFWEEK(ed.esd_data) = 7
              AND fer.fer_id IS NULL
              AND ed.tur_id <> ?
        ", [$empId, $turSabado, $espId, $turSabado]);

        DB::statement("
            UPDATE escalas_diarias ed
            LEFT JOIN feriados fer ON fer.fer_data = ed.esd_data AND (fer.emp_id IS NULL OR fer.emp_id = ?)
            SET ed.tur_id = ?,
                ed.esd_observacao = CASE
                    WHEN fer.fer_id IS NOT NULL THEN CONCAT('Expediente Feriado (08h às 13h): ', COALESCE(fer.fer_descricao, 'Feriado'))
                    ELSE 'Expediente Domingo (08h às 13h)'
                END
            WHERE ed.esp_id = ?
              AND ed.esd_tipo_dia = 'trabalho'
              AND (DAYOFWEEK(ed.esd_data) = 1 OR fer.fer_id IS NOT NULL)
              AND ed.tur_id <> ?
        ", [$empId, $turDomFer, $espId, $turDomFer]);

        // 2. Verifica se algum Sábado, Domingo ou Feriado (com expediente) ficou sem nenhum colaborador em TRABALHO
        $datasSemCobertura = DB::select("
            SELECT
                ed.esd_data,
                DAYOFWEEK(ed.esd_data) AS dow,
                CASE WHEN MAX(fer.fer_id) IS NOT NULL THEN 1 ELSE 0 END AS eh_feriado,
                MAX(fer.fer_descricao) AS desc_feriado,
                SUM(CASE WHEN ed.esd_tipo_dia = 'trabalho' THEN 1 ELSE 0 END) AS total_trabalhando
            FROM escalas_diarias ed
            LEFT JOIN feriados fer ON fer.fer_data = ed.esd_data AND (fer.emp_id IS NULL OR fer.emp_id = ?)
            WHERE ed.esp_id = ?
            GROUP BY ed.esd_data
            HAVING total_trabalhando = 0
               AND (dow IN (1, 7) OR (eh_feriado = 1 AND ? = 'expediente'))
            ORDER BY ed.esd_data ASC
        ", [$empId, $espId, $feriadoModo]);

        foreach ($datasSemCobertura as $dia) {
            $dataAlvo = $dia->esd_data;
            $ehSabado = ((int) $dia->dow === 7) && ((int) $dia->eh_feriado === 0);
            $turnoAlvo = $ehSabado ? $turSabado : $turDomFer;
            $obsAlvo = $ehSabado
                ? 'Expediente Sábado (08h às 20h) - Revezamento'
                : (((int) $dia->eh_feriado === 1)
                    ? 'Expediente Feriado (08h às 13h): ' . ($dia->desc_feriado ?: 'Feriado')
                    : 'Expediente Domingo (08h às 13h) - Revezamento');

            // Seleciona candidatos da escala naquele dia priorizando homens aos domingos ou mulheres com > 2 domingos de folga no mês
            $candidatos = DB::select("
                SELECT
                    ed.esd_id,
                    ed.fun_id,
                    f.fun_sexo,
                    (
                        SELECT COUNT(*)
                        FROM escalas_diarias ed_dom
                        WHERE ed_dom.esp_id = ed.esp_id
                          AND ed_dom.fun_id = ed.fun_id
                          AND DAYOFWEEK(ed_dom.esd_data) = 1
                          AND ed_dom.esd_tipo_dia IN ('folga', 'dsr', 'feriado')
                    ) AS domingos_folga
                FROM escalas_diarias ed
                INNER JOIN funcionarios f ON f.fun_id = ed.fun_id
                WHERE ed.esp_id = ?
                  AND ed.esd_data = ?
                ORDER BY
                    CASE WHEN ? = 1 AND f.fun_sexo = 'M' THEN 0
                         WHEN ? = 1 AND f.fun_sexo = 'F' THEN 1
                         ELSE 0 END ASC,
                    domingos_folga DESC,
                    ed.fun_id ASC
            ", [$espId, $dataAlvo, (int) $dia->dow, (int) $dia->dow]);

            $escaladosNoDia = 0;
            foreach ($candidatos as $cand) {
                // Se for domingo e colaboradora mulher tiver apenas 2 domingos de folga, preserva o Art. 386 se houver outros candidatos
                if ((int) $dia->dow === 1 && $cand->fun_sexo === 'F' && (int) $cand->domingos_folga <= 2 && count($candidatos) > 1 && $escaladosNoDia > 0) {
                    continue;
                }

                try {
                    DB::update("
                        UPDATE escalas_diarias
                        SET esd_tipo_dia = 'trabalho',
                            tur_id = ?,
                            esd_observacao = ?
                        WHERE esd_id = ?
                    ", [$turnoAlvo, $obsAlvo, $cand->esd_id]);
                    $escaladosNoDia++;
                    if ($escaladosNoDia >= 2) {
                        break;
                    }
                } catch (\Throwable $ignore) {
                    // Caso o colaborador tenha saído às 23:00 na véspera (interjornada < 11h), tenta o próximo colaborador
                    continue;
                }
            }
        }
    }

    // =========================================================================
    // 4. EDIÇÃO PONTUAL DE ESCALA DIÁRIA (Com validação de Triggers CLT Art. 66)
    // =========================================================================
    public function atualizarEscalaDiaria(Request $request, int $id): JsonResponse
    {
        $escalaDia = EscalaDiaria::findOrFail($id);

        $validated = $request->validate([
            'esd_tipo_dia' => ['required', Rule::in(['trabalho', 'folga', 'dsr', 'feriado'])],
            'tur_id' => ['nullable', 'integer', 'exists:turnohorarios,tur_id'],
            'esd_observacao' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['esd_tipo_dia'] === 'trabalho' && empty($validated['tur_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Para dia de "trabalho", é obrigatório selecionar o Turno/Horário.',
            ], 422);
        }

        if ($validated['esd_tipo_dia'] !== 'trabalho') {
            $validated['tur_id'] = null;
        }

        try {
            $escalaDia->update($validated);

            return response()->json([
                'status' => 'ok',
                'message' => 'Dia da escala atualizado com sucesso (Interjornada CLT Art. 66 validada)!',
                'data' => $escalaDia->load(['funcionario', 'turno']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $this->extrairMensagemBanco($e),
            ], 422);
        }
    }

    // =========================================================================
    // 5. AFASTAMENTO E TROCAS PONTUAIS (trocas_escala & sp_aprovar_troca_escala)
    // =========================================================================
    public function listarTrocasEAfastamentos(Request $request): JsonResponse
    {
        $empId = $this->empId($request);

        $trocas = TrocaEscala::with([
            'solicitante.cargo',
            'substituto.cargo',
            'escalaOrigem.turno',
            'escalaDestino.turno',
            'aprovador',
        ])
            ->whereHas('solicitante', fn ($q) => $q->where('emp_id', $empId))
            ->orderByDesc('tro_id')
            ->get();

        $afastados = Funcionario::with('cargo')
            ->where('emp_id', $empId)
            ->whereIn('fun_status', ['afastado', 'ferias', 'desligado'])
            ->orderBy('fun_nome')
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'trocas' => $trocas,
                'afastados' => $afastados,
            ],
        ]);
    }

    public function buscarDiasEscalaFuncionario(Request $request, int $funId): JsonResponse
    {
        $dias = EscalaDiaria::with('turno')
            ->where('fun_id', $funId)
            ->orderBy('esd_data')
            ->get()
            ->map(fn ($d) => [
                'esd_id' => $d->esd_id,
                'esd_data' => $d->esd_data->format('Y-m-d'),
                'esd_data_br' => $d->esd_data->format('d/m/Y'),
                'esd_tipo_dia' => $d->esd_tipo_dia,
                'tur_id' => $d->tur_id,
                'tur_descricao' => $d->turno?->tur_descricao ?? strtoupper($d->esd_tipo_dia),
            ]);

        return response()->json([
            'status' => 'ok',
            'data' => $dias,
        ]);
    }

    public function solicitarTroca(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fun_id_solicitante' => ['required', 'integer', 'exists:funcionarios,fun_id'],
            'fun_id_substituto' => ['required', 'integer', 'exists:funcionarios,fun_id', 'different:fun_id_solicitante'],
            'esd_id_origem' => ['required', 'integer', 'exists:escalas_diarias,esd_id'],
            'esd_id_destino' => ['nullable', 'integer', 'exists:escalas_diarias,esd_id'],
            'tro_motivo' => ['required', 'string', 'max:255'],
        ], [
            'fun_id_substituto.different' => 'O funcionário substituto deve ser diferente do solicitante.',
        ]);

        $validated['tro_status'] = 'pendente';

        $troca = TrocaEscala::create($validated);

        return response()->json([
            'status' => 'ok',
            'message' => 'Solicitação de troca pontual registrada com sucesso!',
            'data' => $troca->load(['solicitante', 'substituto', 'escalaOrigem.turno', 'escalaDestino.turno']),
        ]);
    }

    public function aprovarTroca(Request $request, int $id): JsonResponse
    {
        $gestorId = (int) ($request->session()->get('fun_id') ?: 1);

        try {
            $this->dbService->aprovarTrocaEscala($id, $gestorId);

            return response()->json([
                'status' => 'ok',
                'message' => 'Troca aprovada e processada na escala via sp_aprovar_troca_escala!',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $this->extrairMensagemBanco($e),
            ], 422);
        }
    }

    public function alterarStatusTroca(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tro_status' => ['required', Rule::in(['recusada', 'cancelada'])],
        ]);

        $troca = TrocaEscala::findOrFail($id);
        $troca->update([
            'tro_status' => $validated['tro_status'],
            'tro_aprovador_fun_id' => (int) ($request->session()->get('fun_id') ?: 1),
            'tro_aprovado_em' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Status da solicitação de troca atualizado para "' . $validated['tro_status'] . '".',
        ]);
    }

    public function registrarAfastamento(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fun_id' => ['required', 'integer', 'exists:funcionarios,fun_id'],
            'fun_status' => ['required', Rule::in(['ativo', 'afastado', 'ferias', 'desligado'])],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ]);

        DB::beginTransaction();
        try {
            $func = Funcionario::findOrFail((int) $validated['fun_id']);
            $func->update([
                'fun_status' => $validated['fun_status'],
            ]);

            $diasAfetados = 0;
            if (!empty($validated['data_inicio']) && !empty($validated['data_fim']) && in_array($validated['fun_status'], ['afastado', 'ferias'], true)) {
                $obs = $validated['observacao'] ?: ('Colaborador em ' . strtoupper($validated['fun_status']));
                $diasAfetados = EscalaDiaria::where('fun_id', $func->fun_id)
                    ->whereBetween('esd_data', [$validated['data_inicio'], $validated['data_fim']])
                    ->update([
                        'esd_tipo_dia' => 'folga',
                        'tur_id' => null,
                        'esd_observacao' => $obs,
                    ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'ok',
                'message' => "Status de {$func->fun_nome} atualizado para '{$validated['fun_status']}'" . ($diasAfetados > 0 ? " ({$diasAfetados} dia(s) ajustados na escala)." : '.'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao registrar afastamento: ' . $this->extrairMensagemBanco($e),
            ], 422);
        }
    }
}

