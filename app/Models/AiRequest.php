<?php

namespace App\Models;

use App\Enums\AiFailureCode;
use App\Enums\AiRequestStatus;
use App\Enums\QuotaUnit;
use App\Enums\SchemaSource;
use App\Enums\SqlDialect;
use Database\Factories\AiRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma solicitacao do AI SQL Builder.
 *
 * Nasce no POST (`queued`), passa por `processing` no job e termina em `done`
 * ou `failed`. O `canonical_payload` so existe enquanto o job precisa dos dados
 * e e limpo na liquidacao; `response_json` guarda o contrato ja validado.
 *
 * `reserved_tokens`/`reserved_uses` e `quota_unit` registram o que foi debitado
 * no inicio: a liquidacao ajusta ou estorna na mesma unidade, mesmo que a
 * assinatura mude no meio do voo.
 *
 * @property string $id
 * @property int $user_id
 * @property int $output_target_id
 * @property AiRequestStatus $status
 * @property SqlDialect $dialect
 * @property SchemaSource $schema_source
 * @property string $prompt_hash
 * @property int $prompt_version
 * @property string $prompt_template
 * @property string $model
 * @property int $estimated_tokens
 * @property int $reserved_tokens
 * @property int $reserved_uses
 * @property QuotaUnit $quota_unit
 * @property int|null $tokens_in
 * @property int|null $tokens_out
 * @property bool $cache_hit
 * @property AiFailureCode|null $error_code
 * @property bool $history_visible
 * @property string|null $canonical_payload
 * @property string|null $response_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read OutputTarget $outputTarget
 */
#[Fillable([
    'user_id',
    'output_target_id',
    'status',
    'dialect',
    'schema_source',
    'prompt_hash',
    'prompt_version',
    'prompt_template',
    'model',
    'estimated_tokens',
    'reserved_tokens',
    'reserved_uses',
    'quota_unit',
    'tokens_in',
    'tokens_out',
    'cache_hit',
    'error_code',
    'history_visible',
    'canonical_payload',
    'response_json',
])]
class AiRequest extends Model
{
    /** @use HasFactory<AiRequestFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * O id e ULID, nao a sequencia do banco.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiRequestStatus::class,
            'dialect' => SqlDialect::class,
            'schema_source' => SchemaSource::class,
            'quota_unit' => QuotaUnit::class,
            'error_code' => AiFailureCode::class,
            'prompt_version' => 'integer',
            'estimated_tokens' => 'integer',
            'reserved_tokens' => 'integer',
            'reserved_uses' => 'integer',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cache_hit' => 'boolean',
            'history_visible' => 'boolean',
        ];
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Solicitacoes ainda na fila ou em processamento - o que o reconciliador
     * procura quando um worker morre sem passar por `failed()`.
     *
     * @param  Builder<AiRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [
            AiRequestStatus::Queued->value,
            AiRequestStatus::Processing->value,
        ]);
    }

    /**
     * O contrato validado, ou nulo enquanto a solicitacao nao terminou.
     *
     * @return array<string, mixed>|null
     */
    public function result(): ?array
    {
        if ($this->response_json === null) {
            return null;
        }

        $result = json_decode($this->response_json, true);

        return is_array($result) ? $result : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OutputTarget, $this>
     */
    public function outputTarget(): BelongsTo
    {
        return $this->belongsTo(OutputTarget::class);
    }
}
