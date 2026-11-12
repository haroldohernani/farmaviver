<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feriado extends Model
{
    protected $table = 'feriados';
    protected $primaryKey = 'fer_id';

    const CREATED_AT = 'fer_created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'emp_id',
        'fer_data',
        'fer_descricao',
        'fer_tipo',
    ];

    protected $casts = [
        'fer_data' => 'date:Y-m-d',
        'fer_created_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'emp_id', 'emp_id');
    }
}

