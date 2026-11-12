<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAutenticacao extends Model
{
    protected $table = 'logs_autenticacao';
    protected $primaryKey = 'log_id';

    const CREATED_AT = 'log_data_hora';
    const UPDATED_AT = null;

    protected $fillable = [
        'fun_id',
        'log_cpf_informado',
        'log_status',
        'log_ip',
        'log_user_agent',
        'log_detalhes',
    ];

    protected $casts = [
        'log_data_hora' => 'datetime',
    ];

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class, 'fun_id', 'fun_id');
    }
}

