<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Assinatura de uma conta. `manual` hoje; o gateway chega na F6.
 *
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property string $gateway
 * @property string|null $gateway_ref
 * @property SubscriptionStatus $status
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Plan $plan
 */
#[Fillable([
    'user_id',
    'plan_id',
    'gateway',
    'gateway_ref',
    'status',
    'current_period_start',
    'current_period_end',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
        ];
    }

    /**
     * Vale agora? Periodo sem fim (nulo) vale sempre - e o caso da concessao
     * vitalicia.
     */
    public function isCurrent(): bool
    {
        if ($this->status !== SubscriptionStatus::Active) {
            return false;
        }

        return $this->current_period_end === null || $this->current_period_end->isFuture();
    }

    /**
     * Assinaturas vigentes. A comparacao com o enum vai pelo `value` porque o
     * query builder nao conhece o cast do model.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            });
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
