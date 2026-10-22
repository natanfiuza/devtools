<?php

namespace App\Models;

use App\Enums\CheckoutMode;
use App\Enums\CheckoutStatus;
use Database\Factories\CheckoutSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Intencao de pagamento em andamento (F6).
 *
 * Existe para separar "a pessoa foi pagar" de "a pessoa pagou": enquanto o
 * provedor nao confirma, nada entra em `payments` nem libera cota. O `ulid` e
 * o identificador publico (rota de retorno e `external_reference`); o `id`
 * sequencial fica no banco.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int $plan_id
 * @property CheckoutMode $mode
 * @property int $amount_cents
 * @property string $currency
 * @property CheckoutStatus $status
 * @property string $gateway
 * @property string|null $gateway_ref
 * @property string|null $gateway_payment_id
 * @property int|null $payment_id
 * @property int|null $subscription_id
 * @property string|null $init_point
 * @property Carbon|null $authorized_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Plan $plan
 * @property-read Payment|null $payment
 * @property-read Subscription|null $subscription
 */
#[Fillable([
    'user_id',
    'plan_id',
    'mode',
    'amount_cents',
    'currency',
    'status',
    'gateway',
    'gateway_ref',
    'gateway_payment_id',
    'payment_id',
    'subscription_id',
    'init_point',
    'authorized_at',
    'expires_at',
    'approved_at',
])]
class CheckoutSession extends Model
{
    /** @use HasFactory<CheckoutSessionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            $session->ulid ??= (string) Str::ulid();
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
            'mode' => CheckoutMode::class,
            'status' => CheckoutStatus::class,
            'amount_cents' => 'integer',
            'authorized_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * A sessao ainda aceita confirmacao? A expiracao e preguicosa: uma sessao
     * pendente com prazo vencido ja nao vale, mesmo antes da varredura.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Estado que a tela deve mostrar, sem alterar o banco.
     *
     * Um checkout avulso pendente vence junto com o `init_point`. Ja uma
     * recorrencia autorizada nao expira: a preapproval foi aceita e o primeiro
     * pagamento pode levar cerca de uma hora - nesse caso ela segue
     * "processando" ate o webhook de aprovacao.
     */
    public function effectiveStatus(): CheckoutStatus
    {
        if (
            $this->status === CheckoutStatus::Pending
            && $this->authorized_at === null
            && $this->expires_at !== null
            && $this->expires_at->isPast()
        ) {
            return CheckoutStatus::Expired;
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

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
