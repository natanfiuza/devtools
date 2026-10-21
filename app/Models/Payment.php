<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma cobranca registrada. Fica no banco mesmo depois de a conta ser
 * anonimizada (ADR-028): e registro fiscal.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $subscription_id
 * @property int|null $plan_id
 * @property int $amount_cents
 * @property string $currency
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property Carbon|null $paid_at
 * @property string|null $note
 * @property string $gateway
 * @property string|null $gateway_ref
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Subscription|null $subscription
 * @property-read Plan|null $plan
 */
#[Fillable([
    'user_id',
    'subscription_id',
    'plan_id',
    'amount_cents',
    'currency',
    'method',
    'status',
    'paid_at',
    'note',
    'gateway',
    'gateway_ref',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
