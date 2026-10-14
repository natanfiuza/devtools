<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Database\Factories\UsageLedgerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lancamento do livro-razao do consumo. A cota e sempre somada a partir daqui
 * (App\Support\Quota) - nunca lida de uma coluna de saldo.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $ai_request_id
 * @property LedgerEntryType $type
 * @property int $tokens
 * @property int $uses
 * @property Carbon|null $created_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'ai_request_id', 'type', 'tokens', 'uses'])]
class UsageLedger extends Model
{
    /** @use HasFactory<UsageLedgerFactory> */
    use HasFactory;

    /**
     * `usage_ledger` e singular de proposito - e um livro-razao, um so. O nome
     * nao sai do plural automatico do Eloquent.
     *
     * @var string
     */
    protected $table = 'usage_ledger';

    /**
     * O livro-razao so cresce: nao existe atualizacao de lancamento.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'tokens' => 'integer',
            'uses' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
