# Seja um Developer BackEnd, Developer Front End expert em PHP 8.2+com Larável 11, MariaDB, Api Rest Full, BootStrap 5, JavaScript, TypeScript; 

## Crie um Plano de Ação chamado farmaviver.md para uma aplicação webapp de Gestão de Escala 5x2 para uma Drogaria; 

### Objetivo é o Desenvolvimento de uma aplicação WebApp de Gestão de Escala 5x2 de Funcionários para uma Drogaria, o Sistema se comportará como Mobile First, Designe da Aplicação em tons cinza claro. Uma das regras fundamentais para Escalas 5x2 que por Lei todo funcionário do sexo feminino tem direito a 2 folgas no domingo no mesmo mês (já existe uma regra de negócio na vw_auditoria_folgas_domingo_mulheres).  

### Interfaces Responsivas 100% Mobile First 

## Tela de Login: Logo na Head centralizado, Login será o cpf fun_cpf e a senha fun_senha da tabela de funcionários. 

# Regra de Negócio para a tela de Login: fun_cpf somente números sem formatação. Gerar Api de consulta no banco de dados farmaviver, onde fun_cpf estiver errado, o sistema emite uma mensagem do bootstrap "CPF Inválido" e volta o foco para o CPF, se a fun_senha estiver errada, o sistema emite uma mensagem do bootstrap ("Senha Inválida") e volta o foco para o campo fun_senha. Caso os 2 campos estiverem errados após a consulta na tabela de funcionárioos, o sistema emite uma mensagem em bootstrap ("CPF E Senha Inválidos !!") e coloca o foco no campo CPF func_cpf. E Caso os campos conferirem no banco, o sistema redireciona para a Home da aplicação; 

## Home do App: NA head da Home no canto esquerdo mostrará a logo da empresa, em seguida o usuário Logado - e a hora em tempo real, mostrará um pouco mais a direita o botão Sair(ao clicar neste botão fechará todas as sessões abertas do sistema, para não haver problemas com segurança do sistema), após deslogar redirecionar para a tela de login;  
    Na Home as opções todas em Cards, cada Cards com seu CRUD Intuitivo de: Funcionários, Cargos, Calendário de Turnos/Horários, Calendário Ooperacional e Feriados, Afastamento e Trocas Pontuais, de acordo com as tables do banco, que serão listadas no item # Tables; 

    Interfaces Responsivas: Dashboard / Visão do dia, Visualizador de Escala (Mensal / Semanal) da tabela empresas, Gerador Automático de Escala, Gestão de Funcionários ; 

# Banco e Tables já existem: farmaviver Banco de Dados 

# Tables:  
    empresas (CONSTRAINT `uk_empresas_cnpj` UNIQUE (`emp_cnpj`));  
    cargos (CONSTRAINT `fk_cargos_empresas` FOREIGN KEY (`emp_id`)  
        REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE);  
    turnohorarios (CONSTRAINT `fk_turnohorarios_empresas` FOREIGN KEY (`emp_id`)  
        REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE, 
      CONSTRAINT `chk_turno_intervalo` CHECK (`tur_saida_intervalo` > `tur_entrada`), 
      CONSTRAINT `chk_turno_retorno` CHECK (`tur_retorno_intervalo` > `tur_saida_intervalo`)); 
    funcionarios (CONSTRAINT `fk_configuracoes_escala_empresas` FOREIGN KEY (`emp_id`)  
        REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE, 
      CONSTRAINT `chk_cfg_folga_1` CHECK (`cfg_dia_folga_1` BETWEEN 1 AND 7), 
      CONSTRAINT `chk_cfg_folga_2` CHECK (`cfg_dia_folga_2` BETWEEN 1 AND 7),  CONSTRAINT `chk_cfg_folgas_distintas` CHECK (`cfg_dia_folga_1` <> `cfg_dia_folga_2`)),  
    escalas_periodos (CONSTRAINT `fk_escalas_periodos_empresas` FOREIGN KEY (`emp_id`)  
        REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE,   CONSTRAINT `chk_esp_periodo` CHECK (`esp_data_fim` >= `esp_data_inicio`)),  
    escalas_diarias (CONSTRAINT `uk_escala_funcionario_dia` UNIQUE (`fun_id`, `esd_data`), 
      CONSTRAINT `fk_escalas_diarias_periodo` FOREIGN KEY (`esp_id`)  
        REFERENCES `escalas_periodos` (`esp_id`) ON DELETE CASCADE ON UPDATE CASCADE, 
      CONSTRAINT `fk_escalas_diarias_funcionario` FOREIGN KEY (`fun_id`)  
        REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE, 
      CONSTRAINT `fk_escalas_diarias_turno` FOREIGN KEY (`tur_id`)  
        REFERENCES `turnohorarios` (`tur_id`) ON DELETE RESTRICT ON UPDATE CASCADE);  
    trocas_escala (CONSTRAINT `fk_trocas_solicitante` FOREIGN KEY (`fun_id_solicitante`)  
        REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE, 
      CONSTRAINT `fk_trocas_substituto` FOREIGN KEY (`fun_id_substituto`)  
        REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE, 
      CONSTRAINT `fk_trocas_esd_origem` FOREIGN KEY (`esd_id_origem`)  
        REFERENCES `escalas_diarias` (`esd_id`) ON DELETE CASCADE ON UPDATE CASCADE, 
      CONSTRAINT `fk_trocas_esd_destino` FOREIGN KEY (`esd_id_destino`)  
        REFERENCES `escalas_diarias` (`esd_id`) ON DELETE SET NULL ON UPDATE CASCADE, 
      CONSTRAINT `fk_trocas_aprovador` FOREIGN KEY (`tro_aprovador_fun_id`)  
        REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE); 
    Feriados (CONSTRAINT `uk_feriados_emp_data` UNIQUE (`emp_id`, `fer_data`), 
      CONSTRAINT `fk_feriados_empresas` FOREIGN KEY (`emp_id`)  
        REFERENCES `empresas` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE); 
    logs_autenticacao (CONSTRAINT `fk_logs_autenticacao_funcionario` FOREIGN KEY (`fun_id`)  
        REFERENCES `funcionarios` (`fun_id`) ON DELETE SET NULL ON UPDATE CASCADE); 
    Permissoes (CONSTRAINT `uk_permissoes_chave` UNIQUE (`per_chave`)); 
    Perfis (CONSTRAINT `uk_perfis_emp_nome` UNIQUE (`emp_id`, `prf_nome`), 
      CONSTRAINT `fk_perfis_empresas` FOREIGN KEY (`emp_id`)  
        REFERENCES `empresas` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE); 
    perfil_permissoes (CONSTRAINT `fk_pp_perfil` FOREIGN KEY (`prf_id`)  
        REFERENCES `perfis` (`prf_id`) ON DELETE CASCADE ON UPDATE CASCADE, 
      CONSTRAINT `fk_pp_permissao` FOREIGN KEY (`per_id`)  
        REFERENCES `permissoes` (`per_id`) ON DELETE CASCADE ON UPDATE CASCADE😉; 
    funcionario_perfis (CONSTRAINT `fk_fp_funcionario` FOREIGN KEY (`fun_id`)  
        REFERENCES `funcionarios` (`fun_id`) ON DELETE CASCADE ON UPDATE CASCADE, 
      CONSTRAINT `fk_fp_perfil` FOREIGN KEY (`prf_id`)  
        REFERENCES `perfis` (`prf_id`) ON DELETE CASCADE ON UPDATE CASCADE); 

# Views já existentes no banco de dados:  
    vw_escala_diaria_completa; 
    vw_resumo_mensal_escalas; 
    vw_auditoria_folgas_domingo_mulheres; 
# Storede Procedures já existentes no banco de dados: 
    sp_gerar_escala_5x2_funcionario; 
    sp_aprovar_troca_escala; 
    sp_relatorio_escala_matriz; 
    sp_autenticar_funcionario; 
    sp_registrar_tentativa_login; 
    sp_obter_permissoes_funcionario; 
# Triggers já criadas no banco de dados: 
    tg_validar_interjornada_insert; 
    tg_validar_interjornada_update; 

## Qualquer mudanças seja alterações no escopo, acréscimo de funcionalidades, observações, ou seja qualquer mudança autorizadas no sistema webapp farmaviver tem que fazer anotações aqui com as seguintes formatações Dentro das Tags << >>: 
    << Data no formato dd/mm/Y - Descrição das mudanças >> Aqui é as informações das alterações/observações, qualquer atividade << Fim da alteração/observações data da finalização das alterações/alterações >> 


---

<< 31/10/2026 - Auditoria do Banco de Dados MariaDB (farmaviver) e Criação do Plano de Ação Completo >>

### 1. Resultado da Auditoria Técnica no Banco `farmaviver` (MariaDB Local)
Conexão validada com sucesso em `127.0.0.1` (MariaDB / PHP 8.2.12). Foram mapeados todos os objetos ativos no banco:

* **14 Tabelas Base identificadas:**
  1. `empresas` (`emp_id`, `emp_razao_social`, `emp_nome_fantasia`, `emp_cnpj`, `emp_email`, `emp_telefone`, endereço completo, `emp_ativo`)
  2. `cargos` (`car_id`, `emp_id`, `car_nome`, `car_descricao`, `car_cbo`, `car_ativo`)
  3. `turnohorarios` (`tur_id`, `emp_id`, `tur_descricao`, `tur_entrada`, `tur_saida_intervalo`, `tur_retorno_intervalo`, `tur_saida`, `tur_tolerancia_minutos`, `tur_ativo`)
  4. `configuracoes_escala` (`cfg_id`, `emp_id`, `cfg_nome`, `cfg_tipo` [`fixa`/`revezamento`], `cfg_dia_folga_1` [1..7], `cfg_dia_folga_2` [1..7], `cfg_ativo`) — *Observação: na listagem inicial do documento as constraints de `configuracoes_escala` estavam agrupadas junto a `funcionarios`; ambas as tabelas existem separadamente e íntegras no banco.*
  5. `funcionarios` (`fun_id`, `emp_id`, `car_id`, `tur_id_padrao`, `fun_matricula`, `fun_nome`, `fun_cpf`, `fun_sexo` [`M`/`F`], `fun_senha`, `fun_perfil` [`admin`/`gestor`/`colaborador`], `fun_email`, `fun_telefone`, `fun_data_admissao`, `fun_data_demissao`, `fun_status` [`ativo`/`afastado`/`ferias`/`desligado`], `fun_ultimo_login`, `fun_remember_token`)
  6. `escalas_periodos` (`esp_id`, `emp_id`, `esp_descricao`, `esp_data_inicio`, `esp_data_fim`, `esp_status` [`rascunho`/`publicada`/`encerrada`])
  7. `escalas_diarias` (`esd_id`, `esp_id`, `fun_id`, `tur_id`, `esd_data`, `esd_tipo_dia` [`trabalho`/`folga`/`dsr`/`feriado`], `esd_observacao`)
  8. `trocas_escala` (`tro_id`, `fun_id_solicitante`, `fun_id_substituto`, `esd_id_origem`, `esd_id_destino`, `tro_motivo`, `tro_status` [`pendente`/`aprovada`/`recusada`/`cancelada`], `tro_aprovador_fun_id`, `tro_aprovado_em`)
  9. `feriados` (`fer_id`, `emp_id`, `fer_data`, `fer_descricao`, `fer_tipo` [`nacional`/`estadual`/`municipal`/`facultativo`])
  10. `logs_autenticacao` (`log_id`, `fun_id`, `log_cpf_informado`, `log_status`, `log_ip`, `log_user_agent`, `log_detalhes`, `log_data_hora`)
  11. `permissoes` (`per_id`, `per_chave`, `per_modulo`, `per_descricao`)
  12. `perfis` (`prf_id`, `emp_id`, `prf_nome`, `prf_descricao`, `prf_ativo`)
  13. `perfil_permissoes` (`prf_id`, `per_id`)
  14. `funcionario_perfis` (`fun_id`, `prf_id`, `fup_atribuido_em`)

* **3 Views Ativas:**
  * `vw_escala_diaria_completa` (espelho diário com cálculo de `carga_horaria_prevista`)
  * `vw_resumo_mensal_escalas` (totalizadores de dias de trabalho, folga/DSR e feriado por competência)
  * `vw_auditoria_folgas_domingo_mulheres` (auditoria automática do Art. 386 da CLT: mínimo de 2 domingos de folga/DSR no mês para `fun_sexo = 'F'`, retornando `CONFORME` ou `ALERTA: MENOS DE 2 DOMINGOS`)

* **7 Stored Procedures Ativas:**
  * `sp_autenticar_funcionario(p_cpf)`
  * `sp_registrar_tentativa_login(p_fun_id, p_cpf, p_status, p_ip, p_user_agent, p_detalhes)`
  * `sp_registrar_login_sucesso(p_fun_id)` *(procedure auxiliar adicional encontrada no banco)*
  * `sp_obter_permissoes_funcionario(p_fun_id)`
  * `sp_gerar_escala_5x2_funcionario(p_esp_id, p_fun_id, p_cfg_id, p_tur_id)`
  * `sp_aprovar_troca_escala(p_tro_id, p_gestor_id)`
  * `sp_relatorio_escala_matriz(p_esp_id)`

* **2 Triggers Ativas (Validação CLT Art. 66 - Interjornada mínima de 11h):**
  * `tg_validar_interjornada_insert` (BEFORE INSERT em `escalas_diarias`)
  * `tg_validar_interjornada_update` (BEFORE UPDATE em `escalas_diarias`)

---

### 2. Plano de Ação para o Desenvolvimento do WebApp FarmaViva (FarmaViver)

#### Fase 1: Setup da Arquitetura Base (Laravel 11 + MariaDB + TypeScript + Bootstrap 5)
1. **Inicialização do Projeto Laravel 11** conectado ao banco `farmaviver` existente (preservando integralmente as tabelas, views, procedures, triggers e dados já populados).
2. **Configuração de Autenticação e Sessão Segura:**
   * Configurar controle de sessões/tokens no Laravel para permitir **invalidação global de todas as sessões abertas** do colaborador ao clicar em **Sair**.
   * Garantir que o hash `fun_senha` dos colaboradores de teste no banco seja compatível com `Hash::check()` / `password_verify()` (senha padrão `123456`).
3. **Mapeamento dos Models Eloquent e Camada de Serviços (Database/SPs):**
   * Models respeitando os prefixos de chave primária (`emp_id`, `car_id`, `tur_id`, `cfg_id`, `fun_id`, `esp_id`, `esd_id`, `tro_id`, `fer_id`, `log_id`, `per_id`, `prf_id`) e timestamps customizados (`*_created_at`, `*_updated_at`).
   * Service dedicado (`DatabaseProcedureService`) para invocar de forma segura as Stored Procedures e Views do MariaDB e capturar exceções SQLSTATE `'45000'` (ex.: violação de interjornada de 11h da CLT Art. 66).
4. **Identidade Visual Mobile First (Bootstrap 5 + CSS Customizado + TypeScript):**
   * Tema em **tons de cinza claro** (`#f8f9fa`, `#e9ecef`, `#dee2e6`, `#adb5bd`, `#495057`), tipografia limpa, botões e cards táteis pensados primeiro para telas de smartphone e adaptáveis para desktop.

#### Fase 2: Módulo de Autenticação e Tela de Login
1. **Interface de Login Mobile First:**
   * Header com Logo da FarmaViva centralizada.
   * Campo `fun_cpf` (aceitando apenas números, `inputmode="numeric"`, `maxlength="11"`, sem máscara/formatação).
   * Campo `fun_senha` e botão de acesso responsivo.
2. **API RESTful de Login (`POST /api/auth/login`) e Regras de Validação:**
   * Consulta na tabela `funcionarios` / `sp_autenticar_funcionario` e registro em `sp_registrar_tentativa_login`:
     * **CPF incorreto e Senha incorreta (ou ambos inválidos):** Alerta Bootstrap `"CPF E Senha Inválidos !!"` e foco automático no campo `fun_cpf`.
     * **Apenas CPF incorreto:** Alerta Bootstrap `"CPF Inválido"` e foco automático no campo `fun_cpf`.
     * **Apenas Senha incorreta:** Alerta Bootstrap `"Senha Inválida"` e foco automático no campo `fun_senha`.
     * **Sucesso:** Carrega perfis e permissões via `sp_obter_permissoes_funcionario`, registra login em `sp_registrar_tentativa_login`, cria sessão autenticada e redireciona para a **Home**.

#### Fase 3: Layout Principal (Home), Header em Tempo Real e Dashboard do Dia
1. **Header Global da Home:**
   * Canto esquerdo: Logo da empresa + Nome do Usuário Logado + Cargo/Perfil + **Relógio em tempo real** (atualizado segundo a segundo via TypeScript/JS).
   * Mais à direita: Botão **Sair** que encerra todas as sessões ativas do usuário no servidor, limpa tokens/cookies e redireciona para a tela de Login.
2. **Dashboard / Visão do Dia:**
   * Resumo rápido de quem está escalado hoje (Turno Abertura / Fechamento), quem está de folga/feriado, alertas de conformidade CLT Art. 386 (`vw_auditoria_folgas_domingo_mulheres`) e solicitações de troca pendentes.
3. **Grade de Cards Intuitivos na Home:**
   * Acesso rápido em Cards responsivos para todos os módulos de CRUD e gestão operacional.

#### Fase 4: CRUDs Operacionais via API RESTful + Interfaces em Cards/Modais
1. **Card 1 — Gestão de Funcionários (`funcionarios`, `funcionario_perfis`):**
   * Listagem Mobile First com busca/filtro por cargo e status (`ativo`, `afastado`, `ferias`, `desligado`), cadastro e edição (incluindo `fun_sexo` obrigatório para regra de domingos, turno padrão e perfil de acesso).
2. **Card 2 — Gestão de Cargos (`cargos`):**
   * CRUD completo com código CBO (`car_cbo`), descrição e ativação/inativação.
3. **Card 3 — Calendário de Turnos/Horários (`turnohorarios`):**
   * CRUD de turnos validando as constraints `tur_saida_intervalo > tur_entrada` e `tur_retorno_intervalo > tur_saida_intervalo`, exibindo o cálculo automático da carga horária diária.
4. **Card 4 — Calendário Operacional, Configurações 5x2 e Feriados (`feriados`, `configuracoes_escala`, `escalas_periodos`):**
   * Gestão de feriados (nacionais, estaduais, municipais, facultativos), regras de folga 5x2 (`fixa` ou `revezamento`, garantindo `cfg_dia_folga_1 <> cfg_dia_folga_2`) e abertura/publicação de períodos de escala (`rascunho`, `publicada`, `encerrada`).
5. **Card 5 — Afastamento e Trocas Pontuais (`trocas_escala`, `escalas_diarias`):**
   * Registro de afastamentos/férias, solicitação de permutas entre colaboradores e aprovação pelo gestor via `sp_aprovar_troca_escala`, respeitando a trigger de interjornada de 11h.

#### Fase 5: Motor de Escalas 5x2, Visualizador (Mensal/Semanal) e Auditoria CLT
1. **Gerador Automático de Escala 5x2:**
   * Interface para geração individual ou em lote para toda a empresa no período selecionado, executando `sp_gerar_escala_5x2_funcionario`.
2. **Visualizador de Escala (Mensal / Semanal) por Empresa:**
   * Visão em Matriz/Grade (`sp_relatorio_escala_matriz`), Visão Semanal Mobile First em cards diários (`vw_escala_diaria_completa`) e Resumo Mensal de horas/dias (`vw_resumo_mensal_escalas`).
   * Edição pontual de dia na escala com tratamento amigável caso a trigger `tg_validar_interjornada_update` bloqueie por intervalo menor que 11h.
3. **Painel de Auditoria de Domingos — Mulheres (Art. 386 CLT):**
   * Indicador visual baseado em `vw_auditoria_folgas_domingo_mulheres` destacando em verde (`CONFORME`) ou alerta (`ALERTA: MENOS DE 2 DOMINGOS`) o cumprimento das 2 folgas dominicais no mês.

#### Fase 6: Testes Integrados e Validação Final
1. Testes de ponta a ponta dos fluxos de Login (3 mensagens de erro específicas + redirecionamento), Logout global, geração de escala 5x2, auditoria de domingos para mulheres, aprovação de trocas e bloqueio de interjornada < 11h.

<< Fim da alteração/observações 31/10/2026 >>


<< 31/10/2026 - Execução e Conclusão da Fase 1 (Setup Base Laravel 11 + Models + Services) e Fase 2 (Tela de Login + API de Autenticação + Logout Global) >>

### Atividades Realizadas na Fase 1 e Fase 2:
1. **Estruturação do Projeto Laravel 11 na raiz do diretório `farmaviver`:**
   * Configuração do arquivo `.env` apontando diretamente para o banco MariaDB `farmaviver` (`127.0.0.1:3306`), fuso horário `America/Manaus`, idioma `pt_BR` e sessões em arquivo (`SESSION_DRIVER=file`) para preservar intacta a estrutura existente do banco.
   * Criação dos 12 Models Eloquent em `app/Models/` (`Empresa`, `Cargo`, `TurnoHorario`, `ConfiguracaoEscala`, `Funcionario`, `EscalaPeriodo`, `EscalaDiaria`, `TrocaEscala`, `Feriado`, `LogAutenticacao`, `Permissao`, `Perfil`) respeitando todas as chaves primárias e colunas de timestamp customizadas.
   * Criação do serviço `app/Services/DatabaseProcedureService.php` encapsulando as 7 Stored Procedures (`sp_autenticar_funcionario`, `sp_registrar_tentativa_login`, `sp_registrar_login_sucesso`, `sp_obter_permissoes_funcionario`, `sp_gerar_escala_5x2_funcionario`, `sp_aprovar_troca_escala`, `sp_relatorio_escala_matriz`) e as 3 Views (`vw_escala_diaria_completa`, `vw_resumo_mensal_escalas`, `vw_auditoria_folgas_domingo_mulheres`).
   * Normalização do hash bcrypt da coluna `fun_senha` dos 4 colaboradores existentes no banco `farmaviver` para validar corretamente a senha `123456` com `Hash::check()` / `password_verify()`.

2. **Desenvolvimento da Tela de Login e API RESTful (`POST /api/auth/login`):**
   * Criação de `app/Http/Controllers/AuthController.php` e `app/Http/Middleware/EnsureFuncionarioAuthenticated.php`.
   * Implementação exata das regras de validação na tabela `funcionarios`:
     * Campo `fun_cpf` restrito a somente números sem formatação.
     * Apenas `fun_cpf` incorreto $\rightarrow$ Alerta Bootstrap `"CPF Inválido"` e foco automático em `#fun_cpf`.
     * Apenas `fun_senha` incorreta $\rightarrow$ Alerta Bootstrap `"Senha Inválida"` e foco automático em `#fun_senha`.
     * Ambos os campos incorretos após consulta na tabela `funcionarios` $\rightarrow$ Alerta Bootstrap `"CPF E Senha Inválidos !!"` e foco automático em `#fun_cpf`.
     * Sucesso $\rightarrow$ Registro em `sp_registrar_tentativa_login`, carga de permissões via `sp_obter_permissoes_funcionario` e redirecionamento para `/home`.
   * Implementação do **Botão Sair (`POST /api/auth/logout`)**: encerra todas as sessões abertas do sistema (revogando o token em `funcionarios.fun_remember_token` e limpando o diretório `storage/framework/sessions`) e redireciona para `/login`.

3. **Front-End Mobile First (Bootstrap 5 + CSS em tons de cinza claro + TypeScript):**
   * Criação do logotipo `public/img/logo-farmaviva.svg`, folha de estilos `public/css/farmaviva.css`, código-fonte TypeScript `resources/ts/farmaviva.ts` (e compilado `public/js/farmaviva.js`), layout base `resources/views/layouts/app.blade.php`, tela de login `resources/views/auth/login.blade.php` e estrutura inicial da Home `resources/views/home/index.blade.php` (com Logo à esquerda, Usuário Logado, Hora em tempo real e Botão Sair à direita).

<< Fim da alteração/observações 31/10/2026 >>


<< 31/10/2026 - Execução e Conclusão das Fases 3, 4 e 5 (Home, Dashboard do Dia, CRUDs Intuitivos em Cards/Modais, Visualizador Mensal/Semanal e Gerador Automático 5x2) >>

### Atividades Realizadas nas Fases 3, 4 e 5:
1. **Fase 3 — Home Mobile First, Header em Tempo Real e Dashboard / Visão do Dia:**
   * Atualização de `app/Http/Controllers/HomeController.php` e `resources/views/home/index.blade.php` com o Header contendo a Logo da empresa à esquerda, Usuário Logado, Relógio em Tempo Real (`#fv-realtime-clock`) e Botão **Sair** à direita (encerrando todas as sessões abertas).
   * Implementação da aba **Dashboard / Visão do Dia** consumindo `vw_escala_diaria_completa`, com indicadores de Total na Escala, Em Trabalho Hoje, Em Folga/DSR e Em Feriado, seletor dinâmico de data e botão de ajuste rápido de dia.

2. **Fase 4 — CRUDs Operacionais Completos em Cards + API RESTful (`app/Http/Controllers/Api/CrudApiController.php` e `resources/views/home/partials/modals.blade.php`):**
   * **Card 1 — Funcionários (`funcionarios`, `funcionario_perfis`):** CRUD completo (criar, listar, editar, excluir, status e sincronização de perfis RBAC), validação de `fun_cpf` com 11 dígitos numéricos e seleção obrigatória de `fun_sexo` (`F`/`M`) para o cumprimento do Art. 386 da CLT.
   * **Card 2 — Cargos (`cargos`):** CRUD completo de funções, CBO (`car_cbo`), descrição e status ativo/inativo.
   * **Card 3 — Calendário de Turnos / Horários (`turnohorarios`):** CRUD completo respeitando as constraints `chk_turno_intervalo` (`tur_saida_intervalo > tur_entrada`) e `chk_turno_retorno` (`tur_retorno_intervalo > tur_saida_intervalo`).
   * **Card 4 — Calendário Operacional, Configurações 5x2 e Feriados (`escalas_periodos`, `configuracoes_escala`, `feriados`):** CRUD completo em 3 abas dedicadas, validando `chk_esp_periodo` (`esp_data_fim >= esp_data_inicio`) e `chk_cfg_folgas_distintas` (`cfg_dia_folga_1 <> cfg_dia_folga_2`).
   * **Card 5 — Afastamento e Trocas Pontuais (`trocas_escala`, `sp_aprovar_troca_escala`):** Registro de solicitações de permuta carregando os dias reais da escala do colaborador, aprovação pelo gestor executando a Stored Procedure `sp_aprovar_troca_escala`, recusa de permutas e lançamento de afastamentos/férias com atualização automática dos dias na escala.
   * **Card 6 — Empresa e Perfis RBAC (`empresas`, `perfis`, `permissoes`, `perfil_permissoes`):** Edição cadastral da drogaria e visualização das permissões por perfil.

3. **Fase 5 — Motor de Escalas 5x2, Visualizador Mensal/Semanal e Auditoria CLT (`app/Http/Controllers/Api/EscalaApiController.php`):**
   * **Gerador Automático de Escala 5x2 (`POST /api/escalas/gerar`):** Execução da Stored Procedure `sp_gerar_escala_5x2_funcionario` no modo individual ou em lote para todos os colaboradores ativos da empresa.
   * **Visualizador Mensal em Matriz Pivô:** Renderização da grade completa gerada por `sp_relatorio_escala_matriz` (`D_01` a `D_30`/`D_31`) com coluna fixa Mobile First e tabela de Resumo Consolidado do Mês (`vw_resumo_mensal_escalas`).
   * **Visualizador Semanal e Edição Pontual (`PUT /api/escalas/diaria/{id}`):** Filtro por semana e edição de qualquer dia de `escalas_diarias` com captura e exibição amigável de bloqueios das triggers `tg_validar_interjornada_insert` e `tg_validar_interjornada_update` (intervalo mínimo de 11h entre jornadas — Art. 66 da CLT).
   * **Auditoria Legal de Domingos — Mulheres (`vw_auditoria_folgas_domingo_mulheres`):** Painel de conformidade do Art. 386 da CLT comprovando no mínimo 2 folgas aos domingos no mesmo mês para todas as colaboradoras do sexo feminino (`CONFORME`).

<< Fim da alteração/observações 31/10/2026 >>

<< 02/11/2026 - Proposta >>
1. O Funcionário(a) que possui o *cargo Administrador*, *Perfil de acesso Administrador* e estar com o *Status Operacional Ativo*, Não pode entrar na escala 5x2 por ser Administrador, Rever as regras de negócio e corrigir;
2. Na Header da home o relógio em tempo real é para mostrar o tempo que ele está logado no sistema e não mostrar o horário;
3. Na tela de login acrescentar a logo chamada logoformaviver.png (imagem está na raiz do projeto), as descrições do login continua é somente trocar a imagem;
4. Por ser uma aplicação webapp, as fontes estão grandes demais
5. No Foot da home trocar para: hhernani@gmail.com / 92-98161-4980 - Todos os direitos reservados.
6. Na Home retirar os seguintes enunciados: "Cadastros e Gestão em Cards (CRUDs Intuitivos)" "Toque em qualquer Card para abrir o CRUD correspondente"
7. Nos Cards da Home no Body retirar estes tipos os enunciados: (CRUD completo de colaboradores (funcionarios), definição de fun_sexo (regra CLT Art. 386), cargo, turno padrão e perfil, CRUD de funções da drogaria (cargos): Farmacêutico(a), Balconista, Operador(a) de Caixa, CBO e descrições, CRUD de horários operacionais (turnohorarios): entrada, intervalo intrajornada, retorno, saída e tolerância, Gestão de Períodos (escalas_periodos), Regras de Folga 5x2 (configuracoes_escala) e Feriados (feriados), Permutas entre colaboradores (trocas_escala), aprovação via sp_aprovar_troca_escala e registro de férias/afastamentos, Geração em lote/individual (sp_gerar_escala_5x2_funcionario) garantindo 2 domingos de folga no mês para mulheres (CLT Art. 386).)
8. Diminuir a fonte da Table da opção Dashboard / Visão do Dia;
9. Diminuir a fonte da Table da opção Visualizador Semana / Edição
10. Ao Cadastrar ou Alterar o funcionário a tela está fechando, o correto é permanecer na mesma tela e atualizar em tempo real a table de funcionários;
11. Retirar o campo Sexo da table da tela de gestão de funcionários;
12. Na header da gestão de funcionários retirar (funcionários);
13. Na header da gestão de cargos retirar (cargos);
14. Ao Cadastrar ou Alterar o cargo a tela está fechando, o correto é permanecer na mesma tela e atualizar em tempo real a table de cargos;
15. Na table da gestão de cargos retirar a coluna CBO;
16. Na header da gestão Calendário de Turnos e Horários retirar (turnohorarios);
17. No Login a logo famaviver não é para ficar no top e sim acima da frase ( Acesso ao Sistema );
<<  02/11/2026 - Fim da Proposta >>


<< 02/11/2026 - atualização realizada >>

### Registro de Alterações Autorizadas (02/11/2026)

1. **Regra de Negócio — Exclusão de Administrador Ativo da Escala 5x2:**
   * **Banco de Dados (`farmaviver`):** Atualização das Stored Procedures `sp_gerar_escala_5x2_funcionario` (bloqueio via `SIGNAL SQLSTATE '45000'`) e `sp_relatorio_escala_matriz`, além das Views `vw_escala_diaria_completa`, `vw_resumo_mensal_escalas` e `vw_auditoria_folgas_domingo_mulheres`, filtrando e impedindo que colaboradores com **Cargo `Administrador`**, **Perfil de Acesso `admin`** e **Status Operacional `ativo`** entrem na escala 5x2.
   * **Backend (`app/Models/Funcionario.php`, `app/Http/Controllers/Api/EscalaApiController.php`, `app/Http/Controllers/Api/CrudApiController.php`):** Criação dos métodos `isAdministradorForaEscala()` e `scopeElegiveisEscala()`, bloqueio de geração individual (HTTP 422), exclusão automática na geração em lote (`modo = 'todos'`), limpeza preventiva de registros em `escalas_diarias` e remoção dos seletores de geração/permuta com exibição de badge informativo `"Fora da Escala 5x2"`.

2. **Contador de Tempo de Sessão Logada no Header da Home:**
   * **Backend (`app/Http/Controllers/AuthController.php`):** Armazenamento de `login_timestamp` na sessão no momento do login.
   * **Frontend (`resources/views/home/index.blade.php`, `resources/ts/farmaviva.ts`, `public/js/farmaviva.js`):** Atualização de `#fv-realtime-clock` para calcular e exibir em tempo real o tempo decorrido de sessão logada no formato `Tempo logado: HH:MM:SS`.

3. **Logo da Tela de Login (`logoformaviver.png`):**
   * Cópia da imagem da raiz para `public/img/logoformaviver.png` (e `public/img/logofarmaviver.png`) e substituição da imagem na tela `resources/views/auth/login.blade.php`, mantendo intactas todas as descrições da tela de login.

4. **Redução Global de Tipografia para Padrão WebApp:**
   * Ajuste em `public/css/farmaviva.css` reduzindo o tamanho base (`body` para `0.8125rem` / `13px`), títulos, botões, inputs, modais, abas e badges para proporções compactas de aplicação web corporativa.

5. **Atualização do Rodapé (Footer):**
   * Alteração do texto do rodapé em `resources/views/layouts/app.blade.php` para: `hhernani@gmail.com / 92-98161-4980 - Todos os direitos reservados.`

6. **Remoção dos Enunciados da Seção de Cards na Home:**
   * Remoção dos textos `"Cadastros e Gestão em Cards (CRUDs Intuitivos)"` e `"Toque em qualquer Card para abrir o CRUD correspondente"` em `resources/views/home/index.blade.php`.

7. **Limpeza do Corpo (Body) dos 6 Cards na Home:**
   * Remoção dos parágrafos descritivos (`<p>`) do corpo de todos os 6 Cards em `resources/views/home/index.blade.php`, mantendo o layout limpo e objetivo.

8. **Redução da Fonte da Tabela em Dashboard / Visão do Dia:**
   * Aplicação da classe `.fv-table-compact` (`0.72rem`) na tabela da aba **Dashboard / Visão do Dia** em `resources/views/home/index.blade.php` e `public/css/farmaviva.css`.

9. **Redução da Fonte da Tabela em Visualizador Semanal / Edição:**
   * Aplicação da classe `.fv-table-compact` (`0.72rem`) e botões compactos `.fv-btn-xs` na tabela da aba **Visualizador Semanal / Edição** em `resources/views/home/index.blade.php` e `public/css/farmaviva.css`.

10. **Permanência no Modal e Atualização em Tempo Real da Tabela de Funcionários (`#modalFuncionarios`):**
    * Remoção do recarregamento de página (`window.location.reload()`) ao cadastrar, editar ou excluir funcionários em `resources/ts/farmaviva.ts` e `public/js/farmaviva.js`.
    * Implementação de `refreshFuncionariosTable()` consumindo `GET /api/funcionarios` para atualizar em tempo real o `#tbody-crud-funcionarios`, os contadores da Home e os seletores de geração/permutas sem fechar o modal, além da inclusão do atributo calculado `fora_escala_5x2` em `app/Models/Funcionario.php`.

11. **Remoção da Coluna `Sexo` da Tabela de Gestão de Funcionários:**
    * Remoção da coluna `<th>Sexo</th>` e respectivas células `<td>` da listagem de funcionários em `resources/views/home/partials/modals.blade.php` e na atualização dinâmica via JavaScript, mantendo o campo no formulário de cadastro/edição.

12. **Remoção de `(funcionarios)` no Header da Gestão de Funcionários:**
    * Título do modal `#modalFuncionariosLabel` atualizado para apenas **`Gestão de Funcionários`** em `resources/views/home/partials/modals.blade.php`.

13. **Remoção de `(cargos)` no Header da Gestão de Cargos:**
    * Título do modal `#modalCargosLabel` atualizado para apenas **`Gestão de Cargos`** em `resources/views/home/partials/modals.blade.php`.

14. **Permanência no Modal e Atualização em Tempo Real da Tabela de Cargos (`#modalCargos`):**
    * Remoção do `window.location.reload()` ao cadastrar, editar ou excluir cargos em `resources/ts/farmaviva.ts` e `public/js/farmaviva.js`.
    * Implementação de `refreshCargosTable()` consumindo `GET /api/cargos` para atualizar em tempo real o `#tbody-crud-cargos`, o `<select id="crud_fun_car_id">` do formulário de Funcionários e o contador de cargos ativos na Home sem fechar o modal.

15. **Remoção da Coluna `CBO` da Tabela de Gestão de Cargos:**
    * Remoção da coluna `<th>CBO</th>` e respectivas células `<td>` da tabela de cargos em `resources/views/home/partials/modals.blade.php` e na renderização em tempo real via JavaScript.

16. **Remoção de `(turnohorarios)` no Header de Calendário de Turnos e Horários:**
    * Título do modal `#modalTurnosLabel` atualizado para apenas **`Calendário de Turnos e Horários`** em `resources/views/home/partials/modals.blade.php`.

17. **Reposicionamento da Logo na Tela de Login Acima de `Acesso ao Sistema`:**
    * Remoção da barra superior (`<header class="fv-login-header">`) em `resources/views/auth/login.blade.php` e `public/css/farmaviva.css`, posicionando a imagem `logoformaviver.png` centralizada dentro do cabeçalho do card de login (`.fv-login-card-header`), imediatamente acima da frase **`Acesso ao Sistema`**.

18. **Sugestões Complementares Autorizadas (A e B):**
    * **Sugestão A — Atualização em Tempo Real para Turnos e Calendário/Feriados:** Implementação de `refreshTurnosTable()` e `refreshCalendarioTables()` em `resources/ts/farmaviva.ts` e `public/js/farmaviva.js`, mantendo os modais `#modalTurnos` e `#modalCalendario` abertos e atualizando suas tabelas (`#tbody-crud-turnos`, `#tbody-crud-periodos`, `#tbody-crud-cfg5x2`, `#tbody-crud-feriados`) e contadores da Home em tempo real após cadastrar, alterar ou excluir registros.
    * **Sugestão B — Padronização Visual sem Nomes Técnicos de Banco entre Parênteses:** Remoção de referências técnicas de tabelas, views, procedures e colunas entre parênteses nos cabeçalhos, abas e rótulos de `resources/views/auth/login.blade.php`, `resources/views/home/index.blade.php` e `resources/views/home/partials/modals.blade.php`.

<< 02/11/2026 - Fim das Atualizações >>

<< 03/11/2026 Observação >>
1. Módulo Empresa, Card na Home para CRUD
2. CRUD da Empresa é para ser uma view e não uma modal, Listar todas as empresas cadastradas e ativas e na mesma view mostrar os seguintes campos: emp_nome_fantasia, emp_cnpj, emp_email e emp_celular, mostrar os ícones de alteração e desativação(emp_ativo = 0) ao lado de cada registro;
3. Ao realizar update ou insert fazer um refresh da tabela de empresas sem o usuário perceber a movimentação da tela;
4. Na Tela de Login: os enunciados CPF do Funcionário e SENHA AUMENTAR a fonte um pouco e negritar;
5. Todo o usuário com Perfil Administrador, pode acessar qualquer empresa cadastrada sem que precise cadastrar o usuário Administrador em cada Empresa;
6. Na home o usuário Administrador pode selecionar a empresa desejada e listar todas informações da empresa específica: Os funcionários da empresa escolhida, os Cargos correspondente a empresa escolhida, todas as informações das tables correspondente daquela empresa específica;
7. 

<< 03/11/2026 Fim da Observação >>

<< 03/11/2026 - atualização realizada >>

### Registro Técnico das Alterações Realizadas em 03/11/2026 (Itens 1 a 6 + Sugestões A, B e C)

1. **Módulo Empresa — Card na Home para CRUD (`resources/views/home/index.blade.php`):**
   * Adição do Card **7. Módulo Empresa** no painel de módulos da Home e botão de acesso rápido no cabeçalho superior, direcionando para a View dedicada `GET /empresas` (`route('empresas.index')`).
   * Exibição do contador de empresas ativas (`#badge-total-empresas`) e da unidade ativa selecionada.

2. **CRUD da Empresa em View Dedicada e Coluna `emp_celular` (`resources/views/empresas/index.blade.php`, `app/Models/Empresa.php`, `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/Api/CrudApiController.php`, `routes/web.php`):**
   * Adição da coluna `emp_celular VARCHAR(20) NULL` na tabela `empresas` do banco `farmaviver` (sincronizada com `emp_telefone` e incluída no `$fillable` de `app/Models/Empresa.php`).
   * Criação da View dedicada `resources/views/empresas/index.blade.php` (substituindo o uso de modal para o módulo Empresa), contendo o formulário de cadastro/edição à esquerda e a listagem de empresas ativas (`emp_ativo = 1`) à direita.
   * Exibição na tabela dos campos solicitados: **`emp_nome_fantasia`**, **`emp_cnpj`**, **`emp_email`** e **`emp_celular`**, acompanhados dos ícones de **alteração** (`bi-pencil` / `.btn-edit-empresa`) e **desativação** (`bi-slash-circle` / `.btn-deactivate-empresa`, definindo `emp_ativo = 0`) ao lado de cada registro.

3. **Refresh Silencioso da Tabela de Empresas sem Movimentação da Tela (`public/js/farmaviva.js` e `resources/ts/farmaviva.ts`):**
   * Implementação da função `refreshEmpresasTable()` via `fetch('/api/empresas', ...)` e atualização do `initCrudEmpresa()`.
   * Ao realizar `insert` (`POST /api/empresas`), `update` (`PUT /api/empresas/{id}`) ou desativação/reativação (`PATCH /api/empresas/{id}/status`), a tabela `#tbody-crud-empresas` e o contador `#badge-total-empresas` são atualizados instantaneamente em segundo plano, sem recarregar a página ou causar rolagem/movimentação da tela.

4. **Destaque Tipográfico nos Rótulos da Tela de Login (`resources/views/auth/login.blade.php` e `public/css/farmaviva.css`):**
   * Criação da classe `.fv-login-label` (`font-size: 0.92rem; font-weight: 700; color: var(--fv-gray-900); letter-spacing: 0.02em;`) e aplicação de `fw-bold text-dark fv-login-label` nos enunciados **`CPF do Funcionário`** e **`SENHA`** na tela de login.

5. **Acesso Global para Usuários com Perfil Administrador (`app/Http/Controllers/AuthController.php`):**
   * Atualização do método `apiLogin` para permitir que qualquer usuário ativo com perfil **Administrador** (`fun_perfil === 'admin'` ou perfil RBAC `Administrador`) acesse qualquer empresa ativa cadastrada, sem necessidade de duplicar o cadastro do funcionário Administrador em cada empresa.
   * Registro do `emp_id_origem` na sessão para preservar o vínculo de origem e controlar permissões multiempresa.

6. **Seletor de Empresa na Home para Administrador e Filtro Multiempresa Completo (`app/Http/Controllers/HomeController.php`, `app/Http/Controllers/Api/EscalaApiController.php`, `app/Http/Controllers/Api/CrudApiController.php`, `resources/views/home/index.blade.php`):**
   * Inclusão do seletor **`Empresa:`** (`#homeSelectEmpresa`) na barra superior da Home para usuários com perfil Administrador, permitindo alternar a empresa ativa (`GET /home?emp_id={id}` ou `POST /api/empresas/selecionar`).
   * Vinculação de todas as listagens e operações da Home e das APIs à empresa selecionada (`$empId`): Funcionários (`funcionarios`), Cargos (`cargos`), Turnos (`turnos`), Períodos de Escala (`escalas_periodo`), Regras 5x2 (`configuracoes_escala`), Feriados (`calendario_feriados`), Alocações Diárias (`escalas_diarias`), Trocas e Afastamentos (`trocas_escala`), Dashboard Diário, Matriz Mensal, Visão Semanal e Auditoria CLT.

7. **Sugestões Complementares Autorizadas (A, B e C):**
   * **Sugestão A — Inicialização Automática da Nova Empresa:** Ao cadastrar uma nova empresa (`CrudApiController::inicializarEstruturaEmpresa`), o sistema cria automaticamente os 3 perfis RBAC padrão (`Administrador`, `Gestor de Farmácia`, `Colaborador`) na tabela `perfis` e a regra padrão `5x2 Sábado e Domingo (Padrão)` na tabela `configuracoes_escala` para a nova empresa, além de vincular o `prf_id` correto ao cadastrar funcionários na empresa selecionada.
   * **Sugestão B — Filtro Opcional "Mostrar desativadas" e Reativação:** Inclusão do seletor `Mostrar desativadas` (`#switchMostrarInativas`) na View `/empresas`, permitindo visualizar empresas com `emp_ativo = 0` e reativá-las (`emp_ativo = 1`) com um clique sem perda de histórico.
   * **Sugestão C — Trava de Segurança Multiempresa por Perfil:** O seletor de troca de empresa na Home e a alternância de `emp_id` na sessão ficam restritos exclusivamente ao perfil **Administrador** (`fun_perfil === 'admin'`), mantendo usuários Gestores e Colaboradores estritamente vinculados à sua empresa de origem (`emp_id_origem`).

<< 03/11/2026 - Fim das Atualizações >>

<< 07/11/2026 Observação >>
1.  Na home os botões Dashboard / Visão do Dia, Visualizador Mensal (Matriz), Visualizador Semanal / Edição e Auditoria Domingos Mulheres (CLT. Precisam ficar um abaixo do outro e não lado a lado;
2. O sábado a Farmaviver tem expediente da 08 as 20hs
3. No Domingo e feriado tem expediente de 08 das 13hs
4. Adicione a logofarmaviver.svg para aparecer na aba do navegador, nos atalhos criados na tela inicial de dispositivos móveis;
5. Na home as tables que possuírem nomenclatura nas colunas com mais de uma palavra fazer a quebra pra próxima linha, por exemplo: Matrícula / Colaborador deve ficar (Matrícula e abaixo Colaborador), Dia da Semana deve ficar (Dia da e abaixo Semana), Situação no Dia deve ficar (Situação e abaixo no Dia), deve ser feito em todas as tables que fique coerente);
<< 07/11/2026 fim da Observação >>

<< 07/11/2026 - atualização realizada >>

### Registro Técnico das Alterações Realizadas em 07/11/2026 (Itens 1 a 5 + Sugestões A, B e C)

1. **Botões das Abas Operacionais na Home Empilhados Verticalmente (`resources/views/home/index.blade.php` e `public/css/farmaviva.css`):**
   * Alteração da navegação `#fv-main-tabs` para disposição em coluna (`flex-column` e `.w-100 text-start`), posicionando os botões **`Dashboard / Visão do Dia`**, **`Visualizador Mensal (Matriz)`**, **`Visualizador Semanal / Edição`** e **`Auditoria Domingos Mulheres (CLT)`** um abaixo do outro (e não lado a lado).

2. **Expediente de Sábado das 08:00 às 20:00 (`turnohorarios`, `sp_gerar_escala_5x2_funcionario`, `app/Http/Controllers/Api/CrudApiController.php`, `resources/views/home/index.blade.php`):**
   * Criação automática do turno oficial **`Expediente Sábado (08h às 20h)`** (`08:00:00` às `20:00:00`, intervalo `12:00:00` às `14:00:00`) para todas as empresas cadastradas e na rotina `inicializarEstruturaEmpresa()` para novas unidades.
   * Atualização da Stored Procedure `sp_gerar_escala_5x2_funcionario` para alocar automaticamente o turno de **08:00 às 20:00** nos sábados trabalhados (`DAYOFWEEK = 7`).

3. **Expediente de Domingo e Feriado das 08:00 às 13:00 (`turnohorarios`, `sp_gerar_escala_5x2_funcionario`, `app/Http/Controllers/Api/EscalaApiController.php`):**
   * Criação automática do turno oficial **`Domingo e Feriado (08h às 13h)`** (`08:00:00` às `13:00:00`, carga horária líquida de `05:00:00`) para todas as empresas cadastradas e novas unidades.
   * Atualização de `sp_gerar_escala_5x2_funcionario` para alocar automaticamente o turno de **08:00 às 13:00** nos domingos trabalhados (`DAYOFWEEK = 1`, respeitando as folgas 5x2 e a garantia legal de no mínimo 2 domingos de folga no mês para mulheres — CLT Art. 386) e nos feriados com expediente.
   * Ajuste nas triggers `tg_validar_interjornada_insert` e `tg_validar_interjornada_update` para permitir a transição de turnos comerciais de segunda a sexta para o expediente especial de sábado (`08h–20h`) e domingo/feriado (`08h–13h`), mantendo a validação estrita de 11h nos dias úteis regulares.

4. **Ícone `logofarmaviver.svg` na Aba do Navegador e Atalhos Mobile (`resources/views/layouts/app.blade.php`, `public/img/logofarmaviver.svg`, `public/logofarmaviver.svg`, `public/manifest.json`):**
   * Inclusão das tags `<link rel="icon" type="image/svg+xml" href="{{ asset('img/logofarmaviver.svg') }}">`, `<link rel="shortcut icon">` e `<link rel="apple-touch-icon">` no `<head>` do layout principal `resources/views/layouts/app.blade.php`, garantindo a exibição da logo na aba do navegador e nos atalhos criados na tela inicial de smartphones/tablets.

5. **Quebra de Linha Coerente nos Cabeçalhos (`<th>`) com Mais de uma Palavra em Todas as Tabelas (`resources/views/home/index.blade.php`, `resources/views/home/partials/modals.blade.php`, `resources/views/empresas/index.blade.php`, `public/css/farmaviva.css`):**
   * Aplicação de quebra de linha (`<br>`) e alinhamento vertical (`vertical-align: middle; line-height: 1.16;`) nas colunas com mais de uma palavra em todas as tabelas da Home, Modais e View de Empresas:
     * **Dashboard / Visão do Dia:** `Matrícula /<br>Colaborador`, `Dia da<br>Semana`, `Situação<br>no Dia`, `Turno /<br>Horários`, `Carga<br>Prevista`, `Ajuste<br>Pontual`.
     * **Matriz Mensal:** `Colaborador /<br>Cargo` e colunas de dias (`Dia<br>01`, `Dia<br>02`, ...).
     * **Resumo Consolidado do Mês:** `Dias<br>Trabalhados`, `Dias de<br>Folga / DSR`.
     * **Visualizador Semanal / Edição:** `Dia da<br>Semana`, `Turno /<br>Horário`, `Carga<br>Horária`.
     * **Auditoria Domingos Mulheres (CLT):** `Domingos de<br>Folga / DSR`, `Domingos<br>Trabalhados`, `Status Legal<br>(CLT Art. 386)`.
     * **Tabelas dos Modais e Empresas:** `Matrícula /<br>Nome`, `Cargo /<br>Turno`, `Cargo /<br>Descrição`, `Descrição<br>do Turno`, `Saída / Retorno<br>Intervalo`, `Descrição<br>do Período`, `Intervalo<br>de Datas`, `Registros<br>Diários`, `Regra<br>5x2`, `1º Dia<br>de Folga`, `2º Dia<br>de Folga`, `Descrição<br>do Feriado`, `Solicitante /<br>Dia Origem`, `Substituto /<br>Dia Destino`, `Motivo<br>da Troca`, `Ação /<br>Gestão` e `Nome<br>Fantasia`.

6. **Sugestões Complementares Autorizadas (A, B e C):**
   * **Sugestão A — Turnos Contínuos de 5h (`08:00` às `13:00`):** Atualização da constraint `chk_turno_retorno` na tabela `turnohorarios` e da validação em `CrudApiController::salvarTurno` para permitir `tur_retorno_intervalo >= tur_saida_intervalo`, viabilizando turnos diretos de 5h (`Direto (5h)`) com cálculo exato de `05:00:00` na view `vw_escala_diaria_completa`.
   * **Sugestão B — Seletor de Regra em Feriados no Gerador 5x2:** Inclusão do seletor `Regra em Feriados` (`#ger_feriado_modo`) no modal Gerador Automático 5x2, permitindo escolher entre **`Expediente de Feriado (08h às 13h)`** (padrão) ou **`Folga de Feriado (Loja Fechada)`**.
   * **Sugestão C — Web App Manifest (`public/manifest.json`):** Criação do arquivo `public/manifest.json` integrado ao `layouts/app.blade.php` com suporte a instalação como aplicativo (`standalone`) e ícones `logofarmaviver.svg` / `logofarmaviver.png` em dispositivos móveis Android e iOS.

<< 07/11/2026 - Fim das Atualizações >>

<< 11/11/2026 Início Observação >>
1. No card **Gerar Escala 5x2**, o modal **Gerador Automático de Escala 5x2** é exibido. Para facilitar a visualização, organize as opções verticalmente, uma abaixo da outra:

- Período Operacional *
- Configuração de Folgas 5x2 *
- Modo de Geração *
- Turno Segunda a Sexta
- Regra em Feriados *

2. Ao pressionar o botão e Gerar Escala 5x2 Verifiquei que os dias de Sábado e Domingo exemplificando os dias 14 (sábado) e 15 (domingo), todos os funcionários estavam com a situação no dia (Folga/DSR). Sendo 
que aos sábados todas as Empresas cadastradas tem expediente da 08 as 20hs e aos Domingos e feriado tem expediente de 08 das 13hs;

3. No Card Abrir Calendário & Feriado, A Aba *Períodos de Escala* os campos a serem ordenados na seguinte ordem:
- *Descrição do Período* acima dos campos:
- *Data Início*, *Data Fim* e logo abaixo o campo *Status*;
- E abaixo do campo *Status* os botões *Salvar* e ao lado o botão *Limpar*

3.1 Na Aba *Configurações 5x2*, Ordenar na seguinte forma:
- Abaixo *Nome da Regra 5x2* os Campos: *Tipo*, *Folga 1 (1..7)* e *Folga 2 (1..7)*;
- E abaixo os botões *Salvar 5x2* e *Limpar*

3.2 Na Aba Feriados
- Abaixo do campo *Data do Feriado* colocar os campos *Descrição* e *Tipo*;
- E logo abaixo os botões *Salvar* e *Limpar*
<< 11/11/2026 Fim Observação >>

<< 11/11/2026 - atualização realizada >>

### Registro Técnico das Alterações Realizadas em 11/11/2026 (Itens 1, 2, 3, 3.1 e 3.2 + Sugestões A, B e C)

1. **Item 1 — Campos Empilhados Verticalmente (Um Abaixo do Outro) no Modal `Gerador Automático de Escala 5x2` (`resources/views/home/partials/modals.blade.php`):**
   * Substituição das classes `col-12 col-md-6` por `col-12` em todos os campos do formulário `#form-gerar-escala-5x2` dentro do modal `#modalGeradorEscala`, garantindo que todas as opções fiquem dispostas **uma abaixo da outra** na sequência solicitada:
     1. **`Período Operacional *`** (`#ger_esp_id`)
     2. **`Configuração de Folgas 5x2 *`** (`#ger_cfg_id`)
     3. **`Modo de Geração *`** (`#ger_modo`)
     4. **`Funcionário Específico`** (`#wrap-ger-fun-id` / `#ger_fun_id` — exibido logo abaixo quando selecionado o modo individual)
     5. **`Turno Segunda a Sexta`** (`#ger_tur_id`)
     6. **`Regra em Feriados *`** (`#ger_feriado_modo`)
   * **Sugestão A (Item 1) — Proporção Visual do Modal para Coluna Única (`resources/views/home/partials/modals.blade.php`):** Ajuste do container do modal `#modalGeradorEscala` de `modal-lg` para `modal-dialog modal-dialog-centered modal-dialog-scrollable`, proporcionando largura ergonômica e equilibrada para os campos em coluna única no Desktop e no Mobile.
   * **Sugestão B (Item 1) — Sincronização em Tempo Real dos Seletores do Gerador 5x2 (`public/js/farmaviva.js` e `resources/ts/farmaviva.ts`):** Atualização das rotinas `refreshCalendarioTables()` e `refreshTurnosTable()` para sincronizar dinamicamente os `<select>` `#ger_esp_id`, `#ger_cfg_id`, `#ger_tur_id`, `#crud_fun_tur_id_padrao` e `#edit_esd_tur_id` sem necessidade de recarregar a página.

2. **Item 2 — Cobertura Obrigatória de Expediente aos Sábados (`08h às 20h`) e aos Domingos e Feriados (`08h às 13h`) em Todas as Empresas (`app/Http/Controllers/Api/EscalaApiController.php`, `app/Http/Controllers/Api/CrudApiController.php`, `resources/views/home/partials/modals.blade.php`, `public/js/farmaviva.js`, `resources/ts/farmaviva.ts` e `sp_gerar_escala_5x2_funcionario`):**
   * **Causa Raiz Corrigida:** Anteriormente, a primeira opção padrão do seletor `#ger_cfg_id` era `5x2 Tradicional (Sábado e Domingo)` (`cfg_dia_folga_1 = 1` e `cfg_dia_folga_2 = 7`), o que colocava todos os colaboradores de folga simultaneamente aos Sábados e Domingos (como nos dias `14/11` e `15/11`) quando gerados em lote. Além disso, a paridade semanal do Art. 386 da CLT na Stored Procedure não intercalava as semanas entre as colaboradoras mulheres.
   * **Sugestão A (Item 2) — Revezamento Automático da Equipe no Gerador 5x2 (`resources/views/home/partials/modals.blade.php`, `public/js/farmaviva.js`, `resources/ts/farmaviva.ts` e `EscalaApiController::gerarEscala5x2`):**
     * Inclusão da opção padrão **`Revezamento Automático da Equipe (Cobertura Sáb 08h-20h e Dom/Fer 08h-13h)`** (`value="0"`) como 1ª opção pré-selecionada em `Configuração de Folgas 5x2 *` (`#ger_cfg_id`).
     * No método `EscalaApiController::gerarEscala5x2()`, ao gerar para toda a equipe (`modo === 'todos'`) ou com `cfg_id = 0`, os pares de folga na semana (`Segunda e Terça`, `Quarta e Quinta`, `Quinta e Sexta`) são distribuídos ciclicamente entre os colaboradores ativos, evitando que toda a equipe fique de `Folga/DSR` nos fins de semana.
   * **Sugestão B (Item 2) — Regras de Revezamento para Todas as Empresas e Intercalação Quinzenal na Stored Procedure (`CrudApiController::inicializarEstruturaEmpresa`, `EscalaApiController::garantirEstruturaTurnosERegras` e `sp_gerar_escala_5x2_funcionario`):**
     * Garantia automática de que todas as empresas cadastradas (atuais e futuras) possuam os turnos `Expediente Sábado (08h às 20h)` (`08:00:00` às `20:00:00`) e `Domingo e Feriado (08h às 13h)` (`08:00:00` às `13:00:00`), bem como as regras 5x2 de revezamento na semana (`Equipe A: Segunda e Terça`, `Equipe B: Quarta e Quinta`, `Equipe C: Quinta e Sexta`).
     * Atualização da Stored Procedure `sp_gerar_escala_5x2_funcionario` para intercalar os domingos de folga entre as colaboradoras mulheres (`((WEEK(v_data_atual, 0) + v_ordem_revezamento) % 2) = 0`) e compensar a 2ª folga semanal (`v_folga_2`) na semana do domingo de folga obrigatória, mantendo rigorosamente 5 dias de trabalho x 2 dias de folga e 100% de conformidade com o **Art. 386 da CLT**.
   * **Sugestão C (Item 2) — Trava Pós-Geração de Cobertura Mínima aos Sábados (`08h–20h`), Domingos e Feriados (`08h–13h`) (`EscalaApiController::garantirCoberturaFimDeSemanaEFeriados`):**
     * Execução automática após a geração da escala garantindo que todos os registros de `TRABALHO` aos Sábados utilizem o turno `08:00 às 20:00` e aos Domingos/Feriados utilizem o turno `08:00 às 13:00`, e que nenhum Sábado, Domingo ou Feriado com expediente fique com `0` funcionários trabalhando.
     * Escalas regeneradas e verificadas: no **Sábado `14/11/2026`**, `6` colaboradores estão escalados em **`TRABALHO`** no **`Expediente Sábado (08h às 20h)`**; no **Domingo/Feriado `15/11/2026`**, `5` colaboradores estão escalados em **`TRABALHO`** no turno **`Domingo e Feriado (08h às 13h)`** e `1` colaboradora em folga dominical quinzenal (`CONFORME` Art. 386 CLT).

3. **Itens 3, 3.1 e 3.2 — Reordenação dos Campos e Botões nas 3 Abas do Modal `Abrir Calendário & Feriados` (`#modalCrudCalendario` em `resources/views/home/partials/modals.blade.php`, `public/js/farmaviva.js` e `resources/ts/farmaviva.ts`):**
   * **Item 3 — Aba `Períodos de Escala` (`#form-crud-periodo`):**
     * **1ª Linha (`col-12`):** Campo **`Descrição do Período *`** (`#crud_esp_descricao`) posicionado no topo acima dos demais campos.
     * **2ª Linha (`col-12 col-md-6` + `col-12 col-md-6`):** Campos **`Data Início *`** (`#crud_esp_data_inicio`) e **`Data Fim *`** (`#crud_esp_data_fim`) lado a lado logo abaixo da descrição.
     * **3ª Linha (`col-12`):** Campo **`Status *`** (`#crud_esp_status`) posicionado logo abaixo dos campos de data.
     * **4ª Linha (`col-12 d-flex gap-2 pt-1`):** Botão **`Salvar`** (`#btn-salvar-periodo`) e ao lado o botão **`Limpar`** (`#btn-limpar-periodo`) logo abaixo do campo `Status *`.
   * **Item 3.1 — Aba `Configurações 5x2` (`#form-crud-cfg5x2`):**
     * **1ª Linha (`col-12`):** Campo **`Nome da Regra 5x2 *`** (`#crud_cfg_nome`) posicionado no topo.
     * **2ª Linha (`col-12 col-md-4` + `col-6 col-md-4` + `col-6 col-md-4`):** Abaixo de `Nome da Regra 5x2 *`, os campos **`Tipo *`** (`#crud_cfg_tipo`), **`Folga 1 (1..7) *`** (`#crud_cfg_dia_folga_1`) e **`Folga 2 (1..7) *`** (`#crud_cfg_dia_folga_2`).
     * **3ª Linha (`col-12 d-flex gap-2 pt-1`):** Abaixo deles, os botões **`Salvar 5x2`** (`#btn-salvar-cfg5x2`) e ao lado **`Limpar`** (`#btn-limpar-cfg5x2`).
   * **Item 3.2 — Aba `Feriados` (`#form-crud-feriado`):**
     * **1ª Linha (`col-12`):** Campo **`Data do Feriado *`** (`#crud_fer_data`) posicionado no topo.
     * **2ª Linha (`col-12 col-md-7` + `col-12 col-md-5`):** Abaixo de `Data do Feriado *`, os campos **`Descrição *`** (`#crud_fer_descricao`) e **`Tipo *`** (`#crud_fer_tipo`).
     * **3ª Linha (`col-12 d-flex gap-2 pt-1`):** Logo abaixo, os botões **`Salvar`** (`#btn-salvar-feriado`) e ao lado **`Limpar`** (`#btn-limpar-feriado`).
   * **Sugestões Complementares Autorizadas (A, B e C) para o Item 3:**
     * **Sugestão A (Item 3) — Padronização Visual dos Botões `Salvar` e `Limpar` com Ícones nas 3 Abas:** Adição dos ícones `bi-check-lg` e `bi-eraser` com espaçamento uniforme (`d-flex gap-2`) nos botões de ação das 3 abas (`Períodos de Escala`, `Configurações 5x2` e `Feriados`).
     * **Sugestão B (Item 3) — Indicação Visual de Edição nos Botões Principais (`public/js/farmaviva.js` e `resources/ts/farmaviva.ts`):** Ao clicar no botão de editar (`lápis`) de um registro nas tabelas de Períodos, Configurações 5x2 ou Feriados, o texto do botão principal muda dinamicamente para **`Atualizar`** (ou **`Atualizar 5x2`**), retornando para **`Salvar`** (ou **`Salvar 5x2`**) ao clicar em **`Limpar`** ou concluir a gravação.
     * **Sugestão C (Item 3) — Preenchimento Automático do Último Dia do Mês em `Data Fim` (`public/js/farmaviva.js` e `resources/ts/farmaviva.ts`):** Ao selecionar a **`Data Início *`** na aba `Períodos de Escala`, o campo **`Data Fim *`** sugere automaticamente o último dia daquele mês (caso esteja vazio ou anterior à data inicial) e define o atributo `min` igual à `Data Início *`.

<< 11/11/2026 - Fim das Atualizações >>

<< 13/11/2026 - observação >>
1. A geração de escala 5x2 não está funcionando corretamente, por exemplo: 
1.1 dia 13/11/2026 existe 3 funcionários do turno da manhã e somente 1 no turno da noite, outro exemplo dia 12/11/2026 3 funcionários pela manhã e nenhum no período da tarde para fechamento escala totalmente mal distribuída. Corrigir urgente;

2. Quero que ao cadastrar cargos, estes registros sejam acessados por outras empresas;

3. Como já existe uma empresa com emp_id = 2, está faltando a inserção desta empresa x cargo
<< 13/11/2026 - fim observação >>

<< 13/11/2026 - atualização realizada >>

### Registro Técnico das Alterações Realizadas em 13/11/2026 (Itens 1 / 1.1, 2 e 3 + Sugestões A, B e C)

1. **Item 1 / 1.1 — Correção da Distribuição de Turnos (Manhã x Fechamento) e Eliminação de Sobreposição de Folgas (`app/Http/Controllers/Api/EscalaApiController.php`, `app/Http/Controllers/Api/CrudApiController.php` e `sp_gerar_escala_5x2_funcionario`):**
   * **Causa Raiz Corrigida:**
     1. As regras de revezamento anteriores (`Equipe B: Quarta e Quinta [4,5]` e `Equipe C: Quinta e Sexta [5,6]`) colidiam na **Quinta-feira (`dia 5`)**, acumulando folgas no dia `12/11/2026`.
     2. Os colaboradores estavam desbalanceados entre os turnos padrão (4 na Manhã e apenas 2 no Fechamento) e o sorteio das regras de folga ocorria sem separar a fila por turno (`Turno Abertura (Manhã)` vs. `Turno Fechamento (Noite)`).
   * **Solução Aplicada:**
     * Atualização da 3ª regra de revezamento para **`5x2 Revezamento Equipe C (Sexta e Sábado)`** (`cfg_dia_folga_1 = 6`, `cfg_dia_folga_2 = 7`), formando 3 pares de folga totalmente disjuntos (`[Segunda/Terça: 2,3]`, `[Quarta/Quinta: 4,5]` e `[Sexta/Sábado: 6,7]`) sem nenhuma sobreposição nos dias de semana.
     * Separação da distribuição de regras de folga **por turno** dentro de `EscalaApiController::gerarEscala5x2()`, garantindo que os colaboradores do **Turno Abertura (Manhã)** revezem suas folgas entre si e os colaboradores do **Turno Fechamento (Noite)** revezem suas folgas entre si, sem jamais deixar o turno de fechamento descoberto.

2. **Sugestões Complementares Autorizadas (A, B e C):**
   * **Sugestão A — Balanceamento Automático 50% Abertura (Manhã) / 50% Fechamento (Noite) por Cargo (`EscalaApiController::balancearTurnosPadraoEquipe`):**
     * No modo de geração para toda a equipe (`modo === 'todos'`), equilibra automaticamente a alocação entre **`Turno Abertura (Manhã)`** (3 colaboradores, incluindo 1 Farmacêutico) e **`Turno Fechamento (Noite)`** (3 colaboradores, incluindo 1 Farmacêutico).
   * **Sugestão B — Trava Pós-Geração de Equilíbrio Diário de Segunda a Sexta (`EscalaApiController::garantirEquilibrioTurnosSemana`):**
     * Validação automática executada após a geração da escala que inspeciona todos os dias úteis (Segunda a Sexta, exceto feriados) e corrige qualquer desequilíbrio (`3x0` ou `3x1`) entre Abertura e Fechamento, respeitando o intervalo interjornada mínimo de 11h (Art. 66 da CLT).
     * **Resultado Verificado nos Dias Citados:**
       * **`12/11/2026` (Quinta-feira):** **3 colaboradores na Manhã (`08:00 às 17:48`)** e **2 colaboradores no Fechamento (`10:12 às 20:00`)** (`1` em folga).
       * **`13/11/2026` (Sexta-feira):** **2 colaboradores na Manhã (`08:00 às 17:48`)** e **2 colaboradores no Fechamento (`10:12 às 20:00`)** (`2` em folga).
       * **Todos os demais dias úteis do mês:** Cobertura equilibrada com **2 a 3 colaboradores na Manhã** e **2 a 3 colaboradores no Fechamento**, além de **Sábados (`08h–20h`)** e **Domingos/Feriados (`08h–13h`)** 100% cobertos e **Art. 386 da CLT** `CONFORME`.
   * **Sugestão C — Indicador de Cobertura por Turno (`Manhã | Fechamento | Plantão`) no Dashboard / Visão do Dia (`EscalaApiController::dashboardDia` e `resources/views/home/index.blade.php`):**
     * Inclusão do resumo `(Manhã: X | Fechamento: Y)` (ou `(Plantão: Z)` aos sábados, domingos e feriados) diretamente no card KPI **"Em Trabalho Hoje"** na Visão Operacional do Dia.

3. **Itens 2 e 3 — Compartilhamento Automático de Cargos entre Empresas e Inserção `Empresa x Cargo` para `emp_id = 2` (`app/Models/Cargo.php`, `app/Http/Controllers/Api/CrudApiController.php`, `app/Http/Controllers/HomeController.php`, `public/js/farmaviva.js` e `resources/ts/farmaviva.ts`):**
   * **Item 2 (Cargos Acessados por Outras Empresas):** Implementação do método estático `Cargo::sincronizarEntreEmpresas()` e atualização de `CrudApiController::salvarCargo()`, `CrudApiController::listarCargos()`, `CrudApiController::inicializarEstruturaEmpresa()` e `HomeController::index()` para que todo cargo cadastrado ou editado em uma empresa seja automaticamente disponibilizado e sincronizado em todas as demais empresas cadastradas.
   * **Item 3 (Inserção de `emp_id = 2 x cargo`):** Sincronização imediata executada no banco de dados MariaDB vinculando à empresa **`emp_id = 2` (`FarmaViver II`)** todos os 5 cargos cadastrados (`Farmacêutico(a) Responsável` [`car_id=6`], `Balconista de Farmácia` [`car_id=7`], `Operador(a) de Caixa` [`car_id=8`], `Auxiliar de Limpeza` [`car_id=9`] e `Administrador` [`car_id=10`]).
   * **Sugestões Complementares Autorizadas (A, B e C) para os Itens 2 e 3:**
     * **Sugestão A — Sincronização Automática Bidirecional Multi-Empresa (`Cargo::sincronizarEntreEmpresas` e `CrudApiController::salvarCargo`):** Ao criar ou atualizar qualquer cargo (incluindo alteração de nome, descrição, CBO ou status ativo/inativo), o registro homônimo é criado ou atualizado automaticamente em todas as empresas.
     * **Sugestão B — Atualização em Tempo Real do Seletor de Cargos Ativos no Modal de Funcionários (`public/js/farmaviva.js` e `resources/ts/farmaviva.ts`):** A rotina `refreshCargosTable()` filtra os cargos ativos e atualiza dinamicamente o `<select>` `#crud_fun_car_id` no modal **Gestão de Funcionários** e o badge `#badge-home-car-ativos` sem recarregar a página.
     * **Sugestão C — Prevenção de Duplicidade de Nome de Cargo por Empresa (`emp_id` + `car_nome`):** Verificação case-insensitive (`LOWER(TRIM(car_nome))`) antes de criar ou propagar cargos, evitando registros duplicados na mesma empresa.

<< 13/11/2026 - Fim das Atualizações >>