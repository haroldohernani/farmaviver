<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracaoEscala extends Model
{
    protected $table = 'configuracoes_escala';
    protected $primaryKey = 'cfg_id';

    const CREATED_AT = 'cfg_created_at';
    const UPDATED_AT = 'cfg_updated_at';

    protected $fillable = [
        'emp_id',
        'cfg_nome',
        'cfg_tipo',
        'cfg_dia_folga_1',
        'cfg_dia_folga_2',
        'cfg_ativo',
    ];

    protected $casts = [
        'cfg_dia_folga_1' => 'integer',
        'cfg_dia_folga_2' => 'integer',
        'cfg_ativo' => 'boolean',
        'cfg_created_at' => 'datetime',
        'cfg_updated_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }
}

