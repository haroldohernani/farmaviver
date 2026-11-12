<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;

class DatabaseProcedureService
{
    /**
     * Executa uma Stored Procedure que retorna resultados e libera o cursor PDO.
     */
    protected function callProcedureSelect(string $sql, array $bindings = []): array
    {
        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $results;
    }

    /**
     * Executa uma Stored Procedure de escrita/ação e libera o cursor PDO.
     */
    protected function callProcedureStatement(string $sql, array $bindings = []): void
    {
        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $stmt->closeCursor();
    }

    /**
     * sp_autenticar_funcionario(p_cpf)
     */
    public function autenticarFuncionario(string $cpf): ?array
    {
        $rows = $this->callProcedureSelect('CALL sp_autenticar_funcionario(?)', [$cpf]);
        return $rows[0] ?? null;
    }

    /**
     * sp_registrar_tentativa_login(p_fun_id, p_cpf, p_status, p_ip, p_user_agent, p_detalhes)
     */
    public function registrarTentativaLogin(
        ?int $funId,
        string $cpf,
        string $status,
        string $ip,
        ?string $userAgent,
        ?string $detalhes
    ): void {
        $this->callProcedureStatement(
            'CALL sp_registrar_tentativa_login(?, ?, ?, ?, ?, ?)',
            [
                $funId,
                substr($cpf, 0, 14),
                $status,
                substr($ip, 0, 45),
                $userAgent ? substr($userAgent, 0, 255) : null,
                $detalhes ? substr($detalhes, 0, 255) : null,
            ]
        );
    }

    /**
     * sp_registrar_login_sucesso(p_fun_id)
     */
    public function registrarLoginSucesso(int $funId): void
    {
        $this->callProcedureStatement('CALL sp_registrar_login_sucesso(?)', [$funId]);
    }

    /**
     * sp_obter_permissoes_funcionario(p_fun_id)
     */
    public function obterPermissoesFuncionario(int $funId): array
    {
        $rows = $this->callProcedureSelect('CALL sp_obter_permissoes_funcionario(?)', [$funId]);
        if (empty($rows) || empty($rows[0]['permissoes_json'])) {
            return [
                'funcionario_id' => $funId,
                'perfis' => [],
                'permissoes' => [],
            ];
        }

        $decoded = json_decode($rows[0]['permissoes_json'], true);
        return [
            'funcionario_id' => $decoded['funcionario_id'] ?? $funId,
            'nome' => $decoded['nome'] ?? '',
            'perfis' => $decoded['perfis'] ?? [],
            'permissoes' => $decoded['permissoes'] ?? [],
        ];
    }

    /**
     * sp_gerar_escala_5x2_funcionario(p_esp_id, p_fun_id, p_cfg_id, p_tur_id)
     */
    public function gerarEscala5x2Funcionario(int $espId, int $funId, int $cfgId, ?int $turId = null): void
    {
        $this->callProcedureStatement(
            'CALL sp_gerar_escala_5x2_funcionario(?, ?, ?, ?)',
            [$espId, $funId, $cfgId, $turId]
        );
    }

    /**
     * sp_aprovar_troca_escala(p_tro_id, p_gestor_id)
     */
    public function aprovarTrocaEscala(int $troId, int $gestorId): void
    {
        $this->callProcedureStatement(
            'CALL sp_aprovar_troca_escala(?, ?)',
            [$troId, $gestorId]
        );
    }

    /**
     * sp_relatorio_escala_matriz(p_esp_id)
     */
    public function relatorioEscalaMatriz(int $espId): array
    {
        return $this->callProcedureSelect('CALL sp_relatorio_escala_matriz(?)', [$espId]);
    }

    /**
     * View: vw_escala_diaria_completa
     */
    public function consultarEscalaDiariaCompleta(array $filters = []): array
    {
        $query = DB::table('vw_escala_diaria_completa');

        if (!empty($filters['emp_id'])) {
            $query->where('emp_id', $filters['emp_id']);
        }
        if (!empty($filters['fun_id'])) {
            $query->where('fun_id', $filters['fun_id']);
        }
        if (!empty($filters['data'])) {
            $query->where('esd_data', $filters['data']);
        }
        if (!empty($filters['data_inicio'])) {
            $query->where('esd_data', '>=', $filters['data_inicio']);
        }
        if (!empty($filters['data_fim'])) {
            $query->where('esd_data', '<=', $filters['data_fim']);
        }

        return $query->orderBy('esd_data')->orderBy('fun_nome')->get()->map(fn ($r) => (array) $r)->all();
    }

    /**
     * View: vw_resumo_mensal_escalas
     */
    public function consultarResumoMensalEscalas(?int $espId = null, ?string $mesCompetencia = null): array
    {
        $query = DB::table('vw_resumo_mensal_escalas');

        if ($espId !== null) {
            $query->where('esp_id', $espId);
        }
        if ($mesCompetencia !== null) {
            $query->where('mes_competencia', $mesCompetencia);
        }

        return $query->orderBy('fun_nome')->get()->map(fn ($r) => (array) $r)->all();
    }

    /**
     * View: vw_auditoria_folgas_domingo_mulheres
     */
    public function consultarAuditoriaDomingosMulheres(?int $espId = null, ?string $mesCompetencia = null): array
    {
        $query = DB::table('vw_auditoria_folgas_domingo_mulheres');

        if ($espId !== null) {
            $query->where('esp_id', $espId);
        }
        if ($mesCompetencia !== null) {
            $query->where('mes_competencia', $mesCompetencia);
        }

        return $query->orderBy('fun_nome')->get()->map(fn ($r) => (array) $r)->all();
    }
}

