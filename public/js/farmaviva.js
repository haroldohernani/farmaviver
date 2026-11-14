/**
 * FarmaViva (FarmaViver) - Script Compilado a partir de resources/ts/farmaviva.ts
 * Fases 1 a 5 Completas:
 * - Login Mobile First, Validação Bootstrap ("CPF Inválido", "Senha Inválida", "CPF E Senha Inválidos !!") e Foco
 * - Relógio em Tempo Real e Logout Global de Sessões
 * - CRUDs Intuitivos em Cards/Modais (Funcionários, Cargos, Turnos, Calendário/Feriados/5x2, Trocas/Afastamentos, Empresa)
 * - Dashboard do Dia, Visualizador Mensal/Semanal e Gerador Automático 5x2 (sp_gerar_escala_5x2_funcionario)
 */
(function () {
    'use strict';

    class FarmaVivaApp {
        constructor() {
            document.addEventListener('DOMContentLoaded', () => {
                this.initNumericInputs();
                this.initLoginScreen();
                this.initRealtimeClock();
                this.initLogoutHandler();
                this.initCrudFuncionarios();
                this.initCrudCargos();
                this.initCrudTurnos();
                this.initCrudCalendario();
                this.initTrocasEAfastamentos();
                this.initGeradorEscala5x2();
                this.initEdicaoDiaEscala();
                this.initDashboardDia();
                this.initCrudEmpresa();
            });
        }

        getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        async apiRequest(url, method, payload) {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
            };
            if (payload !== undefined && method !== 'GET') {
                options.body = JSON.stringify(payload);
            }

            const res = await fetch(url, options);
            const json = await res.json();

            if (!res.ok && json.errors) {
                const firstKey = Object.keys(json.errors)[0];
                if (firstKey && json.errors[firstKey] && json.errors[firstKey][0]) {
                    json.message = json.errors[firstKey][0];
                }
            }

            return { ok: res.ok, status: res.status, body: json };
        }

        showAlert(containerId, type, message) {
            const container = document.getElementById(containerId) || document.getElementById('fv-global-alert-container');
            if (!container) return;

            const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
            container.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show d-flex align-items-center shadow-sm mb-3" role="alert">
                    <i class="bi ${icon} fs-5 me-2 flex-shrink-0"></i>
                    <div class="fw-semibold">${message}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            `;
        }

        initNumericInputs() {
            document.querySelectorAll('.fv-only-digits').forEach((input) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D+/g, '');
                });
            });
        }

        initLoginScreen() {
            const loginForm = document.getElementById('fv-login-form');
            const cpfInput = document.getElementById('fun_cpf');
            const senhaInput = document.getElementById('fun_senha');
            const togglePasswordBtn = document.getElementById('btn-toggle-senha');

            if (!loginForm || !cpfInput || !senhaInput) return;

            cpfInput.addEventListener('input', () => {
                const digitsOnly = cpfInput.value.replace(/\D+/g, '').slice(0, 11);
                if (cpfInput.value !== digitsOnly) {
                    cpfInput.value = digitsOnly;
                }
                cpfInput.classList.remove('is-invalid');
            });

            senhaInput.addEventListener('input', () => {
                senhaInput.classList.remove('is-invalid');
            });

            if (togglePasswordBtn) {
                togglePasswordBtn.addEventListener('click', () => {
                    const isPassword = senhaInput.type === 'password';
                    senhaInput.type = isPassword ? 'text' : 'password';
                    const icon = togglePasswordBtn.querySelector('i');
                    if (icon) {
                        icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
                    }
                });
            }

            document.querySelectorAll('[data-demo-cpf]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    cpfInput.value = btn.getAttribute('data-demo-cpf') || '';
                    senhaInput.value = btn.getAttribute('data-demo-senha') || '123456';
                    cpfInput.classList.remove('is-invalid');
                    senhaInput.classList.remove('is-invalid');
                    cpfInput.focus();
                });
            });

            loginForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                cpfInput.value = cpfInput.value.replace(/\D+/g, '').slice(0, 11);

                const submitBtn = document.getElementById('btn-login-submit');
                cpfInput.classList.remove('is-invalid');
                senhaInput.classList.remove('is-invalid');

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Validando...';
                }

                try {
                    const { ok, body } = await this.apiRequest('/api/auth/login', 'POST', {
                        fun_cpf: cpfInput.value.trim(),
                        fun_senha: senhaInput.value,
                    });

                    if (ok && body.status === 'ok') {
                        this.showAlert('fv-login-alert-container', 'success', body.message || 'Redirecionando...');
                        window.location.href = body.redirect || '/home';
                        return;
                    }

                    this.showAlert('fv-login-alert-container', 'danger', body.message || 'CPF E Senha Inválidos !!');

                    if (body.error_type === 'senha_invalida' || body.focus_field === 'fun_senha') {
                        senhaInput.classList.add('is-invalid');
                        setTimeout(() => {
                            senhaInput.focus();
                            senhaInput.select();
                        }, 30);
                    } else if (body.error_type === 'cpf_invalido') {
                        cpfInput.classList.add('is-invalid');
                        setTimeout(() => {
                            cpfInput.focus();
                            cpfInput.select();
                        }, 30);
                    } else {
                        cpfInput.classList.add('is-invalid');
                        senhaInput.classList.add('is-invalid');
                        setTimeout(() => {
                            cpfInput.focus();
                            cpfInput.select();
                        }, 30);
                    }
                } catch {
                    this.showAlert('fv-login-alert-container', 'danger', 'Erro de comunicação com o servidor.');
                    cpfInput.focus();
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Entrar no Sistema';
                    }
                }
            });
        }

        initRealtimeClock() {
            const clockEl = document.getElementById('fv-realtime-clock');
            if (!clockEl) return;

            const attrTs = Number(clockEl.getAttribute('data-login-timestamp') || 0);
            const startSeconds = attrTs > 0 ? attrTs : Math.floor(Date.now() / 1000);

            const formatElapsed = (totalSec) => {
                const sec = Math.max(0, totalSec);
                const hours = Math.floor(sec / 3600);
                const minutes = Math.floor((sec % 3600) / 60);
                const seconds = sec % 60;
                const hh = String(hours).padStart(2, '0');
                const mm = String(minutes).padStart(2, '0');
                const ss = String(seconds).padStart(2, '0');
                return `${hh}:${mm}:${ss}`;
            };

            const updateClock = () => {
                const nowSec = Math.floor(Date.now() / 1000);
                const elapsed = nowSec - startSeconds;
                clockEl.innerHTML = `<i class="bi bi-stopwatch me-1"></i>Tempo logado: ${formatElapsed(elapsed)}`;
            };

            updateClock();
            window.setInterval(updateClock, 1000);
        }

        initLogoutHandler() {
            const logoutBtn = document.getElementById('btn-logout-all');
            if (!logoutBtn) return;

            logoutBtn.addEventListener('click', async (event) => {
                event.preventDefault();
                logoutBtn.disabled = true;
                logoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saindo...';

                try {
                    const { body } = await this.apiRequest('/api/auth/logout', 'POST');
                    window.location.href = body.redirect || '/login';
                } catch {
                    window.location.href = '/login';
                }
            });
        }

        escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        async refreshFuncionariosTable() {
            const tbody = document.getElementById('tbody-crud-funcionarios');
            if (!tbody) return;

            const { ok, body } = await this.apiRequest('/api/funcionarios', 'GET');
            if (!ok || !Array.isArray(body.data)) return;

            const lista = body.data;
            let html = '';

            lista.forEach((f) => {
                const cargoNome = f.cargo && f.cargo.car_nome ? f.cargo.car_nome : '-';
                const turnoDesc = (f.turno_padrao && f.turno_padrao.tur_descricao)
                    ? f.turno_padrao.tur_descricao
                    : (f.turnoPadrao && f.turnoPadrao.tur_descricao ? f.turnoPadrao.tur_descricao : 'Sem turno fixo');
                const dataAdmissao = f.fun_data_admissao ? String(f.fun_data_admissao).slice(0, 10) : '';
                const isAdminFora = Boolean(
                    f.fora_escala_5x2 ||
                    (String(cargoNome).trim().toLowerCase() === 'administrador' && f.fun_perfil === 'admin' && f.fun_status === 'ativo')
                );

                const jsonData = this.escapeHtml(JSON.stringify({
                    fun_id: f.fun_id,
                    fun_matricula: f.fun_matricula,
                    fun_cpf: f.fun_cpf,
                    fun_nome: f.fun_nome,
                    fun_sexo: f.fun_sexo,
                    car_id: f.car_id,
                    tur_id_padrao: f.tur_id_padrao,
                    fun_perfil: f.fun_perfil,
                    fun_status: f.fun_status,
                    fun_data_admissao: dataAdmissao,
                    fun_telefone: f.fun_telefone,
                    fun_email: f.fun_email,
                }));

                const statusBadgeClass = f.fun_status === 'ativo' ? 'bg-secondary' : 'bg-warning text-dark';
                const badgeForaEscala = isAdminFora
                    ? '<div class="mt-1"><span class="badge bg-dark">Fora da Escala 5x2</span></div>'
                    : '';

                html += `
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">${this.escapeHtml(f.fun_nome)}</div>
                            <div class="small text-secondary">${this.escapeHtml(f.fun_matricula)} • <span class="badge bg-light text-dark border">${this.escapeHtml(String(f.fun_perfil || '').toUpperCase())}</span></div>
                        </td>
                        <td><code>${this.escapeHtml(f.fun_cpf)}</code></td>
                        <td>
                            <div class="small fw-semibold">${this.escapeHtml(cargoNome)}</div>
                            <div class="small text-secondary">${this.escapeHtml(turnoDesc)}</div>
                        </td>
                        <td>
                            <span class="badge ${statusBadgeClass}">
                                ${this.escapeHtml(String(f.fun_status || '').toUpperCase())}
                            </span>
                            ${badgeForaEscala}
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-fv-outline btn-edit-funcionario" data-json="${jsonData}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-funcionario" data-id="${f.fun_id}" data-nome="${this.escapeHtml(f.fun_nome)}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            // Atualiza contadores da Home e seletores em tempo real
            const ativos = lista.filter((f) => f.fun_status === 'ativo').length;
            const elegiveis = lista.filter((f) => {
                const cNome = f.cargo && f.cargo.car_nome ? String(f.cargo.car_nome).trim().toLowerCase() : '';
                const adminFora = Boolean(f.fora_escala_5x2 || (cNome === 'administrador' && f.fun_perfil === 'admin' && f.fun_status === 'ativo'));
                return f.fun_status === 'ativo' && !adminFora;
            });

            const elAtivos = document.getElementById('badge-home-fun-ativos');
            const elTotal = document.getElementById('badge-home-fun-total');
            const elEscala = document.getElementById('badge-home-fun-escala');
            const elTopCount = document.getElementById('btn-top-fun-count');
            if (elAtivos) elAtivos.textContent = `${ativos} ativos`;
            if (elTotal) elTotal.textContent = `${lista.length} total`;
            if (elEscala) elEscala.textContent = `${elegiveis.length} na escala 5x2`;
            if (elTopCount) elTopCount.textContent = String(ativos);

            // Atualiza select de funcionário no Gerador 5x2 e Permutas
            const selGerFun = document.getElementById('ger_fun_id');
            if (selGerFun) {
                selGerFun.innerHTML = elegiveis.map((f) => `<option value="${f.fun_id}">${this.escapeHtml(f.fun_nome)} (Sexo: ${this.escapeHtml(f.fun_sexo)})</option>`).join('');
            }
            const optsTroca = '<option value="">-- Selecione --</option>' + elegiveis.map((f) => {
                const cNome = f.cargo && f.cargo.car_nome ? f.cargo.car_nome : '';
                return `<option value="${f.fun_id}">${this.escapeHtml(f.fun_nome)} (${this.escapeHtml(cNome)})</option>`;
            }).join('');
            const selSol = document.getElementById('tro_fun_solicitante');
            const selSub = document.getElementById('tro_fun_substituto');
            if (selSol) selSol.innerHTML = optsTroca;
            if (selSub) selSub.innerHTML = optsTroca;
        }

        async refreshCargosTable() {
            const tbody = document.getElementById('tbody-crud-cargos');
            if (!tbody) return;

            const { ok, body } = await this.apiRequest('/api/cargos', 'GET');
            if (!ok || !Array.isArray(body.data)) return;

            const lista = body.data;
            let html = '';

            lista.forEach((c) => {
                const ativo = Boolean(c.car_ativo);
                const jsonData = this.escapeHtml(JSON.stringify({
                    car_id: c.car_id,
                    car_nome: c.car_nome,
                    car_cbo: c.car_cbo,
                    car_descricao: c.car_descricao,
                    car_ativo: ativo ? 1 : 0,
                }));

                html += `
                    <tr>
                        <td><code>#${c.car_id}</code></td>
                        <td>
                            <div class="fw-bold">${this.escapeHtml(c.car_nome)}</div>
                            <div class="small text-secondary">${this.escapeHtml(c.car_descricao || '-')}</div>
                        </td>
                        <td><span class="badge bg-light text-dark border">${c.funcionarios_count ?? 0} colab.</span></td>
                        <td>
                            <span class="badge ${ativo ? 'bg-secondary' : 'bg-light text-muted border'}">
                                ${ativo ? 'ATIVO' : 'INATIVO'}
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-fv-outline btn-edit-cargo" data-json="${jsonData}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-cargo" data-id="${c.car_id}" data-nome="${this.escapeHtml(c.car_nome)}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            // Sugestão B (13/11/2026): Atualiza select de cargos ativos no formulário de Funcionários e contador na Home
            const listaAtivos = lista.filter((c) => Boolean(c.car_ativo));
            const selCargoFun = document.getElementById('crud_fun_car_id');
            if (selCargoFun) {
                const currentVal = selCargoFun.value;
                selCargoFun.innerHTML = listaAtivos.map((c) => `<option value="${c.car_id}">${this.escapeHtml(c.car_nome)}</option>`).join('');
                if (currentVal) selCargoFun.value = currentVal;
            }

            const cargosAtivos = listaAtivos.length;
            const badgeCar = document.getElementById('badge-home-car-ativos');
            if (badgeCar) badgeCar.textContent = `${cargosAtivos} cargos ativos`;
        }

        async refreshTurnosTable() {
            const tbody = document.getElementById('tbody-crud-turnos');
            if (!tbody) return;

            const { ok, body } = await this.apiRequest('/api/turnos', 'GET');
            if (!ok || !Array.isArray(body.data)) return;

            const lista = body.data;
            let html = '';

            lista.forEach((t) => {
                const ativo = Boolean(t.tur_ativo);
                const ent = String(t.tur_entrada || '').slice(0, 5);
                const saiInt = String(t.tur_saida_intervalo || '').slice(0, 5);
                const retInt = String(t.tur_retorno_intervalo || '').slice(0, 5);
                const sai = String(t.tur_saida || '').slice(0, 5);

                const jsonData = this.escapeHtml(JSON.stringify({
                    tur_id: t.tur_id,
                    tur_descricao: t.tur_descricao,
                    tur_entrada: ent,
                    tur_saida_intervalo: saiInt,
                    tur_retorno_intervalo: retInt,
                    tur_saida: sai,
                    tur_tolerancia_minutos: t.tur_tolerancia_minutos,
                    tur_ativo: ativo ? 1 : 0,
                }));

                const intervaloTexto = saiInt === retInt
                    ? '<code>Direto (5h)</code>'
                    : `<code>${this.escapeHtml(saiInt)} às ${this.escapeHtml(retInt)}</code>`;

                html += `
                    <tr>
                        <td class="fw-bold">${this.escapeHtml(t.tur_descricao)}</td>
                        <td><code>${this.escapeHtml(ent)}</code></td>
                        <td>${intervaloTexto}</td>
                        <td><code>${this.escapeHtml(sai)}</code></td>
                        <td>${t.tur_tolerancia_minutos ?? 10} min</td>
                        <td>
                            <span class="badge ${ativo ? 'bg-secondary' : 'bg-light text-muted border'}">
                                ${ativo ? 'ATIVO' : 'INATIVO'}
                            </span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-fv-outline btn-edit-turno" data-json="${jsonData}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-turno" data-id="${t.tur_id}" data-nome="${this.escapeHtml(t.tur_descricao)}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            const listaAtivos = lista.filter((t) => Boolean(t.tur_ativo));
            const badgeTur = document.getElementById('badge-home-tur-ativos');
            if (badgeTur) badgeTur.textContent = `${listaAtivos.length} turnos ativos`;

            // Sugestão B (11/11/2026): Sincroniza selects de Turno no Gerador 5x2, Funcionários e Edição de Dia
            const optsTurnos = listaAtivos.map((t) => {
                const ent = String(t.tur_entrada || '').slice(0, 5);
                const sai = String(t.tur_saida || '').slice(0, 5);
                return `<option value="${t.tur_id}">${this.escapeHtml(t.tur_descricao)} (${this.escapeHtml(ent)} às ${this.escapeHtml(sai)})</option>`;
            }).join('');

            const selGerTur = document.getElementById('ger_tur_id');
            if (selGerTur) {
                const curGerTur = selGerTur.value;
                selGerTur.innerHTML = '<option value="">Usar o Turno Padrão de cada funcionário (Recomendado)</option>' + optsTurnos;
                if (curGerTur) selGerTur.value = curGerTur;
            }

            const selFunTur = document.getElementById('crud_fun_tur_id_padrao');
            if (selFunTur) {
                const curFunTur = selFunTur.value;
                selFunTur.innerHTML = '<option value="">-- Sem turno fixo --</option>' + optsTurnos;
                if (curFunTur) selFunTur.value = curFunTur;
            }

            const selEditTur = document.getElementById('edit_esd_tur_id');
            if (selEditTur) {
                const curEditTur = selEditTur.value;
                selEditTur.innerHTML = optsTurnos;
                if (curEditTur) selEditTur.value = curEditTur;
            }
        }

        async refreshCalendarioTables() {
            const { ok, body } = await this.apiRequest('/api/calendario-operacional', 'GET');
            if (!ok || !body.data) return;

            const diasMap = {
                1: '1 - Domingo',
                2: '2 - Segunda-feira',
                3: '3 - Terça-feira',
                4: '4 - Quarta-feira',
                5: '5 - Quinta-feira',
                6: '6 - Sexta-feira',
                7: '7 - Sábado',
            };

            const formatBr = (iso) => {
                const s = String(iso || '').slice(0, 10);
                const p = s.split('-');
                return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : s;
            };

            const tbodyPer = document.getElementById('tbody-crud-periodos');
            if (tbodyPer && Array.isArray(body.data.periodos)) {
                tbodyPer.innerHTML = body.data.periodos.map((p) => {
                    const ini = String(p.esp_data_inicio || '').slice(0, 10);
                    const fim = String(p.esp_data_fim || '').slice(0, 10);
                    const jsonData = this.escapeHtml(JSON.stringify({
                        esp_id: p.esp_id,
                        esp_descricao: p.esp_descricao,
                        esp_data_inicio: ini,
                        esp_data_fim: fim,
                        esp_status: p.esp_status,
                    }));
                    return `
                        <tr>
                            <td><code>#${p.esp_id}</code></td>
                            <td class="fw-bold">${this.escapeHtml(p.esp_descricao)}</td>
                            <td><code>${formatBr(ini)} a ${formatBr(fim)}</code></td>
                            <td><span class="badge bg-dark">${this.escapeHtml(String(p.esp_status || '').toUpperCase())}</span></td>
                            <td>${p.escalas_diarias_count ?? 0} dias gerados</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-fv-outline btn-edit-periodo" data-json="${jsonData}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-periodo" data-id="${p.esp_id}" data-nome="${this.escapeHtml(p.esp_descricao)}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
                const bPer = document.getElementById('badge-home-per-total');
                if (bPer) bPer.textContent = `${body.data.periodos.length} períodos`;

                // Sugestão B (11/11/2026 e 14/11/2026): Sincroniza Período Operacional no Gerador 5x2 e no seletor superior #select_top_esp_id
                const optionsPeriodosHtml = body.data.periodos.map((p) => {
                    const ini = formatBr(String(p.esp_data_inicio || '').slice(0, 10));
                    const fim = formatBr(String(p.esp_data_fim || '').slice(0, 10));
                    return `<option value="${p.esp_id}">${this.escapeHtml(p.esp_descricao)} (${ini} a ${fim})</option>`;
                }).join('');

                const selGerEsp = document.getElementById('ger_esp_id');
                if (selGerEsp) {
                    const curEsp = selGerEsp.value;
                    selGerEsp.innerHTML = optionsPeriodosHtml;
                    if (curEsp && body.data.periodos.some((p) => String(p.esp_id) === String(curEsp))) {
                        selGerEsp.value = curEsp;
                    }
                }

                const selTopEsp = document.getElementById('select_top_esp_id');
                if (selTopEsp) {
                    const curTopEsp = selTopEsp.value;
                    selTopEsp.innerHTML = optionsPeriodosHtml
                        ? optionsPeriodosHtml.replace(/<option value="(\d+)">/g, (m, idVal) => {
                            const pObj = body.data.periodos.find((x) => String(x.esp_id) === String(idVal));
                            const ini = pObj ? formatBr(String(pObj.esp_data_inicio || '').slice(0, 10)) : '';
                            const fim = pObj ? formatBr(String(pObj.esp_data_fim || '').slice(0, 10)) : '';
                            return `<option value="${idVal}" data-ini="${ini}" data-fim="${fim}">`;
                        })
                        : '<option value="0">Sem período cadastrado</option>';
                    if (curTopEsp && body.data.periodos.some((p) => String(p.esp_id) === String(curTopEsp))) {
                        selTopEsp.value = curTopEsp;
                    }
                }
            }

            const tbodyCfg = document.getElementById('tbody-crud-cfg5x2');
            if (tbodyCfg && Array.isArray(body.data.configuracoes_escala)) {
                tbodyCfg.innerHTML = body.data.configuracoes_escala.map((cfg) => {
                    const jsonData = this.escapeHtml(JSON.stringify({
                        cfg_id: cfg.cfg_id,
                        cfg_nome: cfg.cfg_nome,
                        cfg_tipo: cfg.cfg_tipo,
                        cfg_dia_folga_1: cfg.cfg_dia_folga_1,
                        cfg_dia_folga_2: cfg.cfg_dia_folga_2,
                        cfg_ativo: cfg.cfg_ativo ? 1 : 0,
                    }));
                    return `
                        <tr>
                            <td><code>#${cfg.cfg_id}</code></td>
                            <td class="fw-bold">${this.escapeHtml(cfg.cfg_nome)}</td>
                            <td><span class="badge bg-secondary">${this.escapeHtml(String(cfg.cfg_tipo || '').toUpperCase())}</span></td>
                            <td>${diasMap[cfg.cfg_dia_folga_1] || cfg.cfg_dia_folga_1}</td>
                            <td>${diasMap[cfg.cfg_dia_folga_2] || cfg.cfg_dia_folga_2}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-fv-outline btn-edit-cfg5x2" data-json="${jsonData}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-cfg5x2" data-id="${cfg.cfg_id}" data-nome="${this.escapeHtml(cfg.cfg_nome)}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
                const bCfg = document.getElementById('badge-home-cfg-total');
                if (bCfg) bCfg.textContent = `${body.data.configuracoes_escala.length} regras 5x2`;

                // Sugestão B (11/11/2026): Sincroniza Configuração de Folgas 5x2 no Gerador 5x2
                const selGerCfg = document.getElementById('ger_cfg_id');
                if (selGerCfg) {
                    const curCfg = selGerCfg.value;
                    selGerCfg.innerHTML = '<option value="0">Revezamento Automático da Equipe (Cobertura Sáb 08h-20h e Dom/Fer 08h-13h)</option>' + body.data.configuracoes_escala.map((cfg) => {
                        return `<option value="${cfg.cfg_id}">${this.escapeHtml(cfg.cfg_nome)} (${this.escapeHtml(String(cfg.cfg_tipo || '').toUpperCase())})</option>`;
                    }).join('');
                    selGerCfg.value = curCfg || '0';
                }
            }

            const tbodyFer = document.getElementById('tbody-crud-feriados');
            if (tbodyFer && Array.isArray(body.data.feriados)) {
                tbodyFer.innerHTML = body.data.feriados.map((fer) => {
                    const dtIso = String(fer.fer_data || '').slice(0, 10);
                    const jsonData = this.escapeHtml(JSON.stringify({
                        fer_id: fer.fer_id,
                        fer_data: dtIso,
                        fer_descricao: fer.fer_descricao,
                        fer_tipo: fer.fer_tipo,
                    }));
                    return `
                        <tr>
                            <td><code>${formatBr(dtIso)}</code></td>
                            <td class="fw-bold">${this.escapeHtml(fer.fer_descricao)}</td>
                            <td><span class="badge bg-secondary">${this.escapeHtml(String(fer.fer_tipo || '').toUpperCase())}</span></td>
                            <td>${fer.emp_id ? 'Específico da Loja' : 'Geral / Nacional'}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-fv-outline btn-edit-feriado" data-json="${jsonData}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-feriado" data-id="${fer.fer_id}" data-nome="${this.escapeHtml(fer.fer_descricao)}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
                const bFer = document.getElementById('badge-home-fer-total');
                if (bFer) bFer.textContent = `${body.data.feriados.length} feriados`;
            }
        }

        initCrudFuncionarios() {
            const form = document.getElementById('form-crud-funcionario');
            if (!form) return;

            const idInput = document.getElementById('crud_fun_id');
            const tituloEl = document.getElementById('form-funcionario-titulo');
            const senhaHint = document.getElementById('label-senha-hint');
            const tbody = document.getElementById('tbody-crud-funcionarios');

            const limpar = () => {
                form.reset();
                idInput.value = '';
                if (tituloEl) tituloEl.innerHTML = '<i class="bi bi-person-plus me-1"></i>Novo Funcionário';
                if (senhaHint) senhaHint.textContent = 'obrigatória';
            };

            const btnLimpar = document.getElementById('btn-limpar-funcionario');
            if (btnLimpar) btnLimpar.addEventListener('click', limpar);

            if (tbody) {
                tbody.addEventListener('click', async (event) => {
                    const editBtn = event.target.closest('.btn-edit-funcionario');
                    if (editBtn) {
                        const raw = editBtn.getAttribute('data-json');
                        if (!raw) return;
                        const d = JSON.parse(raw);
                        idInput.value = String(d.fun_id);
                        document.getElementById('crud_fun_matricula').value = d.fun_matricula || '';
                        document.getElementById('crud_fun_cpf').value = d.fun_cpf || '';
                        document.getElementById('crud_fun_nome').value = d.fun_nome || '';
                        document.getElementById('crud_fun_sexo').value = d.fun_sexo || 'F';
                        document.getElementById('crud_fun_car_id').value = String(d.car_id || '');
                        document.getElementById('crud_fun_tur_id_padrao').value = d.tur_id_padrao ? String(d.tur_id_padrao) : '';
                        document.getElementById('crud_fun_perfil').value = d.fun_perfil || 'colaborador';
                        document.getElementById('crud_fun_status').value = d.fun_status || 'ativo';
                        document.getElementById('crud_fun_data_admissao').value = d.fun_data_admissao || '';
                        document.getElementById('crud_fun_telefone').value = d.fun_telefone || '';
                        document.getElementById('crud_fun_email').value = d.fun_email || '';
                        document.getElementById('crud_fun_senha').value = '';
                        if (tituloEl) tituloEl.innerHTML = `<i class="bi bi-pencil-square me-1"></i>Editando: ${d.fun_nome}`;
                        if (senhaHint) senhaHint.textContent = 'opcional ao editar';
                        return;
                    }

                    const delBtn = event.target.closest('.btn-delete-funcionario');
                    if (delBtn) {
                        const id = delBtn.getAttribute('data-id');
                        const nome = delBtn.getAttribute('data-nome');
                        if (!id || !confirm(`Confirma a exclusão do funcionário "${nome}"?`)) return;

                        const { ok, body } = await this.apiRequest(`/api/funcionarios/${id}`, 'DELETE');
                        this.showAlert('alert-modal-funcionarios', ok ? 'success' : 'danger', body.message);
                        if (ok) {
                            limpar();
                            await this.refreshFuncionariosTable();
                            await this.refreshCargosTable();
                        }
                    }
                });
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = idInput.value;
                const turPadraoVal = document.getElementById('crud_fun_tur_id_padrao').value;
                const payload = {
                    fun_matricula: document.getElementById('crud_fun_matricula').value.trim(),
                    fun_cpf: document.getElementById('crud_fun_cpf').value.replace(/\D+/g, ''),
                    fun_nome: document.getElementById('crud_fun_nome').value.trim(),
                    fun_sexo: document.getElementById('crud_fun_sexo').value,
                    car_id: Number(document.getElementById('crud_fun_car_id').value),
                    tur_id_padrao: turPadraoVal ? Number(turPadraoVal) : null,
                    fun_perfil: document.getElementById('crud_fun_perfil').value,
                    fun_status: document.getElementById('crud_fun_status').value,
                    fun_data_admissao: document.getElementById('crud_fun_data_admissao').value,
                    fun_telefone: document.getElementById('crud_fun_telefone').value.trim() || null,
                    fun_email: document.getElementById('crud_fun_email').value.trim() || null,
                    fun_senha: document.getElementById('crud_fun_senha').value || null,
                };

                const url = id ? `/api/funcionarios/${id}` : '/api/funcionarios';
                const method = id ? 'PUT' : 'POST';
                const { ok, body } = await this.apiRequest(url, method, payload);

                this.showAlert('alert-modal-funcionarios', ok ? 'success' : 'danger', body.message);
                if (ok) {
                    limpar();
                    await this.refreshFuncionariosTable();
                    await this.refreshCargosTable();
                }
            });
        }

        initCrudCargos() {
            const form = document.getElementById('form-crud-cargo');
            if (!form) return;

            const idInput = document.getElementById('crud_car_id');
            const tbody = document.getElementById('tbody-crud-cargos');
            const limpar = () => {
                form.reset();
                idInput.value = '';
            };

            const btnLimpar = document.getElementById('btn-limpar-cargo');
            if (btnLimpar) btnLimpar.addEventListener('click', limpar);

            if (tbody) {
                tbody.addEventListener('click', async (event) => {
                    const editBtn = event.target.closest('.btn-edit-cargo');
                    if (editBtn) {
                        const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                        idInput.value = String(d.car_id || '');
                        document.getElementById('crud_car_nome').value = d.car_nome || '';
                        document.getElementById('crud_car_cbo').value = d.car_cbo || '';
                        document.getElementById('crud_car_descricao').value = d.car_descricao || '';
                        document.getElementById('crud_car_ativo').value = String(d.car_ativo ?? 1);
                        return;
                    }

                    const delBtn = event.target.closest('.btn-delete-cargo');
                    if (delBtn) {
                        const id = delBtn.getAttribute('data-id');
                        const nome = delBtn.getAttribute('data-nome');
                        if (!id || !confirm(`Confirma a exclusão do cargo "${nome}"?`)) return;

                        const { ok, body } = await this.apiRequest(`/api/cargos/${id}`, 'DELETE');
                        this.showAlert('alert-modal-cargos', ok ? 'success' : 'danger', body.message);
                        if (ok) {
                            limpar();
                            await this.refreshCargosTable();
                        }
                    }
                });
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = idInput.value;
                const payload = {
                    car_nome: document.getElementById('crud_car_nome').value.trim(),
                    car_cbo: document.getElementById('crud_car_cbo').value.trim() || null,
                    car_descricao: document.getElementById('crud_car_descricao').value.trim() || null,
                    car_ativo: document.getElementById('crud_car_ativo').value === '1',
                };

                const { ok, body } = await this.apiRequest(id ? `/api/cargos/${id}` : '/api/cargos', id ? 'PUT' : 'POST', payload);
                this.showAlert('alert-modal-cargos', ok ? 'success' : 'danger', body.message);
                if (ok) {
                    limpar();
                    await this.refreshCargosTable();
                    await this.refreshFuncionariosTable();
                }
            });
        }

        initCrudTurnos() {
            const form = document.getElementById('form-crud-turno');
            if (!form) return;

            const idInput = document.getElementById('crud_tur_id');
            const tbody = document.getElementById('tbody-crud-turnos');
            const limpar = () => {
                form.reset();
                idInput.value = '';
            };

            const btnLimpar = document.getElementById('btn-limpar-turno');
            if (btnLimpar) btnLimpar.addEventListener('click', limpar);

            if (tbody) {
                tbody.addEventListener('click', async (event) => {
                    const editBtn = event.target.closest('.btn-edit-turno');
                    if (editBtn) {
                        const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                        idInput.value = String(d.tur_id || '');
                        document.getElementById('crud_tur_descricao').value = d.tur_descricao || '';
                        document.getElementById('crud_tur_entrada').value = d.tur_entrada || '07:00';
                        document.getElementById('crud_tur_saida_intervalo').value = d.tur_saida_intervalo || '12:00';
                        document.getElementById('crud_tur_retorno_intervalo').value = d.tur_retorno_intervalo || '13:00';
                        document.getElementById('crud_tur_saida').value = d.tur_saida || '16:48';
                        document.getElementById('crud_tur_tolerancia').value = String(d.tur_tolerancia_minutos ?? 10);
                        document.getElementById('crud_tur_ativo').value = String(d.tur_ativo ?? 1);
                        return;
                    }

                    const delBtn = event.target.closest('.btn-delete-turno');
                    if (delBtn) {
                        const id = delBtn.getAttribute('data-id');
                        const nome = delBtn.getAttribute('data-nome');
                        if (!id || !confirm(`Confirma a exclusão do turno "${nome}"?`)) return;

                        const { ok, body } = await this.apiRequest(`/api/turnos/${id}`, 'DELETE');
                        this.showAlert('alert-modal-turnos', ok ? 'success' : 'danger', body.message);
                        if (ok) {
                            limpar();
                            await this.refreshTurnosTable();
                        }
                    }
                });
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = idInput.value;
                const payload = {
                    tur_descricao: document.getElementById('crud_tur_descricao').value.trim(),
                    tur_entrada: document.getElementById('crud_tur_entrada').value,
                    tur_saida_intervalo: document.getElementById('crud_tur_saida_intervalo').value,
                    tur_retorno_intervalo: document.getElementById('crud_tur_retorno_intervalo').value,
                    tur_saida: document.getElementById('crud_tur_saida').value,
                    tur_tolerancia_minutos: Number(document.getElementById('crud_tur_tolerancia').value),
                    tur_ativo: document.getElementById('crud_tur_ativo').value === '1',
                };

                const { ok, body } = await this.apiRequest(id ? `/api/turnos/${id}` : '/api/turnos', id ? 'PUT' : 'POST', payload);
                this.showAlert('alert-modal-turnos', ok ? 'success' : 'danger', body.message);
                if (ok) {
                    limpar();
                    await this.refreshTurnosTable();
                    await this.refreshFuncionariosTable();
                }
            });
        }

        initCrudCalendario() {
            // 1. Períodos
            const formPer = document.getElementById('form-crud-periodo');
            const tbodyPer = document.getElementById('tbody-crud-periodos');
            if (formPer) {
                const idPer = document.getElementById('crud_esp_id');
                const btnSalvarPer = document.getElementById('btn-salvar-periodo');
                const dtInicioPer = document.getElementById('crud_esp_data_inicio');
                const dtFimPer = document.getElementById('crud_esp_data_fim');

                // Sugestão C (11/11/2026): Sugere automaticamente o último dia do mês em Data Fim e define min
                if (dtInicioPer && dtFimPer) {
                    dtInicioPer.addEventListener('change', () => {
                        const valIni = dtInicioPer.value;
                        if (!valIni) {
                            dtFimPer.removeAttribute('min');
                            return;
                        }
                        dtFimPer.setAttribute('min', valIni);
                        if (!dtFimPer.value || dtFimPer.value < valIni) {
                            const parts = valIni.split('-');
                            if (parts.length === 3) {
                                const year = Number(parts[0]);
                                const month = Number(parts[1]);
                                const lastDay = new Date(year, month, 0).getDate();
                                const mm = String(month).padStart(2, '0');
                                const dd = String(lastDay).padStart(2, '0');
                                dtFimPer.value = `${year}-${mm}-${dd}`;
                            }
                        }
                    });
                }

                const limparPer = () => {
                    formPer.reset();
                    idPer.value = '';
                    if (dtFimPer) dtFimPer.removeAttribute('min');
                    if (btnSalvarPer) {
                        btnSalvarPer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Salvar';
                    }
                };
                const btnLimparPer = document.getElementById('btn-limpar-periodo');
                if (btnLimparPer) btnLimparPer.addEventListener('click', limparPer);

                if (tbodyPer) {
                    tbodyPer.addEventListener('click', async (event) => {
                        const editBtn = event.target.closest('.btn-edit-periodo');
                        if (editBtn) {
                            const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                            idPer.value = String(d.esp_id || '');
                            document.getElementById('crud_esp_descricao').value = d.esp_descricao || '';
                            document.getElementById('crud_esp_data_inicio').value = d.esp_data_inicio || '';
                            document.getElementById('crud_esp_data_fim').value = d.esp_data_fim || '';
                            if (dtFimPer && d.esp_data_inicio) {
                                dtFimPer.setAttribute('min', d.esp_data_inicio);
                            }
                            document.getElementById('crud_esp_status').value = d.esp_status || 'rascunho';
                            if (btnSalvarPer) {
                                btnSalvarPer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Atualizar';
                            }
                            return;
                        }

                        const delBtn = event.target.closest('.btn-delete-periodo');
                        if (delBtn) {
                            const id = delBtn.getAttribute('data-id');
                            const nome = delBtn.getAttribute('data-nome');
                            if (!id || !confirm(`Confirma a exclusão do período "${nome}"?`)) return;

                            const { ok, body } = await this.apiRequest(`/api/periodos/${id}`, 'DELETE');
                            this.showAlert('alert-modal-calendario', ok ? 'success' : 'danger', body.message);
                            if (ok) {
                                limparPer();
                                await this.refreshCalendarioTables();
                            }
                        }
                    });
                }

                formPer.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = idPer.value;
                    const payload = {
                        esp_descricao: document.getElementById('crud_esp_descricao').value.trim(),
                        esp_data_inicio: document.getElementById('crud_esp_data_inicio').value,
                        esp_data_fim: document.getElementById('crud_esp_data_fim').value,
                        esp_status: document.getElementById('crud_esp_status').value,
                    };
                    const { ok, body } = await this.apiRequest(id ? `/api/periodos/${id}` : '/api/periodos', id ? 'PUT' : 'POST', payload);
                    this.showAlert('alert-modal-calendario', ok ? 'success' : 'danger', body.message);
                    if (ok) {
                        limparPer();
                        await this.refreshCalendarioTables();
                    }
                });
            }

            // 2. Configurações 5x2
            const formCfg = document.getElementById('form-crud-cfg5x2');
            const tbodyCfg = document.getElementById('tbody-crud-cfg5x2');
            if (formCfg) {
                const idCfg = document.getElementById('crud_cfg_id');
                const btnSalvarCfg = document.getElementById('btn-salvar-cfg5x2');
                const limparCfg = () => {
                    formCfg.reset();
                    idCfg.value = '';
                    if (btnSalvarCfg) {
                        btnSalvarCfg.innerHTML = '<i class="bi bi-check-lg me-1"></i>Salvar 5x2';
                    }
                };
                const btnLimparCfg = document.getElementById('btn-limpar-cfg5x2');
                if (btnLimparCfg) btnLimparCfg.addEventListener('click', limparCfg);

                if (tbodyCfg) {
                    tbodyCfg.addEventListener('click', async (event) => {
                        const editBtn = event.target.closest('.btn-edit-cfg5x2');
                        if (editBtn) {
                            const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                            idCfg.value = String(d.cfg_id || '');
                            document.getElementById('crud_cfg_nome').value = d.cfg_nome || '';
                            document.getElementById('crud_cfg_tipo').value = d.cfg_tipo || 'fixa';
                            document.getElementById('crud_cfg_dia_folga_1').value = String(d.cfg_dia_folga_1 || 1);
                            document.getElementById('crud_cfg_dia_folga_2').value = String(d.cfg_dia_folga_2 || 7);
                            if (btnSalvarCfg) {
                                btnSalvarCfg.innerHTML = '<i class="bi bi-check-lg me-1"></i>Atualizar 5x2';
                            }
                            return;
                        }

                        const delBtn = event.target.closest('.btn-delete-cfg5x2');
                        if (delBtn) {
                            const id = delBtn.getAttribute('data-id');
                            const nome = delBtn.getAttribute('data-nome');
                            if (!id || !confirm(`Confirma a exclusão da regra 5x2 "${nome}"?`)) return;

                            const { ok, body } = await this.apiRequest(`/api/configuracoes-escala/${id}`, 'DELETE');
                            this.showAlert('alert-modal-calendario', ok ? 'success' : 'danger', body.message);
                            if (ok) {
                                limparCfg();
                                await this.refreshCalendarioTables();
                            }
                        }
                    });
                }

                formCfg.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = idCfg.value;
                    const payload = {
                        cfg_nome: document.getElementById('crud_cfg_nome').value.trim(),
                        cfg_tipo: document.getElementById('crud_cfg_tipo').value,
                        cfg_dia_folga_1: Number(document.getElementById('crud_cfg_dia_folga_1').value),
                        cfg_dia_folga_2: Number(document.getElementById('crud_cfg_dia_folga_2').value),
                        cfg_ativo: true,
                    };
                    const { ok, body } = await this.apiRequest(id ? `/api/configuracoes-escala/${id}` : '/api/configuracoes-escala', id ? 'PUT' : 'POST', payload);
                    this.showAlert('alert-modal-calendario', ok ? 'success' : 'danger', body.message);
                    if (ok) {
                        limparCfg();
                        await this.refreshCalendarioTables();
                    }
                });
            }

            // 3. Feriados
            const formFer = document.getElementById('form-crud-feriado');
            const tbodyFer = document.getElementById('tbody-crud-feriados');
            if (formFer) {
                const idFer = document.getElementById('crud_fer_id');
                const btnSalvarFer = document.getElementById('btn-salvar-feriado');
                const limparFer = () => {
                    formFer.reset();
                    idFer.value = '';
                    if (btnSalvarFer) {
                        btnSalvarFer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Salvar';
                    }
                };
                const btnLimparFer = document.getElementById('btn-limpar-feriado');
                if (btnLimparFer) btnLimparFer.addEventListener('click', limparFer);

                if (tbodyFer) {
                    tbodyFer.addEventListener('click', async (event) => {
                        const editBtn = event.target.closest('.btn-edit-feriado');
                        if (editBtn) {
                            const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                            idFer.value = String(d.fer_id || '');
                            document.getElementById('crud_fer_data').value = d.fer_data || '';
                            document.getElementById('crud_fer_descricao').value = d.fer_descricao || '';
                            document.getElementById('crud_fer_tipo').value = d.fer_tipo || 'nacional';
                            if (btnSalvarFer) {
                                btnSalvarFer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Atualizar';
                            }
                            return;
                        }

                        const delBtn = event.target.closest('.btn-delete-feriado');
                        if (delBtn) {
                            const id = delBtn.getAttribute('data-id');
                            const nome = delBtn.getAttribute('data-nome');
                            if (!id || !confirm(`Confirma a exclusão do feriado "${nome}"?`)) return;

                            const { ok, body } = await this.apiRequest(`/api/feriados/${id}`, 'DELETE');
                            this.showAlert('alert-modal-calendario', ok ? 'success' : 'danger', body.message);
                            if (ok) {
                                limparFer();
                                await this.refreshCalendarioTables();
                            }
                        }
                    });
                }

                formFer.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = idFer.value;
                    const payload = {
                        fer_data: document.getElementById('crud_fer_data').value,
                        fer_descricao: document.getElementById('crud_fer_descricao').value.trim(),
                        fer_tipo: document.getElementById('crud_fer_tipo').value,
                    };
                    const { ok, body } = await this.apiRequest(id ? `/api/feriados/${id}` : '/api/feriados', id ? 'PUT' : 'POST', payload);
                    this.showAlert('alert-modal-calendario', ok ? 'success' : 'danger', body.message);
                    if (ok) {
                        limparFer();
                        await this.refreshCalendarioTables();
                    }
                });
            }
        }

        initTrocasEAfastamentos() {
            const selSolicitante = document.getElementById('tro_fun_solicitante');
            const selOrigem = document.getElementById('tro_esd_origem');
            const selSubstituto = document.getElementById('tro_fun_substituto');
            const selDestino = document.getElementById('tro_esd_destino');

            const carregarDias = async (funId, selectTarget, isOptional) => {
                if (!funId) {
                    selectTarget.innerHTML = `<option value="">${isOptional ? '-- Troca Simples / Cobertura --' : 'Selecione o colaborador'}</option>`;
                    return;
                }
                const { ok, body } = await this.apiRequest(`/api/trocas/dias-funcionario/${funId}`, 'GET');
                if (!ok || !body.data) return;

                let html = isOptional ? '<option value="">-- Troca Simples / Cobertura --</option>' : '';
                body.data.forEach((d) => {
                    html += `<option value="${d.esd_id}">${d.esd_data_br} — ${d.tur_descricao}</option>`;
                });
                selectTarget.innerHTML = html;
            };

            if (selSolicitante && selOrigem) {
                selSolicitante.addEventListener('change', () => carregarDias(selSolicitante.value, selOrigem, false));
            }
            if (selSubstituto && selDestino) {
                selSubstituto.addEventListener('change', () => carregarDias(selSubstituto.value, selDestino, true));
            }

            const formTroca = document.getElementById('form-solicitar-troca');
            if (formTroca) {
                formTroca.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const payload = {
                        fun_id_solicitante: Number(selSolicitante ? selSolicitante.value : 0),
                        fun_id_substituto: Number(selSubstituto ? selSubstituto.value : 0),
                        esd_id_origem: Number(selOrigem ? selOrigem.value : 0),
                        esd_id_destino: selDestino && selDestino.value ? Number(selDestino.value) : null,
                        tro_motivo: document.getElementById('tro_motivo').value.trim(),
                    };

                    const { ok, body } = await this.apiRequest('/api/trocas', 'POST', payload);
                    this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
                    if (ok) setTimeout(() => window.location.reload(), 700);
                });
            }

            document.querySelectorAll('.btn-aprovar-troca').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    if (!id) return;
                    const { ok, body } = await this.apiRequest(`/api/trocas/${id}/aprovar`, 'POST');
                    this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
                    if (ok) setTimeout(() => window.location.reload(), 800);
                });
            });

            document.querySelectorAll('.btn-recusar-troca').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');
                    if (!id) return;
                    const { ok, body } = await this.apiRequest(`/api/trocas/${id}/status`, 'POST', { tro_status: 'recusada' });
                    this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
                    if (ok) setTimeout(() => window.location.reload(), 700);
                });
            });

            const formAfa = document.getElementById('form-registrar-afastamento');
            if (formAfa) {
                formAfa.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const payload = {
                        fun_id: Number(document.getElementById('afa_fun_id').value),
                        fun_status: document.getElementById('afa_fun_status').value,
                        data_inicio: document.getElementById('afa_data_inicio').value || null,
                        data_fim: document.getElementById('afa_data_fim').value || null,
                        observacao: document.getElementById('afa_observacao').value.trim() || null,
                    };
                    const { ok, body } = await this.apiRequest('/api/afastamentos', 'POST', payload);
                    this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
                    if (ok) setTimeout(() => window.location.reload(), 800);
                });
            }
        }

        initGeradorEscala5x2() {
            const selModo = document.getElementById('ger_modo');
            const wrapFun = document.getElementById('wrap-ger-fun-id');
            const formGer = document.getElementById('form-gerar-escala-5x2');

            if (selModo && wrapFun) {
                selModo.addEventListener('change', () => {
                    wrapFun.style.display = selModo.value === 'individual' ? 'block' : 'none';
                });
            }

            if (formGer) {
                formGer.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const btn = document.getElementById('btn-executar-gerador');
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando sp_gerar_escala_5x2_funcionario...';
                    }

                    const turVal = document.getElementById('ger_tur_id').value;
                    const ferModoEl = document.getElementById('ger_feriado_modo');
                    const payload = {
                        esp_id: Number(document.getElementById('ger_esp_id').value),
                        cfg_id: Number(document.getElementById('ger_cfg_id').value),
                        modo: document.getElementById('ger_modo').value,
                        fun_id: Number(document.getElementById('ger_fun_id').value),
                        tur_id: turVal ? Number(turVal) : null,
                        feriado_modo: ferModoEl ? ferModoEl.value : 'expediente',
                    };

                    const { ok, body } = await this.apiRequest('/api/escalas/gerar', 'POST', payload);
                    this.showAlert('alert-modal-gerador', ok ? 'success' : 'danger', body.message);

                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-gear-wide-connected me-2"></i>Executar Geração Automática 5x2';
                    }

                    if (ok) {
                        setTimeout(() => window.location.reload(), 900);
                    }
                });
            }
        }

        initEdicaoDiaEscala() {
            const selTipo = document.getElementById('edit_esd_tipo_dia');
            const wrapTurno = document.getElementById('wrap-edit-esd-turno');
            const formEdit = document.getElementById('form-editar-dia-escala');

            if (selTipo && wrapTurno) {
                selTipo.addEventListener('change', () => {
                    wrapTurno.style.display = selTipo.value === 'trabalho' ? 'block' : 'none';
                });
            }

            document.querySelectorAll('.btn-abrir-editar-dia').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const esdId = btn.getAttribute('data-esd-id') || '';
                    const funNome = btn.getAttribute('data-fun-nome') || '';
                    const esdData = btn.getAttribute('data-esd-data') || '';
                    const esdTipo = btn.getAttribute('data-esd-tipo') || 'trabalho';
                    const turId = btn.getAttribute('data-tur-id') || '';

                    document.getElementById('edit_esd_id').value = esdId;
                    const infoEl = document.getElementById('edit_esd_info_label');
                    if (infoEl) infoEl.textContent = `${funNome} • Data: ${esdData}`;

                    if (selTipo) {
                        selTipo.value = esdTipo;
                        if (wrapTurno) wrapTurno.style.display = esdTipo === 'trabalho' ? 'block' : 'none';
                    }

                    const selTur = document.getElementById('edit_esd_tur_id');
                    if (selTur && turId) selTur.value = turId;

                    const alertEl = document.getElementById('alert-modal-editar-dia');
                    if (alertEl) alertEl.innerHTML = '';

                    const modalEl = document.getElementById('modalEditarDiaEscala');
                    if (modalEl && window.bootstrap) {
                        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    }
                });
            });

            if (formEdit) {
                formEdit.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const esdId = document.getElementById('edit_esd_id').value;
                    const tipoDia = document.getElementById('edit_esd_tipo_dia').value;
                    const turId = document.getElementById('edit_esd_tur_id').value;
                    const obs = document.getElementById('edit_esd_observacao').value.trim();

                    const { ok, body } = await this.apiRequest(`/api/escalas/diaria/${esdId}`, 'PUT', {
                        esd_tipo_dia: tipoDia,
                        tur_id: tipoDia === 'trabalho' ? Number(turId) : null,
                        esd_observacao: obs || null,
                    });

                    this.showAlert('alert-modal-editar-dia', ok ? 'success' : 'danger', body.message);
                    if (ok) setTimeout(() => window.location.reload(), 800);
                });
            }
        }

        initDashboardDia() {
            const btn = document.getElementById('btn-filtrar-dia');
            const input = document.getElementById('input-data-dashboard');

            if (!btn || !input) return;

            const consultarDia = () => {
                if (!input.value) return;
                const url = new URL(window.location.href);
                url.searchParams.set('data_dia', input.value);
                window.location.href = url.toString();
            };

            btn.addEventListener('click', consultarDia);
            input.addEventListener('change', consultarDia);
        }

        async refreshEmpresasTable() {
            const tbody = document.getElementById('tbody-crud-empresas');
            if (!tbody) return;

            const chkInativas = document.getElementById('chk-mostrar-empresas-inativas');
            const incluirInativas = chkInativas && chkInativas.checked ? '1' : '0';

            const { ok, body } = await this.apiRequest(`/api/empresas?incluir_inativas=${incluirInativas}`, 'GET');
            if (!ok || !Array.isArray(body.data)) return;

            const lista = body.data;
            const empIdAtiva = Number(body.emp_id_ativa || 0);
            const badgeCount = document.getElementById('badge-count-empresas');
            if (badgeCount) badgeCount.textContent = String(lista.length);

            if (lista.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-3">Nenhuma empresa ativa encontrada.</td></tr>';
                return;
            }

            tbody.innerHTML = lista.map((emp) => {
                const isAtiva = Boolean(emp.emp_ativo);
                const celular = emp.emp_celular || emp.emp_telefone || '-';
                const jsonData = this.escapeHtml(JSON.stringify({
                    emp_id: emp.emp_id,
                    emp_nome_fantasia: emp.emp_nome_fantasia,
                    emp_razao_social: emp.emp_razao_social,
                    emp_cnpj: emp.emp_cnpj,
                    emp_email: emp.emp_email,
                    emp_celular: emp.emp_celular || emp.emp_telefone || '',
                    emp_logradouro: emp.emp_logradouro,
                    emp_numero: emp.emp_numero,
                    emp_bairro: emp.emp_bairro,
                    emp_cidade: emp.emp_cidade,
                    emp_uf: emp.emp_uf,
                }));

                const badgeSelecionada = Number(emp.emp_id) === empIdAtiva
                    ? '<span class="badge bg-dark ms-1">Selecionada</span>'
                    : '';
                const badgeStatus = !isAtiva
                    ? '<span class="badge bg-warning text-dark ms-1">Inativa</span>'
                    : '';

                const btnStatusAction = isAtiva
                    ? `<button type="button" class="btn btn-sm btn-outline-danger btn-deactivate-view-empresa" data-id="${emp.emp_id}" data-nome="${this.escapeHtml(emp.emp_nome_fantasia)}" data-ativo="0" title="Desativar Empresa"><i class="bi bi-slash-circle"></i></button>`
                    : `<button type="button" class="btn btn-sm btn-outline-success btn-deactivate-view-empresa" data-id="${emp.emp_id}" data-nome="${this.escapeHtml(emp.emp_nome_fantasia)}" data-ativo="1" title="Reativar Empresa"><i class="bi bi-check-circle"></i></button>`;

                return `
                    <tr data-emp-id="${emp.emp_id}">
                        <td>
                            <div class="fw-bold text-dark">
                                ${this.escapeHtml(emp.emp_nome_fantasia)}
                                ${badgeSelecionada}
                                ${badgeStatus}
                            </div>
                        </td>
                        <td><code>${this.escapeHtml(emp.emp_cnpj)}</code></td>
                        <td>${this.escapeHtml(emp.emp_email)}</td>
                        <td>${this.escapeHtml(celular)}</td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-fv-outline btn-edit-view-empresa" title="Alterar Empresa" data-json="${jsonData}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            ${btnStatusAction}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        initCrudEmpresa() {
            // 1. View dedicada de CRUD de Empresas (/empresas)
            const formView = document.getElementById('form-view-empresa');
            const tbodyView = document.getElementById('tbody-crud-empresas');

            if (formView && tbodyView) {
                const idInput = document.getElementById('view_emp_id');
                const tituloEl = document.getElementById('form-empresa-view-titulo');
                const chkInativas = document.getElementById('chk-mostrar-empresas-inativas');

                const limparFormView = () => {
                    formView.reset();
                    idInput.value = '';
                    const cidadeEl = document.getElementById('view_emp_cidade');
                    const ufEl = document.getElementById('view_emp_uf');
                    if (cidadeEl && !cidadeEl.value) cidadeEl.value = 'Manaus';
                    if (ufEl && !ufEl.value) ufEl.value = 'AM';
                    if (tituloEl) tituloEl.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Nova Empresa';
                };

                const btnLimpar = document.getElementById('btn-limpar-view-empresa');
                if (btnLimpar) btnLimpar.addEventListener('click', limparFormView);

                if (chkInativas) {
                    chkInativas.addEventListener('change', () => {
                        this.refreshEmpresasTable();
                    });
                }

                tbodyView.addEventListener('click', async (event) => {
                    const editBtn = event.target.closest('.btn-edit-view-empresa');
                    if (editBtn) {
                        const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                        idInput.value = String(d.emp_id || '');
                        document.getElementById('view_emp_nome_fantasia').value = d.emp_nome_fantasia || '';
                        document.getElementById('view_emp_razao_social').value = d.emp_razao_social || '';
                        document.getElementById('view_emp_cnpj').value = d.emp_cnpj || '';
                        document.getElementById('view_emp_email').value = d.emp_email || '';
                        document.getElementById('view_emp_celular').value = d.emp_celular || '';
                        document.getElementById('view_emp_logradouro').value = d.emp_logradouro || '';
                        document.getElementById('view_emp_numero').value = d.emp_numero || '';
                        document.getElementById('view_emp_bairro').value = d.emp_bairro || '';
                        document.getElementById('view_emp_cidade').value = d.emp_cidade || '';
                        document.getElementById('view_emp_uf').value = d.emp_uf || '';
                        if (tituloEl) tituloEl.innerHTML = `<i class="bi bi-pencil-square me-1"></i>Editando: ${d.emp_nome_fantasia}`;
                        return;
                    }

                    const statusBtn = event.target.closest('.btn-deactivate-view-empresa');
                    if (statusBtn) {
                        const id = statusBtn.getAttribute('data-id');
                        const nome = statusBtn.getAttribute('data-nome');
                        const novoAtivo = statusBtn.getAttribute('data-ativo') === '1';
                        const acaoTexto = novoAtivo ? 'reativar' : 'desativar';
                        if (!id || !confirm(`Confirma ${acaoTexto} a empresa "${nome}"?`)) return;

                        const { ok, body } = await this.apiRequest(`/api/empresas/${id}/status`, 'PATCH', {
                            emp_ativo: novoAtivo,
                        });
                        this.showAlert('alert-view-empresas', ok ? 'success' : 'danger', body.message);
                        if (ok) {
                            if (idInput.value === String(id)) {
                                limparFormView();
                            }
                            await this.refreshEmpresasTable();
                        }
                    }
                });

                formView.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const id = idInput.value;
                    const payload = {
                        emp_nome_fantasia: document.getElementById('view_emp_nome_fantasia').value.trim(),
                        emp_razao_social: document.getElementById('view_emp_razao_social').value.trim() || null,
                        emp_cnpj: document.getElementById('view_emp_cnpj').value.replace(/\D+/g, ''),
                        emp_email: document.getElementById('view_emp_email').value.trim(),
                        emp_celular: document.getElementById('view_emp_celular').value.trim() || null,
                        emp_logradouro: document.getElementById('view_emp_logradouro').value.trim() || null,
                        emp_numero: document.getElementById('view_emp_numero').value.trim() || null,
                        emp_bairro: document.getElementById('view_emp_bairro').value.trim() || null,
                        emp_cidade: document.getElementById('view_emp_cidade').value.trim() || null,
                        emp_uf: document.getElementById('view_emp_uf').value.trim().toUpperCase() || null,
                    };

                    const url = id ? `/api/empresas/${id}` : '/api/empresas';
                    const method = id ? 'PUT' : 'POST';
                    const { ok, body } = await this.apiRequest(url, method, payload);

                    this.showAlert('alert-view-empresas', ok ? 'success' : 'danger', body.message);
                    if (ok) {
                        limparFormView();
                        await this.refreshEmpresasTable();
                    }
                });
            }

            // 2. Compatibilidade com modal secundário caso utilizado
            const form = document.getElementById('form-crud-empresa');
            if (!form) return;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('crud_emp_id').value;
                const payload = {
                    emp_razao_social: document.getElementById('crud_emp_razao_social').value.trim(),
                    emp_nome_fantasia: document.getElementById('crud_emp_nome_fantasia').value.trim(),
                    emp_cnpj: document.getElementById('crud_emp_cnpj').value.replace(/\D+/g, ''),
                    emp_email: document.getElementById('crud_emp_email').value.trim(),
                    emp_celular: document.getElementById('crud_emp_telefone').value.trim() || null,
                    emp_telefone: document.getElementById('crud_emp_telefone').value.trim() || null,
                    emp_logradouro: document.getElementById('crud_emp_logradouro').value.trim() || null,
                    emp_numero: document.getElementById('crud_emp_numero').value.trim() || null,
                    emp_bairro: document.getElementById('crud_emp_bairro').value.trim() || null,
                    emp_cidade: document.getElementById('crud_emp_cidade').value.trim() || null,
                    emp_uf: document.getElementById('crud_emp_uf').value.trim().toUpperCase() || null,
                };

                const { ok, body } = await this.apiRequest(`/api/empresas/${id}`, 'PUT', payload);
                this.showAlert('alert-modal-empresa', ok ? 'success' : 'danger', body.message);
            });
        }
    }

    new FarmaVivaApp();
})();
