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
}

