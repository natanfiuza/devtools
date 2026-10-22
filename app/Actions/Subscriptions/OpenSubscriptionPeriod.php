<?php

namespace App\Actions\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Abre uma linha de assinatura para um periodo (F6).
 *
 * Um periodo pago e uma linha; renovacoes empilham em vez de esticar a linha
 * anterior. Isso mantem a janela da cota honesta: cada periodo tem seu proprio
 * inicio e sua propria franquia, mesmo quando o pagamento chega antes do fim do
 * periodo atual.
 *
 * Regras:
 * - Free nao abre linha: o Free e a ausencia de assinatura;
 * - o mesmo plano empilha no maior fim futuro daquele plano - renovacao
 *   antecipada nao reinicia a cota no meio;
 * - sem periodo vigente (ou plano diferente, que quem chama ja cancelou), o
 *   periodo comeca agora.
 */
final class OpenSubscriptionPeriod
{
    public function __invoke(
        User $user,
        Plan $plan,
        string $gateway = 'manual',
        ?string $gatewayRef = null,
    ): ?Subscription {
        if ($plan->isFree()) {
            return null;
        }

        $start = now();

        $latestEnd = $user->subscriptions()
            ->where('plan_id', $plan->getKey())
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '>', $start)
            ->orderByDesc('current_period_end')
            ->value('current_period_end');

        // `value()` devolve o atributo ja passado pelo cast, entao pode vir
        // data; `Date::parse` respeita o `Date::use` do app (imutavel), para a
        // soma do fim do periodo nao alterar o proprio inicio.
        if ($latestEnd !== null) {
            $start = Date::parse($latestEnd);
        }

        /** @var Subscription $subscription */
        $subscription = $user->subscriptions()->create([
            'plan_id' => $plan->getKey(),
            'gateway' => $gateway,
            'gateway_ref' => $gatewayRef,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $start,
            'current_period_end' => $plan->periodEndFrom($start),
        ]);

        return $subscription;
    }
}
