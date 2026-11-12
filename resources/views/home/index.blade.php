@extends('layouts.app')

@section('title', 'Home — FarmaViva Gestão de Escala 5x2')

@section('header')
<!-- Head da Home: Canto esquerdo Logo + Usuário Logado + Tempo Logado em tempo real | Mais à direita Botão Sair -->
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

            <!-- Mais à Direita: Atalho Módulo Empresa + Botão Sair -->
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('empresas.index') }}" class="btn btn-sm btn-fv-outline d-none d-md-inline-flex align-items-center gap-1">
                    <i class="bi bi-building-gear"></i>
                    <span>Módulo Empresa</span>
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
@php
    $diasSemanaPt = [
        1 => 'Domingo',
        2 => 'Segunda-feira',
        3 => 'Terça-feira',
        4 => 'Quarta-feira',
        5 => 'Quinta-feira',
        6 => 'Sexta-feira',
        7 => 'Sábado',
    ];
@endphp

<div class="container-fluid py-2 py-md-3">

    <!-- Container Global de Alertas de Operações -->
    <div id="fv-global-alert-container"></div>

    <!-- Banner Superior da Empresa, Seletor Multiempresa (Admin) e Ações Rápidas -->
    <div class="card border-0 shadow-sm mb-3" style="background-color: #e9ecef; border-left: 4px solid #343a40 !important;">
        <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h1 class="fv-banner-title fw-bold mb-0 text-dark">
                    <i class="bi bi-shop me-1"></i>{{ $empresa->emp_nome_fantasia ?? 'Drogaria Farma Viver' }}
                    <span class="badge bg-secondary ms-1">CNPJ: {{ $empresa->emp_cnpj ?? '' }}</span>
                </h1>
                <p class="small text-secondary mb-0">
                    Período Selecionado:
                    <strong>{{ $periodoAtivo->esp_descricao ?? 'Nenhum período selecionado' }}</strong>
                    @if($periodoAtivo)
                        <span class="badge bg-dark ms-1">{{ strtoupper($periodoAtivo->esp_status) }}</span>
                    @endif
                    <span class="ms-2 d-inline-block">
                        <i class="bi bi-clock me-1"></i>Expediente: <strong>Sáb 08h às 20h</strong> | <strong>Dom e Feriados 08h às 13h</strong>
                    </span>
                </p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @if(!empty($isAdmin))
                    <form method="GET" action="{{ route('home') }}" class="d-flex align-items-center gap-1">
                        <label for="select-home-empresa-ativa" class="small fw-bold text-dark text-nowrap mb-0">
                            <i class="bi bi-buildings me-1"></i>Empresa:
                        </label>
                        <select name="emp_id" id="select-home-empresa-ativa" class="form-select form-select-sm fv-form-control fw-semibold" onchange="this.form.submit()" style="min-width: 210px;">
                            @foreach($empresasAtivas as $empItem)
                                <option value="{{ $empItem->emp_id }}" {{ optional($empresa)->emp_id === $empItem->emp_id ? 'selected' : '' }}>
                                    {{ $empItem->emp_nome_fantasia }} ({{ $empItem->emp_cnpj }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <button type="button" class="btn btn-sm btn-fv-primary" data-bs-toggle="modal" data-bs-target="#modalGeradorEscala">
                    <i class="bi bi-magic me-1"></i>Gerador Automático 5x2
                </button>
                <button type="button" class="btn btn-sm btn-fv-outline" data-bs-toggle="modal" data-bs-target="#modalFuncionarios">
                    <i class="bi bi-person-plus me-1"></i>Funcionários (<span id="btn-top-fun-count">{{ $resumoCards['funcionarios_ativos'] }}</span>)
                </button>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         SEÇÃO 1: CARDS DE GESTÃO (Sem enunciados e sem textos descritivos no Body)
         =================================================================== -->
    <div class="row g-2 mb-3">
        <!-- Card 1: Funcionários -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fv-module-card">
                <div class="fv-module-card-header">
                    <span class="fw-bold text-dark">1. Funcionários</span>
                    <span class="fv-icon-box"><i class="bi bi-people"></i></span>
                </div>
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge bg-secondary" id="badge-home-fun-ativos">{{ $resumoCards['funcionarios_ativos'] }} ativos</span>
                        <span class="badge bg-light text-dark border" id="badge-home-fun-total">{{ $resumoCards['funcionarios_total'] }} total</span>
                        <span class="badge bg-dark" id="badge-home-fun-escala">{{ $funcionariosEscala->count() }} na escala 5x2</span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <button type="button" class="btn btn-sm btn-fv-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalFuncionarios">
                            <i class="bi bi-pencil-square me-1"></i>Gestão de Funcionários
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Cargos -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fv-module-card">
                <div class="fv-module-card-header">
                    <span class="fw-bold text-dark">2. Cargos</span>
                    <span class="fv-icon-box"><i class="bi bi-briefcase"></i></span>
                </div>
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge bg-secondary" id="badge-home-car-ativos">{{ $resumoCards['cargos_ativos'] }} cargos ativos</span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <button type="button" class="btn btn-sm btn-fv-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalCargos">
                            <i class="bi bi-pencil-square me-1"></i>Cargos
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Calendário de Turnos / Horários -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fv-module-card">
                <div class="fv-module-card-header">
                    <span class="fw-bold text-dark">3. Calendário de Turnos / Horários</span>
                    <span class="fv-icon-box"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge bg-secondary" id="badge-home-tur-ativos">{{ $resumoCards['turnos_ativos'] }} turnos ativos</span>
                        <span class="badge bg-light text-dark border">Sáb: 08h-20h</span>
                        <span class="badge bg-light text-dark border">Dom/Fer: 08h-13h</span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <button type="button" class="btn btn-sm btn-fv-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalTurnos">
                            <i class="bi bi-pencil-square me-1"></i>Turnos
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: Calendário Operacional e Feriados -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fv-module-card">
                <div class="fv-module-card-header">
                    <span class="fw-bold text-dark">4. Calendário Operacional e Feriados</span>
                    <span class="fv-icon-box"><i class="bi bi-calendar3"></i></span>
                </div>
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge bg-secondary" id="badge-home-per-total">{{ $resumoCards['periodos_total'] }} períodos</span>
                        <span class="badge bg-dark" id="badge-home-cfg-total">{{ $resumoCards['configuracoes_5x2'] }} regras 5x2</span>
                        <span class="badge bg-light text-dark border" id="badge-home-fer-total">{{ $resumoCards['feriados_total'] }} feriados</span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <button type="button" class="btn btn-sm btn-fv-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalCalendario">
                            <i class="bi bi-calendar-plus me-1"></i>Abrir Calendário & Feriados
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 5: Afastamento e Trocas Pontuais -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fv-module-card">
                <div class="fv-module-card-header">
                    <span class="fw-bold text-dark">5. Afastamento e Trocas Pontuais</span>
                    <span class="fv-icon-box"><i class="bi bi-arrow-left-right"></i></span>
                </div>
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge {{ $resumoCards['trocas_pendentes'] > 0 ? 'bg-warning text-dark' : 'bg-secondary' }}">
                            {{ $resumoCards['trocas_pendentes'] }} trocas pendentes
                        </span>
                        <span class="badge bg-light text-dark border">{{ $resumoCards['afastados_total'] }} afastados/férias</span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <button type="button" class="btn btn-sm btn-fv-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalTrocasAfastamentos">
                            <i class="bi bi-arrow-left-right me-1"></i>Gerenciar Trocas & Afastamentos
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 6: Gerador Automático de Escala 5x2 & Auditoria -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fv-module-card">
                <div class="fv-module-card-header">
                    <span class="fw-bold text-dark">6. Gerador Automático 5x2 & Auditoria</span>
                    <span class="fv-icon-box"><i class="bi bi-magic"></i></span>
                </div>
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge {{ $resumoCards['alertas_clt_mulheres'] === 0 ? 'bg-dark' : 'bg-danger' }}">
                            CLT Art. 386: {{ $resumoCards['alertas_clt_mulheres'] === 0 ? 'CONFORME' : 'ALERTA' }}
                        </span>
                        <span class="badge bg-light text-dark border">Domingos Mulheres</span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <button type="button" class="btn btn-sm btn-fv-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalGeradorEscala">
                            <i class="bi bi-gear-wide-connected me-1"></i>Gerar Escala 5x2
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 7: Módulo Empresa (CRUD em View dedicada - Item 1 e 2 de 03/11/2026) -->
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="fv-module-card">
                <div class="fv-module-card-header">
                    <span class="fw-bold text-dark">7. Módulo Empresa</span>
                    <span class="fv-icon-box"><i class="bi bi-building-gear"></i></span>
                </div>
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between flex-grow-1">
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge bg-secondary" id="badge-home-emp-ativas">{{ $resumoCards['empresas_ativas'] ?? 1 }} empresas ativas</span>
                        <span class="badge bg-light text-dark border">Atual: {{ Str::limit($empresa->emp_nome_fantasia ?? '', 24) }}</span>
                    </div>
                    <div class="d-flex gap-2 mt-auto">
                        <a href="{{ route('empresas.index') }}" class="btn btn-sm btn-fv-primary flex-grow-1 text-center">
                            <i class="bi bi-building-add me-1"></i>Gestão de Empresas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================
         SEÇÃO 2: INTERFACES OPERACIONAIS RESPONSIVAS
         (Dashboard do Dia | Visualizador Mensal Matriz | Semanal | Auditoria Mulheres)
         =================================================================== -->
    <div class="card border shadow-sm">
        <div class="card-header bg-light py-2 px-3">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                <ul class="nav nav-pills fv-nav-pills flex-column mb-0" id="fv-main-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active text-start w-100" id="tab-btn-dia" data-bs-toggle="pill" data-bs-target="#pane-visao-dia" type="button" role="tab">
                            <i class="bi bi-sun me-1"></i>Dashboard / Visão do Dia
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-start w-100" id="tab-btn-mensal" data-bs-toggle="pill" data-bs-target="#pane-escala-mensal" type="button" role="tab">
                            <i class="bi bi-grid-3x3 me-1"></i>Visualizador Mensal (Matriz)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-start w-100" id="tab-btn-semanal" data-bs-toggle="pill" data-bs-target="#pane-escala-semanal" type="button" role="tab">
                            <i class="bi bi-calendar-week me-1"></i>Visualizador Semanal / Edição
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-start w-100" id="tab-btn-auditoria" data-bs-toggle="pill" data-bs-target="#pane-auditoria-mulheres" type="button" role="tab">
                            <i class="bi bi-shield-check me-1"></i>Auditoria Domingos Mulheres (CLT)
                        </button>
                    </li>
                </ul>

                <!-- Seletor Rápido de Período Ativo -->
                <form method="GET" action="{{ route('home') }}" class="d-flex align-items-center gap-2">
                    <label for="select_top_esp_id" class="small fw-semibold text-secondary text-nowrap mb-0">Período:</label>
                    <select name="esp_id" id="select_top_esp_id" class="form-select form-select-sm fv-form-control" onchange="this.form.submit()">
                        @foreach($periodos as $p)
                            <option value="{{ $p->esp_id }}" {{ optional($periodoAtivo)->esp_id === $p->esp_id ? 'selected' : '' }}>
                                {{ $p->esp_descricao }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="card-body p-2 p-md-3">
            <div class="tab-content">

                <!-- =======================================================
                     ABA 1: DASHBOARD / VISÃO DO DIA (vw_escala_diaria_completa)
                     ======================================================= -->
                <div class="tab-pane fade show active" id="pane-visao-dia" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div>
                            <h3 class="fv-section-title fw-bold mb-0 text-dark">
                                <i class="bi bi-calendar2-day me-1"></i>Visão Operacional do Dia:
                                <span id="label-data-dashboard" class="badge bg-dark ms-1">{{ \Carbon\Carbon::parse($dataDashboard)->format('d/m/Y') }}</span>
                            </h3>
                            <small class="text-secondary">Acompanhamento diário da equipe em escala</small>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <label for="input-data-dashboard" class="small fw-semibold text-secondary mb-0">Consultar Data:</label>
                            <input type="date" id="input-data-dashboard" class="form-control form-control-sm fv-form-control" value="{{ $dataDashboard }}" style="width: 145px;">
                            <button type="button" id="btn-filtrar-dia" class="btn btn-sm btn-fv-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    @php
                        $trabHoje = collect($escalaHoje)->where('esd_tipo_dia', 'trabalho')->count();
                        $folgaHoje = collect($escalaHoje)->whereIn('esd_tipo_dia', ['folga', 'dsr'])->count();
                        $ferHoje = collect($escalaHoje)->where('esd_tipo_dia', 'feriado')->count();
                    @endphp

                    <!-- KPIs do Dia -->
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-3">
                            <div class="fv-kpi-box d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-secondary">Total na Escala</div>
                                    <div class="h5 fw-bold mb-0" id="kpi-dia-total">{{ count($escalaHoje) }}</div>
                                </div>
                                <i class="bi bi-people fs-4 text-secondary"></i>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fv-kpi-box d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-secondary">Em Trabalho Hoje</div>
                                    <div class="h5 fw-bold mb-0 text-dark" id="kpi-dia-trabalho">{{ $trabHoje }}</div>
                                </div>
                                <i class="bi bi-person-check-fill fs-4 text-dark"></i>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fv-kpi-box d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-secondary">Em Folga / DSR</div>
                                    <div class="h5 fw-bold mb-0 text-secondary" id="kpi-dia-folga">{{ $folgaHoje }}</div>
                                </div>
                                <i class="bi bi-cup-hot fs-4 text-secondary"></i>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fv-kpi-box d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-secondary">Em Feriado</div>
                                    <div class="h5 fw-bold mb-0 text-secondary" id="kpi-dia-feriado">{{ $ferHoje }}</div>
                                </div>
                                <i class="bi bi-calendar-heart fs-4 text-secondary"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Tabela Compacta do Dia (Item 8: fonte reduzida) -->
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-sm table-hover align-middle mb-0 fv-table-compact">
                            <thead class="table-light">
                                <tr>
                                    <th>Matrícula /<br>Colaborador</th>
                                    <th>Cargo</th>
                                    <th>Dia da<br>Semana</th>
                                    <th>Situação<br>no Dia</th>
                                    <th>Turno /<br>Horários</th>
                                    <th>Carga<br>Prevista</th>
                                    <th class="text-end">Ajuste<br>Pontual</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-dashboard-dia">
                                @forelse($escalaHoje as $item)
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark">{{ $item['fun_nome'] }}</span>
                                            <span class="text-secondary ms-1">(<code>{{ $item['fun_matricula'] }}</code>)</span>
                                        </td>
                                        <td>{{ $item['car_nome'] }}</td>
                                        <td>{{ $diasSemanaPt[(int)$item['esd_dia_semana_num']] ?? $item['esd_dia_semana_en'] }}</td>
                                        <td>
                                            @if($item['esd_tipo_dia'] === 'trabalho')
                                                <span class="badge bg-dark">TRABALHO</span>
                                            @elseif($item['esd_tipo_dia'] === 'feriado')
                                                <span class="badge bg-secondary">FERIADO</span>
                                            @else
                                                <span class="badge bg-light text-dark border">FOLGA / DSR</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($item['esd_tipo_dia'] === 'trabalho' && !empty($item['tur_descricao']))
                                                <span class="fw-semibold">{{ $item['tur_descricao'] }}</span>
                                                <span class="text-secondary ms-1">
                                                    @if(substr($item['tur_saida_intervalo'], 0, 5) === substr($item['tur_retorno_intervalo'], 0, 5))
                                                        <code>{{ substr($item['tur_entrada'], 0, 5) }} às {{ substr($item['tur_saida'], 0, 5) }} (Direto)</code>
                                                    @else
                                                        <code>{{ substr($item['tur_entrada'], 0, 5) }}-{{ substr($item['tur_saida_intervalo'], 0, 5) }} | {{ substr($item['tur_retorno_intervalo'], 0, 5) }}-{{ substr($item['tur_saida'], 0, 5) }}</code>
                                                    @endif
                                                </span>
                                            @else
                                                <span class="text-secondary">Descanso programado</span>
                                            @endif
                                        </td>
                                        <td><code>{{ $item['carga_horaria_prevista'] ? substr($item['carga_horaria_prevista'], 0, 5) : '-' }}</code></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-fv-outline fv-btn-xs btn-abrir-editar-dia"
                                                data-esd-id="{{ $item['esd_id'] }}"
                                                data-fun-nome="{{ $item['fun_nome'] }}"
                                                data-esd-data="{{ \Carbon\Carbon::parse($item['esd_data'])->format('d/m/Y') }}"
                                                data-esd-tipo="{{ $item['esd_tipo_dia'] }}"
                                                data-tur-id="{{ $item['tur_id'] ?? '' }}">
                                                <i class="bi bi-pencil-square me-1"></i>Editar Dia
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-3">
                                            Nenhum registro encontrado para esta data. Use o seletor de data acima ou o <strong>Gerador Automático 5x2</strong>.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- =======================================================
                     ABA 2: VISUALIZADOR MENSAL (MATRIZ PIVÔ + RESUMO MENSAL)
                     ======================================================= -->
                <div class="tab-pane fade" id="pane-escala-mensal" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div>
                            <h3 class="fv-section-title fw-bold mb-0 text-dark">
                                <i class="bi bi-table me-1"></i>Matriz Mensal da Empresa
                            </h3>
                            <small class="text-secondary">
                                Legenda: <strong>Turno</strong> = Dia trabalhado | <strong>F</strong> = Folga 5x2 / Domingo CLT | <strong>FER</strong> = Feriado
                            </small>
                        </div>
                        <button type="button" class="btn btn-sm btn-fv-primary" data-bs-toggle="modal" data-bs-target="#modalGeradorEscala">
                            <i class="bi bi-magic me-1"></i>Regenerar / Atualizar Escala 5x2
                        </button>
                    </div>

                    <div class="table-responsive border rounded bg-white mb-3" style="max-height: 420px;">
                        <table class="table fv-matrix-table mb-0">
                            <thead>
                                <tr>
                                    <th class="fv-matrix-sticky-col">Colaborador /<br>Cargo</th>
                                    @foreach($colunasMatriz as $col)
                                        <th class="text-center">{!! str_replace('D_', 'Dia<br>', e($col)) !!}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($matrizEscala as $linha)
                                    <tr>
                                        <td class="fv-matrix-sticky-col">
                                            <div class="fw-bold text-dark">{{ $linha['Funcionario'] }}</div>
                                            <div class="small text-secondary">{{ $linha['Matricula'] }} • {{ $linha['Cargo'] }}</div>
                                        </td>
                                        @foreach($colunasMatriz as $col)
                                            @php
                                                $val = $linha[$col] ?? '-';
                                                $cellClass = $val === 'F' ? 'fv-cell-folga' : ($val === 'FER' ? 'fv-cell-feriado' : 'fv-cell-trabalho');
                                            @endphp
                                            <td class="text-center {{ $cellClass }}" title="{{ $linha['Funcionario'] }} - {{ $col }}: {{ $val }}">
                                                @if($val === 'F')
                                                    <span class="badge bg-secondary">F</span>
                                                @elseif($val === 'FER')
                                                    <span class="badge bg-dark">FER</span>
                                                @else
                                                    <span>{{ Str::limit($val, 14) }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="32" class="text-center text-secondary py-3">
                                            Nenhuma escala gerada para este período. Clique em <strong>Gerador Automático 5x2</strong>.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Resumo Mensal -->
                    <h4 class="fv-section-title fw-bold text-dark mb-2">
                        <i class="bi bi-bar-chart-line me-1"></i>Resumo Consolidado do Mês
                    </h4>
                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-sm table-hover align-middle mb-0 fv-table-compact">
                            <thead class="table-light">
                                <tr>
                                    <th>Competência</th>
                                    <th>Matrícula</th>
                                    <th>Colaborador</th>
                                    <th>Cargo</th>
                                    <th class="text-center">Dias<br>Trabalhados</th>
                                    <th class="text-center">Dias de<br>Folga / DSR</th>
                                    <th class="text-center">Feriados</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($resumoMensal as $rm)
                                    <tr>
                                        <td><code>{{ $rm['mes_competencia'] }}</code></td>
                                        <td><code>{{ $rm['fun_matricula'] }}</code></td>
                                        <td class="fw-bold">{{ $rm['fun_nome'] }}</td>
                                        <td>{{ $rm['car_nome'] }}</td>
                                        <td class="text-center"><span class="badge bg-dark">{{ $rm['total_dias_trabalho'] }} dias</span></td>
                                        <td class="text-center"><span class="badge bg-secondary">{{ $rm['total_dias_folga'] }} dias</span></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">{{ $rm['total_dias_feriado'] }} dias</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary py-3">Sem dados de resumo mensal.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- =======================================================
                     ABA 3: VISUALIZADOR SEMANAL / DIÁRIO E EDIÇÃO PONTUAL
                     ======================================================= -->
                <div class="tab-pane fade" id="pane-escala-semanal" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
                        <div>
                            <h3 class="fv-section-title fw-bold mb-0 text-dark">
                                <i class="bi bi-calendar-week me-1"></i>Visualizador Semanal & Edição Pontual de Escala
                            </h3>
                            <small class="text-secondary">
                                Validação automática de intervalo mínimo de 11h entre jornadas (CLT Art. 66)
                            </small>
                        </div>

                        <form method="GET" action="{{ route('home') }}" class="row g-2 align-items-end">
                            <input type="hidden" name="esp_id" value="{{ optional($periodoAtivo)->esp_id }}">
                            <div class="col-auto">
                                <label class="form-label small fw-semibold mb-1">Início da Semana</label>
                                <input type="date" name="semana_inicio" class="form-control form-control-sm fv-form-control" value="{{ $semanaInicio }}">
                            </div>
                            <div class="col-auto">
                                <label class="form-label small fw-semibold mb-1">Fim da Semana</label>
                                <input type="date" name="semana_fim" class="form-control form-control-sm fv-form-control" value="{{ $semanaFim }}">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-sm btn-fv-primary">
                                    <i class="bi bi-funnel me-1"></i>Filtrar Semana
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabela Compacta Semanal (Item 9: fonte reduzida) -->
                    <div class="table-responsive border rounded bg-white" style="max-height: 480px;">
                        <table class="table table-sm table-hover align-middle mb-0 fv-table-compact">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Data</th>
                                    <th>Dia da<br>Semana</th>
                                    <th>Colaborador</th>
                                    <th>Cargo</th>
                                    <th>Classificação</th>
                                    <th>Turno /<br>Horário</th>
                                    <th>Carga<br>Horária</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($escalaSemanal as $es)
                                    <tr class="{{ (int)$es['esd_dia_semana_num'] === 1 ? 'table-secondary' : '' }}">
                                        <td><code>{{ \Carbon\Carbon::parse($es['esd_data'])->format('d/m/Y') }}</code></td>
                                        <td>
                                            <strong>{{ $diasSemanaPt[(int)$es['esd_dia_semana_num']] ?? $es['esd_dia_semana_en'] }}</strong>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ $es['fun_nome'] }}</span>
                                            <span class="text-secondary">({{ $es['fun_matricula'] }})</span>
                                        </td>
                                        <td>{{ $es['car_nome'] }}</td>
                                        <td>
                                            @if($es['esd_tipo_dia'] === 'trabalho')
                                                <span class="badge bg-dark">TRABALHO</span>
                                            @elseif($es['esd_tipo_dia'] === 'feriado')
                                                <span class="badge bg-secondary">FERIADO</span>
                                            @else
                                                <span class="badge bg-light text-dark border">FOLGA / DSR</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($es['esd_tipo_dia'] === 'trabalho' && !empty($es['tur_descricao']))
                                                {{ $es['tur_descricao'] }} (<code>{{ substr($es['tur_entrada'], 0, 5) }}-{{ substr($es['tur_saida'], 0, 5) }}</code>)
                                            @else
                                                <span class="text-secondary">-</span>
                                            @endif
                                        </td>
                                        <td><code>{{ $es['carga_horaria_prevista'] ? substr($es['carga_horaria_prevista'], 0, 5) : '-' }}</code></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-fv-outline fv-btn-xs btn-abrir-editar-dia"
                                                data-esd-id="{{ $es['esd_id'] }}"
                                                data-fun-nome="{{ $es['fun_nome'] }}"
                                                data-esd-data="{{ \Carbon\Carbon::parse($es['esd_data'])->format('d/m/Y') }}"
                                                data-esd-tipo="{{ $es['esd_tipo_dia'] }}"
                                                data-tur-id="{{ $es['tur_id'] ?? '' }}">
                                                <i class="bi bi-pencil"></i> Alterar
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-secondary py-3">Nenhum registro no intervalo semanal selecionado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- =======================================================
                     ABA 4: AUDITORIA DE FOLGAS NO DOMINGO - MULHERES (CLT Art. 386)
                     ======================================================= -->
                <div class="tab-pane fade" id="pane-auditoria-mulheres" role="tabpanel">
                    <div class="alert alert-secondary d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 mb-2">
                        <div>
                            <h3 class="fv-section-title fw-bold mb-0 text-dark">
                                <i class="bi bi-shield-check me-1"></i>Regra Fundamental Escala 5x2 — Mulheres (Art. 386 da CLT)
                            </h3>
                            <p class="small mb-0 text-secondary">
                                Por Lei, toda funcionária do sexo feminino em escala tem direito a pelo menos <strong>2 folgas no domingo no mesmo mês</strong>.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-fv-primary" data-bs-toggle="modal" data-bs-target="#modalGeradorEscala">
                            <i class="bi bi-arrow-repeat me-1"></i>Recalcular Escala 5x2
                        </button>
                    </div>

                    <div class="table-responsive border rounded bg-white">
                        <table class="table table-sm table-hover align-middle mb-0 fv-table-compact">
                            <thead class="table-light">
                                <tr>
                                    <th>Competência</th>
                                    <th>Matrícula</th>
                                    <th>Funcionária</th>
                                    <th>Sexo</th>
                                    <th>Cargo</th>
                                    <th class="text-center">Domingos de<br>Folga / DSR</th>
                                    <th class="text-center">Domingos<br>Trabalhados</th>
                                    <th class="text-center">Status Legal<br>(CLT Art. 386)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($auditoriaMulheres as $aud)
                                    <tr>
                                        <td><code>{{ $aud['mes_competencia'] }}</code></td>
                                        <td><code>{{ $aud['fun_matricula'] }}</code></td>
                                        <td class="fw-bold text-dark">{{ $aud['fun_nome'] }}</td>
                                        <td><span class="badge bg-dark">{{ $aud['fun_sexo'] }}</span></td>
                                        <td>{{ $aud['car_nome'] }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-dark">{{ $aud['total_domingos_folga'] }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">{{ $aud['total_domingos_trabalho'] }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if($aud['status_clt_art_386'] === 'CONFORME')
                                                <span class="badge bg-dark px-2 py-1">
                                                    <i class="bi bi-check-circle-fill me-1"></i>{{ $aud['status_clt_art_386'] }}
                                                </span>
                                            @else
                                                <span class="badge bg-danger px-2 py-1">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $aud['status_clt_art_386'] }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-secondary py-3">
                                            Nenhuma funcionária avaliada neste período. Gere a escala 5x2 para visualizar a auditoria.
                                        </td>
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

<!-- Inclusão de Todos os Modais de CRUD -->
@include('home.partials.modals')
@endsection
