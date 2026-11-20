<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feriado extends Model
{
    protected $table = 'feriados';
    protected $primaryKey = 'fer_id';

    const CREATED_AT = 'fer_created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'emp_id',
        'fer_data',
        'fer_descricao',
        'fer_tipo',
        'fer_status',
    ];

    protected $casts = [
        'fer_data' => 'date:Y-m-d',
        'fer_status' => 'integer',
        'fer_created_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }

    /**
     * Sugestão A (14/11/2026 - Item 3):
     * Calcula a data do Domingo de Páscoa para qualquer ano (Algoritmo de Meeus/Jones/Butcher),
     * sem depender da extensão calendar do PHP.
     */
    public static function calcularPascoa(int $ano): \Carbon\Carbon
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return \Carbon\Carbon::createFromDate($ano, $mes, $dia)->startOfDay();
    }

    /**
     * Sugestão A (14/11/2026 - Item 3):
     * Retorna o calendário oficial de feriados (Nacionais Fixos, Móveis e Estaduais/Municipais AM/Manaus) para o ano informado.
     */
    public static function obterCalendarioOficialAno(int $ano): array
    {
        $pascoa = self::calcularPascoa($ano);
        $carnaval = $pascoa->copy()->subDays(47)->format('Y-m-d');
        $sextaSanta = $pascoa->copy()->subDays(2)->format('Y-m-d');
        $corpusChristi = $pascoa->copy()->addDays(60)->format('Y-m-d');

        $feriados = [
            ['fer_data' => sprintf('%04d-01-01', $ano), 'fer_descricao' => 'Confraternização Universal (Ano Novo)', 'fer_tipo' => 'nacional'],
            ['fer_data' => $carnaval,                   'fer_descricao' => 'Carnaval', 'fer_tipo' => 'facultativo'],
            ['fer_data' => $sextaSanta,                 'fer_descricao' => 'Sexta-Feira Santa (Paixão de Cristo)', 'fer_tipo' => 'nacional'],
            ['fer_data' => sprintf('%04d-04-21', $ano), 'fer_descricao' => 'Tiradentes', 'fer_tipo' => 'nacional'],
            ['fer_data' => sprintf('%04d-05-01', $ano), 'fer_descricao' => 'Dia Mundial do Trabalho', 'fer_tipo' => 'nacional'],
            ['fer_data' => $corpusChristi,              'fer_descricao' => 'Corpus Christi', 'fer_tipo' => 'facultativo'],
            ['fer_data' => sprintf('%04d-09-05', $ano), 'fer_descricao' => 'Elevação do Amazonas à Categoria de Província', 'fer_tipo' => 'estadual'],
            ['fer_data' => sprintf('%04d-09-07', $ano), 'fer_descricao' => 'Independência do Brasil', 'fer_tipo' => 'nacional'],
            ['fer_data' => sprintf('%04d-10-12', $ano), 'fer_descricao' => 'Nossa Senhora Aparecida', 'fer_tipo' => 'nacional'],
            ['fer_data' => sprintf('%04d-10-24', $ano), 'fer_descricao' => 'Aniversário de Manaus', 'fer_tipo' => 'municipal'],
            ['fer_data' => sprintf('%04d-11-02', $ano), 'fer_descricao' => 'Finados', 'fer_tipo' => 'nacional'],
            ['fer_data' => sprintf('%04d-11-15', $ano), 'fer_descricao' => 'Proclamação da República', 'fer_tipo' => 'nacional'],
            ['fer_data' => sprintf('%04d-11-20', $ano), 'fer_descricao' => 'Dia Nacional de Zumbi e da Consciência Negra', 'fer_tipo' => 'nacional'],
            ['fer_data' => sprintf('%04d-12-08', $ano), 'fer_descricao' => 'Nossa Senhora da Conceição (Padroeira de Manaus)', 'fer_tipo' => 'municipal'],
            ['fer_data' => sprintf('%04d-12-25', $ano), 'fer_descricao' => 'Natal', 'fer_tipo' => 'nacional'],
        ];

        usort($feriados, fn (array $a, array $b) => strcmp($a['fer_data'], $b['fer_data']));

        return $feriados;
    }

    /**
     * Item 3 + Sugestão A (14/11/2026):
     * Gera automaticamente os feriados oficiais compreendidos entre $dataInicio e $dataFim
     * para todas as empresas cadastradas, respeitando a constraint uk_feriados_emp_data (emp_id, fer_data).
     */
    public static function gerarFeriadosDoPeriodo(string $dataInicio, string $dataFim): int
    {
        $ini = substr(trim($dataInicio), 0, 10);
        $fim = substr(trim($dataFim), 0, 10);
        if (!$ini || !$fim || $fim < $ini) {
            return 0;
        }

        $anoIni = (int) substr($ini, 0, 4);
        $anoFim = (int) substr($fim, 0, 4);
        if ($anoIni < 2000 || $anoFim > 2100) {
            return 0;
        }

        $empresasIds = Empresa::orderBy('emp_id')->pluck('emp_id')->all();
        if (empty($empresasIds)) {
            return 0;
        }

        $feriadosPeriodo = [];
        for ($ano = $anoIni; $ano <= $anoFim; $ano++) {
            foreach (self::obterCalendarioOficialAno($ano) as $f) {
                if ($f['fer_data'] >= $ini && $f['fer_data'] <= $fim) {
                    $feriadosPeriodo[$f['fer_data']] = $f;
                }
            }
        }

        foreach ($feriadosPeriodo as $dataIso => $dadosFer) {
            // Sugestão B (17/11/2026): Preserva o fer_status caso o feriado já exista em alguma empresa (ex.: inativado com 0)
            $statusExistente = self::whereDate('fer_data', $dataIso)->value('fer_status');
            $statusAplicar = $statusExistente !== null ? (int) $statusExistente : 1;

            foreach ($empresasIds as $idEmp) {
                $existe = self::where('emp_id', $idEmp)
                    ->whereDate('fer_data', $dataIso)
                    ->exists();

                if (!$existe) {
                    self::create([
                        'emp_id' => $idEmp,
                        'fer_data' => $dataIso,
                        'fer_descricao' => $dadosFer['fer_descricao'],
                        'fer_tipo' => $dadosFer['fer_tipo'],
                        'fer_status' => $statusAplicar,
                    ]);
                }
            }
        }

        return count($feriadosPeriodo);
    }

    /**
     * Item 3 + Sugestões A e C (14/11/2026):
     * Garante que todos os períodos cadastrados tenham seus feriados gerados e que todos os
     * feriados existam na tabela `feriados` para cada empresa cadastrada (ex.: emp_id = 1, emp_id = 2),
     * convertendo eventuais registros legados com emp_id = NULL para registros explícitos por empresa.
     */
    public static function sincronizarEntreEmpresas(): void
    {
        $empresasIds = Empresa::orderBy('emp_id')->pluck('emp_id')->all();
        if (empty($empresasIds)) {
            return;
        }

        // 1. Garante que todos os Períodos de Escala já cadastrados tenham os feriados do seu intervalo gerados
        $periodos = EscalaPeriodo::orderBy('esp_data_inicio')->get();
        foreach ($periodos as $per) {
            $ini = $per->esp_data_inicio instanceof \Carbon\CarbonInterface
                ? $per->esp_data_inicio->format('Y-m-d')
                : substr((string) $per->esp_data_inicio, 0, 10);
            $fim = $per->esp_data_fim instanceof \Carbon\CarbonInterface
                ? $per->esp_data_fim->format('Y-m-d')
                : substr((string) $per->esp_data_fim, 0, 10);
            self::gerarFeriadosDoPeriodo($ini, $fim);
        }

        // 2. Coleta todos os feriados únicos por data (fer_data) existentes na tabela
        $todosFeriados = self::orderBy('fer_data')->orderBy('fer_id')->get();
        if ($todosFeriados->isEmpty()) {
            return;
        }

        $modelosPorData = [];
        foreach ($todosFeriados as $fer) {
            $dtIso = $fer->fer_data instanceof \Carbon\CarbonInterface
                ? $fer->fer_data->format('Y-m-d')
                : substr((string) $fer->fer_data, 0, 10);

            if (!isset($modelosPorData[$dtIso])) {
                $modelosPorData[$dtIso] = [
                    'fer_data' => $dtIso,
                    'fer_descricao' => trim($fer->fer_descricao),
                    'fer_tipo' => $fer->fer_tipo ?: 'nacional',
                    'fer_status' => (int) ($fer->fer_status ?? 1),
                ];
            }
        }

        // 3. Normaliza registros com emp_id NULL (atribuindo à primeira empresa ou removendo se já existir)
        $primeiraEmpId = (int) $empresasIds[0];
        $nulos = self::whereNull('emp_id')->get();
        foreach ($nulos as $nulo) {
            $dtIso = $nulo->fer_data instanceof \Carbon\CarbonInterface
                ? $nulo->fer_data->format('Y-m-d')
                : substr((string) $nulo->fer_data, 0, 10);

            $jaTemNaPrimeira = self::where('emp_id', $primeiraEmpId)
                ->whereDate('fer_data', $dtIso)
                ->exists();

            if ($jaTemNaPrimeira) {
                $nulo->delete();
            } else {
                $nulo->update(['emp_id' => $primeiraEmpId]);
            }
        }

        // 4. Garante que cada empresa possua seu registro em `feriados` para todas as datas catalogadas
        foreach ($empresasIds as $idEmp) {
            $datasExistentes = self::where('emp_id', $idEmp)
                ->get()
                ->map(fn (self $f) => $f->fer_data instanceof \Carbon\CarbonInterface
                    ? $f->fer_data->format('Y-m-d')
                    : substr((string) $f->fer_data, 0, 10))
                ->flip()
                ->all();

            foreach ($modelosPorData as $dtIso => $modelo) {
                if (!isset($datasExistentes[$dtIso])) {
                    self::create([
                        'emp_id' => $idEmp,
                        'fer_data' => $modelo['fer_data'],
                        'fer_descricao' => $modelo['fer_descricao'],
                        'fer_tipo' => $modelo['fer_tipo'],
                        'fer_status' => $modelo['fer_status'],
                    ]);
                }
            }
        }
    }
}

