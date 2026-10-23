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
 * @property Carbon|null $renewal_canceled_at
 * @property Carbon|null $renewal_failed_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $refunded_at
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
    'renewal_canceled_at',
    'renewal_failed_at',
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
            'renewal_canceled_at' => 'datetime',
            'renewal_failed_at' => 'datetime',
            'ended_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Encerra a linha (ADR-054). `ended_at` marca ate onde os meses iniciados
     * contam na cota; `refunded` zera o credito da linha inteira.
     */
    public function end(bool $refunded = false): void
    {
        $now = now();

        $this->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'ended_at' => $this->ended_at ?? $now,
            'refunded_at' => $refunded ? ($this->refunded_at ?? $now) : $this->refunded_at,
        ])->save();
    }

    /**
     * Vale agora? O periodo precisa ter comecado e nao ter acabado. Periodo
     * sem inicio ou sem fim vale sempre - e o caso da concessao vitalicia.
     *
     * Com periodos empilhados (F6), a linha futura de um plano renovado tem
     * status ativo desde que nasce; sem a checagem de inicio ela apareceria
     * como vigente antes da hora e reiniciaria a cota.
     */
    public function isCurrent(): bool
    {
        if ($this->status !== SubscriptionStatus::Active) {
            return false;
        }

        $now = now();

        if ($this->current_period_start !== null && $this->current_period_start->isAfter($now)) {
            return false;
        }

        return $this->current_period_end === null || $this->current_period_end->isAfter($now);
    }

    /**
     * Assinatura ativa que ainda nao comecou: o proximo periodo empilhado.
     */
    public function isScheduled(): bool
    {
        if ($this->status !== SubscriptionStatus::Active) {
            return false;
        }

        if ($this->current_period_start === null || ! $this->current_period_start->isAfter(now())) {
            return false;
        }

        return $this->current_period_end === null || $this->current_period_end->isAfter(now());
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
                    ->whereNull('current_period_start')
                    ->orWhere('current_period_start', '<=', now());
            })
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
