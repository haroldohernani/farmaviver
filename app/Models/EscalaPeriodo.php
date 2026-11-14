<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalaPeriodo extends Model
{
    protected $table = 'escalas_periodos';
    protected $primaryKey = 'esp_id';

    const CREATED_AT = 'esp_created_at';
    const UPDATED_AT = 'esp_updated_at';

    protected $fillable = [
        'emp_id',
        'esp_descricao',
        'esp_data_inicio',
        'esp_data_fim',
        'esp_status',
    ];

    protected $casts = [
        'esp_data_inicio' => 'date:Y-m-d',
        'esp_data_fim' => 'date:Y-m-d',
        'esp_created_at' => 'datetime',
        'esp_updated_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }

    public function escalasDiarias(): HasMany
    {
        return $this->hasMany(EscalaDiaria::class, 'esp_id', 'esp_id');
    }

    /**
     * Itens 1 e 2 + Sugestões A e C (14/11/2026):
     * Sincroniza todos os Períodos de Escala (escalas_periodos) entre todas as empresas cadastradas,
     * garantindo que qualquer período criado em uma empresa esteja disponível para todas as demais,
     * com prevenção contra duplicidade por descrição normalizada ou mesmo intervalo de datas.
     */
    public static function sincronizarEntreEmpresas(): void
    {
        $empresasIds = Empresa::orderBy('emp_id')->pluck('emp_id')->all();
        if (count($empresasIds) <= 1) {
            return;
        }

        $todosPeriodos = self::orderBy('esp_id')->get();
        if ($todosPeriodos->isEmpty()) {
            return;
        }

        // Agrupa modelos de períodos únicos (por descrição normalizada ou intervalo de datas)
        $modelosUnicos = [];
        foreach ($todosPeriodos as $per) {
            $chaveDesc = mb_strtolower(trim($per->esp_descricao), 'UTF-8');
            $dataIni = $per->esp_data_inicio instanceof \Carbon\CarbonInterface
                ? $per->esp_data_inicio->format('Y-m-d')
                : substr((string) $per->esp_data_inicio, 0, 10);
            $dataFim = $per->esp_data_fim instanceof \Carbon\CarbonInterface
                ? $per->esp_data_fim->format('Y-m-d')
                : substr((string) $per->esp_data_fim, 0, 10);

            $jaCatalogado = false;
            foreach ($modelosUnicos as $m) {
                if ($m['chave_desc'] === $chaveDesc || ($m['data_ini'] === $dataIni && $m['data_fim'] === $dataFim)) {
                    $jaCatalogado = true;
                    break;
                }
            }

            if (!$jaCatalogado) {
                $modelosUnicos[] = [
                    'chave_desc' => $chaveDesc,
                    'esp_descricao' => trim($per->esp_descricao),
                    'data_ini' => $dataIni,
                    'data_fim' => $dataFim,
                    'esp_status' => $per->esp_status ?: 'publicada',
                ];
            }
        }

        foreach ($empresasIds as $idEmp) {
            $periodosEmpresa = self::where('emp_id', $idEmp)->get();

            foreach ($modelosUnicos as $modelo) {
                $jaExiste = $periodosEmpresa->contains(function (self $p) use ($modelo) {
                    $descP = mb_strtolower(trim($p->esp_descricao), 'UTF-8');
                    $iniP = $p->esp_data_inicio instanceof \Carbon\CarbonInterface
                        ? $p->esp_data_inicio->format('Y-m-d')
                        : substr((string) $p->esp_data_inicio, 0, 10);
                    $fimP = $p->esp_data_fim instanceof \Carbon\CarbonInterface
                        ? $p->esp_data_fim->format('Y-m-d')
                        : substr((string) $p->esp_data_fim, 0, 10);

                    return $descP === $modelo['chave_desc']
                        || ($iniP === $modelo['data_ini'] && $fimP === $modelo['data_fim']);
                });

                if (!$jaExiste) {
                    $novo = self::create([
                        'emp_id' => $idEmp,
                        'esp_descricao' => $modelo['esp_descricao'],
                        'esp_data_inicio' => $modelo['data_ini'],
                        'esp_data_fim' => $modelo['data_fim'],
                        'esp_status' => $modelo['esp_status'],
                    ]);
                    $periodosEmpresa->push($novo);
                }
            }
        }
    }
}

