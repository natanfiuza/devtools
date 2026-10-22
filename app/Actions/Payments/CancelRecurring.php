<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Enums\SubscriptionStatus;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Cancela a renovacao automatica sem encerrar o periodo pago (F6).
 *
 * O gateway vem primeiro: se o provedor recusar o cancelamento, nada muda no
 * banco - melhor a conta continuar com uma renovacao que sera cobrada do que
 * achar que cancelou e levar a cobranca assim mesmo.
 *
 * `404`/`409` contam como sucesso: a preapproval ja nao existe ou ja esta
 * cancelada. O acesso segue ate `current_period_end`; `renewal_canceled_at`
 * registra a decisao sem mexer em `status`.
 */
final class CancelRecurring
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @return int quantidade de preapprovals canceladas agora
     */
    public function __invoke(User $user): int
    {
        $subscriptions = $this->recurringSubscriptions($user);

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        /** @var list<string> $references */
        $references = $subscriptions
            ->pluck('gateway_ref')
            ->filter(fn (mixed $reference): bool => is_string($reference) && $reference !== '')
            ->unique()
            ->values()
            ->all();

        foreach ($references as $reference) {
            try {
                $this->gateway->cancelPreapproval($reference);
            } catch (PaymentGatewayException $exception) {
                if (! $exception->isAlreadyGone()) {
                    throw $exception;
                }
            }
        }

        $now = now();

        foreach ($subscriptions as $subscription) {
            $subscription->forceFill(['renewal_canceled_at' => $now])->save();
        }

        return count($references);
    }

    /**
     * Linhas de assinatura que ainda valem e cuja preapproval segue ativa.
     *
     * @return Collection<int, Subscription>
     */
    public function recurringSubscriptions(User $user): Collection
    {
        return $user->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->where('gateway', 'mercadopago')
            ->whereNotNull('gateway_ref')
            ->whereNull('renewal_canceled_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            })
            ->get();
    }
}
