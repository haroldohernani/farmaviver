<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TurnoHorario extends Model
{
    protected $table = 'turnohorarios';
    protected $primaryKey = 'tur_id';

    const CREATED_AT = 'tur_created_at';
    const UPDATED_AT = 'tur_updated_at';

    protected $fillable = [
        'emp_id',
        'tur_descricao',
        'tur_entrada',
        'tur_saida_intervalo',
        'tur_retorno_intervalo',
        'tur_saida',
        'tur_tolerancia_minutos',
        'tur_ativo',
    ];

    protected $casts = [
        'tur_tolerancia_minutos' => 'integer',
        'tur_ativo' => 'boolean',
        'tur_created_at' => 'datetime',
        'tur_updated_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }

    public function funcionariosPadrao(): HasMany
    {
        return $this->hasMany(Funcionario::class, 'tur_id_padrao', 'tur_id');
    }
}

