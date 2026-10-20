<?php

namespace App\Models;

use Database\Factories\AiCacheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Resposta da IA guardada por `prompt_hash` (SPEC secao 6).
 *
 * O hash cobre o payload canonico inteiro, modelo e versao do prompt. A
 * resposta so chega aqui depois de passar pelo contrato e pelo gate; o payload
 * canonico fica comprimido para o job conseguir reidratar a solicitacao.
 *
 * @property int $id
 * @property string $prompt_hash
 * @property string $canonical_payload
 * @property string $response_json
 * @property string $model
 * @property int $prompt_version
 * @property int $tokens_in
 * @property int $tokens_out
 * @property int $hits
 * @property Carbon|null $last_hit_at
 * @property Carbon|null $created_at
 * @property Carbon $expires_at
 */
#[Fillable([
    'prompt_hash',
    'canonical_payload',
    'response_json',
    'model',
    'prompt_version',
    'tokens_in',
    'tokens_out',
    'hits',
    'last_hit_at',
    'created_at',
    'expires_at',
])]
class AiCache extends Model
{
    /** @use HasFactory<AiCacheFactory> */
    use HasFactory;

    /**
     * O nome nao sai do plural automatico do Eloquent.
     *
     * @var string
     */
    protected $table = 'ai_cache';

    /**
     * O cache so e criado e lido; atualizar `hits` nao mexe em `updated_at`.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_version' => 'integer',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return array<string, mixed>
     */
    public function result(): array
    {
        $result = json_decode($this->response_json, true);

        return is_array($result) ? $result : [];
    }
}
