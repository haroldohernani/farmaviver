@extends('layouts.app')

@section('title', 'Módulo Empresa — FarmaViva Gestão de Escala 5x2')

@section('header')
<header class="fv-home-navbar sticky-top">
    <div class="container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <!-- Canto Esquerdo: Logo + Usuário Logado + Tempo Logado em Tempo Real -->
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('home') }}" class="text-decoration-none d-flex align-items-center">
                    <img src="{{ asset('img/logoformaviver.png') }}" alt="Logo FarmaViva" class="fv-home-logo">
                </a>

                <div class="fv-user-badge d-flex flex-wrap align-items-center gap-2">
                    <span>
                        <i class="bi bi-person-circle me-1 text-secondary"></i>
                        <strong>{{ session('fun_nome') }}</strong>
                        <span class="text-secondary d-none d-sm-inline">({{ session('car_nome') }} • {{ strtoupper(session('fun_perfil')) }})</span>
                    </span>
                    <span class="fv-clock-badge" id="fv-realtime-clock" data-login-timestamp="{{ session('login_timestamp', now()->timestamp) }}" title="Tempo logado na sessão atual">
                        <i class="bi bi-stopwatch me-1"></i>Tempo logado: 00:00:00
                    </span>
                </div>
            </div>

            <!-- Direita: Voltar para Home + Botão Sair -->
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('home') }}" class="btn btn-sm btn-fv-outline d-inline-flex align-items-center gap-1">
                    <i class="bi bi-arrow-left"></i>
                    <span>Voltar para Home</span>
                </a>
                <button type="button" id="btn-logout-all" class="btn btn-sm btn-fv-primary d-flex align-items-center gap-1 shadow-sm" title="Fechar todas as sessões abertas e sair">
                    <i class="bi bi-power"></i>
                    <span>Sair</span>
                </button>
            </div>
        </div>
    </div>
</header>
@endsection

@section('content')
<div class="container-fluid py-2 py-md-3">

    <!-- Container de Alertas da View de Empresas -->
    <div id="alert-view-empresas"></div>

    <!-- Cabeçalho do Módulo Empresa -->
    <div class="card border-0 shadow-sm mb-3" style="background-color: #e9ecef; border-left: 4px solid #343a40 !important;">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h1 class="fv-banner-title fw-bold mb-0 text-dark">
                    <i class="bi bi-building-gear me-1"></i>Módulo Empresa — Gestão e Cadastro de Unidades
                </h1>
                <p class="small text-secondary mb-0">
                    Empresa atualmente selecionada na sessão:
                    <strong id="label-empresa-sessao-atual">{{ $empresaAtiva->emp_nome_fantasia ?? session('emp_nome_fantasia') }}</strong>
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('home') }}" class="btn btn-sm btn-fv-primary">
                    <i class="bi bi-house-door me-1"></i>Ir para o Painel Principal (Home)
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Coluna Esquerda: Formulário de Cadastro / Alteração de Empresa -->
        <div class="col-12 col-lg-4">
            <div class="card border shadow-sm">
                <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                    <span id="form-empresa-view-titulo">
                        <i class="bi bi-plus-circle me-1"></i>Nova Empresa
                    </span>
                    <button type="button" class="btn btn-sm btn-fv-outline" id="btn-limpar-view-empresa">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpar
                    </button>
                </div>
                <div class="card-body">
                    <form id="form-view-empresa" autocomplete="off">
                        <input type="hidden" id="view_emp_id" value="">

                        <div class="row g-2">
                            <div class="col-12">
                                <label for="view_emp_nome_fantasia" class="form-label small fw-semibold mb-1">Nome Fantasia *</label>
                                <input type="text" class="form-control form-control-sm fv-form-control" id="view_emp_nome_fantasia" placeholder="Ex: Drogaria Farma Viver - Filial Centro" required>
                            </div>

                            <div class="col-12">
                                <label for="view_emp_razao_social" class="form-label small fw-semibold mb-1">Razão Social</label>
                                <input type="text" class="form-control form-control-sm fv-form-control" id="view_emp_razao_social" placeholder="Ex: Farma Viver Comércio Farmacêutico Ltda">
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="view_emp_cnpj" class="form-label small fw-semibold mb-1">CNPJ (somente números) *</label>
                                <input type="text" inputmode="numeric" maxlength="14" class="form-control form-control-sm fv-form-control fv-only-digits" id="view_emp_cnpj" placeholder="14 dígitos" required>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="view_emp_celular" class="form-label small fw-semibold mb-1">Celular *</label>
                                <input type="text" maxlength="20" class="form-control form-control-sm fv-form-control" id="view_emp_celular" placeholder="Ex: 92981614980" required>
                            </div>

                            <div class="col-12">
                                <label for="view_emp_email" class="form-label small fw-semibold mb-1">E-mail *</label>
                                <input type="email" class="form-control form-control-sm fv-form-control" id="view_emp_email" placeholder="contato@farmaviver.com.br" required>
                            </div>

                            <div class="col-8">
                                <label for="view_emp_logradouro" class="form-label small fw-semibold mb-1">Logradouro</label>
                                <input type="text" class="form-control form-control-sm fv-form-control" id="view_emp_logradouro" placeholder="Av. / Rua">
                            </div>

                            <div class="col-4">
                                <label for="view_emp_numero" class="form-label small fw-semibold mb-1">Número</label>
                                <input type="text" class="form-control form-control-sm fv-form-control" id="view_emp_numero" placeholder="S/N">
                            </div>

                            <div class="col-5">
                                <label for="view_emp_bairro" class="form-label small fw-semibold mb-1">Bairro</label>
                                <input type="text" class="form-control form-control-sm fv-form-control" id="view_emp_bairro">
                            </div>

                            <div class="col-5">
                                <label for="view_emp_cidade" class="form-label small fw-semibold mb-1">Cidade</label>
                                <input type="text" class="form-control form-control-sm fv-form-control" id="view_emp_cidade" value="Manaus">
                            </div>

                            <div class="col-2">
                                <label for="view_emp_uf" class="form-label small fw-semibold mb-1">UF</label>
                                <input type="text" maxlength="2" class="form-control form-control-sm fv-form-control text-uppercase" id="view_emp_uf" value="AM">
                            </div>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-sm btn-fv-primary" id="btn-salvar-view-empresa">
                                <i class="bi bi-check2-circle me-1"></i>Salvar Empresa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Tabela de Empresas Cadastradas e Ativas -->
        <div class="col-12 col-lg-8">
            <div class="card border shadow-sm">
                <div class="card-header bg-light fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>
                        <i class="bi bi-building-check me-1 text-secondary"></i>Empresas Cadastradas e Ativas
                        <span class="badge bg-secondary ms-1" id="badge-count-empresas">{{ $empresas->count() }}</span>
                    </span>

                    <!-- Sugestão B: Alternar exibição de inativas caso precise reativar -->
                    <div class="form-check form-switch mb-0 small fw-normal">
                        <input class="form-check-input" type="checkbox" role="switch" id="chk-mostrar-empresas-inativas">
                        <label class="form-check-label text-secondary" for="chk-mostrar-empresas-inativas">Mostrar desativadas</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome<br>Fantasia</th>
                                    <th>CNPJ</th>
                                    <th>E-mail</th>
                                    <th>Celular</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-crud-empresas">
                                @forelse($empresas as $emp)
                                    <tr data-emp-id="{{ $emp->emp_id }}">
                                        <td>
                                            <div class="fw-bold text-dark">
                                                {{ $emp->emp_nome_fantasia }}
                                                @if((int) session('emp_id') === (int) $emp->emp_id)
                                                    <span class="badge bg-dark ms-1">Selecionada</span>
                                                @endif
                                                @if(!$emp->emp_ativo)
                                                    <span class="badge bg-warning text-dark ms-1">Inativa</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td><code>{{ $emp->emp_cnpj }}</code></td>
                                        <td>{{ $emp->emp_email }}</td>
                                        <td>{{ $emp->emp_celular ?? $emp->emp_telefone ?? '-' }}</td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm btn-fv-outline btn-edit-view-empresa" title="Alterar Empresa"
                                                data-json="{{ json_encode([
                                                    'emp_id' => $emp->emp_id,
                                                    'emp_nome_fantasia' => $emp->emp_nome_fantasia,
                                                    'emp_razao_social' => $emp->emp_razao_social,
                                                    'emp_cnpj' => $emp->emp_cnpj,
                                                    'emp_email' => $emp->emp_email,
                                                    'emp_celular' => $emp->emp_celular ?? $emp->emp_telefone,
                                                    'emp_logradouro' => $emp->emp_logradouro,
                                                    'emp_numero' => $emp->emp_numero,
                                                    'emp_bairro' => $emp->emp_bairro,
                                                    'emp_cidade' => $emp->emp_cidade,
                                                    'emp_uf' => $emp->emp_uf,
                                                ]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-deactivate-view-empresa"
                                                data-id="{{ $emp->emp_id }}"
                                                data-nome="{{ $emp->emp_nome_fantasia }}"
                                                data-ativo="0"
                                                title="Desativar Empresa">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-3">Nenhuma empresa ativa encontrada.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

