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

    /**
     * Itens 2 e 3 + Sugestões A e C (13/11/2026):
     * Garante que todos os cargos cadastrados estejam vinculados e disponíveis em todas as empresas (emp_id x cargo),
     * sem duplicidade de nome dentro da mesma empresa.
     */
    public static function sincronizarEntreEmpresas(): void
    {
        $empIds = Empresa::orderBy('emp_id')->pluck('emp_id')->all();
        if (empty($empIds)) {
            return;
        }

        $todosCargos = self::orderBy('car_id')->get();
        $catalogoPorNome = [];

        foreach ($todosCargos as $c) {
            $chave = mb_strtolower(trim((string) $c->car_nome));
            if ($chave !== '' && !isset($catalogoPorNome[$chave])) {
                $catalogoPorNome[$chave] = [
                    'car_nome' => trim((string) $c->car_nome),
                    'car_descricao' => $c->car_descricao,
                    'car_cbo' => $c->car_cbo,
                    'car_ativo' => (bool) $c->car_ativo,
                ];
            }
        }

        if (empty($catalogoPorNome)) {
            $padroes = [
                ['car_nome' => 'Administrador', 'car_descricao' => 'Gestão executiva e administração geral da rede/unidade', 'car_cbo' => '1421-05', 'car_ativo' => true],
                ['car_nome' => 'Farmacêutico(a) Responsável', 'car_descricao' => 'Responsável técnico(a) pela assistência farmacêutica e SNGPC', 'car_cbo' => '2234-05', 'car_ativo' => true],
                ['car_nome' => 'Balconista de Farmácia', 'car_descricao' => 'Atendimento ao cliente e dispensação de medicamentos sob supervisão', 'car_cbo' => '5211-30', 'car_ativo' => true],
                ['car_nome' => 'Operador(a) de Caixa', 'car_descricao' => 'Operação de PDV, recebimentos e fechamento de caixa', 'car_cbo' => '4211-25', 'car_ativo' => true],
                ['car_nome' => 'Auxiliar de Estoque / Repositor', 'car_descricao' => 'Recebimento de mercadorias, controle de validade (PVPS) e organização', 'car_cbo' => '4141-05', 'car_ativo' => true],
            ];
            foreach ($padroes as $p) {
                $catalogoPorNome[mb_strtolower($p['car_nome'])] = $p;
            }
        }

        foreach ($empIds as $empId) {
            $existentesNaEmpresa = self::where('emp_id', $empId)
                ->get()
                ->keyBy(fn (self $item) => mb_strtolower(trim((string) $item->car_nome)));

            foreach ($catalogoPorNome as $chave => $dadosCargo) {
                if (!$existentesNaEmpresa->has($chave)) {
                    self::create(array_merge($dadosCargo, [
                        'emp_id' => (int) $empId,
                    ]));
                }
            }
        }
    }
}

