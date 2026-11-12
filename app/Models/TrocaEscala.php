<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrocaEscala extends Model
{
    protected $table = 'trocas_escala';
    protected $primaryKey = 'tro_id';

    const CREATED_AT = 'tro_created_at';
    const UPDATED_AT = 'tro_updated_at';

    protected $fillable = [
        'fun_id_solicitante',
        'fun_id_substituto',
        'esd_id_origem',
        'esd_id_destino',
        'tro_motivo',
        'tro_status',
        'tro_aprovador_fun_id',
        'tro_aprovado_em',
    ];

    protected $casts = [
        'tro_aprovado_em' => 'datetime',
        'tro_created_at' => 'datetime',
        'tro_updated_at' => 'datetime',
    ];

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fun_id_solicitante', 'fun_id');
    }

    public function substituto(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fun_id_substituto', 'fun_id');
    }

    public function escalaOrigem(): BelongsTo
    {
        return $this->belongsTo(EscalaDiaria::class, 'esd_id_origem', 'esd_id');
    }

    public function escalaDestino(): BelongsTo
    {
        return $this->belongsTo(EscalaDiaria::class, 'esd_id_destino', 'esd_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'tro_aprovador_fun_id', 'fun_id');
    }
}

