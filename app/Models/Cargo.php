<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cargo extends Model
{
    protected $table = 'cargos';
    protected $primaryKey = 'car_id';

    const CREATED_AT = 'car_created_at';
    const UPDATED_AT = 'car_updated_at';

    protected $fillable = [
        'emp_id',
        'car_nome',
        'car_descricao',
        'car_cbo',
        'car_ativo',
    ];

    protected $casts = [
        'car_ativo' => 'boolean',
        'car_created_at' => 'datetime',
        'car_updated_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class, 'car_id', 'car_id');
    }
}

