<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\ConfiguracaoEscala;
use App\Models\Empresa;
use App\Models\EscalaDiaria;
use App\Models\EscalaPeriodo;
use App\Models\Feriado;
use App\Models\Funcionario;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\TrocaEscala;
use App\Models\TurnoHorario;
use App\Services\DatabaseProcedureService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        protected DatabaseProcedureService $dbService
    ) {}

    public function index(Request $request): View
    {
        $isAdmin = $request->session()->get('fun_perfil') === 'admin';
        $empresasAtivas = Empresa::where('emp_ativo', true)->orderBy('emp_id')->get();

        // Regra 03/11/2026 (Itens 5 e 6 + Sugestão C):
        // Administrador pode selecionar qualquer empresa ativa e visualizar todas as informações específicas dela
        if ($isAdmin && $request->filled('emp_id')) {
            $empSelecionada = Empresa::find((int) $request->query('emp_id'));
            if ($empSelecionada) {
                $request->session()->put([
                    'emp_id' => (int) $empSelecionada->emp_id,
                    'emp_nome_fantasia' => $empSelecionada->emp_nome_fantasia,
                ]);
            }
        } elseif (!$isAdmin) {
            $funId = (int) $request->session()->get('fun_id', 0);
            $empOrigem = (int) ($request->session()->get('emp_id_origem') ?: Funcionario::where('fun_id', $funId)->value('emp_id') ?: 1);
            $request->session()->put('emp_id', $empOrigem);
        }

        $empId = (int) $request->session()->get('emp_id', 1);
        $empresa = Empresa::find($empId) ?? $empresasAtivas->first() ?? Empresa::first();
        if ($empresa && (int) $empresa->emp_id !== $empId) {
            $empId = (int) $empresa->emp_id;
            $request->session()->put([
                'emp_id' => $empId,
                'emp_nome_fantasia' => $empresa->emp_nome_fantasia,
            ]);
        }

        $hoje = now()->toDateString();

        $periodos = EscalaPeriodo::where('emp_id', $empId)
            ->withCount('escalasDiarias')
            ->orderByDesc('esp_data_inicio')
            ->get();

        $espIdSelecionado = (int) $request->query('esp_id', 0);
        $periodoAtivo = $espIdSelecionado > 0
            ? $periodos->firstWhere('esp_id', $espIdSelecionado) ?? $periodos->first()
            : $periodos->first();

        // Data do Dashboard do Dia (filtrada pela empresa selecionada)
        $dataDashboard = $request->query('data_dia');
        if (!$dataDashboard) {
            $temHoje = EscalaDiaria::where('esd_data', $hoje)
                ->whereHas('periodo', fn ($q) => $q->where('emp_id', $empId))
                ->exists();
            if ($temHoje) {
                $dataDashboard = $hoje;
            } elseif ($periodoAtivo) {
                $dataDashboard = $periodoAtivo->esp_data_inicio->format('Y-m-d');
            } else {
                $dataDashboard = $hoje;
            }
        }

        $escalaHoje = $this->dbService->consultarEscalaDiariaCompleta([
            'emp_id' => $empId,
            'data' => $dataDashboard,
        ]);

        $auditoriaMulheres = $periodoAtivo
            ? $this->dbService->consultarAuditoriaDomingosMulheres($periodoAtivo->esp_id)
            : [];

        $resumoMensal = $periodoAtivo
            ? $this->dbService->consultarResumoMensalEscalas($periodoAtivo->esp_id)
            : [];

        $matrizEscala = [];
        $colunasMatriz = [];
        if ($periodoAtivo) {
            try {
                $matrizEscala = $this->dbService->relatorioEscalaMatriz($periodoAtivo->esp_id);
                if (!empty($matrizEscala)) {
                    foreach (array_keys($matrizEscala[0]) as $col) {
                        if (str_starts_with($col, 'D_')) {
                            $colunasMatriz[] = $col;
                        }
                    }
                }
            } catch (\Throwable) {
                $matrizEscala = [];
            }
        }

        // Semana inicial para o Visualizador Semanal
        $semanaInicio = $request->query('semana_inicio')
            ?: ($periodoAtivo ? $periodoAtivo->esp_data_inicio->format('Y-m-d') : $hoje);
        $semanaFim = $request->query('semana_fim')
            ?: date('Y-m-d', strtotime($semanaInicio . ' +6 days'));

        $escalaSemanal = $this->dbService->consultarEscalaDiariaCompleta([
            'emp_id' => $empId,
            'data_inicio' => $semanaInicio,
            'data_fim' => $semanaFim,
        ]);

        // Itens 2 e 3 + Sugestão A (13/11/2026): Garante que todos os cargos cadastrados estejam sincronizados entre todas as empresas
        Cargo::sincronizarEntreEmpresas();

        $cargos = Cargo::where('emp_id', $empId)->withCount('funcionarios')->orderBy('car_nome')->get();
        $turnos = TurnoHorario::where('emp_id', $empId)->withCount('funcionariosPadrao')->orderBy('tur_entrada')->get();
        $funcionarios = Funcionario::with(['cargo', 'turnoPadrao', 'perfis'])
            ->where('emp_id', $empId)
            ->orderBy('fun_nome')
            ->get();

        // Funcionários elegíveis à escala 5x2 (exclui Cargo Administrador + Perfil Admin + Status Ativo)
        $funcionariosEscala = $funcionarios
            ->where('fun_status', 'ativo')
            ->reject(fn (Funcionario $f) => $f->isAdministradorForaEscala())
            ->values();

        if (!$request->session()->has('login_timestamp')) {
            $request->session()->put('login_timestamp', now()->timestamp);
        }
        $configuracoes5x2 = ConfiguracaoEscala::where('emp_id', $empId)->orderBy('cfg_id')->get();
        $feriados = Feriado::whereNull('emp_id')->orWhere('emp_id', $empId)->orderBy('fer_data')->get();
        $trocas = TrocaEscala::with(['solicitante.cargo', 'substituto.cargo', 'escalaOrigem.turno', 'escalaDestino.turno', 'aprovador'])
            ->whereHas('solicitante', fn ($q) => $q->where('emp_id', $empId))
            ->orderByDesc('tro_id')
            ->get();
        $perfis = Perfil::with('permissoes')->where('emp_id', $empId)->orderBy('prf_id')->get();
        $permissoes = Permissao::orderBy('per_modulo')->orderBy('per_chave')->get();

        $resumoCards = [
            'empresas_ativas' => $empresasAtivas->count(),
            'funcionarios_ativos' => $funcionarios->where('fun_status', 'ativo')->count(),
            'funcionarios_total' => $funcionarios->count(),
            'cargos_ativos' => $cargos->where('car_ativo', true)->count(),
            'cargos_total' => $cargos->count(),
            'turnos_ativos' => $turnos->where('tur_ativo', true)->count(),
            'turnos_total' => $turnos->count(),
            'configuracoes_5x2' => $configuracoes5x2->where('cfg_ativo', true)->count(),
            'feriados_total' => $feriados->count(),
            'periodos_total' => $periodos->count(),
            'trocas_pendentes' => $trocas->where('tro_status', 'pendente')->count(),
            'trocas_total' => $trocas->count(),
            'afastados_total' => $funcionarios->whereIn('fun_status', ['afastado', 'ferias'])->count(),
            'alertas_clt_mulheres' => collect($auditoriaMulheres)
                ->where('status_clt_art_386', '!=', 'CONFORME')
                ->count(),
        ];

        return view('home.index', [
            'empresa' => $empresa,
            'empresasAtivas' => $empresasAtivas,
            'isAdmin' => $isAdmin,
            'periodoAtivo' => $periodoAtivo,
            'periodos' => $periodos,
            'dataDashboard' => $dataDashboard,
            'escalaHoje' => $escalaHoje,
            'auditoriaMulheres' => $auditoriaMulheres,
            'resumoMensal' => $resumoMensal,
            'matrizEscala' => $matrizEscala,
            'colunasMatriz' => $colunasMatriz,
            'semanaInicio' => $semanaInicio,
            'semanaFim' => $semanaFim,
            'escalaSemanal' => $escalaSemanal,
            'cargos' => $cargos,
            'turnos' => $turnos,
            'funcionarios' => $funcionarios,
            'funcionariosEscala' => $funcionariosEscala,
            'configuracoes5x2' => $configuracoes5x2,
            'feriados' => $feriados,
            'trocas' => $trocas,
            'perfis' => $perfis,
            'permissoes' => $permissoes,
            'resumoCards' => $resumoCards,
        ]);
    }

    /**
     * View dedicada do Módulo Empresa (CRUD de Empresas - Item 2 de 03/11/2026).
     */
    public function empresasView(Request $request): View
    {
        if (!$request->session()->has('login_timestamp')) {
            $request->session()->put('login_timestamp', now()->timestamp);
        }

        $isAdmin = $request->session()->get('fun_perfil') === 'admin';
        $empId = (int) $request->session()->get('emp_id', 1);
        $empresaAtiva = Empresa::find($empId) ?? Empresa::where('emp_ativo', true)->first() ?? Empresa::first();
        $empresas = Empresa::where('emp_ativo', true)->orderBy('emp_id')->get();

        return view('empresas.index', [
            'empresas' => $empresas,
            'empresaAtiva' => $empresaAtiva,
            'isAdmin' => $isAdmin,
        ]);
    }
}
