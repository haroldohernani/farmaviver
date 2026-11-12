<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Funcionario extends Authenticatable
{
    use Notifiable;

    protected $table = 'funcionarios';
    protected $primaryKey = 'fun_id';

    const CREATED_AT = 'fun_created_at';
    const UPDATED_AT = 'fun_updated_at';

    protected $fillable = [
        'emp_id',
        'car_id',
        'tur_id_padrao',
        'fun_matricula',
        'fun_nome',
        'fun_cpf',
        'fun_sexo',
        'fun_senha',
        'fun_perfil',
        'fun_email',
        'fun_telefone',
        'fun_data_admissao',
        'fun_data_demissao',
        'fun_status',
        'fun_ultimo_login',
        'fun_remember_token',
    ];

    protected $hidden = [
        'fun_senha',
        'fun_remember_token',
    ];

    protected $casts = [
        'fun_data_admissao' => 'date:Y-m-d',
        'fun_data_demissao' => 'date:Y-m-d',
        'fun_ultimo_login' => 'datetime',
        'fun_created_at' => 'datetime',
        'fun_updated_at' => 'datetime',
    ];

    protected $appends = [
        'fora_escala_5x2',
    ];

    public function getForaEscala5x2Attribute(): bool
    {
        return $this->isAdministradorForaEscala();
    }

    public function getAuthPassword()
    {
        return $this->fun_senha;
    }

    public function getRememberTokenName()
    {
        return 'fun_remember_token';
    }

    /**
     * Regra de Negócio (02/11/2026):
     * Funcionário(a) que possui Cargo Administrador, Perfil de acesso Administrador (admin)
     * e Status Operacional Ativo (ativo) não pode entrar na escala 5x2.
     */
    public function isAdministradorForaEscala(): bool
    {
        $nomeCargo = mb_strtolower(trim((string) ($this->cargo?->car_nome ?? '')));
        return $nomeCargo === 'administrador'
            && $this->fun_perfil === 'admin'
            && $this->fun_status === 'ativo';
    }

    /**
     * Escopo para filtrar apenas funcionários elegíveis a participar da escala 5x2.
     */
    public function scopeElegiveisEscala(Builder $query): Builder
    {
        return $query->whereDoesntHave('cargo', function (Builder $q) {
            $q->whereRaw("LOWER(TRIM(car_nome)) = 'administrador'");
        })->orWhere(function (Builder $q) {
            $q->whereHas('cargo', function (Builder $qc) {
                $qc->whereRaw("LOWER(TRIM(car_nome)) = 'administrador'");
            })->where(function (Builder $qf) {
                $qf->where('fun_perfil', '!=', 'admin')
                   ->orWhere('fun_status', '!=', 'ativo');
            });
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'car_id', 'car_id');
    }

    public function turnoPadrao(): BelongsTo
    {
        return $this->belongsTo(TurnoHorario::class, 'tur_id_padrao', 'tur_id');
    }

    public function perfis(): BelongsToMany
    {
        return $this->belongsToMany(Perfil::class, 'funcionario_perfis', 'fun_id', 'prf_id')
            ->withPivot('fup_atribuido_em');
    }

    public function escalasDiarias(): HasMany
    {
        return $this->hasMany(EscalaDiaria::class, 'fun_id', 'fun_id');
    }
}
