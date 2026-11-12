<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Funcionario;
use App\Services\DatabaseProcedureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected DatabaseProcedureService $dbService
    ) {}

    /**
     * Exibe a tela de Login Mobile First.
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        $funId = $request->session()->get('fun_id');
        $sessionToken = $request->session()->get('auth_session_token');

        if ($funId && $sessionToken) {
            $func = Funcionario::where('fun_id', $funId)
                ->where('fun_status', 'ativo')
                ->first();

            if ($func && !empty($func->fun_remember_token) && hash_equals($func->fun_remember_token, $sessionToken)) {
                return redirect()->route('home');
            }
        }

        $colaboradoresDemo = Funcionario::with('cargo')
            ->where('fun_status', 'ativo')
            ->orderBy('fun_id')
            ->get();

        return view('auth.login', [
            'colaboradoresDemo' => $colaboradoresDemo,
        ]);
    }

    /**
     * API de consulta e autenticação no banco farmaviver.
     * Regras de negócio (farmaviver.md):
     * - fun_cpf somente números sem formatação.
     * - Se fun_cpf estiver errado -> "CPF Inválido" e foco em fun_cpf.
     * - Se fun_senha estiver errada -> "Senha Inválida" e foco em fun_senha.
     * - Se os 2 campos estiverem errados após consulta na tabela de funcionários -> "CPF E Senha Inválidos !!" e foco em fun_cpf.
     * - Se conferirem -> redireciona para a Home da aplicação.
     */
    public function apiLogin(Request $request): JsonResponse
    {
        $rawCpf = (string) $request->input('fun_cpf', '');
        $senha = (string) $request->input('fun_senha', '');
        $ip = (string) ($request->ip() ?? '127.0.0.1');
        $userAgent = $request->userAgent();

        // Garante que apenas dígitos numéricos sem formatação sejam aceitos
        $apenasNumeros = preg_match('/^\d{11}$/', $rawCpf) === 1;
        $cpfLimpo = preg_replace('/\D/', '', $rawCpf);

        // 1. Consulta via Stored Procedure sp_autenticar_funcionario no banco farmaviver
        $registroSp = ($apenasNumeros && strlen($cpfLimpo) === 11)
            ? $this->dbService->autenticarFuncionario($cpfLimpo)
            : null;

        // Regra 03/11/2026 (Item 5): Todo usuário com Perfil Administrador pode acessar qualquer empresa cadastrada
        if ($registroSp === null && $apenasNumeros && strlen($cpfLimpo) === 11) {
            $adminGlobal = Funcionario::with(['empresa', 'cargo'])
                ->where('fun_cpf', $cpfLimpo)
                ->where('fun_status', 'ativo')
                ->where('fun_perfil', 'admin')
                ->first();

            if ($adminGlobal) {
                $empresaInicial = Empresa::where('emp_ativo', true)->orderBy('emp_id')->first() ?? $adminGlobal->empresa;
                $registroSp = [
                    'fun_id' => $adminGlobal->fun_id,
                    'emp_id' => $empresaInicial ? $empresaInicial->emp_id : $adminGlobal->emp_id,
                    'emp_nome_fantasia' => $empresaInicial ? $empresaInicial->emp_nome_fantasia : '',
                    'fun_nome' => $adminGlobal->fun_nome,
                    'fun_cpf' => $adminGlobal->fun_cpf,
                    'fun_matricula' => $adminGlobal->fun_matricula,
                    'fun_senha' => $adminGlobal->fun_senha,
                    'fun_perfil' => $adminGlobal->fun_perfil,
                    'car_id' => $adminGlobal->car_id,
                    'car_nome' => $adminGlobal->cargo?->car_nome ?? 'Administrador',
                ];
            }
        }

        // Caso o CPF exista na tabela de funcionários ativos
        if ($registroSp !== null) {
            $funId = (int) $registroSp['fun_id'];
            $hashArmazenado = (string) $registroSp['fun_senha'];

            if ($this->verificarSenha($senha, $hashArmazenado)) {
                // Sucesso: CPF e Senha conferem
                $this->dbService->registrarTentativaLogin(
                    $funId,
                    $cpfLimpo,
                    'sucesso',
                    $ip,
                    $userAgent,
                    'Autenticação bem-sucedida via WebApp'
                );

                $permissoesData = $this->dbService->obterPermissoesFuncionario($funId);

                // Gera token de sessão exclusivo para permitir encerramento global no botão Sair
                $sessionToken = Str::random(60);
                Funcionario::where('fun_id', $funId)->update([
                    'fun_remember_token' => $sessionToken,
                    'fun_ultimo_login' => now(),
                ]);

                $request->session()->regenerate();
                $request->session()->put([
                    'fun_id' => $funId,
                    'emp_id' => (int) $registroSp['emp_id'],
                    'emp_id_origem' => (int) $registroSp['emp_id'],
                    'emp_nome_fantasia' => $registroSp['emp_nome_fantasia'],
                    'fun_nome' => $registroSp['fun_nome'],
                    'fun_cpf' => $registroSp['fun_cpf'],
                    'fun_matricula' => $registroSp['fun_matricula'],
                    'fun_perfil' => $registroSp['fun_perfil'],
                    'car_id' => (int) $registroSp['car_id'],
                    'car_nome' => $registroSp['car_nome'],
                    'perfis' => $permissoesData['perfis'] ?? [],
                    'permissoes' => $permissoesData['permissoes'] ?? [],
                    'auth_session_token' => $sessionToken,
                    'login_timestamp' => now()->timestamp,
                ]);

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Autenticação realizada com sucesso!',
                    'redirect' => route('home'),
                ]);
            }

            // CPF existe, porém fun_senha está errada
            $this->dbService->registrarTentativaLogin(
                $funId,
                $cpfLimpo,
                'senha_invalida',
                $ip,
                $userAgent,
                'Senha Inválida'
            );

            return response()->json([
                'status' => 'error',
                'error_type' => 'senha_invalida',
                'message' => 'Senha Inválida',
                'focus_field' => 'fun_senha',
            ], 401);
        }

        // 2. O CPF informado NÃO confere na tabela de funcionários.
        // Verificamos na tabela de funcionários se a senha informada confere com algum registro ativo
        // para distinguir entre apenas "CPF Inválido" vs os 2 campos errados ("CPF E Senha Inválidos !!").
        $algumaSenhaConfere = false;
        if ($senha !== '') {
            $hashesAtivos = Funcionario::where('fun_status', 'ativo')->pluck('fun_senha');
            foreach ($hashesAtivos as $hash) {
                if ($this->verificarSenha($senha, (string) $hash)) {
                    $algumaSenhaConfere = true;
                    break;
                }
            }
        }

        $cpfLog = $cpfLimpo !== '' ? substr($cpfLimpo, 0, 14) : 'VAZIO';

        if ($algumaSenhaConfere) {
            $this->dbService->registrarTentativaLogin(
                null,
                $cpfLog,
                'usuario_inexistente',
                $ip,
                $userAgent,
                'CPF Inválido'
            );

            return response()->json([
                'status' => 'error',
                'error_type' => 'cpf_invalido',
                'message' => 'CPF Inválido',
                'focus_field' => 'fun_cpf',
            ], 401);
        }

        $this->dbService->registrarTentativaLogin(
            null,
            $cpfLog,
            'usuario_inexistente',
            $ip,
            $userAgent,
            'CPF E Senha Inválidos !!'
        );

        return response()->json([
            'status' => 'error',
            'error_type' => 'ambos_invalidos',
            'message' => 'CPF E Senha Inválidos !!',
            'focus_field' => 'fun_cpf',
        ], 401);
    }

    /**
     * Botão Sair: Fecha todas as sessões abertas do sistema para segurança e redireciona para o Login.
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $funId = $request->session()->get('fun_id');

        if ($funId) {
            Funcionario::where('fun_id', $funId)->update([
                'fun_remember_token' => null,
            ]);
        } else {
            Funcionario::query()->whereNotNull('fun_remember_token')->update([
                'fun_remember_token' => null,
            ]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $sessionPath = storage_path('framework/sessions');
        if (File::isDirectory($sessionPath)) {
            foreach (File::files($sessionPath) as $file) {
                if ($file->getFilename() !== '.gitignore') {
                    @File::delete($file->getPathname());
                }
            }
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Todas as sessões foram encerradas com segurança.',
                'redirect' => route('login'),
            ]);
        }

        return redirect()->route('login');
    }

    protected function verificarSenha(string $senhaInformada, string $hashArmazenado): bool
    {
        if ($senhaInformada === '' || $hashArmazenado === '') {
            return false;
        }

        if (str_starts_with($hashArmazenado, '$2y$') || str_starts_with($hashArmazenado, '$2a$') || str_starts_with($hashArmazenado, '$argon2')) {
            if (Hash::check($senhaInformada, $hashArmazenado)) {
                return true;
            }
        }

        return hash_equals($hashArmazenado, $senhaInformada);
    }
}
