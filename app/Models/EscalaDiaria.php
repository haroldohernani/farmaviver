<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalaDiaria extends Model
{
    protected $table = 'escalas_diarias';
    protected $primaryKey = 'esd_id';

    const CREATED_AT = 'esd_created_at';
    const UPDATED_AT = 'esd_updated_at';

    protected $fillable = [
        'esp_id',
        'fun_id',
        'tur_id',
        'esd_data',
        'esd_tipo_dia',
        'esd_observacao',
    ];

    protected $casts = [
        'esd_data' => 'date:Y-m-d',
        'esd_created_at' => 'datetime',
        'esd_updated_at' => 'datetime',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(EscalaPeriodo::class, 'esp_id', 'esp_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fun_id', 'fun_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(TurnoHorario::class, 'tur_id', 'tur_id');
    }
}

