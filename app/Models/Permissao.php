<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permissao extends Model
{
    protected $table = 'permissoes';
    protected $primaryKey = 'per_id';

    const CREATED_AT = 'per_created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'per_chave',
        'per_modulo',
        'per_descricao',
    ];

    protected $casts = [
        'per_created_at' => 'datetime',
    ];

    public function perfis(): BelongsToMany
    {
        return $this->belongsToMany(Perfil::class, 'perfil_permissoes', 'per_id', 'prf_id');
    }
}

