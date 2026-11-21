<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracaoEscala;
use App\Models\EscalaDiaria;
use App\Models\EscalaPeriodo;
use App\Models\Feriado;
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

        $manhaCount = $trabalhando->filter(function ($r) {
            $arr = (array) $r;
            $desc = mb_strtolower((string) ($arr['tur_descricao'] ?? ''));
            $ent = substr((string) ($arr['tur_entrada'] ?? ''), 0, 5);
            $sai = substr((string) ($arr['tur_saida'] ?? ''), 0, 5);
            if ($ent === '08:00' && in_array($sai, ['13:00', '20:00'], true)) {
                return false;
            }
            if (str_contains($desc, 'abertura') || str_contains($desc, 'manhã') || str_contains($desc, 'manha')) {
                return true;
            }
            if (str_contains($desc, 'fechamento') || str_contains($desc, 'noite') || str_contains($desc, 'tarde')) {
                return false;
            }
            return $ent !== '' && $ent < '10:00';
        })->count();

        $noiteCount = $trabalhando->filter(function ($r) {
            $arr = (array) $r;
            $desc = mb_strtolower((string) ($arr['tur_descricao'] ?? ''));
            $ent = substr((string) ($arr['tur_entrada'] ?? ''), 0, 5);
            $sai = substr((string) ($arr['tur_saida'] ?? ''), 0, 5);
            if ($ent === '08:00' && in_array($sai, ['13:00', '20:00'], true)) {
                return false;
            }
            if (str_contains($desc, 'fechamento') || str_contains($desc, 'noite') || str_contains($desc, 'tarde')) {
                return true;
            }
            if (str_contains($desc, 'abertura') || str_contains($desc, 'manhã') || str_contains($desc, 'manha')) {
                return false;
            }
            return $ent >= '10:00';
        })->count();

        $plantaoCount = max(0, $trabalhando->count() - $manhaCount - $noiteCount);

        return response()->json([
            'status' => 'ok',
            'data' => [
                'data_selecionada' => $dataConsulta,
                'totais' => [
                    'total_escalados' => count($registrosDia),
                    'trabalhando' => $trabalhando->count(),
                    'manha_abertura' => $manhaCount,
                    'noite_fechamento' => $noiteCount,
                    'plantao_especial' => $plantaoCount,
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

        // Busca configurações 5x2 de revezamento (folgas desencontradas) para cobrir Abertura/Fechamento na semana, Sábados (08h-20h) e Domingos/Feriados (08h-13h)
        $todasConfigs = ConfiguracaoEscala::where('emp_id', $empId)
            ->where('cfg_ativo', 1)
            ->orderBy('cfg_id')
            ->get();

        $configsRotacao = $todasConfigs->filter(function (ConfiguracaoEscala $c) {
            $f1 = (int) $c->cfg_dia_folga_1;
            $f2 = (int) $c->cfg_dia_folga_2;
            return !(in_array($f1, [1, 7], true) && in_array($f2, [1, 7], true));
        })->sortBy(fn (ConfiguracaoEscala $c) => (int) $c->cfg_dia_folga_1)->pluck('cfg_id')->values()->all();

        if (empty($configsRotacao)) {
            $configsRotacao = $todasConfigs->pluck('cfg_id')->all();
        }

        $cfgSelecionadaObj = $cfgId > 0 ? $todasConfigs->firstWhere('cfg_id', $cfgId) : null;
        $cfgSelecionadaEhSabDom = $cfgSelecionadaObj
            && in_array((int) $cfgSelecionadaObj->cfg_dia_folga_1, [1, 7], true)
            && in_array((int) $cfgSelecionadaObj->cfg_dia_folga_2, [1, 7], true);

        [$turManha, $turNoite] = $this->obterTurnosAberturaEFechamento($empId);

        // Sugestão A (13/11/2026): No modo 'todos' sem turno único forçado, equilibra 50% Abertura (Manhã) e 50% Fechamento (Noite) por cargo
        if ($validated['modo'] === 'todos' && !$turId && $turManha && $turNoite) {
            $this->balancearTurnosPadraoEquipe($funcionarios, $turManha, $turNoite);
        }

        // Item 1 / 1.1 (13/11/2026): Separa os colaboradores por turno (Manhã vs Fechamento/Noite)
        // para distribuir as regras de folga independentemente dentro de cada turno, sem colisão no mesmo dia útil
        $mapaCfgPorFuncionario = [];
        if ($cfgId === 0 || ($validated['modo'] === 'todos' && $cfgSelecionadaEhSabDom)) {
            $porTurno = $funcionarios->groupBy(fn (Funcionario $f) => (int) ($turId ?? $f->tur_id_padrao ?? 0));
            foreach ($porTurno as $grupoTurno) {
                // Ordena homens primeiro para os pares [Seg/Ter] e [Qua/Qui] e mulheres para [Sex/Sáb],
                // evitando que a compensação dominical (Art. 386) desfalque os dias de semana
                $ordenadosNoTurno = $grupoTurno->sortBy(function (Funcionario $f) {
                    return ($f->fun_sexo === 'M' ? '0_' : '1_') . str_pad((string) $f->fun_id, 6, '0', STR_PAD_LEFT);
                })->values();

                foreach ($ordenadosNoTurno as $idxTurno => $fItem) {
                    $mapaCfgPorFuncionario[(int) $fItem->fun_id] = (int) $configsRotacao[$idxTurno % count($configsRotacao)];
                }
            }
        }

        $gerados = [];
        try {
            DB::statement('SET @fv_feriado_modo = ?', [$feriadoModo]);

            // Sugestão C (15/11/2026): No modo 'todos', remove registros residuais do período pertencentes a colaboradores inativos/férias/fora da escala
            if ($validated['modo'] === 'todos') {
                $idsAtivosEscala = $funcionarios->pluck('fun_id')->map(fn ($id) => (int) $id)->all();
                EscalaDiaria::where('esp_id', $espId)
                    ->whereNotIn('fun_id', $idsAtivosEscala)
                    ->delete();
            }

            foreach ($funcionarios as $idx => $func) {
                if (isset($mapaCfgPorFuncionario[(int) $func->fun_id])) {
                    $cfgAplicada = $mapaCfgPorFuncionario[(int) $func->fun_id];
                } elseif ($cfgId === 0) {
                    $cfgAplicada = (int) $configsRotacao[((int) $func->fun_id) % count($configsRotacao)];
                } else {
                    $cfgAplicada = $cfgId;
                }

                // Se modo 'todos' e não foi forçado turno único, usa o turno padrão equilibrado de cada colaborador
                $turnoAplicado = $turId ?? $func->tur_id_padrao;

                $this->dbService->gerarEscala5x2Funcionario(
                    $espId,
                    (int) $func->fun_id,
                    $cfgAplicada,
                    $turnoAplicado ? (int) $turnoAplicado : null
                );

                $gerados[] = $func->fun_nome;
            }

            // Item 1 + Sugestão A (15/11/2026): Garante no máximo 1 funcionário de folga por dia de Segunda a Sábado (exceto feriados)
            // e no máximo 2 funcionários de folga nos Domingos e Feriados (nunca 3), preservando o Art. 386 da CLT e o equilíbrio Manhã/Fechamento
            $this->garantirLimitesDiariosDeFolga($espId, $empId, $feriadoModo, $turId, $turManha, $turNoite);

            // Sugestão C (11/11/2026): Verificação pós-geração de cobertura obrigatória aos Sábados (08h-20h) e Domingos/Feriados (08h-13h)
            $this->garantirCoberturaFimDeSemanaEFeriados($espId, $empId, $feriadoModo);

            // Sugestão B (13/11/2026): Trava pós-geração de equilíbrio diário entre Turno Abertura (Manhã) e Turno Fechamento (Noite) de Segunda a Sexta
            if (!$turId && $turManha && $turNoite) {
                $this->garantirEquilibrioTurnosSemana($espId, $empId, $turManha, $turNoite);
            }

            $auditoriaMulheres = $this->dbService->consultarAuditoriaDomingosMulheres($espId);

            return response()->json([
                'status' => 'ok',
                'message' => 'Escala 5x2 gerada com sucesso para ' . count($gerados) . ' colaborador(es) (Máx. 1 folga Seg-Sáb | Máx. 2 folgas Dom/Feriado | CLT Art. 386)!',
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
     * e as regras de revezamento 5x2 sem sobreposição nos dias de semana.
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

        // Atualiza eventual regra antiga "Equipe C (Quinta e Sexta)" [5,6] que colidia na Quinta-feira com a Equipe B [4,5]
        ConfiguracaoEscala::where('emp_id', $empId)
            ->where('cfg_dia_folga_1', 5)
            ->where('cfg_dia_folga_2', 6)
            ->update([
                'cfg_nome' => '5x2 Revezamento Equipe C (Sexta e Sábado)',
                'cfg_tipo' => 'revezamento',
                'cfg_dia_folga_1' => 6,
                'cfg_dia_folga_2' => 7,
            ]);

        $regrasSemana = [
            ['cfg_nome' => '5x2 Revezamento Seg (Domingo e Segunda)', 'cfg_tipo' => 'revezamento', 'f1' => 1, 'f2' => 2],
            ['cfg_nome' => '5x2 Revezamento Ter (Domingo e Terça)',   'cfg_tipo' => 'revezamento', 'f1' => 1, 'f2' => 3],
            ['cfg_nome' => '5x2 Revezamento Qua (Domingo e Quarta)',  'cfg_tipo' => 'revezamento', 'f1' => 1, 'f2' => 4],
            ['cfg_nome' => '5x2 Revezamento Qui (Domingo e Quinta)',  'cfg_tipo' => 'revezamento', 'f1' => 1, 'f2' => 5],
            ['cfg_nome' => '5x2 Revezamento Sex (Domingo e Sexta)',   'cfg_tipo' => 'revezamento', 'f1' => 1, 'f2' => 6],
            ['cfg_nome' => '5x2 Revezamento Sáb (Domingo e Sábado)',  'cfg_tipo' => 'revezamento', 'f1' => 1, 'f2' => 7],
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
     * Verifica se o funcionário pertence ao cargo de Farmacêutico(a).
     */
    protected function ehCargoFarmaceutico(?Funcionario $func): bool
    {
        if (!$func) {
            return false;
        }
        $nomeCargo = mb_strtolower(trim((string) ($func->cargo?->car_nome ?? '')), 'UTF-8');
        return str_starts_with($nomeCargo, 'farmacêutic') || str_starts_with($nomeCargo, 'farmaceutic');
    }

    /**
     * Recalcula os limites diários de folga, cobertura de fim de semana/feriados e equilíbrio de turnos
     * para um período já gerado (utilizado também ao ativar/inativar feriados com fer_status = 1/0).
     */
    public function recalcularLimitesPeriodo(int $espId, int $empId): void
    {
        if (!EscalaDiaria::where('esp_id', $espId)->exists()) {
            return;
        }
        [$turManha, $turNoite] = $this->obterTurnosAberturaEFechamento($empId);
        $this->garantirLimitesDiariosDeFolga($espId, $empId, 'expediente', null, $turManha, $turNoite);
        $this->garantirCoberturaFimDeSemanaEFeriados($espId, $empId, 'expediente');
        if ($turManha && $turNoite) {
            $this->garantirEquilibrioTurnosSemana($espId, $empId, $turManha, $turNoite);
        }
    }

    /**
     * Item 1 + Sugestão A (15/11/2026) + Itens 1 e 2 + Sugestão A (20/11/2026):
     * Garante rigorosamente na escala gerada:
     * 1. NUNCA 2 funcionários com o cargo Farmacêutico(a) tiram folga/DSR/feriado no mesmo dia
     *    (nem de segunda a sábado, nem aos domingos, nem nos feriados).
     * 2. NUNCA mais de 1 funcionário tira `folga` (esd_tipo_dia = 'folga') no mesmo dia:
     *    - De Segunda a Sábado (exceto feriados ativos com fer_status = 1): apenas 1 funcionário descansa ('folga') e 5 trabalham.
     *    - Nos Domingos: 1 funcionário em 'folga' e 1 funcionário em 'dsr' (cumprindo os 2 domingos mensais do Art. 386 da CLT para todas as mulheres, sem 2 registros de 'folga' no mesmo dia e sem 2 Farmacêuticos descansando juntos).
     *    - Nos Feriados ativos (fer_status = 1): 1 funcionário em 'folga' (folguista do dia) e 1 funcionário em 'feriado' (sem 2 Farmacêuticos descansando juntos).
     */
    protected function garantirLimitesDiariosDeFolga(
        int $espId,
        int $empId,
        string $feriadoModo = 'expediente',
        ?int $turIdForcado = null,
        ?int $turManha = null,
        ?int $turNoite = null
    ): void {
        $periodo = EscalaPeriodo::find($espId);
        if (!$periodo) {
            return;
        }

        $funcsEscala = Funcionario::with('cargo')
            ->where('emp_id', $empId)
            ->where('fun_status', 'ativo')
            ->whereIn('fun_id', function ($q) use ($espId) {
                $q->select('fun_id')->from('escalas_diarias')->where('esp_id', $espId);
            })
            ->orderBy('fun_id')
            ->get()
            ->reject(fn (Funcionario $f) => $f->isAdministradorForaEscala())
            ->values();

        $totalFuncs = $funcsEscala->count();
        if ($totalFuncs < 2) {
            return;
        }

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

        // 1. Intercala colaboradores da Manhã e do Fechamento para atribuir 1 dia exclusivo de folga de Segunda (2) a Sábado (7)
        // Ordena cada turno colocando cargos de 1 pessoa no turno (ex.: Caixa, Farmacêutico) nos dias úteis centrais e Balconistas na Sexta/Sábado
        $contagemPorCargo = $funcsEscala->countBy('car_id')->all();
        $ordenarGrupoTurno = function ($colecao) use ($contagemPorCargo) {
            return $colecao->sortBy(function (Funcionario $f) use ($contagemPorCargo) {
                $qtdCargo = $contagemPorCargo[$f->car_id] ?? 1;
                // Ordem: 1º cargo único (ex.: Caixa), 2º cargo duplo (ex.: Farmacêuticos), 3º cargo triplo (ex.: Balconistas)
                return str_pad((string) $qtdCargo, 3, '0', STR_PAD_LEFT)
                    . '_' . ($f->fun_sexo === 'M' ? '0' : '1')
                    . '_' . str_pad((string) $f->fun_id, 6, '0', STR_PAD_LEFT);
            })->values();
        };

        $grupoManha = $ordenarGrupoTurno(
            $funcsEscala->filter(fn (Funcionario $f) => !$turNoite || (int) $f->tur_id_padrao !== (int) $turNoite)
        );
        $grupoNoite = $ordenarGrupoTurno(
            $funcsEscala->filter(fn (Funcionario $f) => $turNoite && (int) $f->tur_id_padrao === (int) $turNoite)
        );

        $intercalados = collect();
        $maxLen = max($grupoManha->count(), $grupoNoite->count());
        for ($i = 0; $i < $maxLen; $i++) {
            if (isset($grupoManha[$i])) {
                $intercalados->push($grupoManha[$i]);
            }
            if (isset($grupoNoite[$i])) {
                $intercalados->push($grupoNoite[$i]);
            }
        }

        // Mapeia o dia exclusivo de Segunda (2) a Sábado (7) para cada colaborador (no máximo 1 colaborador por dia útil/sábado)
        $diasSemanaDisponiveis = [2, 3, 4, 5, 6, 7];
        $mapaDiaFolgaSemana = [];
        foreach ($intercalados as $idx => $f) {
            if ($idx < count($diasSemanaDisponiveis)) {
                $mapaDiaFolgaSemana[(int) $f->fun_id] = $diasSemanaDisponiveis[$idx];
            } else {
                $mapaDiaFolgaSemana[(int) $f->fun_id] = null;
            }
        }

        // 2. Identifica todos os Domingos e Feriados ATIVOS (fer_status = 1) do período (Item 2 - 17/11/2026)
        $dataIni = $periodo->esp_data_inicio->format('Y-m-d');
        $dataFim = $periodo->esp_data_fim->format('Y-m-d');

        $feriadosMap = Feriado::where('emp_id', $empId)
            ->where('fer_status', 1)
            ->whereBetween('fer_data', [$dataIni, $dataFim])
            ->get()
            ->keyBy(fn (Feriado $f) => $f->fer_data instanceof \Carbon\CarbonInterface
                ? $f->fer_data->format('Y-m-d')
                : substr((string) $f->fer_data, 0, 10));

        $datasPeriodo = [];
        $domingosPeriodo = [];
        $cursor = \Carbon\Carbon::parse($dataIni);
        $fimCarbon = \Carbon\Carbon::parse($dataFim);
        while ($cursor->lte($fimCarbon)) {
            $dtIso = $cursor->format('Y-m-d');
            $dow = ((int) $cursor->dayOfWeek) + 1; // Carbon: 0=Dom..6=Sáb -> MariaDB DAYOFWEEK: 1=Dom..7=Sáb
            $datasPeriodo[$dtIso] = $dow;
            if ($dow === 1) {
                $domingosPeriodo[] = $dtIso;
            }
            $cursor->addDay();
        }

        // 3. Planeja as folgas aos Domingos: MÁXIMO 2 funcionários de folga por domingo (nunca 3!)
        $maxFolgasDomFer = min(2, max(1, $totalFuncs - 1));
        $folgasPorDomingo = [];
        foreach ($domingosPeriodo as $dtDom) {
            $folgasPorDomingo[$dtDom] = [];
        }

        $contagemExtras = [];
        foreach ($funcsEscala as $f) {
            $contagemExtras[(int) $f->fun_id] = 0;
        }

        $funcsPorId = $funcsEscala->keyBy(fn (Funcionario $f) => (int) $f->fun_id);
        $jaTemFarmaceuticoNoDia = function (array $idsNoDia) use ($funcsPorId): bool {
            foreach ($idsNoDia as $idExistente) {
                $fExist = $funcsPorId->get((int) $idExistente);
                if ($this->ehCargoFarmaceutico($fExist)) {
                    return true;
                }
            }
            return false;
        };

        // 3.1 Garante primeiro os 2 domingos de folga/DSR no mês para cada colaboradora mulher (CLT Art. 386)
        // sem exceder 2 descansos por domingo (1 'folga' + 1 'dsr') e NUNCA juntando 2 Farmacêuticos no mesmo domingo
        $mulheres = $funcsEscala->where('fun_sexo', 'F')->values();
        $qtdDomingos = count($domingosPeriodo);
        if ($qtdDomingos > 0) {
            foreach ($mulheres as $mIdx => $mulher) {
                $funIdM = (int) $mulher->fun_id;
                $mulherEhFarm = $this->ehCargoFarmaceutico($mulher);
                $metaDomingosMulher = min(2, $qtdDomingos);
                $atribuidos = 0;

                // Primeira passagem: prefere domingos não consecutivos com vaga (< 2)
                for ($tentativa = 0; $tentativa < 2 && $atribuidos < $metaDomingosMulher; $tentativa++) {
                    $candidatosIdx = range(0, $qtdDomingos - 1);
                    usort($candidatosIdx, function (int $a, int $b) use ($domingosPeriodo, $folgasPorDomingo, $mIdx) {
                        $cntA = count($folgasPorDomingo[$domingosPeriodo[$a]]);
                        $cntB = count($folgasPorDomingo[$domingosPeriodo[$b]]);
                        if ($cntA !== $cntB) {
                            return $cntA <=> $cntB;
                        }
                        $parA = (($a + $mIdx) % 2 === 0) ? 0 : 1;
                        $parB = (($b + $mIdx) % 2 === 0) ? 0 : 1;
                        if ($parA !== $parB) {
                            return $parA <=> $parB;
                        }
                        return $a <=> $b;
                    });

                    foreach ($candidatosIdx as $idxDom) {
                        if ($atribuidos >= $metaDomingosMulher) {
                            break;
                        }
                        $dtDom = $domingosPeriodo[$idxDom];
                        if (count($folgasPorDomingo[$dtDom]) >= $maxFolgasDomFer) {
                            continue;
                        }
                        if (in_array($funIdM, $folgasPorDomingo[$dtDom], true)) {
                            continue;
                        }
                        // Item 1 (20/11/2026): Nunca permite 2 Farmacêuticos(as) descansando no mesmo dia
                        if ($mulherEhFarm && $jaTemFarmaceuticoNoDia($folgasPorDomingo[$dtDom])) {
                            continue;
                        }
                        // Na 1ª tentativa evita domingos consecutivos para a mesma colaboradora
                        if ($tentativa === 0) {
                            $prevDom = $idxDom > 0 ? $domingosPeriodo[$idxDom - 1] : null;
                            $nextDom = $idxDom < ($qtdDomingos - 1) ? $domingosPeriodo[$idxDom + 1] : null;
                            if (($prevDom && in_array($funIdM, $folgasPorDomingo[$prevDom], true))
                                || ($nextDom && in_array($funIdM, $folgasPorDomingo[$nextDom], true))) {
                                continue;
                            }
                        }

                        $folgasPorDomingo[$dtDom][] = $funIdM;
                        $contagemExtras[$funIdM]++;
                        $atribuidos++;
                    }
                }
            }

            // 3.2 Preenche as vagas restantes de cada Domingo até $maxFolgasDomFer (1 'folga' + 1 'dsr'),
            // bloqueando estritamente que um 2º Farmacêutico seja alocado no mesmo domingo em que outro Farmacêutico já descansa
            foreach ($domingosPeriodo as $dtDom) {
                while (count($folgasPorDomingo[$dtDom]) < $maxFolgasDomFer) {
                    $temFarmNoDom = $jaTemFarmaceuticoNoDia($folgasPorDomingo[$dtDom]);

                    $candidato = $funcsEscala
                        ->reject(function (Funcionario $f) use ($folgasPorDomingo, $dtDom, $temFarmNoDom) {
                            if (in_array((int) $f->fun_id, $folgasPorDomingo[$dtDom], true)) {
                                return true;
                            }
                            if ($temFarmNoDom && $this->ehCargoFarmaceutico($f)) {
                                return true;
                            }
                            return false;
                        })
                        ->sortBy(function (Funcionario $f) use ($contagemExtras) {
                            $fid = (int) $f->fun_id;
                            return ($f->fun_sexo === 'M' ? '0_' : '1_')
                                . str_pad((string) ($contagemExtras[$fid] ?? 0), 4, '0', STR_PAD_LEFT)
                                . '_' . str_pad((string) $fid, 6, '0', STR_PAD_LEFT);
                        })
                        ->first();

                    if (!$candidato) {
                        break;
                    }

                    $fidCand = (int) $candidato->fun_id;
                    $folgasPorDomingo[$dtDom][] = $fidCand;
                    $contagemExtras[$fidCand]++;
                }
            }
        }

        // 4. Aplica dia a dia as regras estritas (Itens 1 e 2 - 20/11/2026):
        //    - Nunca 2 Farmacêuticos(as) sem trabalhar no mesmo dia
        //    - Nunca mais de 1 registro com esd_tipo_dia = 'folga' no mesmo dia:
        //      * Segunda a Sábado (não feriado ativo): 1 'folga' e 5 trabalhando
        //      * Domingos: 1 'folga' e 1 'dsr' (4 trabalhando 08h às 13h)
        //      * Feriados ativos (fer_status = 1): 1 'folga' e 1 'feriado' (4 trabalhando 08h às 13h)
        foreach ($datasPeriodo as $dtIso => $dow) {
            $feriadoObj = $feriadosMap->get($dtIso);
            $ehFeriado = $feriadoObj !== null;

            if ($dow === 1) {
                // DOMINGO: 1º descanso classificado como 'folga' e 2º descanso como 'dsr' (nunca 2 'folga' no mesmo dia e nunca 2 Farmacêuticos!)
                $idsFolgaHoje = array_values($folgasPorDomingo[$dtIso] ?? []);

                foreach ($funcsEscala as $func) {
                    $fid = (int) $func->fun_id;
                    $posDom = array_search($fid, $idsFolgaHoje, true);
                    if ($posDom !== false) {
                        $tipoDiaDom = ($posDom === 0) ? 'folga' : 'dsr';
                        $obsFolga = $func->fun_sexo === 'F'
                            ? ($tipoDiaDom === 'folga'
                                ? 'Folga dominical obrigatória (CLT Art. 386)'
                                : 'DSR dominical obrigatório (CLT Art. 386)')
                            : ($tipoDiaDom === 'folga'
                                ? 'Folga dominical 5x2 (Revezamento)'
                                : 'DSR dominical 5x2 (Revezamento)');

                        DB::update("
                            UPDATE escalas_diarias
                            SET esd_tipo_dia = ?,
                                tur_id = NULL,
                                esd_observacao = ?
                            WHERE esp_id = ? AND fun_id = ? AND esd_data = ?
                        ", [$tipoDiaDom, $obsFolga, $espId, $fid, $dtIso]);
                    } else {
                        $turnoDom = $turDomFer ?: ($turIdForcado ?: ($func->tur_id_padrao ?: $turManha));
                        $obsTrab = $ehFeriado
                            ? 'Expediente Feriado (08h às 13h): ' . $feriadoObj->fer_descricao
                            : 'Expediente Domingo (08h às 13h)';

                        DB::update("
                            UPDATE escalas_diarias
                            SET esd_tipo_dia = 'trabalho',
                                tur_id = ?,
                                esd_observacao = ?
                            WHERE esp_id = ? AND fun_id = ? AND esd_data = ?
                        ", [$turnoDom, $obsTrab, $espId, $fid, $dtIso]);
                    }
                }
            } elseif ($ehFeriado) {
                // FERIADO ATIVO (fer_status = 1) EM DIA DE SEGUNDA A SÁBADO:
                // 1 funcionário em 'folga' (folguista do dia) + 1 funcionário em 'feriado' (nunca 2 'folga' e nunca 2 Farmacêuticos!)
                $idsFolgaFeriado = [];
                foreach ($funcsEscala as $func) {
                    $fid = (int) $func->fun_id;
                    if (($mapaDiaFolgaSemana[$fid] ?? null) === $dow) {
                        $idsFolgaFeriado[] = $fid;
                        break;
                    }
                }

                while (count($idsFolgaFeriado) < $maxFolgasDomFer) {
                    $temFarmNoFer = $jaTemFarmaceuticoNoDia($idsFolgaFeriado);

                    $candFer = $funcsEscala
                        ->reject(function (Funcionario $f) use ($idsFolgaFeriado, $temFarmNoFer) {
                            if (in_array((int) $f->fun_id, $idsFolgaFeriado, true)) {
                                return true;
                            }
                            // Item 1 (20/11/2026): Nunca permite 2 Farmacêuticos(as) sem trabalhar no mesmo dia
                            if ($temFarmNoFer && $this->ehCargoFarmaceutico($f)) {
                                return true;
                            }
                            return false;
                        })
                        ->sortBy(function (Funcionario $f) use ($contagemExtras) {
                            $fid = (int) $f->fun_id;
                            return str_pad((string) ($contagemExtras[$fid] ?? 0), 4, '0', STR_PAD_LEFT)
                                . '_' . ($f->fun_sexo === 'M' ? '0' : '1')
                                . '_' . str_pad((string) $fid, 6, '0', STR_PAD_LEFT);
                        })
                        ->first();

                    if (!$candFer) {
                        break;
                    }

                    $fidCandFer = (int) $candFer->fun_id;
                    $idsFolgaFeriado[] = $fidCandFer;
                    $contagemExtras[$fidCandFer]++;
                }

                foreach ($funcsEscala as $func) {
                    $fid = (int) $func->fun_id;
                    if (in_array($fid, $idsFolgaFeriado, true)) {
                        $ehFolguistaDia = (($mapaDiaFolgaSemana[$fid] ?? null) === $dow);
                        $tipoDiaFer = $ehFolguistaDia ? 'folga' : 'feriado';
                        $obsFer = $ehFolguistaDia
                            ? 'Folga regular 5x2 (' . $feriadoObj->fer_descricao . ')'
                            : 'Feriado: ' . $feriadoObj->fer_descricao;

                        DB::update("
                            UPDATE escalas_diarias
                            SET esd_tipo_dia = ?,
                                tur_id = NULL,
                                esd_observacao = ?
                            WHERE esp_id = ? AND fun_id = ? AND esd_data = ?
                        ", [$tipoDiaFer, $obsFer, $espId, $fid, $dtIso]);
                    } else {
                        $turnoFer = $turDomFer ?: ($turIdForcado ?: ($func->tur_id_padrao ?: $turManha));
                        $obsTrabFer = 'Expediente Feriado (08h às 13h): ' . $feriadoObj->fer_descricao;

                        DB::update("
                            UPDATE escalas_diarias
                            SET esd_tipo_dia = 'trabalho',
                                tur_id = ?,
                                esd_observacao = ?
                            WHERE esp_id = ? AND fun_id = ? AND esd_data = ?
                        ", [$turnoFer, $obsTrabFer, $espId, $fid, $dtIso]);
                    }
                }
            } else {
                // SEGUNDA A SÁBADO (DIA NORMAL, SEM FERIADO): no máximo 1 funcionário de folga por dia (nunca 2!)
                foreach ($funcsEscala as $func) {
                    $fid = (int) $func->fun_id;
                    $ehFolgaHoje = (($mapaDiaFolgaSemana[$fid] ?? null) === $dow);

                    if ($ehFolgaHoje) {
                        DB::update("
                            UPDATE escalas_diarias
                            SET esd_tipo_dia = 'folga',
                                tur_id = NULL,
                                esd_observacao = 'Folga regular 5x2'
                            WHERE esp_id = ? AND fun_id = ? AND esd_data = ?
                        ", [$espId, $fid, $dtIso]);
                    } else {
                        if ($dow === 7) {
                            $turnoDia = $turSabado ?: ($turIdForcado ?: ($func->tur_id_padrao ?: $turManha));
                            $obsDia = 'Expediente Sábado (08h às 20h)';
                        } else {
                            $turnoDia = $turIdForcado ?: ($func->tur_id_padrao ?: $turManha);
                            $obsDia = 'Turno programado';
                        }

                        DB::update("
                            UPDATE escalas_diarias
                            SET esd_tipo_dia = 'trabalho',
                                tur_id = ?,
                                esd_observacao = ?
                            WHERE esp_id = ? AND fun_id = ? AND esd_data = ?
                        ", [$turnoDia, $obsDia, $espId, $fid, $dtIso]);
                    }
                }
            }
        }
    }

    /**
     * Identifica os IDs do Turno Abertura (Manhã) e Turno Fechamento (Noite) regulares de Segunda a Sexta.
     */
    protected function obterTurnosAberturaEFechamento(int $empId): array
    {
        $turnosSemana = TurnoHorario::where('emp_id', $empId)
            ->where('tur_ativo', 1)
            ->whereRaw("NOT (tur_entrada = '08:00:00' AND tur_saida IN ('13:00:00', '20:00:00'))")
            ->orderBy('tur_entrada')
            ->get();

        if ($turnosSemana->count() < 2) {
            return [null, null];
        }

        $turManha = (int) $turnosSemana->first()->tur_id;
        $turNoite = (int) $turnosSemana->sortByDesc('tur_saida')->first()->tur_id;

        if ($turManha === $turNoite) {
            return [$turManha, null];
        }

        return [$turManha, $turNoite];
    }

    /**
     * Sugestão A (13/11/2026): Equilibra 50% dos colaboradores no Turno Abertura (Manhã) e 50% no Turno Fechamento (Noite),
     * distribuindo também por cargo (ex.: 1 Farmacêutico na Manhã e 1 no Fechamento).
     */
    protected function balancearTurnosPadraoEquipe($funcionarios, int $turManha, int $turNoite): void
    {
        $total = $funcionarios->count();
        if ($total < 2) {
            return;
        }

        $qtdManhaAtual = $funcionarios->filter(fn (Funcionario $f) => (int) $f->tur_id_padrao === $turManha)->count();
        $qtdNoiteAtual = $funcionarios->filter(fn (Funcionario $f) => (int) $f->tur_id_padrao === $turNoite)->count();

        // Verifica se há desequilíbrio global (> 1 de diferença) ou algum cargo com >= 2 colaboradores concentrado em um único turno
        $precisaRebalancear = abs($qtdManhaAtual - $qtdNoiteAtual) > 1 || ($qtdManhaAtual + $qtdNoiteAtual) < $total;
        if (!$precisaRebalancear) {
            foreach ($funcionarios->groupBy('car_id') as $grupoCargo) {
                if ($grupoCargo->count() >= 2) {
                    $mCargo = $grupoCargo->filter(fn (Funcionario $f) => (int) $f->tur_id_padrao === $turManha)->count();
                    $nCargo = $grupoCargo->filter(fn (Funcionario $f) => (int) $f->tur_id_padrao === $turNoite)->count();
                    if ($mCargo === 0 || $nCargo === 0) {
                        $precisaRebalancear = true;
                        break;
                    }
                }
            }
        }

        if (!$precisaRebalancear) {
            return;
        }

        // Ordena por cargo e sexo para alternar equilibradamente entre Abertura (Manhã) e Fechamento (Noite)
        $alocadosManha = 0;
        $alocadosNoite = 0;
        $metaManha = (int) ceil($total / 2);

        $porCargo = $funcionarios->groupBy('car_id');
        foreach ($porCargo as $grupoCargo) {
            $ordenados = $grupoCargo->sortBy(function (Funcionario $f) {
                return ($f->fun_sexo === 'M' ? '0_' : '1_') . str_pad((string) $f->fun_id, 6, '0', STR_PAD_LEFT);
            })->values();

            foreach ($ordenados as $idxCargo => $func) {
                if ($alocadosManha < $metaManha && ($alocadosManha <= $alocadosNoite || $idxCargo % 2 === 0)) {
                    $novoTurno = $turManha;
                    $alocadosManha++;
                } else {
                    $novoTurno = $turNoite;
                    $alocadosNoite++;
                }

                if ((int) $func->tur_id_padrao !== $novoTurno) {
                    $func->tur_id_padrao = $novoTurno;
                    Funcionario::where('fun_id', $func->fun_id)->update(['tur_id_padrao' => $novoTurno]);
                }
            }
        }
    }

    /**
     * Sugestão B (13/11/2026): Garante equilíbrio diário entre Turno Abertura (Manhã) e Turno Fechamento (Noite)
     * em todos os dias úteis (Segunda a Sexta, exceto feriados), impedindo dias com 0 no fechamento ou 3x1.
     */
    protected function garantirEquilibrioTurnosSemana(int $espId, int $empId, int $turManha, int $turNoite): void
    {
        $diasSemana = DB::select("
            SELECT
                ed.esd_data,
                SUM(CASE WHEN ed.esd_tipo_dia = 'trabalho' AND ed.tur_id = ? THEN 1 ELSE 0 END) AS qtd_manha,
                SUM(CASE WHEN ed.esd_tipo_dia = 'trabalho' AND ed.tur_id = ? THEN 1 ELSE 0 END) AS qtd_noite
            FROM escalas_diarias ed
            LEFT JOIN feriados fer ON fer.fer_data = ed.esd_data AND (fer.emp_id IS NULL OR fer.emp_id = ?)
            WHERE ed.esp_id = ?
              AND DAYOFWEEK(ed.esd_data) BETWEEN 2 AND 6
              AND fer.fer_id IS NULL
            GROUP BY ed.esd_data
            ORDER BY ed.esd_data ASC
        ", [$turManha, $turNoite, $empId, $espId]);

        foreach ($diasSemana as $dia) {
            $qtdManha = (int) $dia->qtd_manha;
            $qtdNoite = (int) $dia->qtd_noite;
            $dataAlvo = $dia->esd_data;

            // Caso 1: Fechamento (Noite) desfalcado (ex.: 3x0, 3x1, 4x1)
            while (($qtdNoite === 0 && $qtdManha >= 2) || (($qtdManha - $qtdNoite) >= 2 && $qtdNoite < 2)) {
                // Busca um colaborador trabalhando na Manhã naquele dia que possa assumir o Fechamento sem violar a interjornada de 11h no dia seguinte
                $candidatos = DB::select("
                    SELECT
                        ed.esd_id,
                        ed.fun_id,
                        f.fun_sexo,
                        ed_next.esd_tipo_dia AS next_tipo,
                        t_next.tur_entrada AS next_entrada,
                        DAYOFWEEK(DATE_ADD(ed.esd_data, INTERVAL 1 DAY)) AS next_dow
                    FROM escalas_diarias ed
                    JOIN funcionarios f ON f.fun_id = ed.fun_id
                    LEFT JOIN escalas_diarias ed_next
                           ON ed_next.esp_id = ed.esp_id
                          AND ed_next.fun_id = ed.fun_id
                          AND ed_next.esd_data = DATE_ADD(ed.esd_data, INTERVAL 1 DAY)
                    LEFT JOIN turnohorarios t_next ON t_next.tur_id = ed_next.tur_id
                    WHERE ed.esp_id = ?
                      AND ed.esd_data = ?
                      AND ed.esd_tipo_dia = 'trabalho'
                      AND ed.tur_id = ?
                    ORDER BY
                        CASE WHEN ed_next.esd_tipo_dia IS NULL OR ed_next.esd_tipo_dia <> 'trabalho' THEN 0
                             WHEN DAYOFWEEK(DATE_ADD(ed.esd_data, INTERVAL 1 DAY)) IN (1, 7) THEN 1
                             WHEN t_next.tur_entrada >= '10:00:00' THEN 2
                             ELSE 3 END ASC,
                        ed.fun_id DESC
                ", [$espId, $dataAlvo, $turManha]);

                $transferido = false;
                foreach ($candidatos as $cand) {
                    // Se no dia seguinte trabalha às 07:00 em dia útil, sair às 23:00 hoje violaria as 11h de interjornada
                    if ($cand->next_tipo === 'trabalho' && !in_array((int) $cand->next_dow, [1, 7], true) && $cand->next_entrada < '10:00:00') {
                        continue;
                    }

                    try {
                        DB::update("
                            UPDATE escalas_diarias
                            SET tur_id = ?,
                                esd_observacao = 'Turno programado (Equilíbrio Fechamento)'
                            WHERE esd_id = ?
                        ", [$turNoite, $cand->esd_id]);
                        $qtdManha--;
                        $qtdNoite++;
                        $transferido = true;
                        break;
                    } catch (\Throwable $ignore) {
                        continue;
                    }
                }

                if (!$transferido) {
                    break;
                }
            }

            // Caso 2: Abertura (Manhã) desfalcada (ex.: 0x3, 1x3, 1x4)
            while (($qtdManha === 0 && $qtdNoite >= 2) || (($qtdNoite - $qtdManha) >= 2 && $qtdManha < 2)) {
                $candidatos = DB::select("
                    SELECT
                        ed.esd_id,
                        ed.fun_id,
                        ed_prev.esd_tipo_dia AS prev_tipo,
                        t_prev.tur_saida AS prev_saida
                    FROM escalas_diarias ed
                    LEFT JOIN escalas_diarias ed_prev
                           ON ed_prev.esp_id = ed.esp_id
                          AND ed_prev.fun_id = ed.fun_id
                          AND ed_prev.esd_data = DATE_SUB(ed.esd_data, INTERVAL 1 DAY)
                    LEFT JOIN turnohorarios t_prev ON t_prev.tur_id = ed_prev.tur_id
                    WHERE ed.esp_id = ?
                      AND ed.esd_data = ?
                      AND ed.esd_tipo_dia = 'trabalho'
                      AND ed.tur_id = ?
                    ORDER BY
                        CASE WHEN ed_prev.esd_tipo_dia IS NULL OR ed_prev.esd_tipo_dia <> 'trabalho' THEN 0
                             WHEN t_prev.tur_saida <= '20:00:00' THEN 1
                             ELSE 2 END ASC,
                        ed.fun_id ASC
                ", [$espId, $dataAlvo, $turNoite]);

                $transferido = false;
                foreach ($candidatos as $cand) {
                    if ($cand->prev_tipo === 'trabalho' && $cand->prev_saida > '20:00:00') {
                        continue;
                    }

                    try {
                        DB::update("
                            UPDATE escalas_diarias
                            SET tur_id = ?,
                                esd_observacao = 'Turno programado (Equilíbrio Abertura)'
                            WHERE esd_id = ?
                        ", [$turManha, $cand->esd_id]);
                        $qtdNoite--;
                        $qtdManha++;
                        $transferido = true;
                        break;
                    } catch (\Throwable $ignore) {
                        continue;
                    }
                }

                if (!$transferido) {
                    break;
                }
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
        // e todo registro de TRABALHO aos Domingos ou Feriados ativos (fer_status = 1) use o turno 08h-13h
        DB::statement("
            UPDATE escalas_diarias ed
            LEFT JOIN feriados fer ON fer.fer_data = ed.esd_data AND fer.fer_status = 1 AND (fer.emp_id IS NULL OR fer.emp_id = ?)
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
            LEFT JOIN feriados fer ON fer.fer_data = ed.esd_data AND fer.fer_status = 1 AND (fer.emp_id IS NULL OR fer.emp_id = ?)
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

        // 2. Verifica se algum Sábado, Domingo ou Feriado ativo (fer_status = 1) ficou sem nenhum colaborador em TRABALHO
        $datasSemCobertura = DB::select("
            SELECT
                ed.esd_data,
                DAYOFWEEK(ed.esd_data) AS dow,
                CASE WHEN MAX(fer.fer_id) IS NOT NULL THEN 1 ELSE 0 END AS eh_feriado,
                MAX(fer.fer_descricao) AS desc_feriado,
                SUM(CASE WHEN ed.esd_tipo_dia = 'trabalho' THEN 1 ELSE 0 END) AS total_trabalhando
            FROM escalas_diarias ed
            LEFT JOIN feriados fer ON fer.fer_data = ed.esd_data AND fer.fer_status = 1 AND (fer.emp_id IS NULL OR fer.emp_id = ?)
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

            // Sugestão B (15/11/2026): Trava preventiva de limite diário de folgas na edição manual
            // - Segunda a Sábado (exceto feriados): máximo 1 funcionário de folga
            // - Domingos e Feriados: máximo 2 funcionários de folga (nunca 3 ou mais)
            $dataAlvoIso = $escalaDia->esd_data instanceof \Carbon\CarbonInterface
                ? $escalaDia->esd_data->format('Y-m-d')
                : substr((string) $escalaDia->esd_data, 0, 10);
            $dowAlvo = ((int) \Carbon\Carbon::parse($dataAlvoIso)->dayOfWeek) + 1;

            $empIdPeriodo = (int) (EscalaPeriodo::where('esp_id', $escalaDia->esp_id)->value('emp_id') ?: $this->empId($request));
            $ehFeriadoAlvo = Feriado::where('emp_id', $empIdPeriodo)
                ->where('fer_status', 1)
                ->whereDate('fer_data', $dataAlvoIso)
                ->exists();

            $outrosEmFolga = EscalaDiaria::with('funcionario.cargo')
                ->where('esp_id', $escalaDia->esp_id)
                ->whereDate('esd_data', $dataAlvoIso)
                ->where('esd_id', '!=', $escalaDia->esd_id)
                ->whereIn('esd_tipo_dia', ['folga', 'dsr', 'feriado'])
                ->get();

            // Item 1 + Sugestão A (20/11/2026): Nunca permite 2 funcionários com cargo = Farmacêutico(a) de folga no mesmo dia
            $funcAlvo = Funcionario::with('cargo')->find($escalaDia->fun_id);
            if ($this->ehCargoFarmaceutico($funcAlvo)) {
                $outroFarmFolga = $outrosEmFolga->first(fn (EscalaDiaria $d) => $this->ehCargoFarmaceutico($d->funcionario));
                if ($outroFarmFolga) {
                    $nomeFarm = $outroFarmFolga->funcionario?->fun_nome ?? ('#' . $outroFarmFolga->fun_id);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Regra de Assistência Farmacêutica: Não pode o funcionário com o cargo Farmacêutico(a) tirar folga no mesmo dia que outro Farmacêutico(a). Já está de folga nesta data: {$nomeFarm}.",
                    ], 422);
                }
            }

            // Item 2 (20/11/2026): Nunca permite mais de 1 funcionário com esd_tipo_dia = 'folga' no mesmo dia
            if ($validated['esd_tipo_dia'] === 'folga') {
                $outroComFolga = $outrosEmFolga->firstWhere('esd_tipo_dia', 'folga');
                if ($outroComFolga) {
                    $nomeFolga = $outroComFolga->funcionario?->fun_nome ?? ('#' . $outroComFolga->fun_id);
                    return response()->json([
                        'status' => 'error',
                        'message' => "Regra de Escala 5x2: Não pode mais de 1 funcionário tirar folga no mesmo dia. Já está de folga nesta data: {$nomeFolga}.",
                    ], 422);
                }
            }

            $nomesEmFolga = $outrosEmFolga
                ->map(fn (EscalaDiaria $d) => $d->funcionario?->fun_nome ?? ('#' . $d->fun_id))
                ->implode(', ');

            if ($dowAlvo === 1 || $ehFeriadoAlvo) {
                if ($outrosEmFolga->count() >= 2) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Regra de Escala 5x2: Nos domingos e feriados ativos só é permitido no máximo 2 descansos por dia (1 Folga + 1 DSR/Feriado). Já estão descansando nesta data: {$nomesEmFolga}.",
                    ], 422);
                }
            } else {
                if ($outrosEmFolga->count() >= 1) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Regra de Escala 5x2: Não pode mais de 1 funcionário tirar folga no mesmo dia. Já está de folga nesta data: {$nomesEmFolga}.",
                    ], 422);
                }
            }
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

