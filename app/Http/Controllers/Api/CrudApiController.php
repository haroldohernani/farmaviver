<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\ConfiguracaoEscala;
use App\Models\Empresa;
use App\Models\EscalaPeriodo;
use App\Models\Feriado;
use App\Models\Funcionario;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\TurnoHorario;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CrudApiController extends Controller
{
    protected function empId(Request $request): int
    {
        return (int) ($request->input('emp_id') ?: $request->session()->get('emp_id', 1));
    }

    // =========================================================================
    // 1. EMPRESAS (Módulo Empresa - 03/11/2026)
    // =========================================================================
    public function listarEmpresas(Request $request): JsonResponse
    {
        $incluirInativas = $request->boolean('incluir_inativas', false);

        $query = Empresa::orderBy('emp_id');
        if (!$incluirInativas) {
            $query->where('emp_ativo', true);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $query->get(),
            'emp_id_ativa' => (int) $request->session()->get('emp_id', 1),
        ]);
    }

    public function salvarEmpresa(Request $request, ?int $id = null): JsonResponse
    {
        $cnpjLimpo = preg_replace('/\D/', '', (string) $request->input('emp_cnpj', ''));
        $request->merge(['emp_cnpj' => $cnpjLimpo]);

        $validated = $request->validate([
            'emp_razao_social' => ['nullable', 'string', 'max:150'],
            'emp_nome_fantasia' => ['required', 'string', 'max:150'],
            'emp_cnpj' => [
                'required',
                'digits:14',
                Rule::unique('empresas', 'emp_cnpj')->ignore($id, 'emp_id'),
            ],
            'emp_email' => ['required', 'email', 'max:100'],
            'emp_celular' => ['nullable', 'string', 'max:20'],
            'emp_telefone' => ['nullable', 'string', 'max:20'],
            'emp_logradouro' => ['nullable', 'string', 'max:150'],
            'emp_numero' => ['nullable', 'string', 'max:20'],
            'emp_bairro' => ['nullable', 'string', 'max:80'],
            'emp_cidade' => ['nullable', 'string', 'max:80'],
            'emp_uf' => ['nullable', 'string', 'max:2'],
            'emp_cep' => ['nullable', 'string', 'max:8'],
            'emp_ativo' => ['nullable', 'boolean'],
        ], [
            'emp_cnpj.digits' => 'O CNPJ deve conter exatamente 14 dígitos numéricos.',
            'emp_cnpj.unique' => 'Este CNPJ já está cadastrado para outra empresa.',
        ]);

        if (empty($validated['emp_razao_social'])) {
            $validated['emp_razao_social'] = $validated['emp_nome_fantasia'];
        }

        $celularOuTelefone = $validated['emp_celular'] ?? $validated['emp_telefone'] ?? null;
        $validated['emp_celular'] = $celularOuTelefone;
        $validated['emp_telefone'] = $celularOuTelefone;

        DB::beginTransaction();
        try {
            if ($id) {
                $empresa = Empresa::findOrFail($id);
                $empresa->update($validated);
                $msg = 'Empresa atualizada com sucesso!';

                if ((int) $request->session()->get('emp_id') === (int) $empresa->emp_id) {
                    $request->session()->put('emp_nome_fantasia', $empresa->emp_nome_fantasia);
                }
            } else {
                $validated['emp_ativo'] = $validated['emp_ativo'] ?? true;
                $empresa = Empresa::create($validated);
                $msg = 'Nova empresa cadastrada com sucesso!';

                // Sugestão A (03/11/2026): Inicializa perfis RBAC padrão e regra 5x2 inicial para a nova empresa
                $this->inicializarEstruturaEmpresa((int) $empresa->emp_id);
            }

            DB::commit();

            return response()->json([
                'status' => 'ok',
                'message' => $msg,
                'data' => $empresa->fresh(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao salvar empresa: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function atualizarEmpresa(Request $request, int $id): JsonResponse
    {
        return $this->salvarEmpresa($request, $id);
    }

    public function alterarStatusEmpresa(Request $request, int $id): JsonResponse
    {
        $empresa = Empresa::findOrFail($id);
        $novoStatus = $request->has('emp_ativo') ? $request->boolean('emp_ativo') : false;

        $empresa->update([
            'emp_ativo' => $novoStatus,
        ]);

        // Caso a empresa desativada seja a empresa ativa na sessão, alterna para a primeira ativa disponível
        if (!$novoStatus && (int) $request->session()->get('emp_id') === (int) $empresa->emp_id) {
            $outraAtiva = Empresa::where('emp_ativo', true)->orderBy('emp_id')->first();
            if ($outraAtiva) {
                $request->session()->put([
                    'emp_id' => (int) $outraAtiva->emp_id,
                    'emp_nome_fantasia' => $outraAtiva->emp_nome_fantasia,
                ]);
            }
        }

        return response()->json([
            'status' => 'ok',
            'message' => $novoStatus
                ? "Empresa \"{$empresa->emp_nome_fantasia}\" reativada com sucesso!"
                : "Empresa \"{$empresa->emp_nome_fantasia}\" desativada com sucesso!",
            'data' => $empresa,
        ]);
    }

    public function selecionarEmpresaAtiva(Request $request): JsonResponse
    {
        if ($request->session()->get('fun_perfil') !== 'admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'Apenas usuários com perfil Administrador podem alternar entre empresas.',
            ], 403);
        }

        $validated = $request->validate([
            'emp_id' => ['required', 'integer', 'exists:empresas,emp_id'],
        ]);

        $empresa = Empresa::findOrFail((int) $validated['emp_id']);
        $request->session()->put([
            'emp_id' => (int) $empresa->emp_id,
            'emp_nome_fantasia' => $empresa->emp_nome_fantasia,
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => "Contexto alterado para: {$empresa->emp_nome_fantasia}",
            'data' => $empresa,
        ]);
    }

    /**
     * Sugestão A (03/11/2026): Inicializa perfis RBAC e configuração 5x2 base para uma nova empresa.
     */
    protected function inicializarEstruturaEmpresa(int $empId): void
    {
        if (Perfil::where('emp_id', $empId)->doesntExist()) {
            $perfisBase = [
                ['prf_nome' => 'Administrador', 'prf_descricao' => 'Acesso total ao sistema da empresa'],
                ['prf_nome' => 'Gestor de Farmácia', 'prf_descricao' => 'Gestão de escalas 5x2, trocas e equipe'],
                ['prf_nome' => 'Colaborador', 'prf_descricao' => 'Consulta de escala e solicitação de trocas'],
            ];

            $todasPermissoes = Permissao::pluck('per_id')->all();

            foreach ($perfisBase as $idx => $pb) {
                $novoPerfil = Perfil::create([
                    'emp_id' => $empId,
                    'prf_nome' => $pb['prf_nome'],
                    'prf_descricao' => $pb['prf_descricao'],
                    'prf_ativo' => true,
                ]);

                if ($idx === 0 && !empty($todasPermissoes)) {
                    $novoPerfil->permissoes()->sync($todasPermissoes);
                }
            }
        }

        if (ConfiguracaoEscala::where('emp_id', $empId)->doesntExist()) {
            $configsBase = [
                [
                    'cfg_nome' => '5x2 Revezamento Equipe A (Segunda e Terça)',
                    'cfg_tipo' => 'revezamento',
                    'cfg_dia_folga_1' => 2,
                    'cfg_dia_folga_2' => 3,
                ],
                [
                    'cfg_nome' => '5x2 Revezamento Equipe B (Quarta e Quinta)',
                    'cfg_tipo' => 'revezamento',
                    'cfg_dia_folga_1' => 4,
                    'cfg_dia_folga_2' => 5,
                ],
                [
                    'cfg_nome' => '5x2 Revezamento Equipe C (Sexta e Sábado)',
                    'cfg_tipo' => 'revezamento',
                    'cfg_dia_folga_1' => 6,
                    'cfg_dia_folga_2' => 7,
                ],
                [
                    'cfg_nome' => '5x2 Sábado e Domingo (Padrão)',
                    'cfg_tipo' => 'fixa',
                    'cfg_dia_folga_1' => 7,
                    'cfg_dia_folga_2' => 1,
                ],
            ];

            foreach ($configsBase as $cb) {
                ConfiguracaoEscala::create(array_merge($cb, [
                    'emp_id' => $empId,
                    'cfg_ativo' => true,
                ]));
            }
        }

        if (TurnoHorario::where('emp_id', $empId)->doesntExist()) {
            $turnosBase = [
                [
                    'tur_descricao' => 'Turno Abertura (Manhã)',
                    'tur_entrada' => '07:00:00',
                    'tur_saida_intervalo' => '12:00:00',
                    'tur_retorno_intervalo' => '13:00:00',
                    'tur_saida' => '16:48:00',
                ],
                [
                    'tur_descricao' => 'Turno Fechamento (Noite)',
                    'tur_entrada' => '13:12:00',
                    'tur_saida_intervalo' => '17:00:00',
                    'tur_retorno_intervalo' => '18:00:00',
                    'tur_saida' => '23:00:00',
                ],
                [
                    'tur_descricao' => 'Expediente Sábado (08h às 20h)',
                    'tur_entrada' => '08:00:00',
                    'tur_saida_intervalo' => '12:00:00',
                    'tur_retorno_intervalo' => '14:00:00',
                    'tur_saida' => '20:00:00',
                ],
                [
                    'tur_descricao' => 'Domingo e Feriado (08h às 13h)',
                    'tur_entrada' => '08:00:00',
                    'tur_saida_intervalo' => '10:30:00',
                    'tur_retorno_intervalo' => '10:30:00',
                    'tur_saida' => '13:00:00',
                ],
            ];

            foreach ($turnosBase as $tb) {
                TurnoHorario::create(array_merge($tb, [
                    'emp_id' => $empId,
                    'tur_tolerancia_minutos' => 10,
                    'tur_ativo' => true,
                ]));
            }
        }

        // Itens 2 e 3 + Sugestão A (13/11/2026): Sincroniza todos os cargos cadastrados para a empresa
        Cargo::sincronizarEntreEmpresas();

        // Itens 1, 2 e 3 + Sugestão A (14/11/2026): Sincroniza todos os períodos de escala e feriados para a empresa
        EscalaPeriodo::sincronizarEntreEmpresas();
        Feriado::sincronizarEntreEmpresas();
    }

    // =========================================================================
    // 2. CARGOS (Tabela: cargos)
    // =========================================================================
    public function listarCargos(Request $request): JsonResponse
    {
        $empId = $this->empId($request);

        // Garante que todos os cargos cadastrados em qualquer empresa estejam sincronizados
        Cargo::sincronizarEntreEmpresas();

        $cargos = Cargo::where('emp_id', $empId)
            ->withCount('funcionarios')
            ->orderBy('car_nome')
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => $cargos,
        ]);
    }

    public function salvarCargo(Request $request, ?int $id = null): JsonResponse
    {
        $empId = $this->empId($request);

        $validated = $request->validate([
            'car_nome' => ['required', 'string', 'max:100'],
            'car_descricao' => ['nullable', 'string', 'max:255'],
            'car_cbo' => ['nullable', 'string', 'max:10'],
            'car_ativo' => ['required', 'boolean'],
        ]);

        $validated['car_nome'] = trim($validated['car_nome']);
        $validated['emp_id'] = $empId;
        $chaveNovoNome = mb_strtolower($validated['car_nome']);

        if ($id) {
            $cargo = Cargo::findOrFail($id);
            $chaveNomeAntigo = mb_strtolower(trim((string) $cargo->car_nome));
            $cargo->update($validated);

            // Item 2 + Sugestões A e C (13/11/2026): Propaga a atualização do cargo para todas as outras empresas
            $todasEmpresas = Empresa::orderBy('emp_id')->pluck('emp_id')->all();
            foreach ($todasEmpresas as $outroEmpId) {
                if ((int) $outroEmpId === $empId) {
                    continue;
                }
                $cargoOutraEmpresa = Cargo::where('emp_id', $outroEmpId)
                    ->whereRaw('LOWER(TRIM(car_nome)) IN (?, ?)', [$chaveNomeAntigo, $chaveNovoNome])
                    ->first();

                $dadosReplica = [
                    'emp_id' => (int) $outroEmpId,
                    'car_nome' => $validated['car_nome'],
                    'car_descricao' => $validated['car_descricao'] ?? null,
                    'car_cbo' => $validated['car_cbo'] ?? null,
                    'car_ativo' => $validated['car_ativo'],
                ];

                if ($cargoOutraEmpresa) {
                    $cargoOutraEmpresa->update($dadosReplica);
                } else {
                    Cargo::create($dadosReplica);
                }
            }

            $msg = 'Cargo atualizado e sincronizado em todas as empresas com sucesso!';
        } else {
            // Sugestão C (13/11/2026): Previne duplicidade de nome de cargo na mesma empresa
            $cargoExistente = Cargo::where('emp_id', $empId)
                ->whereRaw('LOWER(TRIM(car_nome)) = ?', [$chaveNovoNome])
                ->first();

            if ($cargoExistente) {
                $cargoExistente->update($validated);
                $cargo = $cargoExistente;
            } else {
                $cargo = Cargo::create($validated);
            }

            // Item 2 + Sugestão A (13/11/2026): Disponibiliza o cargo recém-cadastrado para todas as demais empresas
            Cargo::sincronizarEntreEmpresas();

            $msg = 'Novo cargo cadastrado e disponibilizado para todas as empresas com sucesso!';
        }

        return response()->json([
            'status' => 'ok',
            'message' => $msg,
            'data' => $cargo,
        ]);
    }

    public function excluirCargo(int $id): JsonResponse
    {
        try {
            $cargo = Cargo::findOrFail($id);
            $cargo->delete();
            return response()->json([
                'status' => 'ok',
                'message' => 'Cargo excluído com sucesso!',
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não é possível excluir este cargo pois existem funcionários vinculados a ele. Você pode inativá-lo.',
            ], 422);
        }
    }

    // =========================================================================
    // 3. TURNOS / HORÁRIOS (Tabela: turnohorarios)
    // =========================================================================
    public function listarTurnos(Request $request): JsonResponse
    {
        $empId = $this->empId($request);
        $turnos = TurnoHorario::where('emp_id', $empId)
            ->withCount('funcionariosPadrao')
            ->orderBy('tur_entrada')
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => $turnos,
        ]);
    }

    public function salvarTurno(Request $request, ?int $id = null): JsonResponse
    {
        $empId = $this->empId($request);

        $validated = $request->validate([
            'tur_descricao' => ['required', 'string', 'max:80'],
            'tur_entrada' => ['required', 'string'],
            'tur_saida_intervalo' => ['required', 'string'],
            'tur_retorno_intervalo' => ['required', 'string'],
            'tur_saida' => ['required', 'string'],
            'tur_tolerancia_minutos' => ['required', 'integer', 'min:0', 'max:120'],
            'tur_ativo' => ['required', 'boolean'],
        ]);

        // Valida constraints chk_turno_intervalo e chk_turno_retorno do banco
        if ($validated['tur_saida_intervalo'] <= $validated['tur_entrada']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Regra de Turno (chk_turno_intervalo): A saída para o intervalo deve ser maior que o horário de entrada.',
            ], 422);
        }

        if ($validated['tur_retorno_intervalo'] < $validated['tur_saida_intervalo']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Regra de Turno (chk_turno_retorno): O retorno do intervalo deve ser maior ou igual à saída para o intervalo.',
            ], 422);
        }

        $validated['emp_id'] = $empId;

        if ($id) {
            $turno = TurnoHorario::findOrFail($id);
            $turno->update($validated);
            $msg = 'Turno atualizado com sucesso!';
        } else {
            $turno = TurnoHorario::create($validated);
            $msg = 'Novo turno cadastrado com sucesso!';
        }

        return response()->json([
            'status' => 'ok',
            'message' => $msg,
            'data' => $turno,
        ]);
    }

    public function excluirTurno(int $id): JsonResponse
    {
        try {
            $turno = TurnoHorario::findOrFail($id);
            $turno->delete();
            return response()->json([
                'status' => 'ok',
                'message' => 'Turno excluído com sucesso!',
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Não é possível excluir este turno pois está vinculado a escalas diárias registradas. Você pode inativá-lo.',
            ], 422);
        }
    }

    // =========================================================================
    // 4. FUNCIONÁRIOS ( Tabelas: funcionarios, funcionario_perfis )
    // =========================================================================
    public function listarFuncionarios(Request $request): JsonResponse
    {
        $empId = $this->empId($request);
        $funcionarios = Funcionario::with(['cargo', 'turnoPadrao', 'perfis'])
            ->where('emp_id', $empId)
            ->orderBy('fun_nome')
            ->get();

        return response()->json([
            'status' => 'ok',
            'data' => $funcionarios,
        ]);
    }

    public function salvarFuncionario(Request $request, ?int $id = null): JsonResponse
    {
        $empId = $this->empId($request);

        // Normaliza CPF para conter apenas números
        $cpfLimpo = preg_replace('/\D/', '', (string) $request->input('fun_cpf', ''));
        $request->merge(['fun_cpf' => $cpfLimpo]);

        $rules = [
            'fun_matricula' => [
                'required',
                'string',
                'max:30',
                Rule::unique('funcionarios', 'fun_matricula')
                    ->where(fn ($q) => $q->where('emp_id', $empId))
                    ->ignore($id, 'fun_id'),
            ],
            'fun_nome' => ['required', 'string', 'max:150'],
            'fun_cpf' => [
                'required',
                'digits:11',
                Rule::unique('funcionarios', 'fun_cpf')->ignore($id, 'fun_id'),
            ],
            'fun_sexo' => ['required', Rule::in(['M', 'F'])],
            'car_id' => ['required', 'integer', 'exists:cargos,car_id'],
            'tur_id_padrao' => ['nullable', 'integer', 'exists:turnohorarios,tur_id'],
            'fun_perfil' => ['required', Rule::in(['admin', 'gestor', 'colaborador'])],
            'fun_email' => ['nullable', 'email', 'max:100'],
            'fun_telefone' => ['nullable', 'string', 'max:20'],
            'fun_data_admissao' => ['required', 'date'],
            'fun_data_demissao' => ['nullable', 'date'],
            'fun_status' => ['required', Rule::in(['ativo', 'afastado', 'ferias', 'desligado'])],
            'fun_senha' => [$id ? 'nullable' : 'required', 'string', 'min:4'],
            'prf_id' => ['nullable', 'integer', 'exists:perfis,prf_id'],
        ];

        $validated = $request->validate($rules, [
            'fun_cpf.digits' => 'O CPF deve conter exatamente 11 dígitos numéricos sem formatação.',
            'fun_cpf.unique' => 'Este CPF já está cadastrado para outro funcionário.',
            'fun_matricula.unique' => 'Esta matrícula já está em uso nesta empresa.',
        ]);

        $dados = collect($validated)->except(['fun_senha', 'prf_id'])->toArray();
        $dados['emp_id'] = $empId;

        if (!empty($validated['fun_senha'])) {
            $dados['fun_senha'] = Hash::make($validated['fun_senha']);
        }

        DB::beginTransaction();
        try {
            if ($id) {
                $funcionario = Funcionario::findOrFail($id);
                $funcionario->update($dados);
                $msg = 'Funcionário atualizado com sucesso!';
            } else {
                $funcionario = Funcionario::create($dados);
                $msg = 'Funcionário cadastrado com sucesso!';
            }

            // Sincroniza perfil RBAC na tabela funcionario_perfis para a empresa do funcionário
            $nomePerfilBusca = match ($validated['fun_perfil']) {
                'admin' => 'Administrador',
                'gestor' => 'Gestor de Farmácia',
                default => 'Colaborador',
            };
            $prfIdEmpresa = Perfil::where('emp_id', $empId)
                ->where('prf_nome', $nomePerfilBusca)
                ->value('prf_id');

            $prfId = $validated['prf_id'] ?? $prfIdEmpresa ?? match ($validated['fun_perfil']) {
                'admin' => 1,
                'gestor' => 2,
                default => 3,
            };

            if (Perfil::where('prf_id', $prfId)->exists()) {
                DB::table('funcionario_perfis')->where('fun_id', $funcionario->fun_id)->delete();
                DB::table('funcionario_perfis')->insert([
                    'fun_id' => $funcionario->fun_id,
                    'prf_id' => $prfId,
                    'fup_atribuido_em' => now(),
                ]);
            }

            // Regra de Negócio (02/11/2026): Se Cargo Administrador + Perfil admin + Status ativo, não pode constar na escala 5x2
            $funcionario->load('cargo');
            if ($funcionario->isAdministradorForaEscala()) {
                DB::table('escalas_diarias')->where('fun_id', $funcionario->fun_id)->delete();
            }

            DB::commit();

            return response()->json([
                'status' => 'ok',
                'message' => $msg,
                'data' => $funcionario->load(['cargo', 'turnoPadrao', 'perfis']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao salvar funcionário: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function excluirFuncionario(int $id): JsonResponse
    {
        try {
            $funcionario = Funcionario::findOrFail($id);
            $funcionario->delete();
            return response()->json([
                'status' => 'ok',
                'message' => 'Funcionário removido com sucesso!',
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este colaborador já possui escalas ou trocas vinculadas. Altere o status dele para "desligado" ou "afastado".',
            ], 422);
        }
    }

    // =========================================================================
    // 5. CALENDÁRIO OPERACIONAL, CONFIGURAÇÕES 5x2 E FERIADOS
    // =========================================================================
    public function listarCalendarioOperacional(Request $request): JsonResponse
    {
        $empId = $this->empId($request);

        // Itens 1, 2 e 3 + Sugestão A (14/11/2026): Sincroniza períodos de escala e feriados entre todas as empresas
        EscalaPeriodo::sincronizarEntreEmpresas();
        Feriado::sincronizarEntreEmpresas();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'feriados' => Feriado::where('emp_id', $empId)
                    ->orderBy('fer_data')
                    ->get(),
                'configuracoes_escala' => ConfiguracaoEscala::where('emp_id', $empId)
                    ->orderBy('cfg_id')
                    ->get(),
                'periodos' => EscalaPeriodo::where('emp_id', $empId)
                    ->withCount('escalasDiarias')
                    ->orderByDesc('esp_data_inicio')
                    ->get(),
            ],
        ]);
    }

    public function salvarFeriado(Request $request, ?int $id = null): JsonResponse
    {
        $empId = $this->empId($request);

        $validated = $request->validate([
            'fer_data' => ['required', 'date'],
            'fer_descricao' => ['required', 'string', 'max:100'],
            'fer_tipo' => ['required', Rule::in(['nacional', 'estadual', 'municipal', 'facultativo'])],
            'fer_status' => ['nullable', 'integer', Rule::in([0, 1])],
            'escopo_empresa' => ['nullable', 'boolean'],
        ]);

        $dados = [
            'emp_id' => $empId,
            'fer_data' => $validated['fer_data'],
            'fer_descricao' => trim($validated['fer_descricao']),
            'fer_tipo' => $validated['fer_tipo'],
            'fer_status' => isset($validated['fer_status']) ? (int) $validated['fer_status'] : 1,
        ];

        try {
            if ($id) {
                $feriado = Feriado::findOrFail($id);
                $dataAntiga = $feriado->fer_data instanceof \Carbon\CarbonInterface
                    ? $feriado->fer_data->format('Y-m-d')
                    : substr((string) $feriado->fer_data, 0, 10);

                $feriado->update($dados);

                // Sugestão C (14/11/2026) + Sugestão B (17/11/2026): Propaga a edição (incluindo fer_status) para as demais empresas
                Feriado::where('fer_id', '!=', $feriado->fer_id)
                    ->whereDate('fer_data', $dataAntiga)
                    ->update([
                        'fer_data' => $dados['fer_data'],
                        'fer_descricao' => $dados['fer_descricao'],
                        'fer_tipo' => $dados['fer_tipo'],
                        'fer_status' => $dados['fer_status'],
                    ]);

                Feriado::sincronizarEntreEmpresas();
                $this->recalcularEscalasPorDataFeriado($dados['fer_data'], $dataAntiga);
                $msg = 'Feriado atualizado e sincronizado entre todas as empresas!';
            } else {
                // Sugestão C (14/11/2026): Evita erro na chave única uk_feriados_emp_data (emp_id, fer_data)
                $existente = Feriado::where('emp_id', $empId)
                    ->whereDate('fer_data', $dados['fer_data'])
                    ->first();

                if ($existente) {
                    $existente->update($dados);
                    $feriado = $existente;
                } else {
                    $feriado = Feriado::create($dados);
                }

                Feriado::sincronizarEntreEmpresas();
                $this->recalcularEscalasPorDataFeriado($dados['fer_data']);
                $msg = 'Feriado cadastrado e sincronizado para todas as empresas!';
            }

            return response()->json([
                'status' => 'ok',
                'message' => $msg,
                'data' => $feriado,
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Já existe um feriado cadastrado para esta data.',
            ], 422);
        }
    }

    public function excluirFeriado(int $id): JsonResponse
    {
        $feriado = Feriado::findOrFail($id);
        $dataIso = $feriado->fer_data instanceof \Carbon\CarbonInterface
            ? $feriado->fer_data->format('Y-m-d')
            : substr((string) $feriado->fer_data, 0, 10);

        Feriado::whereDate('fer_data', $dataIso)->delete();
        $this->recalcularEscalasPorDataFeriado($dataIso);

        return response()->json([
            'status' => 'ok',
            'message' => 'Feriado removido com sucesso!',
        ]);
    }

    /**
     * Sugestão C (20/11/2026): Ao criar, editar, ativar/inativar (fer_status = 1/0) ou excluir um feriado,
     * recalcula automaticamente os períodos de escala já gerados que abrangem a data do feriado.
     */
    protected function recalcularEscalasPorDataFeriado(string $dataNova, ?string $dataAntiga = null): void
    {
        $datas = array_unique(array_filter([$dataNova, $dataAntiga]));
        if (empty($datas)) {
            return;
        }

        $periodosAfetados = EscalaPeriodo::where(function ($q) use ($datas) {
            foreach ($datas as $dt) {
                $q->orWhere(function ($q2) use ($dt) {
                    $q2->whereDate('esp_data_inicio', '<=', $dt)
                       ->whereDate('esp_data_fim', '>=', $dt);
                });
            }
        })->get();

        if ($periodosAfetados->isEmpty()) {
            return;
        }

        $escalaCtrl = app(EscalaApiController::class);
        foreach ($periodosAfetados as $per) {
            $escalaCtrl->recalcularLimitesPeriodo((int) $per->esp_id, (int) $per->emp_id);
        }
    }

    public function salvarConfiguracaoEscala(Request $request, ?int $id = null): JsonResponse
    {
        $empId = $this->empId($request);

        $validated = $request->validate([
            'cfg_nome' => ['required', 'string', 'max:100'],
            'cfg_tipo' => ['required', Rule::in(['fixa', 'revezamento'])],
            'cfg_dia_folga_1' => ['required', 'integer', 'between:1,7'],
            'cfg_dia_folga_2' => ['required', 'integer', 'between:1,7'],
            'cfg_ativo' => ['required', 'boolean'],
        ]);

        if ((int) $validated['cfg_dia_folga_1'] === (int) $validated['cfg_dia_folga_2']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Regra 5x2 (chk_cfg_folgas_distintas): O 1º dia de folga deve ser diferente do 2º dia de folga.',
            ], 422);
        }

        $validated['emp_id'] = $empId;

        if ($id) {
            $cfg = ConfiguracaoEscala::findOrFail($id);
            $cfg->update($validated);
            $msg = 'Configuração 5x2 atualizada com sucesso!';
        } else {
            $cfg = ConfiguracaoEscala::create($validated);
            $msg = 'Configuração 5x2 criada com sucesso!';
        }

        return response()->json([
            'status' => 'ok',
            'message' => $msg,
            'data' => $cfg,
        ]);
    }

    public function excluirConfiguracaoEscala(int $id): JsonResponse
    {
        $cfg = ConfiguracaoEscala::findOrFail($id);
        $cfg->delete();

        return response()->json([
            'status' => 'ok',
            'message' => 'Configuração 5x2 removida com sucesso!',
        ]);
    }

    public function salvarPeriodoEscala(Request $request, ?int $id = null): JsonResponse
    {
        $empId = $this->empId($request);

        $validated = $request->validate([
            'esp_descricao' => ['required', 'string', 'max:100'],
            'esp_data_inicio' => ['required', 'date'],
            'esp_data_fim' => ['required', 'date', 'after_or_equal:esp_data_inicio'],
            'esp_status' => ['required', Rule::in(['rascunho', 'publicada', 'encerrada'])],
        ], [
            'esp_data_fim.after_or_equal' => 'A data final do período deve ser igual ou posterior à data inicial (chk_esp_periodo).',
        ]);

        $validated['esp_descricao'] = trim($validated['esp_descricao']);
        $validated['emp_id'] = $empId;

        if ($id) {
            $periodo = EscalaPeriodo::findOrFail($id);
            $descAntiga = mb_strtolower(trim($periodo->esp_descricao), 'UTF-8');
            $iniAntigo = $periodo->esp_data_inicio instanceof \Carbon\CarbonInterface
                ? $periodo->esp_data_inicio->format('Y-m-d')
                : substr((string) $periodo->esp_data_inicio, 0, 10);
            $fimAntigo = $periodo->esp_data_fim instanceof \Carbon\CarbonInterface
                ? $periodo->esp_data_fim->format('Y-m-d')
                : substr((string) $periodo->esp_data_fim, 0, 10);

            $periodo->update($validated);

            // Propaga a atualização para os períodos equivalentes nas demais empresas
            $outrosPeriodos = EscalaPeriodo::where('esp_id', '!=', $periodo->esp_id)->get();
            foreach ($outrosPeriodos as $outro) {
                $descOutro = mb_strtolower(trim($outro->esp_descricao), 'UTF-8');
                $iniOutro = $outro->esp_data_inicio instanceof \Carbon\CarbonInterface
                    ? $outro->esp_data_inicio->format('Y-m-d')
                    : substr((string) $outro->esp_data_inicio, 0, 10);
                $fimOutro = $outro->esp_data_fim instanceof \Carbon\CarbonInterface
                    ? $outro->esp_data_fim->format('Y-m-d')
                    : substr((string) $outro->esp_data_fim, 0, 10);

                if ($descOutro === $descAntiga || ($iniOutro === $iniAntigo && $fimOutro === $fimAntigo)) {
                    $outro->update([
                        'esp_descricao' => $validated['esp_descricao'],
                        'esp_data_inicio' => $validated['esp_data_inicio'],
                        'esp_data_fim' => $validated['esp_data_fim'],
                        'esp_status' => $validated['esp_status'],
                    ]);
                }
            }

            EscalaPeriodo::sincronizarEntreEmpresas();
            $qtdFer = Feriado::gerarFeriadosDoPeriodo($validated['esp_data_inicio'], $validated['esp_data_fim']);
            Feriado::sincronizarEntreEmpresas();
            $msg = "Período operacional atualizado e sincronizado entre todas as empresas ({$qtdFer} feriado(s) do período sincronizado(s) na aba Feriados)!";
        } else {
            // Sugestão C (14/11/2026): Prevenção de duplicidade por descrição ou intervalo de datas na empresa ativa
            $chaveDesc = mb_strtolower($validated['esp_descricao'], 'UTF-8');
            $existente = EscalaPeriodo::where('emp_id', $empId)
                ->get()
                ->first(function (EscalaPeriodo $p) use ($chaveDesc, $validated) {
                    $descP = mb_strtolower(trim($p->esp_descricao), 'UTF-8');
                    $iniP = $p->esp_data_inicio instanceof \Carbon\CarbonInterface
                        ? $p->esp_data_inicio->format('Y-m-d')
                        : substr((string) $p->esp_data_inicio, 0, 10);
                    $fimP = $p->esp_data_fim instanceof \Carbon\CarbonInterface
                        ? $p->esp_data_fim->format('Y-m-d')
                        : substr((string) $p->esp_data_fim, 0, 10);

                    return $descP === $chaveDesc
                        || ($iniP === $validated['esp_data_inicio'] && $fimP === $validated['esp_data_fim']);
                });

            if ($existente) {
                $existente->update($validated);
                $periodo = $existente;
            } else {
                $periodo = EscalaPeriodo::create($validated);
            }

            // Itens 1, 2 e 3 + Sugestão A (14/11/2026): Replica automaticamente o período e gera os feriados do mês para todas as empresas
            EscalaPeriodo::sincronizarEntreEmpresas();
            $qtdFer = Feriado::gerarFeriadosDoPeriodo($validated['esp_data_inicio'], $validated['esp_data_fim']);
            Feriado::sincronizarEntreEmpresas();
            $msg = "Novo período operacional aberto e disponibilizado para todas as empresas ({$qtdFer} feriado(s) do período gerado(s) na aba Feriados)!";
        }

        return response()->json([
            'status' => 'ok',
            'message' => $msg,
            'data' => $periodo,
        ]);
    }

    public function excluirPeriodoEscala(int $id): JsonResponse
    {
        $periodo = EscalaPeriodo::findOrFail($id);
        $periodo->delete();

        return response()->json([
            'status' => 'ok',
            'message' => 'Período operacional e suas escalas diárias foram excluídos.',
        ]);
    }

    // =========================================================================
    // 6. PERFIS E PERMISSÕES (RBAC)
    // =========================================================================
    public function listarPerfisPermissoes(Request $request): JsonResponse
    {
        $empId = $this->empId($request);

        return response()->json([
            'status' => 'ok',
            'data' => [
                'perfis' => Perfil::with('permissoes')->where('emp_id', $empId)->orderBy('prf_id')->get(),
                'permissoes' => Permissao::orderBy('per_modulo')->orderBy('per_chave')->get(),
            ],
        ]);
    }

    public function salvarPerfilPermissoes(Request $request, ?int $id = null): JsonResponse
    {
        $empId = $this->empId($request);

        $validated = $request->validate([
            'prf_nome' => ['required', 'string', 'max:50'],
            'prf_descricao' => ['nullable', 'string', 'max:150'],
            'prf_ativo' => ['required', 'boolean'],
            'permissoes_ids' => ['nullable', 'array'],
            'permissoes_ids.*' => ['integer', 'exists:permissoes,per_id'],
        ]);

        DB::beginTransaction();
        try {
            $dados = [
                'emp_id' => $empId,
                'prf_nome' => $validated['prf_nome'],
                'prf_descricao' => $validated['prf_descricao'] ?? null,
                'prf_ativo' => $validated['prf_ativo'],
            ];

            if ($id) {
                $perfil = Perfil::findOrFail($id);
                $perfil->update($dados);
                $msg = 'Perfil e permissões atualizados com sucesso!';
            } else {
                $perfil = Perfil::create($dados);
                $msg = 'Novo perfil de acesso criado com sucesso!';
            }

            if (isset($validated['permissoes_ids'])) {
                $perfil->permissoes()->sync($validated['permissoes_ids']);
            }

            DB::commit();

            return response()->json([
                'status' => 'ok',
                'message' => $msg,
                'data' => $perfil->load('permissoes'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao salvar perfil: ' . $e->getMessage(),
            ], 422);
        }
    }
}

