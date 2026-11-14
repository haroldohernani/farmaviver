/**
 * FarmaViva (FarmaViver) - Módulo Front-End Completo em TypeScript
 * Fases 1 a 5:
 * - Login Mobile First, Validação Bootstrap ("CPF Inválido", "Senha Inválida", "CPF E Senha Inválidos !!") e Foco
 * - Relógio em Tempo Real e Logout Global de Sessões
 * - CRUDs Intuitivos em Cards/Modais (Funcionários, Cargos, Turnos, Calendário/Feriados/5x2, Trocas/Afastamentos, Empresa)
 * - Dashboard do Dia, Visualizador Mensal/Semanal e Gerador Automático 5x2 (sp_gerar_escala_5x2_funcionario)
 */

export interface ApiStandardResponse<T = any> {
    status: 'ok' | 'error';
    error_type?: 'cpf_invalido' | 'senha_invalida' | 'ambos_invalidos';
    message: string;
    focus_field?: 'fun_cpf' | 'fun_senha';
    redirect?: string;
    data?: T;
    errors?: Record<string, string[]>;
}

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

    private getCsrfToken(): string {
        const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
        return meta ? meta.content : '';
    }

    private async apiRequest<T = any>(url: string, method: string, payload?: unknown): Promise<{ ok: boolean; status: number; body: ApiStandardResponse<T> }> {
        const options: RequestInit = {
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

        // Extrai primeiro erro de validação do Laravel caso exista
        if (!res.ok && json.errors) {
            const firstKey = Object.keys(json.errors)[0];
            if (firstKey && json.errors[firstKey]?.[0]) {
                json.message = json.errors[firstKey][0];
            }
        }

        return { ok: res.ok, status: res.status, body: json };
    }

    private showAlert(containerId: string, type: 'success' | 'danger' | 'warning', message: string): void {
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

    private initNumericInputs(): void {
        document.querySelectorAll<HTMLInputElement>('.fv-only-digits').forEach((input) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D+/g, '');
            });
        });
    }

    /**
     * Tela de Login e Regras de Negócio (Fase 2)
     */
    private initLoginScreen(): void {
        const loginForm = document.getElementById('fv-login-form') as HTMLFormElement | null;
        const cpfInput = document.getElementById('fun_cpf') as HTMLInputElement | null;
        const senhaInput = document.getElementById('fun_senha') as HTMLInputElement | null;
        const togglePasswordBtn = document.getElementById('btn-toggle-senha') as HTMLButtonElement | null;

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

        document.querySelectorAll<HTMLButtonElement>('[data-demo-cpf]').forEach((btn) => {
            btn.addEventListener('click', () => {
                cpfInput.value = btn.getAttribute('data-demo-cpf') || '';
                senhaInput.value = btn.getAttribute('data-demo-senha') || '123456';
                cpfInput.classList.remove('is-invalid');
                senhaInput.classList.remove('is-invalid');
                cpfInput.focus();
            });
        });

        loginForm.addEventListener('submit', async (event: Event) => {
            event.preventDefault();
            cpfInput.value = cpfInput.value.replace(/\D+/g, '').slice(0, 11);

            const submitBtn = document.getElementById('btn-login-submit') as HTMLButtonElement | null;
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

    /**
     * Cronômetro em Tempo Real na Head da Home mostrando o Tempo Logado na Sessão (Atualização 02/11/2026)
     */
    private initRealtimeClock(): void {
        const clockEl = document.getElementById('fv-realtime-clock');
        if (!clockEl) return;

        const attrTs = Number(clockEl.getAttribute('data-login-timestamp') || 0);
        const startSeconds = attrTs > 0 ? attrTs : Math.floor(Date.now() / 1000);

        const formatElapsed = (totalSec: number): string => {
            const sec = Math.max(0, totalSec);
            const hours = Math.floor(sec / 3600);
            const minutes = Math.floor((sec % 3600) / 60);
            const seconds = sec % 60;
            const hh = String(hours).padStart(2, '0');
            const mm = String(minutes).padStart(2, '0');
            const ss = String(seconds).padStart(2, '0');
            return `${hh}:${mm}:${ss}`;
        };

        const updateClock = (): void => {
            const nowSec = Math.floor(Date.now() / 1000);
            const elapsed = nowSec - startSeconds;
            clockEl.innerHTML = `<i class="bi bi-stopwatch me-1"></i>Tempo logado: ${formatElapsed(elapsed)}`;
        };

        updateClock();
        window.setInterval(updateClock, 1000);
    }

    /**
     * Logout Global de Todas as Sessões Abertas (Fase 2 / 3)
     */
    private initLogoutHandler(): void {
        const logoutBtn = document.getElementById('btn-logout-all') as HTMLButtonElement | null;
        if (!logoutBtn) return;

        logoutBtn.addEventListener('click', async (event: Event) => {
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

    private escapeHtml(str: unknown): string {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    private async refreshFuncionariosTable(): Promise<void> {
        const tbody = document.getElementById('tbody-crud-funcionarios');
        if (!tbody) return;

        const { ok, body } = await this.apiRequest('/api/funcionarios', 'GET');
        if (!ok || !Array.isArray(body.data)) return;

        const lista = body.data as Array<Record<string, any>>;
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
    }

    private async refreshCargosTable(): Promise<void> {
        const tbody = document.getElementById('tbody-crud-cargos');
        if (!tbody) return;

        const { ok, body } = await this.apiRequest('/api/cargos', 'GET');
        if (!ok || !Array.isArray(body.data)) return;

        const lista = body.data as Array<Record<string, any>>;
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
        const selCargoFun = document.getElementById('crud_fun_car_id') as HTMLSelectElement | null;
        if (selCargoFun) {
            const currentVal = selCargoFun.value;
            selCargoFun.innerHTML = listaAtivos.map((c) => `<option value="${c.car_id}">${this.escapeHtml(c.car_nome)}</option>`).join('');
            if (currentVal) selCargoFun.value = currentVal;
        }

        const cargosAtivos = listaAtivos.length;
        const badgeCar = document.getElementById('badge-home-car-ativos');
        if (badgeCar) badgeCar.textContent = `${cargosAtivos} cargos ativos`;
    }

    private async refreshTurnosTable(): Promise<void> {
        const tbody = document.getElementById('tbody-crud-turnos');
        if (!tbody) return;

        const { ok, body } = await this.apiRequest('/api/turnos', 'GET');
        if (!ok || !Array.isArray(body.data)) return;

        const lista = body.data as Array<Record<string, any>>;
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

        const selGerTur = document.getElementById('ger_tur_id') as HTMLSelectElement | null;
        if (selGerTur) {
            const curGerTur = selGerTur.value;
            selGerTur.innerHTML = '<option value="">Usar o Turno Padrão de cada funcionário (Recomendado)</option>' + optsTurnos;
            if (curGerTur) selGerTur.value = curGerTur;
        }

        const selFunTur = document.getElementById('crud_fun_tur_id_padrao') as HTMLSelectElement | null;
        if (selFunTur) {
            const curFunTur = selFunTur.value;
            selFunTur.innerHTML = '<option value="">-- Sem turno fixo --</option>' + optsTurnos;
            if (curFunTur) selFunTur.value = curFunTur;
        }

        const selEditTur = document.getElementById('edit_esd_tur_id') as HTMLSelectElement | null;
        if (selEditTur) {
            const curEditTur = selEditTur.value;
            selEditTur.innerHTML = optsTurnos;
            if (curEditTur) selEditTur.value = curEditTur;
        }
    }

    private async refreshCalendarioTables(): Promise<void> {
        const { ok, body } = await this.apiRequest('/api/calendario-operacional', 'GET');
        if (!ok || !body.data) return;

        const dataObj = body.data as Record<string, any>;
        const diasMap: Record<number, string> = {
            1: '1 - Domingo',
            2: '2 - Segunda-feira',
            3: '3 - Terça-feira',
            4: '4 - Quarta-feira',
            5: '5 - Quinta-feira',
            6: '6 - Sexta-feira',
            7: '7 - Sábado',
        };

        const formatBr = (iso: unknown): string => {
            const s = String(iso || '').slice(0, 10);
            const p = s.split('-');
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : s;
        };

        const tbodyPer = document.getElementById('tbody-crud-periodos');
        if (tbodyPer && Array.isArray(dataObj.periodos)) {
            tbodyPer.innerHTML = dataObj.periodos.map((p: Record<string, any>) => {
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
            // Sugestão B (11/11/2026 e 14/11/2026): Sincroniza Período Operacional no Gerador 5x2 e no seletor superior #select_top_esp_id
            const optionsPeriodosHtml = dataObj.periodos.map((p: Record<string, any>) => {
                const ini = formatBr(String(p.esp_data_inicio || '').slice(0, 10));
                const fim = formatBr(String(p.esp_data_fim || '').slice(0, 10));
                return `<option value="${p.esp_id}">${this.escapeHtml(p.esp_descricao)} (${ini} a ${fim})</option>`;
            }).join('');

            const selGerEsp = document.getElementById('ger_esp_id') as HTMLSelectElement | null;
            if (selGerEsp) {
                const curEsp = selGerEsp.value;
                selGerEsp.innerHTML = optionsPeriodosHtml;
                if (curEsp && dataObj.periodos.some((p: Record<string, any>) => String(p.esp_id) === String(curEsp))) {
                    selGerEsp.value = curEsp;
                }
            }

            const selTopEsp = document.getElementById('select_top_esp_id') as HTMLSelectElement | null;
            if (selTopEsp) {
                const curTopEsp = selTopEsp.value;
                selTopEsp.innerHTML = dataObj.periodos.length > 0
                    ? dataObj.periodos.map((p: Record<string, any>) => {
                        const ini = formatBr(String(p.esp_data_inicio || '').slice(0, 10));
                        const fim = formatBr(String(p.esp_data_fim || '').slice(0, 10));
                        return `<option value="${p.esp_id}" data-ini="${ini}" data-fim="${fim}">${this.escapeHtml(p.esp_descricao)} (${ini} a ${fim})</option>`;
                    }).join('')
                    : '<option value="0">Sem período cadastrado</option>';
                if (curTopEsp && dataObj.periodos.some((p: Record<string, any>) => String(p.esp_id) === String(curTopEsp))) {
                    selTopEsp.value = curTopEsp;
                }
            }
        }

        const tbodyCfg = document.getElementById('tbody-crud-cfg5x2');
        if (tbodyCfg && Array.isArray(dataObj.configuracoes_escala)) {
            tbodyCfg.innerHTML = dataObj.configuracoes_escala.map((cfg: Record<string, any>) => {
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
                        <td>${diasMap[Number(cfg.cfg_dia_folga_1)] || cfg.cfg_dia_folga_1}</td>
                        <td>${diasMap[Number(cfg.cfg_dia_folga_2)] || cfg.cfg_dia_folga_2}</td>
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

            const selGerCfg = document.getElementById('ger_cfg_id') as HTMLSelectElement | null;
            if (selGerCfg) {
                const curCfg = selGerCfg.value;
                selGerCfg.innerHTML = '<option value="0">Revezamento Automático da Equipe (Cobertura Sáb 08h-20h e Dom/Fer 08h-13h)</option>' + dataObj.configuracoes_escala.map((cfg: Record<string, any>) => {
                    return `<option value="${cfg.cfg_id}">${this.escapeHtml(cfg.cfg_nome)} (${this.escapeHtml(String(cfg.cfg_tipo || '').toUpperCase())})</option>`;
                }).join('');
                selGerCfg.value = curCfg || '0';
            }
        }

        const tbodyFer = document.getElementById('tbody-crud-feriados');
        if (tbodyFer && Array.isArray(dataObj.feriados)) {
            tbodyFer.innerHTML = dataObj.feriados.map((fer: Record<string, any>) => {
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
                        <td>Todas as Empresas</td>
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
            if (bFer) bFer.textContent = `${dataObj.feriados.length} feriados`;
        }
    }

    /**
     * CRUD Card 1: Funcionários (Fase 4 - Atualização em Tempo Real)
     */
    private initCrudFuncionarios(): void {
        const form = document.getElementById('form-crud-funcionario') as HTMLFormElement | null;
        if (!form) return;

        const idInput = document.getElementById('crud_fun_id') as HTMLInputElement;
        const tituloEl = document.getElementById('form-funcionario-titulo');
        const senhaHint = document.getElementById('label-senha-hint');
        const tbody = document.getElementById('tbody-crud-funcionarios');

        const limpar = (): void => {
            form.reset();
            idInput.value = '';
            if (tituloEl) tituloEl.innerHTML = '<i class="bi bi-person-plus me-1"></i>Novo Funcionário';
            if (senhaHint) senhaHint.textContent = 'obrigatória';
        };

        document.getElementById('btn-limpar-funcionario')?.addEventListener('click', limpar);

        tbody?.addEventListener('click', async (event: Event) => {
            const target = event.target as HTMLElement;
            const editBtn = target.closest('.btn-edit-funcionario');
            if (editBtn) {
                const raw = editBtn.getAttribute('data-json');
                if (!raw) return;
                const d = JSON.parse(raw);
                idInput.value = String(d.fun_id);
                (document.getElementById('crud_fun_matricula') as HTMLInputElement).value = d.fun_matricula || '';
                (document.getElementById('crud_fun_cpf') as HTMLInputElement).value = d.fun_cpf || '';
                (document.getElementById('crud_fun_nome') as HTMLInputElement).value = d.fun_nome || '';
                (document.getElementById('crud_fun_sexo') as HTMLSelectElement).value = d.fun_sexo || 'F';
                (document.getElementById('crud_fun_car_id') as HTMLSelectElement).value = String(d.car_id || '');
                (document.getElementById('crud_fun_tur_id_padrao') as HTMLSelectElement).value = d.tur_id_padrao ? String(d.tur_id_padrao) : '';
                (document.getElementById('crud_fun_perfil') as HTMLSelectElement).value = d.fun_perfil || 'colaborador';
                (document.getElementById('crud_fun_status') as HTMLSelectElement).value = d.fun_status || 'ativo';
                (document.getElementById('crud_fun_data_admissao') as HTMLInputElement).value = d.fun_data_admissao || '';
                (document.getElementById('crud_fun_telefone') as HTMLInputElement).value = d.fun_telefone || '';
                (document.getElementById('crud_fun_email') as HTMLInputElement).value = d.fun_email || '';
                (document.getElementById('crud_fun_senha') as HTMLInputElement).value = '';
                if (tituloEl) tituloEl.innerHTML = `<i class="bi bi-pencil-square me-1"></i>Editando: ${d.fun_nome}`;
                if (senhaHint) senhaHint.textContent = 'opcional ao editar';
                return;
            }

            const delBtn = target.closest('.btn-delete-funcionario');
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

        form.addEventListener('submit', async (e: Event) => {
            e.preventDefault();
            const id = idInput.value;
            const payload = {
                fun_matricula: (document.getElementById('crud_fun_matricula') as HTMLInputElement).value.trim(),
                fun_cpf: (document.getElementById('crud_fun_cpf') as HTMLInputElement).value.replace(/\D+/g, ''),
                fun_nome: (document.getElementById('crud_fun_nome') as HTMLInputElement).value.trim(),
                fun_sexo: (document.getElementById('crud_fun_sexo') as HTMLSelectElement).value,
                car_id: Number((document.getElementById('crud_fun_car_id') as HTMLSelectElement).value),
                tur_id_padrao: (document.getElementById('crud_fun_tur_id_padrao') as HTMLSelectElement).value
                    ? Number((document.getElementById('crud_fun_tur_id_padrao') as HTMLSelectElement).value)
                    : null,
                fun_perfil: (document.getElementById('crud_fun_perfil') as HTMLSelectElement).value,
                fun_status: (document.getElementById('crud_fun_status') as HTMLSelectElement).value,
                fun_data_admissao: (document.getElementById('crud_fun_data_admissao') as HTMLInputElement).value,
                fun_telefone: (document.getElementById('crud_fun_telefone') as HTMLInputElement).value.trim() || null,
                fun_email: (document.getElementById('crud_fun_email') as HTMLInputElement).value.trim() || null,
                fun_senha: (document.getElementById('crud_fun_senha') as HTMLInputElement).value || null,
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

    /**
     * CRUD Card 2: Cargos (Fase 4 - Atualização em Tempo Real)
     */
    private initCrudCargos(): void {
        const form = document.getElementById('form-crud-cargo') as HTMLFormElement | null;
        if (!form) return;

        const idInput = document.getElementById('crud_car_id') as HTMLInputElement;
        const tbody = document.getElementById('tbody-crud-cargos');
        const limpar = (): void => {
            form.reset();
            idInput.value = '';
        };
        document.getElementById('btn-limpar-cargo')?.addEventListener('click', limpar);

        tbody?.addEventListener('click', async (event: Event) => {
            const target = event.target as HTMLElement;
            const editBtn = target.closest('.btn-edit-cargo');
            if (editBtn) {
                const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                idInput.value = String(d.car_id || '');
                (document.getElementById('crud_car_nome') as HTMLInputElement).value = d.car_nome || '';
                (document.getElementById('crud_car_cbo') as HTMLInputElement).value = d.car_cbo || '';
                (document.getElementById('crud_car_descricao') as HTMLInputElement).value = d.car_descricao || '';
                (document.getElementById('crud_car_ativo') as HTMLSelectElement).value = String(d.car_ativo ?? 1);
                return;
            }

            const delBtn = target.closest('.btn-delete-cargo');
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

        form.addEventListener('submit', async (e: Event) => {
            e.preventDefault();
            const id = idInput.value;
            const payload = {
                car_nome: (document.getElementById('crud_car_nome') as HTMLInputElement).value.trim(),
                car_cbo: (document.getElementById('crud_car_cbo') as HTMLInputElement).value.trim() || null,
                car_descricao: (document.getElementById('crud_car_descricao') as HTMLInputElement).value.trim() || null,
                car_ativo: (document.getElementById('crud_car_ativo') as HTMLSelectElement).value === '1',
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

    /**
     * CRUD Card 3: Turnos e Horários (Fase 4 - Atualização em Tempo Real)
     */
    private initCrudTurnos(): void {
        const form = document.getElementById('form-crud-turno') as HTMLFormElement | null;
        if (!form) return;

        const idInput = document.getElementById('crud_tur_id') as HTMLInputElement;
        const tbody = document.getElementById('tbody-crud-turnos');
        const limpar = (): void => {
            form.reset();
            idInput.value = '';
        };
        document.getElementById('btn-limpar-turno')?.addEventListener('click', limpar);

        tbody?.addEventListener('click', async (event: Event) => {
            const target = event.target as HTMLElement;
            const editBtn = target.closest('.btn-edit-turno');
            if (editBtn) {
                const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                idInput.value = String(d.tur_id || '');
                (document.getElementById('crud_tur_descricao') as HTMLInputElement).value = d.tur_descricao || '';
                (document.getElementById('crud_tur_entrada') as HTMLInputElement).value = d.tur_entrada || '07:00';
                (document.getElementById('crud_tur_saida_intervalo') as HTMLInputElement).value = d.tur_saida_intervalo || '12:00';
                (document.getElementById('crud_tur_retorno_intervalo') as HTMLInputElement).value = d.tur_retorno_intervalo || '13:00';
                (document.getElementById('crud_tur_saida') as HTMLInputElement).value = d.tur_saida || '16:48';
                (document.getElementById('crud_tur_tolerancia') as HTMLInputElement).value = String(d.tur_tolerancia_minutos ?? 10);
                (document.getElementById('crud_tur_ativo') as HTMLSelectElement).value = String(d.tur_ativo ?? 1);
                return;
            }

            const delBtn = target.closest('.btn-delete-turno');
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

        form.addEventListener('submit', async (e: Event) => {
            e.preventDefault();
            const id = idInput.value;
            const payload = {
                tur_descricao: (document.getElementById('crud_tur_descricao') as HTMLInputElement).value.trim(),
                tur_entrada: (document.getElementById('crud_tur_entrada') as HTMLInputElement).value,
                tur_saida_intervalo: (document.getElementById('crud_tur_saida_intervalo') as HTMLInputElement).value,
                tur_retorno_intervalo: (document.getElementById('crud_tur_retorno_intervalo') as HTMLInputElement).value,
                tur_saida: (document.getElementById('crud_tur_saida') as HTMLInputElement).value,
                tur_tolerancia_minutos: Number((document.getElementById('crud_tur_tolerancia') as HTMLInputElement).value),
                tur_ativo: (document.getElementById('crud_tur_ativo') as HTMLSelectElement).value === '1',
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

    /**
     * CRUD Card 4: Calendário Operacional, Configurações 5x2 e Feriados (Fase 4 - Atualização em Tempo Real)
     */
    private initCrudCalendario(): void {
        // 1. Períodos
        const formPer = document.getElementById('form-crud-periodo') as HTMLFormElement | null;
        const tbodyPer = document.getElementById('tbody-crud-periodos');
        if (formPer) {
            const idPer = document.getElementById('crud_esp_id') as HTMLInputElement;
            const btnSalvarPer = document.getElementById('btn-salvar-periodo');
            const dtInicioPer = document.getElementById('crud_esp_data_inicio') as HTMLInputElement | null;
            const dtFimPer = document.getElementById('crud_esp_data_fim') as HTMLInputElement | null;

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

            const limparPer = (): void => {
                formPer.reset();
                idPer.value = '';
                dtFimPer?.removeAttribute('min');
                if (btnSalvarPer) {
                    btnSalvarPer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Salvar';
                }
            };
            document.getElementById('btn-limpar-periodo')?.addEventListener('click', limparPer);

            tbodyPer?.addEventListener('click', async (event: Event) => {
                const target = event.target as HTMLElement;
                const editBtn = target.closest('.btn-edit-periodo');
                if (editBtn) {
                    const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                    idPer.value = String(d.esp_id || '');
                    (document.getElementById('crud_esp_descricao') as HTMLInputElement).value = d.esp_descricao || '';
                    (document.getElementById('crud_esp_data_inicio') as HTMLInputElement).value = d.esp_data_inicio || '';
                    (document.getElementById('crud_esp_data_fim') as HTMLInputElement).value = d.esp_data_fim || '';
                    if (dtFimPer && d.esp_data_inicio) {
                        dtFimPer.setAttribute('min', d.esp_data_inicio);
                    }
                    (document.getElementById('crud_esp_status') as HTMLSelectElement).value = d.esp_status || 'rascunho';
                    if (btnSalvarPer) {
                        btnSalvarPer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Atualizar';
                    }
                    return;
                }

                const delBtn = target.closest('.btn-delete-periodo');
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

            formPer.addEventListener('submit', async (e: Event) => {
                e.preventDefault();
                const id = idPer.value;
                const payload = {
                    esp_descricao: (document.getElementById('crud_esp_descricao') as HTMLInputElement).value.trim(),
                    esp_data_inicio: (document.getElementById('crud_esp_data_inicio') as HTMLInputElement).value,
                    esp_data_fim: (document.getElementById('crud_esp_data_fim') as HTMLInputElement).value,
                    esp_status: (document.getElementById('crud_esp_status') as HTMLSelectElement).value,
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
        const formCfg = document.getElementById('form-crud-cfg5x2') as HTMLFormElement | null;
        const tbodyCfg = document.getElementById('tbody-crud-cfg5x2');
        if (formCfg) {
            const idCfg = document.getElementById('crud_cfg_id') as HTMLInputElement;
            const btnSalvarCfg = document.getElementById('btn-salvar-cfg5x2');
            const limparCfg = (): void => {
                formCfg.reset();
                idCfg.value = '';
                if (btnSalvarCfg) {
                    btnSalvarCfg.innerHTML = '<i class="bi bi-check-lg me-1"></i>Salvar 5x2';
                }
            };
            document.getElementById('btn-limpar-cfg5x2')?.addEventListener('click', limparCfg);

            tbodyCfg?.addEventListener('click', async (event: Event) => {
                const target = event.target as HTMLElement;
                const editBtn = target.closest('.btn-edit-cfg5x2');
                if (editBtn) {
                    const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                    idCfg.value = String(d.cfg_id || '');
                    (document.getElementById('crud_cfg_nome') as HTMLInputElement).value = d.cfg_nome || '';
                    (document.getElementById('crud_cfg_tipo') as HTMLSelectElement).value = d.cfg_tipo || 'fixa';
                    (document.getElementById('crud_cfg_dia_folga_1') as HTMLSelectElement).value = String(d.cfg_dia_folga_1 || 1);
                    (document.getElementById('crud_cfg_dia_folga_2') as HTMLSelectElement).value = String(d.cfg_dia_folga_2 || 7);
                    if (btnSalvarCfg) {
                        btnSalvarCfg.innerHTML = '<i class="bi bi-check-lg me-1"></i>Atualizar 5x2';
                    }
                    return;
                }

                const delBtn = target.closest('.btn-delete-cfg5x2');
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

            formCfg.addEventListener('submit', async (e: Event) => {
                e.preventDefault();
                const id = idCfg.value;
                const payload = {
                    cfg_nome: (document.getElementById('crud_cfg_nome') as HTMLInputElement).value.trim(),
                    cfg_tipo: (document.getElementById('crud_cfg_tipo') as HTMLSelectElement).value,
                    cfg_dia_folga_1: Number((document.getElementById('crud_cfg_dia_folga_1') as HTMLSelectElement).value),
                    cfg_dia_folga_2: Number((document.getElementById('crud_cfg_dia_folga_2') as HTMLSelectElement).value),
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
        const formFer = document.getElementById('form-crud-feriado') as HTMLFormElement | null;
        const tbodyFer = document.getElementById('tbody-crud-feriados');
        if (formFer) {
            const idFer = document.getElementById('crud_fer_id') as HTMLInputElement;
            const btnSalvarFer = document.getElementById('btn-salvar-feriado');
            const limparFer = (): void => {
                formFer.reset();
                idFer.value = '';
                if (btnSalvarFer) {
                    btnSalvarFer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Salvar';
                }
            };
            document.getElementById('btn-limpar-feriado')?.addEventListener('click', limparFer);

            tbodyFer?.addEventListener('click', async (event: Event) => {
                const target = event.target as HTMLElement;
                const editBtn = target.closest('.btn-edit-feriado');
                if (editBtn) {
                    const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                    idFer.value = String(d.fer_id || '');
                    (document.getElementById('crud_fer_data') as HTMLInputElement).value = d.fer_data || '';
                    (document.getElementById('crud_fer_descricao') as HTMLInputElement).value = d.fer_descricao || '';
                    (document.getElementById('crud_fer_tipo') as HTMLSelectElement).value = d.fer_tipo || 'nacional';
                    if (btnSalvarFer) {
                        btnSalvarFer.innerHTML = '<i class="bi bi-check-lg me-1"></i>Atualizar';
                    }
                    return;
                }

                const delBtn = target.closest('.btn-delete-feriado');
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

            formFer.addEventListener('submit', async (e: Event) => {
                e.preventDefault();
                const id = idFer.value;
                const payload = {
                    fer_data: (document.getElementById('crud_fer_data') as HTMLInputElement).value,
                    fer_descricao: (document.getElementById('crud_fer_descricao') as HTMLInputElement).value.trim(),
                    fer_tipo: (document.getElementById('crud_fer_tipo') as HTMLSelectElement).value,
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

    /**
     * CRUD Card 5: Afastamentos e Trocas Pontuais (Fase 4)
     */
    private initTrocasEAfastamentos(): void {
        const selSolicitante = document.getElementById('tro_fun_solicitante') as HTMLSelectElement | null;
        const selOrigem = document.getElementById('tro_esd_origem') as HTMLSelectElement | null;
        const selSubstituto = document.getElementById('tro_fun_substituto') as HTMLSelectElement | null;
        const selDestino = document.getElementById('tro_esd_destino') as HTMLSelectElement | null;

        const carregarDias = async (funId: string, selectTarget: HTMLSelectElement, isOptional: boolean): Promise<void> => {
            if (!funId) {
                selectTarget.innerHTML = `<option value="">${isOptional ? '-- Troca Simples / Cobertura --' : 'Selecione o colaborador'}</option>`;
                return;
            }
            const { ok, body } = await this.apiRequest<any[]>(`/api/trocas/dias-funcionario/${funId}`, 'GET');
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

        const formTroca = document.getElementById('form-solicitar-troca') as HTMLFormElement | null;
        formTroca?.addEventListener('submit', async (e: Event) => {
            e.preventDefault();
            const payload = {
                fun_id_solicitante: Number(selSolicitante?.value || 0),
                fun_id_substituto: Number(selSubstituto?.value || 0),
                esd_id_origem: Number(selOrigem?.value || 0),
                esd_id_destino: selDestino?.value ? Number(selDestino.value) : null,
                tro_motivo: (document.getElementById('tro_motivo') as HTMLInputElement).value.trim(),
            };

            const { ok, body } = await this.apiRequest('/api/trocas', 'POST', payload);
            this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
            if (ok) setTimeout(() => window.location.reload(), 700);
        });

        document.querySelectorAll<HTMLButtonElement>('.btn-aprovar-troca').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                if (!id) return;
                const { ok, body } = await this.apiRequest(`/api/trocas/${id}/aprovar`, 'POST');
                this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
                if (ok) setTimeout(() => window.location.reload(), 800);
            });
        });

        document.querySelectorAll<HTMLButtonElement>('.btn-recusar-troca').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                if (!id) return;
                const { ok, body } = await this.apiRequest(`/api/trocas/${id}/status`, 'POST', { tro_status: 'recusada' });
                this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
                if (ok) setTimeout(() => window.location.reload(), 700);
            });
        });

        const formAfa = document.getElementById('form-registrar-afastamento') as HTMLFormElement | null;
        formAfa?.addEventListener('submit', async (e: Event) => {
            e.preventDefault();
            const payload = {
                fun_id: Number((document.getElementById('afa_fun_id') as HTMLSelectElement).value),
                fun_status: (document.getElementById('afa_fun_status') as HTMLSelectElement).value,
                data_inicio: (document.getElementById('afa_data_inicio') as HTMLInputElement).value || null,
                data_fim: (document.getElementById('afa_data_fim') as HTMLInputElement).value || null,
                observacao: (document.getElementById('afa_observacao') as HTMLInputElement).value.trim() || null,
            };
            const { ok, body } = await this.apiRequest('/api/afastamentos', 'POST', payload);
            this.showAlert('alert-modal-trocas', ok ? 'success' : 'danger', body.message);
            if (ok) setTimeout(() => window.location.reload(), 800);
        });
    }

    /**
     * Gerador Automático de Escala 5x2 (Fase 5)
     */
    private initGeradorEscala5x2(): void {
        const selModo = document.getElementById('ger_modo') as HTMLSelectElement | null;
        const wrapFun = document.getElementById('wrap-ger-fun-id');
        const formGer = document.getElementById('form-gerar-escala-5x2') as HTMLFormElement | null;

        if (selModo && wrapFun) {
            selModo.addEventListener('change', () => {
                wrapFun.style.display = selModo.value === 'individual' ? 'block' : 'none';
            });
        }

        formGer?.addEventListener('submit', async (e: Event) => {
            e.preventDefault();
            const btn = document.getElementById('btn-executar-gerador') as HTMLButtonElement | null;
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando sp_gerar_escala_5x2_funcionario...';
            }

            const ferModoEl = document.getElementById('ger_feriado_modo') as HTMLSelectElement | null;
            const payload = {
                esp_id: Number((document.getElementById('ger_esp_id') as HTMLSelectElement).value),
                cfg_id: Number((document.getElementById('ger_cfg_id') as HTMLSelectElement).value),
                modo: (document.getElementById('ger_modo') as HTMLSelectElement).value,
                fun_id: Number((document.getElementById('ger_fun_id') as HTMLSelectElement).value),
                tur_id: (document.getElementById('ger_tur_id') as HTMLSelectElement).value
                    ? Number((document.getElementById('ger_tur_id') as HTMLSelectElement).value)
                    : null,
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

    /**
     * Edição Pontual de Dia na Escala com Validação da Trigger Interjornada 11h (Fase 5)
     */
    private initEdicaoDiaEscala(): void {
        const selTipo = document.getElementById('edit_esd_tipo_dia') as HTMLSelectElement | null;
        const wrapTurno = document.getElementById('wrap-edit-esd-turno');
        const formEdit = document.getElementById('form-editar-dia-escala') as HTMLFormElement | null;

        if (selTipo && wrapTurno) {
            selTipo.addEventListener('change', () => {
                wrapTurno.style.display = selTipo.value === 'trabalho' ? 'block' : 'none';
            });
        }

        const bindEditButtons = (): void => {
            document.querySelectorAll<HTMLButtonElement>('.btn-abrir-editar-dia').forEach((btn) => {
                btn.onclick = () => {
                    const esdId = btn.getAttribute('data-esd-id') || '';
                    const funNome = btn.getAttribute('data-fun-nome') || '';
                    const esdData = btn.getAttribute('data-esd-data') || '';
                    const esdTipo = btn.getAttribute('data-esd-tipo') || 'trabalho';
                    const turId = btn.getAttribute('data-tur-id') || '';

                    (document.getElementById('edit_esd_id') as HTMLInputElement).value = esdId;
                    const infoEl = document.getElementById('edit_esd_info_label');
                    if (infoEl) infoEl.textContent = `${funNome} • Data: ${esdData}`;

                    if (selTipo) {
                        selTipo.value = esdTipo;
                        if (wrapTurno) wrapTurno.style.display = esdTipo === 'trabalho' ? 'block' : 'none';
                    }

                    const selTur = document.getElementById('edit_esd_tur_id') as HTMLSelectElement | null;
                    if (selTur && turId) selTur.value = turId;

                    const alertEl = document.getElementById('alert-modal-editar-dia');
                    if (alertEl) alertEl.innerHTML = '';

                    const modalEl = document.getElementById('modalEditarDiaEscala');
                    if (modalEl && (window as any).bootstrap) {
                        const modal = (window as any).bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    }
                };
            });
        };

        bindEditButtons();

        formEdit?.addEventListener('submit', async (e: Event) => {
            e.preventDefault();
            const esdId = (document.getElementById('edit_esd_id') as HTMLInputElement).value;
            const tipoDia = (document.getElementById('edit_esd_tipo_dia') as HTMLSelectElement).value;
            const turId = (document.getElementById('edit_esd_tur_id') as HTMLSelectElement).value;
            const obs = (document.getElementById('edit_esd_observacao') as HTMLInputElement).value.trim();

            const { ok, body } = await this.apiRequest(`/api/escalas/diaria/${esdId}`, 'PUT', {
                esd_tipo_dia: tipoDia,
                tur_id: tipoDia === 'trabalho' ? Number(turId) : null,
                esd_observacao: obs || null,
            });

            this.showAlert('alert-modal-editar-dia', ok ? 'success' : 'danger', body.message);
            if (ok) setTimeout(() => window.location.reload(), 800);
        });
    }

    /**
     * Filtro Dinâmico do Dashboard do Dia (Fase 3 / 5)
     */
    private initDashboardDia(): void {
        const btn = document.getElementById('btn-filtrar-dia');
        const input = document.getElementById('input-data-dashboard') as HTMLInputElement | null;

        if (!btn || !input) return;

        const consultarDia = async (): Promise<void> => {
            if (!input.value) return;
            const url = new URL(window.location.href);
            url.searchParams.set('data_dia', input.value);
            window.location.href = url.toString();
        };

        btn.addEventListener('click', consultarDia);
        input.addEventListener('change', consultarDia);
    }

    private async refreshEmpresasTable(): Promise<void> {
        const tbody = document.getElementById('tbody-crud-empresas');
        if (!tbody) return;

        const chkInativas = document.getElementById('chk-mostrar-empresas-inativas') as HTMLInputElement | null;
        const incluirInativas = chkInativas?.checked ? '1' : '0';

        const { ok, body } = await this.apiRequest(`/api/empresas?incluir_inativas=${incluirInativas}`, 'GET');
        if (!ok || !Array.isArray(body.data)) return;

        const lista = body.data as Array<Record<string, any>>;
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

    /**
     * Módulo Empresa — CRUD em View dedicada e atualização em tempo real (03/11/2026)
     */
    private initCrudEmpresa(): void {
        const formView = document.getElementById('form-view-empresa') as HTMLFormElement | null;
        const tbodyView = document.getElementById('tbody-crud-empresas');

        if (formView && tbodyView) {
            const idInput = document.getElementById('view_emp_id') as HTMLInputElement;
            const tituloEl = document.getElementById('form-empresa-view-titulo');
            const chkInativas = document.getElementById('chk-mostrar-empresas-inativas') as HTMLInputElement | null;

            const limparFormView = (): void => {
                formView.reset();
                idInput.value = '';
                const cidadeEl = document.getElementById('view_emp_cidade') as HTMLInputElement | null;
                const ufEl = document.getElementById('view_emp_uf') as HTMLInputElement | null;
                if (cidadeEl && !cidadeEl.value) cidadeEl.value = 'Manaus';
                if (ufEl && !ufEl.value) ufEl.value = 'AM';
                if (tituloEl) tituloEl.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Nova Empresa';
            };

            document.getElementById('btn-limpar-view-empresa')?.addEventListener('click', limparFormView);
            chkInativas?.addEventListener('change', () => {
                this.refreshEmpresasTable();
            });

            tbodyView.addEventListener('click', async (event: Event) => {
                const target = event.target as HTMLElement;
                const editBtn = target.closest('.btn-edit-view-empresa');
                if (editBtn) {
                    const d = JSON.parse(editBtn.getAttribute('data-json') || '{}');
                    idInput.value = String(d.emp_id || '');
                    (document.getElementById('view_emp_nome_fantasia') as HTMLInputElement).value = d.emp_nome_fantasia || '';
                    (document.getElementById('view_emp_razao_social') as HTMLInputElement).value = d.emp_razao_social || '';
                    (document.getElementById('view_emp_cnpj') as HTMLInputElement).value = d.emp_cnpj || '';
                    (document.getElementById('view_emp_email') as HTMLInputElement).value = d.emp_email || '';
                    (document.getElementById('view_emp_celular') as HTMLInputElement).value = d.emp_celular || '';
                    (document.getElementById('view_emp_logradouro') as HTMLInputElement).value = d.emp_logradouro || '';
                    (document.getElementById('view_emp_numero') as HTMLInputElement).value = d.emp_numero || '';
                    (document.getElementById('view_emp_bairro') as HTMLInputElement).value = d.emp_bairro || '';
                    (document.getElementById('view_emp_cidade') as HTMLInputElement).value = d.emp_cidade || '';
                    (document.getElementById('view_emp_uf') as HTMLInputElement).value = d.emp_uf || '';
                    if (tituloEl) tituloEl.innerHTML = `<i class="bi bi-pencil-square me-1"></i>Editando: ${d.emp_nome_fantasia}`;
                    return;
                }

                const statusBtn = target.closest('.btn-deactivate-view-empresa');
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

            formView.addEventListener('submit', async (e: Event) => {
                e.preventDefault();
                const id = idInput.value;
                const payload = {
                    emp_nome_fantasia: (document.getElementById('view_emp_nome_fantasia') as HTMLInputElement).value.trim(),
                    emp_razao_social: (document.getElementById('view_emp_razao_social') as HTMLInputElement).value.trim() || null,
                    emp_cnpj: (document.getElementById('view_emp_cnpj') as HTMLInputElement).value.replace(/\D+/g, ''),
                    emp_email: (document.getElementById('view_emp_email') as HTMLInputElement).value.trim(),
                    emp_celular: (document.getElementById('view_emp_celular') as HTMLInputElement).value.trim() || null,
                    emp_logradouro: (document.getElementById('view_emp_logradouro') as HTMLInputElement).value.trim() || null,
                    emp_numero: (document.getElementById('view_emp_numero') as HTMLInputElement).value.trim() || null,
                    emp_bairro: (document.getElementById('view_emp_bairro') as HTMLInputElement).value.trim() || null,
                    emp_cidade: (document.getElementById('view_emp_cidade') as HTMLInputElement).value.trim() || null,
                    emp_uf: (document.getElementById('view_emp_uf') as HTMLInputElement).value.trim().toUpperCase() || null,
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
    }
}

new FarmaVivaApp();
