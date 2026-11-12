<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Perfil extends Model
{
    protected $table = 'perfis';
    protected $primaryKey = 'prf_id';

    const CREATED_AT = 'prf_created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'emp_id',
        'prf_nome',
        'prf_descricao',
        'prf_ativo',
    ];

    protected $casts = [
        'prf_ativo' => 'boolean',
        'prf_created_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }

    public function permissoes(): BelongsToMany
    {
        return $this->belongsToMany(Permissao::class, 'perfil_permissoes', 'prf_id', 'per_id');
    }

    public function funcionarios(): BelongsToMany
    {
        return $this->belongsToMany(Funcionario::class, 'funcionario_perfis', 'prf_id', 'fun_id')
            ->withPivot('fup_atribuido_em');
    }
}

