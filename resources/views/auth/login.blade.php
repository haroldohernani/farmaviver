@extends('layouts.app')

@section('title', 'Login — FarmaViva Gestão de Escala 5x2')

@section('content')
<div class="fv-login-wrapper">
    <div class="fv-login-card">
        <div class="fv-login-card-header text-center">
            <div class="mb-2">
                <img src="{{ asset('img/logoformaviver.png') }}" alt="Logo FarmaViva" class="fv-login-logo">
            </div>
            <h1 class="h6 fw-bold mb-1 text-dark">
                <i class="bi bi-shield-lock me-1 text-secondary"></i>Acesso ao Sistema
            </h1>
            <p class="text-secondary small mb-0">
                Informe seu CPF (somente números) e sua senha
            </p>
        </div>

        <div class="fv-login-card-body">
            <!-- Container para Mensagens Bootstrap ("CPF Inválido", "Senha Inválida", "CPF E Senha Inválidos !!") -->
            <div id="fv-login-alert-container" aria-live="polite"></div>

            <form id="fv-login-form" method="POST" action="{{ route('api.auth.login') }}" novalidate autocomplete="off">
                @csrf

                <!-- Campo CPF: somente números sem formatação -->
                <div class="mb-3">
                    <label for="fun_cpf" class="form-label fw-bold text-dark fv-login-label text-uppercase mb-1">
                        <i class="bi bi-person-vcard me-1"></i>CPF do Funcionário
                    </label>
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="11"
                        class="form-control fv-form-control"
                        id="fun_cpf"
                        name="fun_cpf"
                        placeholder="Somente números (11 dígitos)"
                        autofocus
                        required
                    >
                    <div class="form-text text-secondary small">
                        Digite apenas números, sem pontos ou traços.
                    </div>
                </div>

                <!-- Campo Senha -->
                <div class="mb-3">
                    <label for="fun_senha" class="form-label fw-bold text-dark fv-login-label text-uppercase mb-1">
                        <i class="bi bi-key me-1"></i>SENHA
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control fv-form-control"
                            id="fun_senha"
                            name="fun_senha"
                            placeholder="Digite sua senha"
                            required
                        >
                        <button class="btn btn-fv-outline px-3" type="button" id="btn-toggle-senha" title="Mostrar/Ocultar Senha">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" id="btn-login-submit" class="btn btn-fv-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Entrar no Sistema
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
