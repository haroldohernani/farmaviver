@php
    $diasSemanaMap = [
        1 => '1 - Domingo',
        2 => '2 - Segunda-feira',
        3 => '3 - Terça-feira',
        4 => '4 - Quarta-feira',
        5 => '5 - Quinta-feira',
        6 => '6 - Sexta-feira',
        7 => '7 - Sábado',
    ];
@endphp

<!-- =======================================================================
     MODAL 1: CRUD DE FUNCIONÁRIOS
     ======================================================================= -->
<div class="modal fade" id="modalFuncionarios" tabindex="-1" aria-labelledby="modalFuncionariosLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalFuncionariosLabel">
                    <i class="bi bi-people-fill me-2 text-secondary"></i>Gestão de Funcionários
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-funcionarios"></div>

                <div class="row g-3">
                    <!-- Coluna Esquerda: Formulário de Cadastro / Edição -->
                    <div class="col-12 col-lg-5">
                        <div class="card border shadow-sm">
                            <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                                <span id="form-funcionario-titulo"><i class="bi bi-person-plus me-1"></i>Novo Funcionário</span>
                                <button type="button" class="btn btn-sm btn-fv-outline" id="btn-limpar-funcionario">
                                    <i class="bi bi-arrow-counterclockwise"></i> Limpar
                                </button>
                            </div>
                            <div class="card-body">
                                <form id="form-crud-funcionario" autocomplete="off">
                                    <input type="hidden" id="crud_fun_id" value="">

                                    <div class="row g-2">
                                        <div class="col-5">
                                            <label class="form-label small fw-semibold mb-1">Matrícula *</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_fun_matricula" placeholder="Ex: MAT-005" required>
                                        </div>
                                        <div class="col-7">
                                            <label class="form-label small fw-semibold mb-1">CPF (somente números) *</label>
                                            <input type="text" inputmode="numeric" maxlength="11" class="form-control form-control-sm fv-form-control fv-only-digits" id="crud_fun_cpf" placeholder="11 dígitos numéricos" required>
                                        </div>

                                        <div class="col-8">
                                            <label class="form-label small fw-semibold mb-1">Nome Completo *</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_fun_nome" required>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label small fw-semibold mb-1" title="Sexo Feminino possui direito legal a 2 folgas no domingo por mês (CLT Art. 386)">Sexo (CLT) *</label>
                                            <select class="form-select form-select-sm fv-form-control" id="crud_fun_sexo" required>
                                                <option value="F">Feminino (F)</option>
                                                <option value="M">Masculino (M)</option>
                                            </select>
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Cargo *</label>
                                            <select class="form-select form-select-sm fv-form-control" id="crud_fun_car_id" required>
                                                @foreach($cargos as $c)
                                                    <option value="{{ $c->car_id }}">{{ $c->car_nome }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Turno Padrão</label>
                                            <select class="form-select form-select-sm fv-form-control" id="crud_fun_tur_id_padrao">
                                                <option value="">-- Sem turno fixo --</option>
                                                @foreach($turnos as $t)
                                                    <option value="{{ $t->tur_id }}">{{ $t->tur_descricao }} ({{ substr($t->tur_entrada, 0, 5) }}-{{ substr($t->tur_saida, 0, 5) }})</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Perfil de Acesso *</label>
                                            <select class="form-select form-select-sm fv-form-control" id="crud_fun_perfil" required>
                                                <option value="colaborador">Colaborador</option>
                                                <option value="gestor">Gestor</option>
                                                <option value="admin">Administrador</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Status Operacional *</label>
                                            <select class="form-select form-select-sm fv-form-control" id="crud_fun_status" required>
                                                <option value="ativo">Ativo</option>
                                                <option value="afastado">Afastado</option>
                                                <option value="ferias">Férias</option>
                                                <option value="desligado">Desligado</option>
                                            </select>
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Data Admissão *</label>
                                            <input type="date" class="form-control form-control-sm fv-form-control" id="crud_fun_data_admissao" value="{{ now()->format('Y-m-d') }}" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Telefone</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_fun_telefone" placeholder="92999999999">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">E-mail</label>
                                            <input type="email" class="form-control form-control-sm fv-form-control" id="crud_fun_email" placeholder="nome@farmaviver.com.br">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold mb-1">Senha (<code id="label-senha-hint">obrigatória</code>)</label>
                                            <input type="password" class="form-control form-control-sm fv-form-control" id="crud_fun_senha" placeholder="Mín. 4 caracteres">
                                        </div>
                                    </div>

                                    <div class="d-grid mt-3">
                                        <button type="submit" class="btn btn-sm btn-fv-primary" id="btn-salvar-funcionario">
                                            <i class="bi bi-check2-circle me-1"></i>Salvar Funcionário
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Direita: Lista de Funcionários (Sem coluna Sexo, conforme Item 11) -->
                    <div class="col-12 col-lg-7">
                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Matrícula /<br>Nome</th>
                                        <th>CPF</th>
                                        <th>Cargo /<br>Turno</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-crud-funcionarios">
                                    @foreach($funcionarios as $f)
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $f->fun_nome }}</div>
                                                <div class="small text-secondary">{{ $f->fun_matricula }} • <span class="badge bg-light text-dark border">{{ strtoupper($f->fun_perfil) }}</span></div>
                                            </td>
                                            <td><code>{{ $f->fun_cpf }}</code></td>
                                            <td>
                                                <div class="small fw-semibold">{{ $f->cargo->car_nome ?? '-' }}</div>
                                                <div class="small text-secondary">{{ $f->turnoPadrao->tur_descricao ?? 'Sem turno fixo' }}</div>
                                            </td>
                                            <td>
                                                <span class="badge {{ $f->fun_status === 'ativo' ? 'bg-secondary' : 'bg-warning text-dark' }}">
                                                    {{ strtoupper($f->fun_status) }}
                                                </span>
                                                @if($f->isAdministradorForaEscala())
                                                    <div class="mt-1"><span class="badge bg-dark">Fora da Escala 5x2</span></div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-fv-outline btn-edit-funcionario"
                                                    data-json="{{ json_encode([
                                                        'fun_id' => $f->fun_id,
                                                        'fun_matricula' => $f->fun_matricula,
                                                        'fun_cpf' => $f->fun_cpf,
                                                        'fun_nome' => $f->fun_nome,
                                                        'fun_sexo' => $f->fun_sexo,
                                                        'car_id' => $f->car_id,
                                                        'tur_id_padrao' => $f->tur_id_padrao,
                                                        'fun_perfil' => $f->fun_perfil,
                                                        'fun_status' => $f->fun_status,
                                                        'fun_data_admissao' => optional($f->fun_data_admissao)->format('Y-m-d'),
                                                        'fun_telefone' => $f->fun_telefone,
                                                        'fun_email' => $f->fun_email,
                                                    ]) }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-funcionario" data-id="{{ $f->fun_id }}" data-nome="{{ $f->fun_nome }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL 2: CRUD DE CARGOS
     ======================================================================= -->
<div class="modal fade" id="modalCargos" tabindex="-1" aria-labelledby="modalCargosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalCargosLabel">
                    <i class="bi bi-briefcase-fill me-2 text-secondary"></i>Gestão de Cargos
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-cargos"></div>

                <form id="form-crud-cargo" class="card card-body bg-light border mb-3">
                    <input type="hidden" id="crud_car_id" value="">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label small fw-semibold mb-1">Nome do Cargo *</label>
                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_car_nome" placeholder="Ex: Farmacêutico Substituto" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Código CBO</label>
                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_car_cbo" placeholder="Ex: 2234-05">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small fw-semibold mb-1">Status *</label>
                            <select class="form-select form-select-sm fv-form-control" id="crud_car_ativo">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label small fw-semibold mb-1">Descrição das Atribuições</label>
                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_car_descricao" placeholder="Responsabilidades na drogaria">
                        </div>
                        <div class="col-12 col-md-4 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-fv-primary flex-grow-1">
                                <i class="bi bi-save me-1"></i>Salvar Cargo
                            </button>
                            <button type="button" class="btn btn-sm btn-fv-outline" id="btn-limpar-cargo" title="Limpar formulário">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Tabela de Cargos (Sem coluna CBO, conforme Item 15) -->
                <div class="table-responsive border rounded bg-white">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cargo /<br>Descrição</th>
                                <th>Lotação</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-crud-cargos">
                            @foreach($cargos as $c)
                                <tr>
                                    <td><code>#{{ $c->car_id }}</code></td>
                                    <td>
                                        <div class="fw-bold">{{ $c->car_nome }}</div>
                                        <div class="small text-secondary">{{ $c->car_descricao ?? '-' }}</div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $c->funcionarios_count }} colab.</span></td>
                                    <td>
                                        <span class="badge {{ $c->car_ativo ? 'bg-secondary' : 'bg-light text-muted border' }}">
                                            {{ $c->car_ativo ? 'ATIVO' : 'INATIVO' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-fv-outline btn-edit-cargo"
                                            data-json="{{ json_encode([
                                                'car_id' => $c->car_id,
                                                'car_nome' => $c->car_nome,
                                                'car_cbo' => $c->car_cbo,
                                                'car_descricao' => $c->car_descricao,
                                                'car_ativo' => $c->car_ativo ? 1 : 0,
                                            ]) }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-cargo" data-id="{{ $c->car_id }}" data-nome="{{ $c->car_nome }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL 3: CRUD DE TURNOS / HORÁRIOS
     ======================================================================= -->
<div class="modal fade" id="modalTurnos" tabindex="-1" aria-labelledby="modalTurnosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalTurnosLabel">
                    <i class="bi bi-clock-history me-2 text-secondary"></i>Calendário de Turnos e Horários
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-turnos"></div>

                <form id="form-crud-turno" class="card card-body bg-light border mb-3">
                    <input type="hidden" id="crud_tur_id" value="">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold mb-1">Descrição do Turno *</label>
                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_tur_descricao" placeholder="Ex: Turno Intermediário" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Tolerância (min) *</label>
                            <input type="number" min="0" max="120" class="form-control form-control-sm fv-form-control" id="crud_tur_tolerancia" value="10" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Status *</label>
                            <select class="form-select form-select-sm fv-form-control" id="crud_tur_ativo">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Entrada *</label>
                            <input type="time" class="form-control form-control-sm fv-form-control" id="crud_tur_entrada" value="07:00" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Saída Intervalo *</label>
                            <input type="time" class="form-control form-control-sm fv-form-control" id="crud_tur_saida_intervalo" value="12:00" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Retorno Intervalo *</label>
                            <input type="time" class="form-control form-control-sm fv-form-control" id="crud_tur_retorno_intervalo" value="13:00" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-semibold mb-1">Saída Final *</label>
                            <input type="time" class="form-control form-control-sm fv-form-control" id="crud_tur_saida" value="16:48" required>
                        </div>

                        <div class="col-12 d-flex justify-content-between align-items-center mt-2">
                            <span class="small text-secondary">
                                <i class="bi bi-info-circle me-1"></i>Expediente FarmaViver: <strong>Sábado 08h às 20h</strong> | <strong>Domingo e Feriado 08h às 13h</strong>.
                            </span>
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-fv-primary">
                                    <i class="bi bi-save me-1"></i>Salvar Turno
                                </button>
                                <button type="button" class="btn btn-sm btn-fv-outline" id="btn-limpar-turno">
                                    Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive border rounded bg-white">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Descrição<br>do Turno</th>
                                <th>Entrada</th>
                                <th>Saída / Retorno<br>Intervalo</th>
                                <th>Saída</th>
                                <th>Tolerância</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-crud-turnos">
                            @foreach($turnos as $t)
                                <tr>
                                    <td class="fw-bold">{{ $t->tur_descricao }}</td>
                                    <td><code>{{ substr($t->tur_entrada, 0, 5) }}</code></td>
                                    <td>
                                        @if(substr($t->tur_saida_intervalo, 0, 5) === substr($t->tur_retorno_intervalo, 0, 5))
                                            <code>Direto (5h)</code>
                                        @else
                                            <code>{{ substr($t->tur_saida_intervalo, 0, 5) }} às {{ substr($t->tur_retorno_intervalo, 0, 5) }}</code>
                                        @endif
                                    </td>
                                    <td><code>{{ substr($t->tur_saida, 0, 5) }}</code></td>
                                    <td>{{ $t->tur_tolerancia_minutos }} min</td>
                                    <td>
                                        <span class="badge {{ $t->tur_ativo ? 'bg-secondary' : 'bg-light text-muted border' }}">
                                            {{ $t->tur_ativo ? 'ATIVO' : 'INATIVO' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-fv-outline btn-edit-turno"
                                            data-json="{{ json_encode([
                                                'tur_id' => $t->tur_id,
                                                'tur_descricao' => $t->tur_descricao,
                                                'tur_entrada' => substr($t->tur_entrada, 0, 5),
                                                'tur_saida_intervalo' => substr($t->tur_saida_intervalo, 0, 5),
                                                'tur_retorno_intervalo' => substr($t->tur_retorno_intervalo, 0, 5),
                                                'tur_saida' => substr($t->tur_saida, 0, 5),
                                                'tur_tolerancia_minutos' => $t->tur_tolerancia_minutos,
                                                'tur_ativo' => $t->tur_ativo ? 1 : 0,
                                            ]) }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-turno" data-id="{{ $t->tur_id }}" data-nome="{{ $t->tur_descricao }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL 4: CALENDÁRIO OPERACIONAL, CONFIGURAÇÕES 5x2 E FERIADOS
     ======================================================================= -->
<div class="modal fade" id="modalCalendario" tabindex="-1" aria-labelledby="modalCalendarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalCalendarioLabel">
                    <i class="bi bi-calendar3 me-2 text-secondary"></i>Calendário Operacional, Regras 5x2 e Feriados
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-calendario"></div>

                <!-- Abas Internas: Períodos | Regras 5x2 | Feriados -->
                <ul class="nav nav-pills fv-nav-pills mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-cal-periodos" type="button" role="tab">
                            <i class="bi bi-calendar-range me-1"></i>Períodos de Escala
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-cal-cfg5x2" type="button" role="tab">
                            <i class="bi bi-Sliders me-1"></i>Configurações 5x2
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-cal-feriados" type="button" role="tab">
                            <i class="bi bi-calendar-event me-1"></i>Feriados
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- ABA 1: PERÍODOS DE ESCALA -->
                    <div class="tab-pane fade show active" id="tab-cal-periodos" role="tabpanel">
                        <form id="form-crud-periodo" class="card card-body bg-light border mb-3">
                            <input type="hidden" id="crud_esp_id" value="">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1" for="crud_esp_descricao">Descrição do Período *</label>
                                    <input type="text" class="form-control form-control-sm fv-form-control" id="crud_esp_descricao" placeholder="Ex: Escala Mensal - Dezembro/2026" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold mb-1" for="crud_esp_data_inicio">Data Início *</label>
                                    <input type="date" class="form-control form-control-sm fv-form-control" id="crud_esp_data_inicio" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small fw-semibold mb-1" for="crud_esp_data_fim">Data Fim *</label>
                                    <input type="date" class="form-control form-control-sm fv-form-control" id="crud_esp_data_fim" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1" for="crud_esp_status">Status *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="crud_esp_status">
                                        <option value="rascunho">Rascunho</option>
                                        <option value="publicada">Publicada</option>
                                        <option value="encerrada">Encerrada</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2 pt-1">
                                    <button type="submit" class="btn btn-sm btn-fv-primary px-4" id="btn-salvar-periodo">
                                        <i class="bi bi-check-lg me-1"></i>Salvar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-fv-outline px-3" id="btn-limpar-periodo">
                                        <i class="bi bi-eraser me-1"></i>Limpar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Descrição<br>do Período</th>
                                        <th>Intervalo<br>de Datas</th>
                                        <th>Status</th>
                                        <th>Registros<br>Diários</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-crud-periodos">
                                    @foreach($periodos as $p)
                                        <tr>
                                            <td><code>#{{ $p->esp_id }}</code></td>
                                            <td class="fw-bold">{{ $p->esp_descricao }}</td>
                                            <td><code>{{ $p->esp_data_inicio->format('d/m/Y') }} a {{ $p->esp_data_fim->format('d/m/Y') }}</code></td>
                                            <td><span class="badge bg-dark">{{ strtoupper($p->esp_status) }}</span></td>
                                            <td>{{ $p->escalas_diarias_count }} dias gerados</td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-fv-outline btn-edit-periodo"
                                                    data-json="{{ json_encode([
                                                        'esp_id' => $p->esp_id,
                                                        'esp_descricao' => $p->esp_descricao,
                                                        'esp_data_inicio' => $p->esp_data_inicio->format('Y-m-d'),
                                                        'esp_data_fim' => $p->esp_data_fim->format('Y-m-d'),
                                                        'esp_status' => $p->esp_status,
                                                    ]) }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-periodo" data-id="{{ $p->esp_id }}" data-nome="{{ $p->esp_descricao }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ABA 2: CONFIGURAÇÕES DE ESCALA 5x2 -->
                    <div class="tab-pane fade" id="tab-cal-cfg5x2" role="tabpanel">
                        <form id="form-crud-cfg5x2" class="card card-body bg-light border mb-3">
                            <input type="hidden" id="crud_cfg_id" value="">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1" for="crud_cfg_nome">Nome da Regra 5x2 *</label>
                                    <input type="text" class="form-control form-control-sm fv-form-control" id="crud_cfg_nome" placeholder="Ex: 5x2 Quarta e Quinta" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-semibold mb-1" for="crud_cfg_tipo">Tipo *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="crud_cfg_tipo">
                                        <option value="fixa">Fixa</option>
                                        <option value="revezamento">Revezamento</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-semibold mb-1" for="crud_cfg_dia_folga_1">Folga 1 (1..7) *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="crud_cfg_dia_folga_1">
                                        @foreach($diasSemanaMap as $num => $label)
                                            <option value="{{ $num }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-semibold mb-1" for="crud_cfg_dia_folga_2">Folga 2 (1..7) *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="crud_cfg_dia_folga_2">
                                        @foreach($diasSemanaMap as $num => $label)
                                            <option value="{{ $num }}" {{ $num === 7 ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2 pt-1">
                                    <input type="hidden" id="crud_cfg_ativo" value="1">
                                    <button type="submit" class="btn btn-sm btn-fv-primary px-4" id="btn-salvar-cfg5x2">
                                        <i class="bi bi-check-lg me-1"></i>Salvar 5x2
                                    </button>
                                    <button type="button" class="btn btn-sm btn-fv-outline px-3" id="btn-limpar-cfg5x2">
                                        <i class="bi bi-eraser me-1"></i>Limpar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Regra<br>5x2</th>
                                        <th>Tipo</th>
                                        <th>1º Dia<br>de Folga</th>
                                        <th>2º Dia<br>de Folga</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-crud-cfg5x2">
                                    @foreach($configuracoes5x2 as $cfg)
                                        <tr>
                                            <td><code>#{{ $cfg->cfg_id }}</code></td>
                                            <td class="fw-bold">{{ $cfg->cfg_nome }}</td>
                                            <td><span class="badge bg-secondary">{{ strtoupper($cfg->cfg_tipo) }}</span></td>
                                            <td>{{ $diasSemanaMap[$cfg->cfg_dia_folga_1] ?? $cfg->cfg_dia_folga_1 }}</td>
                                            <td>{{ $diasSemanaMap[$cfg->cfg_dia_folga_2] ?? $cfg->cfg_dia_folga_2 }}</td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-fv-outline btn-edit-cfg5x2"
                                                    data-json="{{ json_encode([
                                                        'cfg_id' => $cfg->cfg_id,
                                                        'cfg_nome' => $cfg->cfg_nome,
                                                        'cfg_tipo' => $cfg->cfg_tipo,
                                                        'cfg_dia_folga_1' => $cfg->cfg_dia_folga_1,
                                                        'cfg_dia_folga_2' => $cfg->cfg_dia_folga_2,
                                                        'cfg_ativo' => $cfg->cfg_ativo ? 1 : 0,
                                                    ]) }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-cfg5x2" data-id="{{ $cfg->cfg_id }}" data-nome="{{ $cfg->cfg_nome }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ABA 3: FERIADOS -->
                    <div class="tab-pane fade" id="tab-cal-feriados" role="tabpanel">
                        <form id="form-crud-feriado" class="card card-body bg-light border mb-3">
                            <input type="hidden" id="crud_fer_id" value="">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1" for="crud_fer_data">Data do Feriado *</label>
                                    <input type="date" class="form-control form-control-sm fv-form-control" id="crud_fer_data" required>
                                </div>
                                <div class="col-12 col-md-7">
                                    <label class="form-label small fw-semibold mb-1" for="crud_fer_descricao">Descrição *</label>
                                    <input type="text" class="form-control form-control-sm fv-form-control" id="crud_fer_descricao" placeholder="Ex: Aniversário de Manaus" required>
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label small fw-semibold mb-1" for="crud_fer_tipo">Tipo *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="crud_fer_tipo">
                                        <option value="nacional">Nacional</option>
                                        <option value="estadual">Estadual</option>
                                        <option value="municipal">Municipal</option>
                                        <option value="facultativo">Ponto Facultativo</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex gap-2 pt-1">
                                    <button type="submit" class="btn btn-sm btn-fv-primary px-4" id="btn-salvar-feriado">
                                        <i class="bi bi-check-lg me-1"></i>Salvar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-fv-outline px-3" id="btn-limpar-feriado">
                                        <i class="bi bi-eraser me-1"></i>Limpar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Data</th>
                                        <th>Descrição<br>do Feriado</th>
                                        <th>Tipo</th>
                                        <th>Abrangência</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-crud-feriados">
                                    @foreach($feriados as $fer)
                                        <tr>
                                            <td><code>{{ $fer->fer_data->format('d/m/Y') }}</code></td>
                                            <td class="fw-bold">{{ $fer->fer_descricao }}</td>
                                            <td><span class="badge bg-secondary">{{ strtoupper($fer->fer_tipo) }}</span></td>
                                            <td>{{ $fer->emp_id ? 'Específico da Loja' : 'Geral / Nacional' }}</td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-fv-outline btn-edit-feriado"
                                                    data-json="{{ json_encode([
                                                        'fer_id' => $fer->fer_id,
                                                        'fer_data' => $fer->fer_data->format('Y-m-d'),
                                                        'fer_descricao' => $fer->fer_descricao,
                                                        'fer_tipo' => $fer->fer_tipo,
                                                    ]) }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-feriado" data-id="{{ $fer->fer_id }}" data-nome="{{ $fer->fer_descricao }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL 5: AFASTAMENTO E TROCAS PONTUAIS
     ======================================================================= -->
<div class="modal fade" id="modalTrocasAfastamentos" tabindex="-1" aria-labelledby="modalTrocasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalTrocasLabel">
                    <i class="bi bi-arrow-left-right me-2 text-secondary"></i>Afastamentos, Férias e Trocas Pontuais
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-trocas"></div>

                <ul class="nav nav-pills fv-nav-pills mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-trocas-permutas" type="button" role="tab">
                            <i class="bi bi-arrow-left-right me-1"></i>Permutas e Trocas Pontuais
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-trocas-afastamentos" type="button" role="tab">
                            <i class="bi bi-person-exclamation me-1"></i>Afastamentos e Férias
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- ABA 1: SOLICITAR E APROVAR TROCAS -->
                    <div class="tab-pane fade show active" id="tab-trocas-permutas" role="tabpanel">
                        <form id="form-solicitar-troca" class="card card-body bg-light border mb-3">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-plus-circle me-1"></i>Nova Solicitação de Troca Pontual</h6>
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Solicitante *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="tro_fun_solicitante" required>
                                        <option value="">-- Selecione --</option>
                                        @foreach($funcionariosEscala as $f)
                                            <option value="{{ $f->fun_id }}">{{ $f->fun_nome }} ({{ $f->cargo->car_nome ?? '' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Dia Origem (Solicitante) *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="tro_esd_origem" required>
                                        <option value="">Selecione o solicitante primeiro</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Substituto *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="tro_fun_substituto" required>
                                        <option value="">-- Selecione --</option>
                                        @foreach($funcionariosEscala as $f)
                                            <option value="{{ $f->fun_id }}">{{ $f->fun_nome }} ({{ $f->cargo->car_nome ?? '' }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Dia Destino (Substituto - Opcional)</label>
                                    <select class="form-select form-select-sm fv-form-control" id="tro_esd_destino">
                                        <option value="">-- Troca Simples / Cobertura --</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-9">
                                    <label class="form-label small fw-semibold mb-1">Motivo da Troca *</label>
                                    <input type="text" class="form-control form-control-sm fv-form-control" id="tro_motivo" placeholder="Ex: Consulta médica / Permuta combinada de domingo" required>
                                </div>
                                <div class="col-12 col-md-3 d-grid">
                                    <button type="submit" class="btn btn-sm btn-fv-primary">
                                        <i class="bi bi-send me-1"></i>Registrar Troca
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive border rounded bg-white">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Solicitante /<br>Dia Origem</th>
                                        <th>Substituto /<br>Dia Destino</th>
                                        <th>Motivo<br>da Troca</th>
                                        <th>Status</th>
                                        <th class="text-end">Ação /<br>Gestão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($trocas as $tr)
                                        <tr>
                                            <td><code>#{{ $tr->tro_id }}</code></td>
                                            <td>
                                                <div class="fw-bold">{{ $tr->solicitante->fun_nome ?? '-' }}</div>
                                                <div class="small text-secondary">
                                                    {{ optional($tr->escalaOrigem?->esd_data)->format('d/m/Y') }}
                                                    ({{ strtoupper($tr->escalaOrigem->esd_tipo_dia ?? '') }})
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold">{{ $tr->substituto->fun_nome ?? '-' }}</div>
                                                <div class="small text-secondary">
                                                    @if($tr->escalaDestino)
                                                        {{ optional($tr->escalaDestino->esd_data)->format('d/m/Y') }}
                                                        ({{ strtoupper($tr->escalaDestino->esd_tipo_dia) }})
                                                    @else
                                                        Cobertura simples
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="small">{{ $tr->tro_motivo }}</td>
                                            <td>
                                                <span class="badge {{ $tr->tro_status === 'aprovada' ? 'bg-dark' : ($tr->tro_status === 'pendente' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                                    {{ strtoupper($tr->tro_status) }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                @if($tr->tro_status === 'pendente')
                                                    <button type="button" class="btn btn-sm btn-fv-primary btn-aprovar-troca" data-id="{{ $tr->tro_id }}" title="Executar sp_aprovar_troca_escala">
                                                        <i class="bi bi-check-lg"></i> Aprovar
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-recusar-troca" data-id="{{ $tr->tro_id }}">
                                                        <i class="bi bi-x-lg"></i> Recusar
                                                    </button>
                                                @else
                                                    <span class="small text-secondary">
                                                        Por: {{ $tr->aprovador->fun_nome ?? 'Gestor' }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-secondary py-3">Nenhuma solicitação de troca registrada até o momento.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ABA 2: AFASTAMENTOS E FÉRIAS -->
                    <div class="tab-pane fade" id="tab-trocas-afastamentos" role="tabpanel">
                        <form id="form-registrar-afastamento" class="card card-body bg-light border mb-3">
                            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-calendar-x me-1"></i>Registrar Afastamento, Férias ou Retorno ao Trabalho</h6>
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Colaborador *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="afa_fun_id" required>
                                        @foreach($funcionarios as $f)
                                            <option value="{{ $f->fun_id }}">{{ $f->fun_nome }} (Atual: {{ strtoupper($f->fun_status) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small fw-semibold mb-1">Novo Status *</label>
                                    <select class="form-select form-select-sm fv-form-control" id="afa_fun_status" required>
                                        <option value="afastado">Afastado</option>
                                        <option value="ferias">Em Férias</option>
                                        <option value="ativo">Retorno (Ativo)</option>
                                        <option value="desligado">Desligado</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small fw-semibold mb-1">Início (Escala)</label>
                                    <input type="date" class="form-control form-control-sm fv-form-control" id="afa_data_inicio">
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label small fw-semibold mb-1">Fim (Escala)</label>
                                    <input type="date" class="form-control form-control-sm fv-form-control" id="afa_data_fim">
                                </div>
                                <div class="col-6 col-md-2 d-grid">
                                    <button type="submit" class="btn btn-sm btn-fv-primary">Aplicar</button>
                                </div>
                                <div class="col-12">
                                    <input type="text" class="form-control form-control-sm fv-form-control" id="afa_observacao" placeholder="Observação na escala diária (Ex: Atestado Médico INSS / Férias Regulamentares)">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL 6: GERADOR AUTOMÁTICO DE ESCALA 5x2
     ======================================================================= -->
<div class="modal fade" id="modalGeradorEscala" tabindex="-1" aria-labelledby="modalGeradorEscalaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark" id="modalGeradorEscalaLabel">
                    <i class="bi bi-magic me-2 text-secondary"></i>Gerador Automático de Escala 5x2
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-gerador"></div>

                <div class="alert alert-secondary small mb-3">
                    <i class="bi bi-shield-check me-1"></i>
                    <strong>Regras Ativas (CLT Art. 386 + Expediente FarmaViver):</strong>
                    Mínimo de <strong>2 folgas aos domingos no mês</strong> para funcionárias mulheres |
                    <strong>Sábados: 08h às 20h</strong> |
                    <strong>Domingos e Feriados: 08h às 13h</strong>.
                </div>

                <form id="form-gerar-escala-5x2">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold mb-1">Período Operacional *</label>
                            <select class="form-select fv-form-control" id="ger_esp_id" required>
                                @foreach($periodos as $p)
                                    <option value="{{ $p->esp_id }}" {{ optional($periodoAtivo)->esp_id === $p->esp_id ? 'selected' : '' }}>
                                        {{ $p->esp_descricao }} ({{ $p->esp_data_inicio->format('d/m/Y') }} a {{ $p->esp_data_fim->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold mb-1">Configuração de Folgas 5x2 *</label>
                            <select class="form-select fv-form-control" id="ger_cfg_id" required>
                                <option value="0" selected>Revezamento Automático da Equipe (Cobertura Sáb 08h-20h e Dom/Fer 08h-13h)</option>
                                @foreach($configuracoes5x2 as $cfg)
                                    <option value="{{ $cfg->cfg_id }}">
                                        {{ $cfg->cfg_nome }} ({{ strtoupper($cfg->cfg_tipo) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold mb-1">Modo de Geração *</label>
                            <select class="form-select fv-form-control" id="ger_modo" required>
                                <option value="todos">Gerar para TODOS os funcionários ativos da empresa</option>
                                <option value="individual">Gerar para um funcionário específico</option>
                            </select>
                        </div>

                        <div class="col-12" id="wrap-ger-fun-id" style="display: none;">
                            <label class="form-label small fw-semibold mb-1">Funcionário Específico</label>
                            <select class="form-select fv-form-control" id="ger_fun_id">
                                @foreach($funcionariosEscala as $f)
                                    <option value="{{ $f->fun_id }}">{{ $f->fun_nome }} (Sexo: {{ $f->fun_sexo }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold mb-1">Turno Segunda a Sexta</label>
                            <select class="form-select fv-form-control" id="ger_tur_id">
                                <option value="">Usar o Turno Padrão de cada funcionário (Recomendado)</option>
                                @foreach($turnos as $t)
                                    <option value="{{ $t->tur_id }}">{{ $t->tur_descricao }} ({{ substr($t->tur_entrada, 0, 5) }} às {{ substr($t->tur_saida, 0, 5) }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold mb-1">Regra em Feriados *</label>
                            <select class="form-select fv-form-control" id="ger_feriado_modo">
                                <option value="expediente" selected>Expediente de Feriado (08h às 13h)</option>
                                <option value="folga">Folga de Feriado (Loja Fechada)</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-fv-primary btn-lg" id="btn-executar-gerador">
                            <i class="bi bi-gear-wide-connected me-2"></i>Executar Geração Automática 5x2
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL 7: EDIÇÃO PONTUAL DE DIA NA ESCALA
     ======================================================================= -->
<div class="modal fade" id="modalEditarDiaEscala" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-calendar-check me-2 text-secondary"></i>Alterar Dia da Escala
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-editar-dia"></div>

                <form id="form-editar-dia-escala">
                    <input type="hidden" id="edit_esd_id" value="">

                    <div class="mb-2">
                        <span class="small text-secondary">Colaborador / Data:</span>
                        <div class="fw-bold text-dark" id="edit_esd_info_label">-</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Classificação do Dia *</label>
                        <select class="form-select fv-form-control" id="edit_esd_tipo_dia" required>
                            <option value="trabalho">Trabalho (Turno Programado)</option>
                            <option value="folga">Folga Regular 5x2</option>
                            <option value="dsr">DSR (Descanso Semanal Remunerado)</option>
                            <option value="feriado">Feriado</option>
                        </select>
                    </div>

                    <div class="mb-3" id="wrap-edit-esd-turno">
                        <label class="form-label small fw-semibold">Turno / Horário *</label>
                        <select class="form-select fv-form-control" id="edit_esd_tur_id">
                            @foreach($turnos as $t)
                                <option value="{{ $t->tur_id }}">{{ $t->tur_descricao }} ({{ substr($t->tur_entrada, 0, 5) }} às {{ substr($t->tur_saida, 0, 5) }})</option>
                            @endforeach
                        </select>
                        <div class="form-text small">
                            Validação automática: exige mínimo de 11h de descanso entre jornadas (Art. 66 CLT).
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Observação</label>
                        <input type="text" class="form-control fv-form-control" id="edit_esd_observacao" placeholder="Motivo da alteração ou observação">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-fv-primary">
                            <i class="bi bi-check2-circle me-1"></i>Salvar Alteração no Dia
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL 8: DADOS DA EMPRESA E PERFIS / PERMISSÕES RBAC
     ======================================================================= -->
<div class="modal fade" id="modalEmpresaPerfis" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-building-gear me-2 text-secondary"></i>Cadastro da Empresa & Perfis de Acesso
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="alert-modal-empresa"></div>

                <div class="row g-3">
                    <!-- Dados da Empresa -->
                    <div class="col-12 col-lg-6">
                        <div class="card border">
                            <div class="card-header bg-light fw-bold">Dados da Drogaria</div>
                            <div class="card-body">
                                <form id="form-crud-empresa">
                                    <input type="hidden" id="crud_emp_id" value="{{ $empresa->emp_id ?? 1 }}">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold mb-1">Razão Social *</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_emp_razao_social" value="{{ $empresa->emp_razao_social ?? '' }}" required>
                                        </div>
                                        <div class="col-12 col-md-7">
                                            <label class="form-label small fw-semibold mb-1">Nome Fantasia *</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_emp_nome_fantasia" value="{{ $empresa->emp_nome_fantasia ?? '' }}" required>
                                        </div>
                                        <div class="col-12 col-md-5">
                                            <label class="form-label small fw-semibold mb-1">CNPJ (14 dígitos) *</label>
                                            <input type="text" maxlength="14" class="form-control form-control-sm fv-form-control fv-only-digits" id="crud_emp_cnpj" value="{{ $empresa->emp_cnpj ?? '' }}" required>
                                        </div>
                                        <div class="col-12 col-md-7">
                                            <label class="form-label small fw-semibold mb-1">E-mail *</label>
                                            <input type="email" class="form-control form-control-sm fv-form-control" id="crud_emp_email" value="{{ $empresa->emp_email ?? '' }}" required>
                                        </div>
                                        <div class="col-12 col-md-5">
                                            <label class="form-label small fw-semibold mb-1">Telefone</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_emp_telefone" value="{{ $empresa->emp_telefone ?? '' }}">
                                        </div>
                                        <div class="col-8">
                                            <label class="form-label small fw-semibold mb-1">Logradouro</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_emp_logradouro" value="{{ $empresa->emp_logradouro ?? '' }}">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label small fw-semibold mb-1">Número</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_emp_numero" value="{{ $empresa->emp_numero ?? '' }}">
                                        </div>
                                        <div class="col-5">
                                            <label class="form-label small fw-semibold mb-1">Bairro</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_emp_bairro" value="{{ $empresa->emp_bairro ?? '' }}">
                                        </div>
                                        <div class="col-5">
                                            <label class="form-label small fw-semibold mb-1">Cidade</label>
                                            <input type="text" class="form-control form-control-sm fv-form-control" id="crud_emp_cidade" value="{{ $empresa->emp_cidade ?? '' }}">
                                        </div>
                                        <div class="col-2">
                                            <label class="form-label small fw-semibold mb-1">UF</label>
                                            <input type="text" maxlength="2" class="form-control form-control-sm fv-form-control" id="crud_emp_uf" value="{{ $empresa->emp_uf ?? '' }}">
                                        </div>
                                    </div>
                                    <div class="d-grid mt-3">
                                        <button type="submit" class="btn btn-sm btn-fv-primary">Atualizar Dados da Empresa</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Perfis e Permissões RBAC -->
                    <div class="col-12 col-lg-6">
                        <div class="card border">
                            <div class="card-header bg-light fw-bold">Perfis e Permissões Ativas</div>
                            <div class="card-body">
                                @foreach($perfis as $pf)
                                    <div class="border rounded p-2 mb-2 bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>{{ $pf->prf_nome }}</strong>
                                            <span class="badge bg-secondary">{{ $pf->permissoes->count() }} permissões</span>
                                        </div>
                                        <div class="small text-secondary mb-1">{{ $pf->prf_descricao }}</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($pf->permissoes as $pm)
                                                <span class="badge bg-white text-dark border">{{ $pm->per_chave }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

