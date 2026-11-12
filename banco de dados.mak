## Gere um script sql para o banco chamado farmaviver no MariaDB
## Crie as tabelas seguindo o padrão por exemplo: empresas (emp_id, emp_nome, emp_fantasia, emp_email e ...), tabela funcionarios(fun_id, fun_nome e etc)
## Crie as possíveis tabelas e possíveis campos existentes para a seguinte situação: Geração de escala 5x2 para empresa
## Além das tabelas que você gerar, crie as tabelas: turnohorarios, cargos
## Gere as constrains, as foreing keys, as possíveis views (para agilizar as consultas), as possíveis storeg procedures.

-- =============================================================================
-- BANCO DE DADOS: farmaviver (MariaDB)
-- ESTRUTURA PARA GESTÃO DE ESCALAS 5x2, TURNOS E FUNCIONÁRIOS
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `farmaviver`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `farmaviver`;

-- -----------------------------------------------------------------------------
-- 1. TABELA: empresas
-- Prefixo: emp_
-- -----------------------------------------------------------------------------
CREATE TABLE `empresas` (
  `emp_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_razao_social` VARCHAR(150) NOT NULL,
  `emp_nome_fantasia` VARCHAR(150) NOT NULL,
  `emp_cnpj` VARCHAR(14) NOT NULL,
  `emp_email` VARCHAR(100) NOT NULL,
  `emp_telefone` VARCHAR(20) DEFAULT NULL,
  `emp_logradouro` VARCHAR(150) DEFAULT NULL,
  `emp_numero` VARCHAR(20) DEFAULT NULL,
  `emp_bairro` VARCHAR(80) DEFAULT NULL,
  `emp_cidade` VARCHAR(80) DEFAULT NULL,
  `emp_uf` CHAR(2) DEFAULT NULL,
  `emp_cep` VARCHAR(8) DEFAULT NULL,
  `emp_ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `emp_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `emp_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `uk_empresas_cnpj` UNIQUE (`emp_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. TABELA: cargos
-- Prefixo: car_
-- -----------------------------------------------------------------------------
CREATE TABLE `cargos` (
  `car_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT UNSIGNED NOT NULL,
  `car_nome` VARCHAR(100) NOT NULL,
  `car_descricao` VARCHAR(255) DEFAULT NULL,
  `car_cbo` VARCHAR(10) DEFAULT NULL,
  `car_ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `car_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `car_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cargos_empresas` FOREIGN KEY (`emp_id`) 
    REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. TABELA: turnohorarios
-- Prefixo: tur_
-- -----------------------------------------------------------------------------
CREATE TABLE `turnohorarios` (
  `tur_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT UNSIGNED NOT NULL,
  `tur_descricao` VARCHAR(80) NOT NULL,
  `tur_entrada` TIME NOT NULL,
  `tur_saida_intervalo` TIME NOT NULL,
  `tur_retorno_intervalo` TIME NOT NULL,
  `tur_saida` TIME NOT NULL,
  `tur_tolerancia_minutos` TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `tur_ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `tur_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tur_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_turnohorarios_empresas` FOREIGN KEY (`emp_id`) 
    REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_turno_intervalo` CHECK (`tur_saida_intervalo` > `tur_entrada`),
  CONSTRAINT `chk_turno_retorno` CHECK (`tur_retorno_intervalo` > `tur_saida_intervalo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. TABELA: funcionarios
-- Prefixo: fun_
-- -----------------------------------------------------------------------------
CREATE TABLE `funcionarios` (
  `fun_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT UNSIGNED NOT NULL,
  `car_id` INT UNSIGNED NOT NULL,
  `tur_id_padrao` INT UNSIGNED DEFAULT NULL,
  `fun_matricula` VARCHAR(30) NOT NULL,
  `fun_nome` VARCHAR(150) NOT NULL,
  `fun_cpf` VARCHAR(11) NOT NULL,
  `fun_email` VARCHAR(100) DEFAULT NULL,
  `fun_telefone` VARCHAR(20) DEFAULT NULL,
  `fun_data_admissao` DATE NOT NULL,
  `fun_data_demissao` DATE DEFAULT NULL,
  `fun_status` ENUM('ativo', 'afastado', 'ferias', 'desligado') NOT NULL DEFAULT 'ativo',
  `fun_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fun_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `uk_funcionarios_cpf` UNIQUE (`fun_cpf`),
  CONSTRAINT `uk_funcionarios_emp_matricula` UNIQUE (`emp_id`, `fun_matricula`),
  CONSTRAINT `fk_funcionarios_empresas` FOREIGN KEY (`emp_id`) 
    REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_funcionarios_cargos` FOREIGN KEY (`car_id`) 
    REFERENCES `cargos` (`car_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_funcionarios_turnos` FOREIGN KEY (`tur_id_padrao`) 
    REFERENCES `turnohorarios` (`tur_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. TABELA: configuracoes_escala (Padrões de folga para regimes 5x2)
-- Prefixo: cfg_
-- Define a regra de repouso (ex: Sábado/Domingo fixos ou folgas alternadas na semana)
-- -----------------------------------------------------------------------------
CREATE TABLE `configuracoes_escala` (
  `cfg_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT UNSIGNED NOT NULL,
  `cfg_nome` VARCHAR(100) NOT NULL,
  `cfg_tipo` ENUM('fixa', 'revezamento') NOT NULL DEFAULT 'fixa',
  `cfg_dia_folga_1` TINYINT UNSIGNED NOT NULL COMMENT '1=Domingo, 2=Segunda, ..., 7=Sabado',
  `cfg_dia_folga_2` TINYINT UNSIGNED NOT NULL COMMENT '1=Domingo, 2=Segunda, ..., 7=Sabado',
  `cfg_ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `cfg_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cfg_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_configuracoes_escala_empresas` FOREIGN KEY (`emp_id`) 
    REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_cfg_folga_1` CHECK (`cfg_dia_folga_1` BETWEEN 1 AND 7),
  CONSTRAINT `chk_cfg_folga_2` CHECK (`cfg_dia_folga_2` BETWEEN 1 AND 7),
  CONSTRAINT `chk_cfg_folgas_distintas` CHECK (`cfg_dia_folga_1` <> `cfg_dia_folga_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. TABELA: escalas_periodos (Cabeçalho do lote mensal/semanal gerado)
-- Prefixo: esp_
-- -----------------------------------------------------------------------------
CREATE TABLE `escalas_periodos` (
  `esp_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT UNSIGNED NOT NULL,
  `esp_descricao` VARCHAR(100) NOT NULL,
  `esp_data_inicio` DATE NOT NULL,
  `esp_data_fim` DATE NOT NULL,
  `esp_status` ENUM('rascunho', 'publicada', 'encerrada') NOT NULL DEFAULT 'rascunho',
  `esp_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `esp_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_escalas_periodos_empresas` FOREIGN KEY (`emp_id`) 
    REFERENCES `empresas` (`emp_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_esp_periodo` CHECK (`esp_data_fim` >= `esp_data_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. TABELA: escalas_diarias (Detalhamento dia a dia de cada colaborador)
-- Prefixo: esd_
-- -----------------------------------------------------------------------------
CREATE TABLE `escalas_diarias` (
  `esd_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `esp_id` INT UNSIGNED NOT NULL,
  `fun_id` INT UNSIGNED NOT NULL,
  `tur_id` INT UNSIGNED DEFAULT NULL,
  `esd_data` DATE NOT NULL,
  `esd_tipo_dia` ENUM('trabalho', 'folga', 'dsr', 'feriado') NOT NULL DEFAULT 'trabalho',
  `esd_observacao` VARCHAR(255) DEFAULT NULL,
  `esd_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `esd_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `uk_escala_funcionario_dia` UNIQUE (`fun_id`, `esd_data`),
  CONSTRAINT `fk_escalas_diarias_periodo` FOREIGN KEY (`esp_id`) 
    REFERENCES `escalas_periodos` (`esp_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_escalas_diarias_funcionario` FOREIGN KEY (`fun_id`) 
    REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_escalas_diarias_turno` FOREIGN KEY (`tur_id`) 
    REFERENCES `turnohorarios` (`tur_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. TABELA: trocas_escala (Controle de permutas de plantão e abonos)
-- Prefixo: tro_
-- -----------------------------------------------------------------------------
CREATE TABLE `trocas_escala` (
  `tro_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `fun_id_solicitante` INT UNSIGNED NOT NULL,
  `fun_id_substituto` INT UNSIGNED NOT NULL,
  `esd_id_origem` BIGINT UNSIGNED NOT NULL,
  `esd_id_destino` BIGINT UNSIGNED DEFAULT NULL,
  `tro_motivo` VARCHAR(255) NOT NULL,
  `tro_status` ENUM('pendente', 'aprovada', 'recusada', 'cancelada') NOT NULL DEFAULT 'pendente',
  `tro_aprovador_fun_id` INT UNSIGNED DEFAULT NULL,
  `tro_aprovado_em` DATETIME DEFAULT NULL,
  `tro_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tro_updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_trocas_solicitante` FOREIGN KEY (`fun_id_solicitante`) 
    REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_trocas_substituto` FOREIGN KEY (`fun_id_substituto`) 
    REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_trocas_esd_origem` FOREIGN KEY (`esd_id_origem`) 
    REFERENCES `escalas_diarias` (`esd_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_trocas_esd_destino` FOREIGN KEY (`esd_id_destino`) 
    REFERENCES `escalas_diarias` (`esd_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_trocas_aprovador` FOREIGN KEY (`tro_aprovador_fun_id`) 
    REFERENCES `funcionarios` (`fun_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ÍNDICES ADICIONAIS PARA OTIMIZAÇÃO
-- =============================================================================
CREATE INDEX `idx_escalas_diarias_data` ON `escalas_diarias` (`esd_data`);
CREATE INDEX `idx_escalas_diarias_busca` ON `escalas_diarias` (`fun_id`, `esd_data`, `esd_tipo_dia`);
CREATE INDEX `idx_funcionarios_status` ON `funcionarios` (`emp_id`, `fun_status`);

-- =============================================================================
-- VIEWS
-- =============================================================================

-- View 1: Espelho da escala diária consolidada com horários e postos
CREATE OR REPLACE VIEW `vw_escala_diaria_completa` AS
SELECT 
  ed.esd_id,
  ed.esd_data,
  DAYNAME(ed.esd_data) AS esd_dia_semana_en,
  DAYOFWEEK(ed.esd_data) AS esd_dia_semana_num,
  ed.esd_tipo_dia,
  e.emp_id,
  e.emp_nome_fantasia,
  f.fun_id,
  f.fun_matricula,
  f.fun_nome,
  c.car_id,
  c.car_nome,
  t.tur_id,
  t.tur_descricao,
  t.tur_entrada,
  t.tur_saida_intervalo,
  t.tur_retorno_intervalo,
  t.tur_saida,
  SEC_TO_TIME(
    (TIME_TO_SEC(TIMEDIFF(t.tur_saida_intervalo, t.tur_entrada))) + 
    (TIME_TO_SEC(TIMEDIFF(t.tur_saida, t.tur_retorno_intervalo)))
  ) AS carga_horaria_prevista
FROM `escalas_diarias` ed
JOIN `funcionarios` f ON ed.fun_id = f.fun_id
JOIN `empresas` e ON f.emp_id = e.emp_id
JOIN `cargos` c ON f.car_id = c.car_id
LEFT JOIN `turnohorarios` t ON ed.tur_id = t.tur_id;

-- View 2: Resumo mensal de dias trabalhados e folgas por funcionário
CREATE OR REPLACE VIEW `vw_resumo_mensal_escalas` AS
SELECT 
  ed.esp_id,
  DATE_FORMAT(ed.esd_data, '%Y-%m') AS mes_competencia,
  f.fun_id,
  f.fun_matricula,
  f.fun_nome,
  c.car_nome,
  COUNT(CASE WHEN ed.esd_tipo_dia = 'trabalho' THEN 1 END) AS total_dias_trabalho,
  COUNT(CASE WHEN ed.esd_tipo_dia IN ('folga', 'dsr') THEN 1 END) AS total_dias_folga,
  COUNT(CASE WHEN ed.esd_tipo_dia = 'feriado' THEN 1 END) AS total_dias_feriado
FROM `escalas_diarias` ed
JOIN `funcionarios` f ON ed.fun_id = f.fun_id
JOIN `cargos` c ON f.car_id = c.car_id
GROUP BY ed.esp_id, DATE_FORMAT(ed.esd_data, '%Y-%m'), f.fun_id, f.fun_matricula, f.fun_nome, c.car_nome;

-- =============================================================================
-- STORED PROCEDURES
-- =============================================================================

DELIMITER $$

-- Procedure 1: Geração automática de lote de escala 5x2
-- Gera registros de data inicial a final para um funcionário, aplicando folgas parametrizadas
CREATE PROCEDURE `sp_gerar_escala_5x2_funcionario`(
  IN p_esp_id INT UNSIGNED,
  IN p_fun_id INT UNSIGNED,
  IN p_cfg_id INT UNSIGNED,
  IN p_tur_id INT UNSIGNED
)
BEGIN
  DECLARE v_data_inicio DATE;
  DECLARE v_data_fim DATE;
  DECLARE v_data_atual DATE;
  DECLARE v_folga_1 TINYINT UNSIGNED;
  DECLARE v_folga_2 TINYINT UNSIGNED;
  DECLARE v_dia_semana TINYINT UNSIGNED;
  DECLARE v_turno_final INT UNSIGNED;

  -- Obter datas do período
  SELECT esp_data_inicio, esp_data_fim 
    INTO v_data_inicio, v_data_fim
    FROM escalas_periodos 
   WHERE esp_id = p_esp_id;

  -- Obter regra de folga da configuração
  SELECT cfg_dia_folga_1, cfg_dia_folga_2 
    INTO v_folga_1, v_folga_2
    FROM configuracoes_escala 
   WHERE cfg_id = p_cfg_id;

  -- Se turno não foi informado na chamada, pega o padrão do funcionário
  IF p_tur_id IS NULL THEN
    SELECT tur_id_padrao INTO v_turno_final FROM funcionarios WHERE fun_id = p_fun_id;
  ELSE
    SET v_turno_final = p_tur_id;
  END IF;

  SET v_data_atual = v_data_inicio;

  START TRANSACTION;
  
  WHILE v_data_atual <= v_data_fim DO
    -- DAYOFWEEK: 1=Domingo, 2=Segunda, ..., 7=Sábado
    SET v_dia_semana = DAYOFWEEK(v_data_atual);

    IF v_dia_semana = v_folga_1 OR v_dia_semana = v_folga_2 THEN
      -- Inserção de folga (sem alocação de turno obrigatório)
      INSERT INTO escalas_diarias (esp_id, fun_id, tur_id, esd_data, esd_tipo_dia, esd_observacao)
      VALUES (p_esp_id, p_fun_id, NULL, v_data_atual, 'folga', 'Folga regular 5x2')
      ON DUPLICATE KEY UPDATE 
        tur_id = NULL,
        esd_tipo_dia = 'folga',
        esd_observacao = 'Folga regular 5x2';
    ELSE
      -- Inserção de dia trabalhado
      INSERT INTO escalas_diarias (esp_id, fun_id, tur_id, esd_data, esd_tipo_dia, esd_observacao)
      VALUES (p_esp_id, p_fun_id, v_turno_final, v_data_atual, 'trabalho', 'Turno programado')
      ON DUPLICATE KEY UPDATE 
        tur_id = v_turno_final,
        esd_tipo_dia = 'trabalho',
        esd_observacao = 'Turno programado';
    END IF;

    SET v_data_atual = DATE_ADD(v_data_atual, INTERVAL 1 DAY);
  END WHILE;

  COMMIT;
END$$

-- Procedure 2: Efetivação de permuta aprovada entre colaboradores
CREATE PROCEDURE `sp_aprovar_troca_escala`(
  IN p_tro_id INT UNSIGNED,
  IN p_gestor_id INT UNSIGNED
)
BEGIN
  DECLARE v_esd_origem BIGINT UNSIGNED;
  DECLARE v_esd_destino BIGINT UNSIGNED;
  DECLARE v_tur_origem INT UNSIGNED;
  DECLARE v_tipo_origem ENUM('trabalho', 'folga', 'dsr', 'feriado');
  DECLARE v_tur_destino INT UNSIGNED;
  DECLARE v_tipo_destino ENUM('trabalho', 'folga', 'dsr', 'feriado');

  START TRANSACTION;

  SELECT esd_id_origem, esd_id_destino 
    INTO v_esd_origem, v_esd_destino
    FROM trocas_escala 
   WHERE tro_id = p_tro_id AND tro_status = 'pendente'
   FOR UPDATE;

  IF v_esd_origem IS NOT NULL THEN
    -- Obter detalhes do dia do solicitante
    SELECT tur_id, esd_tipo_dia INTO v_tur_origem, v_tipo_origem FROM escalas_diarias WHERE esd_id = v_esd_origem;

    -- Se for troca casada (duas pontas)
    IF v_esd_destino IS NOT NULL THEN
      SELECT tur_id, esd_tipo_dia INTO v_tur_destino, v_tipo_destino FROM escalas_diarias WHERE esd_id = v_esd_destino;

      UPDATE escalas_diarias SET tur_id = v_tur_destino, esd_tipo_dia = v_tipo_destino WHERE esd_id = v_esd_origem;
      UPDATE escalas_diarias SET tur_id = v_tur_origem, esd_tipo_dia = v_tipo_origem WHERE esd_id = v_esd_destino;
    END IF;

    -- Atualizar status da solicitação
    UPDATE trocas_escala 
       SET tro_status = 'aprovada',
           tro_aprovador_fun_id = p_gestor_id,
           tro_aprovado_em = NOW()
     WHERE tro_id = p_tro_id;

    COMMIT;
  ELSE
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solicitação de troca não encontrada ou já processada.';
  END IF;
END$$

DELIMITER ;

-- início triggers --

USE `farmaviver`;

-- =============================================================================
-- PARTE 1: TRIGGERS PARA REGRAS TRABALHISTAS (CLT)
-- =============================================================================

DELIMITER $$

-- Trigger 1: Validação de Interjornada Mínima de 11 Horas (Art. 66 da CLT)
-- Compara o intervalo entre a saída do dia anterior e a entrada do dia atual
CREATE OR REPLACE TRIGGER `tg_validar_interjornada_insert`
BEFORE INSERT ON `escalas_diarias`
FOR EACH ROW
BEGIN
  DECLARE v_saida_anterior TIME;
  DECLARE v_entrada_atual TIME;
  DECLARE v_horas_descanso DECIMAL(5,2);

  -- Só valida se o dia atual for de trabalho com turno definido
  IF NEW.esd_tipo_dia = 'trabalho' AND NEW.tur_id IS NOT NULL THEN
    
    -- Busca horário de saída do dia imediatamente anterior (caso tenha sido dia de trabalho)
    SELECT t.tur_saida INTO v_saida_anterior
      FROM escalas_diarias ed
      JOIN turnohorarios t ON ed.tur_id = t.tur_id
     WHERE ed.fun_id = NEW.fun_id
       AND ed.esd_data = DATE_SUB(NEW.esd_data, INTERVAL 1 DAY)
       AND ed.esd_tipo_dia = 'trabalho'
     LIMIT 1;

    -- Se houve trabalho no dia anterior, calcula a diferença em horas
    IF v_saida_anterior IS NOT NULL THEN
      SELECT tur_entrada INTO v_entrada_atual 
        FROM turnohorarios 
       WHERE tur_id = NEW.tur_id;

      -- Cálculo da diferença de horas entre os dois pontos
      SET v_horas_descanso = TIMESTAMPDIFF(
        MINUTE, 
        CONCAT(DATE_SUB(NEW.esd_data, INTERVAL 1 DAY), ' ', v_saida_anterior),
        CONCAT(NEW.esd_data, ' ', v_entrada_atual)
      ) / 60.0;

      IF v_horas_descanso < 11.00 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Violação CLT Art. 66: O intervalo interjornada deve ser de no mínimo 11 horas consecutivas.';
      END IF;
    END IF;

  END IF;
END$$

-- Trigger 2: Validação da mesma regra para UPDATE de turnos ou tipo de dia
CREATE OR REPLACE TRIGGER `tg_validar_interjornada_update`
BEFORE UPDATE ON `escalas_diarias`
FOR EACH ROW
BEGIN
  DECLARE v_saida_anterior TIME;
  DECLARE v_entrada_atual TIME;
  DECLARE v_horas_descanso DECIMAL(5,2);

  IF NEW.esd_tipo_dia = 'trabalho' AND NEW.tur_id IS NOT NULL THEN
    
    SELECT t.tur_saida INTO v_saida_anterior
      FROM escalas_diarias ed
      JOIN turnohorarios t ON ed.tur_id = t.tur_id
     WHERE ed.fun_id = NEW.fun_id
       AND ed.esd_data = DATE_SUB(NEW.esd_data, INTERVAL 1 DAY)
       AND ed.esd_tipo_dia = 'trabalho'
       AND ed.esd_id <> NEW.esd_id
     LIMIT 1;

    IF v_saida_anterior IS NOT NULL THEN
      SELECT tur_entrada INTO v_entrada_atual 
        FROM turnohorarios 
       WHERE tur_id = NEW.tur_id;

      SET v_horas_descanso = TIMESTAMPDIFF(
        MINUTE, 
        CONCAT(DATE_SUB(NEW.esd_data, INTERVAL 1 DAY), ' ', v_saida_anterior),
        CONCAT(NEW.esd_data, ' ', v_entrada_atual)
      ) / 60.0;

      IF v_horas_descanso < 11.00 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Violação CLT Art. 66: O intervalo interjornada deve ser de no mínimo 11 horas consecutivas.';
      END IF;
    END IF;

  END IF;
END$$

DELIMITER ;

-- =============================================================================
-- PARTE 2: SEED COM DADOS REALISTAS DE FARMÁCIA E EXECUÇÃO DA ESCALA 5x2
-- =============================================================================

-- 1. Inserir Empresa Matriz
INSERT INTO `empresas` (
  `emp_id`, `emp_razao_social`, `emp_nome_fantasia`, `emp_cnpj`, `emp_email`, `emp_telefone`, 
  `emp_logradouro`, `emp_numero`, `emp_bairro`, `emp_cidade`, `emp_uf`, `emp_cep`, `emp_ativo`
) VALUES (
  1, 'Farma Viver Medicamentos e Perfumaria LTDA', 'Drogaria Farma Viver', '12345678000199', 
  'contato@farmaviver.com.br', '9232345678', 'Av. Djalma Batista', '1500', 'Chapada', 'Manaus', 'AM', '69050010', 1
) ON DUPLICATE KEY UPDATE `emp_nome_fantasia` = VALUES(`emp_nome_fantasia`);

-- 2. Inserir Cargos
INSERT INTO `cargos` (`car_id`, `emp_id`, `car_nome`, `car_cbo`, `car_descricao`) VALUES
(1, 1, 'Farmacêutico(a) Responsável', '2234-05', 'Responsabilidade técnica, dispensação de controlados e SNGPC'),
(2, 1, 'Balconista de Farmácia', '5211-30', 'Atendimento de balcão e dispensação de medicamentos isentos e correlatos'),
(3, 1, 'Operador(a) de Caixa', '4211-25', 'Recebimento de pagamentos, abertura e fechamento de terminal PDV'),
(4, 1, 'Auxiliar de Limpeza', '5143-20', 'Manutenção e assepsia das dependências da drogaria')
ON DUPLICATE KEY UPDATE `car_nome` = VALUES(`car_nome`);

-- 3. Inserir Turnos de Farmácia (Carga horária líquida: 8h48min por dia para fechar 44h semanais em 5 dias)
-- T1: Abertura (07:00 às 16:48 com 1h de almoço)
-- T2: Fechamento (13:12 às 23:00 com 1h de janta)
INSERT INTO `turnohorarios` (
  `tur_id`, `emp_id`, `tur_descricao`, `tur_entrada`, `tur_saida_intervalo`, 
  `tur_retorno_intervalo`, `tur_saida`, `tur_tolerancia_minutos`, `tur_ativo`
) VALUES 
(1, 1, 'Turno Abertura (Manhã)', '07:00:00', '12:00:00', '13:00:00', '16:48:00', 10, 1),
(2, 1, 'Turno Fechamento (Noite)', '13:12:00', '17:00:00', '18:00:00', '23:00:00', 10, 1)
ON DUPLICATE KEY UPDATE `tur_descricao` = VALUES(`tur_descricao`);

-- 4. Inserir Configurações de Escala 5x2
-- Regra 1: Folga padrão Sábado (7) e Domingo (1)
-- Regra 2: Folga alternada de meio de semana: Segunda (2) e Terça (3)
INSERT INTO `configuracoes_escala` (
  `cfg_id`, `emp_id`, `cfg_nome`, `cfg_tipo`, `cfg_dia_folga_1`, `cfg_dia_folga_2`, `cfg_ativo`
) VALUES 
(1, 1, '5x2 Tradicional (Sábado e Domingo)', 'fixa', 1, 7, 1),
(2, 1, '5x2 Balcão Revezamento (Segunda e Terça)', 'revezamento', 2, 3, 1)
ON DUPLICATE KEY UPDATE `cfg_nome` = VALUES(`cfg_nome`);

-- 5. Inserir Funcionários
INSERT INTO `funcionarios` (
  `fun_id`, `emp_id`, `car_id`, `tur_id_padrao`, `fun_matricula`, `fun_nome`, 
  `fun_cpf`, `fun_email`, `fun_data_admissao`, `fun_status`
) VALUES
(1, 1, 1, 1, 'MAT-001', 'Dra. Camila Nogueira', '11122233344', 'camila.farmacia@farmaviver.com.br', '2023-01-15', 'ativo'),
(2, 1, 2, 1, 'MAT-002', 'Bruno Siqueira (Balcão)', '22233344455', 'bruno.balcao@farmaviver.com.br', '2023-06-01', 'ativo'),
(3, 1, 2, 2, 'MAT-003', 'Larissa Mendes (Balcão)', '33344455566', 'larissa.balcao@farmaviver.com.br', '2024-02-10', 'ativo'),
(4, 1, 3, 1, 'MAT-004', 'Eduardo Costa (Caixa)', '44455566677', 'eduardo.caixa@farmaviver.com.br', '2024-05-20', 'ativo')
ON DUPLICATE KEY UPDATE `fun_nome` = VALUES(`fun_nome`);

-- 6. Inserir Período da Escala (Novembro de 2026)
INSERT INTO `escalas_periodos` (
  `esp_id`, `emp_id`, `esp_descricao`, `esp_data_inicio`, `esp_data_fim`, `esp_status`
) VALUES (
  1, 1, 'Escala Mensal Operacional - Novembro/2026', '2026-11-01', '2026-11-30', 'publicada'
) ON DUPLICATE KEY UPDATE `esp_descricao` = VALUES(`esp_descricao`);

-- 7. Execução da Stored Procedure para gerar os dias dos colaboradores
-- Funcionária 1: Farmacêutica (Turno Manhã, folgas Sábado e Domingo)
CALL `sp_gerar_escala_5x2_funcionario`(1, 1, 1, 1);

-- Funcionário 2: Balconista Manhã (folgas Segunda e Terça para cobrir final de semana)
CALL `sp_gerar_escala_5x2_funcionario`(1, 2, 2, 1);

-- Funcionária 3: Balconista Noite (folgas Sábado e Domingo)
CALL `sp_gerar_escala_5x2_funcionario`(1, 3, 1, 2);

-- Funcionário 4: Caixa (folgas Sábado e Domingo)
CALL `sp_gerar_escala_5x2_funcionario`(1, 4, 1, 1);

-- =============================================================================
-- CONSULTAS DE VERIFICAÇÃO
-- =============================================================================

-- Exibe os primeiros 10 registros gerados na visão consolidada
SELECT 
  fun_nome, 
  car_nome, 
  esd_data, 
  esd_tipo_dia, 
  tur_descricao, 
  tur_entrada, 
  tur_saida
FROM `vw_escala_diaria_completa`
WHERE `emp_id` = 1
ORDER BY `fun_id`, `esd_data`
LIMIT 10;

-- Exibe o total acumulado do período
SELECT * FROM `vw_resumo_mensal_escalas` WHERE `esp_id` = 1;

-- fim triggers --

-- início tabela de feriados e adaptação da procedure de geração --

USE `farmaviver`;

-- =============================================================================
-- PARTE 1: TABELA DE FERIADOS E ADAPTAÇÃO DA PROCEDURE DE GERAÇÃO
-- =============================================================================

-- 1. Criação da tabela de feriados
-- Prefixo: fer_
CREATE TABLE IF NOT EXISTS `feriados` (
  `fer_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL indica feriado geral/nacional; valor específico restringe à empresa',
  `fer_data` DATE NOT NULL,
  `fer_descricao` VARCHAR(100) NOT NULL,
  `fer_tipo` ENUM('nacional', 'estadual', 'municipal', 'facultativo') NOT NULL DEFAULT 'nacional',
  `fer_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `uk_feriados_emp_data` UNIQUE (`emp_id`, `fer_data`),
  CONSTRAINT `fk_feriados_empresas` FOREIGN KEY (`emp_id`) 
    REFERENCES `empresas` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carga de feriados de exemplo (Novembro/2026 e calendário comum)
INSERT INTO `feriados` (`emp_id`, `fer_data`, `fer_descricao`, `fer_tipo`) VALUES
(NULL, '2026-11-02', 'Finados', 'nacional'),
(NULL, '2026-11-15', 'Proclamação da República', 'nacional'),
(NULL, '2026-11-20', 'Dia Nacional de Zumbi e da Consciência Negra', 'nacional'),
(1,    '2026-12-08', 'Nossa Senhora da Conceição (Padroeira de Manaus)', 'municipal')
ON DUPLICATE KEY UPDATE `fer_descricao` = VALUES(`fer_descricao`);

DELIMITER $$

-- 2. Atualização da procedure sp_gerar_escala_5x2_funcionario
-- Regra de prioridade:
--  a) Se cair em dia de folga do regime 5x2 -> Mantém 'folga' (DSR).
--  b) Se for dia útil, mas coincidir com feriado -> Marca 'feriado' (sem turno obrigatório).
--  c) Caso contrário -> Marca 'trabalho' com o turno regular.
CREATE OR REPLACE PROCEDURE `sp_gerar_escala_5x2_funcionario`(
  IN p_esp_id INT UNSIGNED,
  IN p_fun_id INT UNSIGNED,
  IN p_cfg_id INT UNSIGNED,
  IN p_tur_id INT UNSIGNED
)
BEGIN
  DECLARE v_emp_id INT UNSIGNED;
  DECLARE v_data_inicio DATE;
  DECLARE v_data_fim DATE;
  DECLARE v_data_atual DATE;
  DECLARE v_folga_1 TINYINT UNSIGNED;
  DECLARE v_folga_2 TINYINT UNSIGNED;
  DECLARE v_dia_semana TINYINT UNSIGNED;
  DECLARE v_turno_final INT UNSIGNED;
  DECLARE v_is_feriado INT DEFAULT 0;
  DECLARE v_feriado_nome VARCHAR(100);

  -- Identificar empresa e datas do período
  SELECT ep.emp_id, ep.esp_data_inicio, ep.esp_data_fim 
    INTO v_emp_id, v_data_inicio, v_data_fim
    FROM escalas_periodos ep
   WHERE ep.esp_id = p_esp_id;

  -- Obter dias de folga da regra 5x2
  SELECT cfg_dia_folga_1, cfg_dia_folga_2 
    INTO v_folga_1, v_folga_2
    FROM configuracoes_escala 
   WHERE cfg_id = p_cfg_id;

  -- Definir turno: parâmetro ou default do colaborador
  IF p_tur_id IS NULL THEN
    SELECT tur_id_padrao INTO v_turno_final FROM funcionarios WHERE fun_id = p_fun_id;
  ELSE
    SET v_turno_final = p_tur_id;
  END IF;

  SET v_data_atual = v_data_inicio;

  START TRANSACTION;
  
  WHILE v_data_atual <= v_data_fim DO
    SET v_dia_semana = DAYOFWEEK(v_data_atual);
    SET v_is_feriado = 0;
    SET v_feriado_nome = NULL;

    -- Verificar se a data consta como feriado (geral ou específico da empresa)
    SELECT 1, fer_descricao 
      INTO v_is_feriado, v_feriado_nome
      FROM feriados
     WHERE fer_data = v_data_atual 
       AND (emp_id IS NULL OR emp_id = v_emp_id)
     LIMIT 1;

    IF v_dia_semana = v_folga_1 OR v_dia_semana = v_folga_2 THEN
      -- Folga contratual 5x2
      INSERT INTO escalas_diarias (esp_id, fun_id, tur_id, esd_data, esd_tipo_dia, esd_observacao)
      VALUES (p_esp_id, p_fun_id, NULL, v_data_atual, 'folga', 'Folga regular 5x2')
      ON DUPLICATE KEY UPDATE 
        tur_id = NULL,
        esd_tipo_dia = 'folga',
        esd_observacao = 'Folga regular 5x2';

    ELSEIF v_is_feriado = 1 THEN
      -- Dia de escala que coincide com feriado
      INSERT INTO escalas_diarias (esp_id, fun_id, tur_id, esd_data, esd_tipo_dia, esd_observacao)
      VALUES (p_esp_id, p_fun_id, NULL, v_data_atual, 'feriado', CONCAT('Feriado: ', v_feriado_nome))
      ON DUPLICATE KEY UPDATE 
        tur_id = NULL,
        esd_tipo_dia = 'feriado',
        esd_observacao = CONCAT('Feriado: ', v_feriado_nome);

    ELSE
      -- Dia normal trabalhado
      INSERT INTO escalas_diarias (esp_id, fun_id, tur_id, esd_data, esd_tipo_dia, esd_observacao)
      VALUES (p_esp_id, p_fun_id, v_turno_final, v_data_atual, 'trabalho', 'Turno programado')
      ON DUPLICATE KEY UPDATE 
        tur_id = v_turno_final,
        esd_tipo_dia = 'trabalho',
        esd_observacao = 'Turno programado';
    END IF;

    SET v_data_atual = DATE_ADD(v_data_atual, INTERVAL 1 DAY);
  END WHILE;

  COMMIT;
END$$

DELIMITER ;

-- =============================================================================
-- PARTE 2: PROCEDURE PARA EXPORTAÇÃO EM GRADE/MATRIZ (DIAS 01 A 31)
-- =============================================================================

DELIMITER $$

-- Procedure que constrói dinamicamente uma tabela pivô por período:
-- Linhas: Matrícula, Colaborador, Cargo
-- Colunas: Dias 01, 02, ..., N do mês (com legenda T=Trabalho, F=Folga, FER=Feriado)
CREATE OR REPLACE PROCEDURE `sp_relatorio_escala_matriz`(
  IN p_esp_id INT UNSIGNED
)
BEGIN
  DECLARE v_data_inicio DATE;
  DECLARE v_data_fim DATE;
  DECLARE v_data_iter DATE;
  DECLARE v_dia_str VARCHAR(2);
  DECLARE v_sql_colunas LONGTEXT DEFAULT '';
  DECLARE v_sql_completo LONGTEXT;

  -- Obter o intervalo do período
  SELECT esp_data_inicio, esp_data_fim 
    INTO v_data_inicio, v_data_fim
    FROM escalas_periodos 
   WHERE esp_id = p_esp_id;

  IF v_data_inicio IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Período informado não existe.';
  END IF;

  SET v_data_iter = v_data_inicio;

  -- Monta dinamicamente os CASE WHEN de cada dia do mês
  WHILE v_data_iter <= v_data_fim DO
    SET v_dia_str = LPAD(DAY(v_data_iter), 2, '0');
    
    SET v_sql_colunas = CONCAT(
      v_sql_colunas,
      ", MAX(CASE WHEN ed.esd_data = '", v_data_iter, "' THEN ",
      "  CASE ",
      "    WHEN ed.esd_tipo_dia = 'folga' THEN 'F' ",
      "    WHEN ed.esd_tipo_dia = 'feriado' THEN 'FER' ",
      "    WHEN ed.esd_tipo_dia = 'trabalho' THEN IFNULL(t.tur_descricao, 'T') ",
      "    ELSE ed.esd_tipo_dia ",
      "  END ",
      "  ELSE '-' END) AS `D_", v_dia_str, "`"
    );

    SET v_data_iter = DATE_ADD(v_data_iter, INTERVAL 1 DAY);
  END WHILE;

  -- Consolidação da consulta pivô
  SET @v_sql_completo = CONCAT(
    "SELECT ",
    "  f.fun_matricula AS Matricula, ",
    "  f.fun_nome AS Funcionario, ",
    "  c.car_nome AS Cargo ",
    v_sql_colunas,
    " FROM escalas_diarias ed ",
    " JOIN funcionarios f ON ed.fun_id = f.fun_id ",
    " JOIN cargos c ON f.car_id = c.car_id ",
    " LEFT JOIN turnohorarios t ON ed.tur_id = t.tur_id ",
    " WHERE ed.esp_id = ", p_esp_id,
    " GROUP BY f.fun_id, f.fun_matricula, f.fun_nome, c.car_nome ",
    " ORDER BY c.car_nome, f.fun_nome;"
  );

  -- Preparação e execução do statement dinâmico
  PREPARE stmt FROM @v_sql_completo;
  EXECUTE stmt;
  DEALLOCATE PREPARE stmt;
END$$

DELIMITER ;

-- =============================================================================
-- TESTES DE EXECUÇÃO
-- =============================================================================

-- 1. Regenerar a escala de Novembro/2026 para os funcionários (agora com feriados)
CALL `sp_gerar_escala_5x2_funcionario`(1, 1, 1, 1);
CALL `sp_gerar_escala_5x2_funcionario`(1, 2, 2, 1);
CALL `sp_gerar_escala_5x2_funcionario`(1, 3, 1, 2);
CALL `sp_gerar_escala_5x2_funcionario`(1, 4, 1, 1);

-- 2. Visualizar os dias de feriado identificados na escala
SELECT 
  ed.esd_data,
  f.fun_nome,
  c.car_nome,
  ed.esd_tipo_dia,
  ed.esd_observacao
FROM escalas_diarias ed
JOIN funcionarios f ON ed.fun_id = f.fun_id
JOIN cargos c ON f.car_id = c.car_id
WHERE ed.esp_id = 1 AND ed.esd_tipo_dia = 'feriado'
ORDER BY ed.esd_data, f.fun_nome;

-- 3. Visualizar a matriz mensal completa em formato grade/pivot
CALL `sp_relatorio_escala_matriz`(1);

-- fim da tabela de feriados e adaptação da procedure de geração --

