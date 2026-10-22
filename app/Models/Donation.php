<?php

namespace App\Models;

use App\Enums\DonationStatus;
use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Doacao por Pix (F6). Nao compra plano nem consome cota.
 *
 * O `ulid` e o identificador publico da pagina de status. `name` e `email` sao
 * opcionais: doacao anonima e valida, e o recibo depende de o doador ter
 * deixado e-mail.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $user_id
 * @property string|null $name
 * @property string|null $email
 * @property string $locale
 * @property int $amount_cents
 * @property string $currency
 * @property DonationStatus $status
 * @property string $gateway
 * @property string|null $gateway_ref
 * @property string|null $qr_code
 * @property string|null $qr_code_base64
 * @property string|null $ticket_url
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id',
    'name',
    'email',
    'locale',
    'amount_cents',
    'currency',
    'status',
    'gateway',
    'gateway_ref',
    'qr_code',
    'qr_code_base64',
    'ticket_url',
    'expires_at',
    'paid_at',
])]
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $donation): void {
            $donation->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DonationStatus::class,
            'amount_cents' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * A pagina de status continua valida enquanto a doacao existe; o que
     * expira e o QR, nao o registro.
     */
    public function isPayable(): bool
    {
        return $this->status === DonationStatus::Pending
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Estado que a tela deve mostrar, sem alterar o banco.
     *
     * A expiracao e preguicosa, como no checkout: um Pix dinamico pendente com
     * prazo vencido ja nao vale, mesmo antes da varredura de `payments:expire`.
     * O Pix estatico nao expira - nao existe prazo para ele.
     */
    public function effectiveStatus(): DonationStatus
    {
        if (
            $this->status === DonationStatus::Pending
            && $this->gateway !== 'manual'
            && $this->expires_at !== null
            && $this->expires_at->isPast()
        ) {
            return DonationStatus::Expired;
        }

        return $this->status;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
