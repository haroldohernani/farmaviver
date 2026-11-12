<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';
    protected $primaryKey = 'emp_id';

    const CREATED_AT = 'emp_created_at';
    const UPDATED_AT = 'emp_updated_at';

    protected $fillable = [
        'emp_razao_social',
        'emp_nome_fantasia',
        'emp_cnpj',
        'emp_email',
        'emp_celular',
        'emp_telefone',
        'emp_logradouro',
        'emp_numero',
        'emp_bairro',
        'emp_cidade',
        'emp_uf',
        'emp_cep',
        'emp_ativo',
    ];

    protected $casts = [
        'emp_ativo' => 'boolean',
        'emp_created_at' => 'datetime',
        'emp_updated_at' => 'datetime',
    ];

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class, 'emp_id', 'emp_id');
    }

    public function turnos(): HasMany
    {
        return $this->hasMany(TurnoHorario::class, 'emp_id', 'emp_id');
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class, 'emp_id', 'emp_id');
    }

    public function configuracoesEscala(): HasMany
    {
        return $this->hasMany(ConfiguracaoEscala::class, 'emp_id', 'emp_id');
    }

    public function escalasPeriodos(): HasMany
    {
        return $this->hasMany(EscalaPeriodo::class, 'emp_id', 'emp_id');
    }
}

