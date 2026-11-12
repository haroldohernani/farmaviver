<?php

use App\Http\Controllers\Api\CrudApiController;
use App\Http\Controllers\Api\EscalaApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Tela de Login e API de Autenticação
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/api/auth/login', [AuthController::class, 'apiLogin'])->name('api.auth.login');
Route::post('/api/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rotas Protegidas (Exigem Sessão Válida do Funcionário)
Route::middleware(['auth.funcionario'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/empresas', [HomeController::class, 'empresasView'])->name('empresas.index');

    // API RESTful - CRUDs e Gestão Operacional 5x2
    Route::prefix('api')->group(function () {
        // Empresas (Módulo Empresa - 03/11/2026)
        Route::get('/empresas', [CrudApiController::class, 'listarEmpresas']);
        Route::post('/empresas', [CrudApiController::class, 'salvarEmpresa']);
        Route::put('/empresas/{id}', [CrudApiController::class, 'salvarEmpresa']);
        Route::patch('/empresas/{id}/status', [CrudApiController::class, 'alterarStatusEmpresa']);
        Route::post('/empresas/selecionar', [CrudApiController::class, 'selecionarEmpresaAtiva']);

        // Cargos
        Route::get('/cargos', [CrudApiController::class, 'listarCargos']);
        Route::post('/cargos', [CrudApiController::class, 'salvarCargo']);
        Route::put('/cargos/{id}', [CrudApiController::class, 'salvarCargo']);
        Route::delete('/cargos/{id}', [CrudApiController::class, 'excluirCargo']);

        // Turnos / Horários
        Route::get('/turnos', [CrudApiController::class, 'listarTurnos']);
        Route::post('/turnos', [CrudApiController::class, 'salvarTurno']);
        Route::put('/turnos/{id}', [CrudApiController::class, 'salvarTurno']);
        Route::delete('/turnos/{id}', [CrudApiController::class, 'excluirTurno']);

        // Funcionários
        Route::get('/funcionarios', [CrudApiController::class, 'listarFuncionarios']);
        Route::post('/funcionarios', [CrudApiController::class, 'salvarFuncionario']);
        Route::put('/funcionarios/{id}', [CrudApiController::class, 'salvarFuncionario']);
        Route::delete('/funcionarios/{id}', [CrudApiController::class, 'excluirFuncionario']);

        // Calendário Operacional (Feriados, Configurações 5x2, Períodos)
        Route::get('/calendario', [CrudApiController::class, 'listarCalendarioOperacional']);
        Route::get('/calendario-operacional', [CrudApiController::class, 'listarCalendarioOperacional']);
        Route::post('/feriados', [CrudApiController::class, 'salvarFeriado']);
        Route::put('/feriados/{id}', [CrudApiController::class, 'salvarFeriado']);
        Route::delete('/feriados/{id}', [CrudApiController::class, 'excluirFeriado']);

        Route::post('/configuracoes-escala', [CrudApiController::class, 'salvarConfiguracaoEscala']);
        Route::put('/configuracoes-escala/{id}', [CrudApiController::class, 'salvarConfiguracaoEscala']);
        Route::delete('/configuracoes-escala/{id}', [CrudApiController::class, 'excluirConfiguracaoEscala']);

        Route::post('/periodos', [CrudApiController::class, 'salvarPeriodoEscala']);
        Route::put('/periodos/{id}', [CrudApiController::class, 'salvarPeriodoEscala']);
        Route::delete('/periodos/{id}', [CrudApiController::class, 'excluirPeriodoEscala']);

        // Perfis e Permissões (RBAC)
        Route::get('/perfis', [CrudApiController::class, 'listarPerfisPermissoes']);
        Route::post('/perfis', [CrudApiController::class, 'salvarPerfilPermissoes']);
        Route::put('/perfis/{id}', [CrudApiController::class, 'salvarPerfilPermissoes']);

        // Escalas 5x2, Dashboard do Dia, Visualizador Mensal/Semanal e Edição Diária
        Route::get('/escalas/dashboard-dia', [EscalaApiController::class, 'dashboardDia']);
        Route::get('/escalas/visualizador', [EscalaApiController::class, 'visualizador']);
        Route::post('/escalas/gerar', [EscalaApiController::class, 'gerarEscala5x2']);
        Route::put('/escalas/diaria/{id}', [EscalaApiController::class, 'atualizarEscalaDiaria']);

        // Afastamento e Trocas Pontuais
        Route::get('/trocas', [EscalaApiController::class, 'listarTrocasEAfastamentos']);
        Route::get('/trocas/dias-funcionario/{funId}', [EscalaApiController::class, 'buscarDiasEscalaFuncionario']);
        Route::post('/trocas', [EscalaApiController::class, 'solicitarTroca']);
        Route::post('/trocas/{id}/aprovar', [EscalaApiController::class, 'aprovarTroca']);
        Route::post('/trocas/{id}/status', [EscalaApiController::class, 'alterarStatusTroca']);
        Route::post('/afastamentos', [EscalaApiController::class, 'registrarAfastamento']);
    });
});
