<?php

namespace App\Http\Middleware;

use App\Models\Funcionario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFuncionarioAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $funId = $request->session()->get('fun_id');
        $sessionToken = $request->session()->get('auth_session_token');

        if (!$funId || !$sessionToken) {
            return $this->unauthenticated($request);
        }

        $funcionario = Funcionario::with(['empresa', 'cargo', 'turnoPadrao'])
            ->where('fun_id', $funId)
            ->where('fun_status', 'ativo')
            ->first();

        if (
            !$funcionario ||
            empty($funcionario->fun_remember_token) ||
            !hash_equals((string) $funcionario->fun_remember_token, (string) $sessionToken)
        ) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $this->unauthenticated($request);
        }

        // Disponibiliza o funcionário autenticado na request
        $request->attributes->set('funcionario', $funcionario);

        return $next($request);
    }

    protected function unauthenticated(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sessão expirada ou encerrada. Faça login novamente.',
                'redirect' => route('login'),
            ], 401);
        }

        return redirect()->route('login');
    }
}

