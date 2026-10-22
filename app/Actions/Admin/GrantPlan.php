<?php

namespace App\Actions\Admin;

use App\Actions\Subscriptions\CancelActiveSubscriptions;
use App\Actions\Subscriptions\OpenSubscriptionPeriod;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * Concede um plano a uma conta (venda manual, ADR-027).
 *
 * Nao existe cobranca aqui: quem concede esta dizendo que o dinheiro entrou
 * por fora (pix, transferencia) ou que o acesso e cortesia. O gateway escreve
 * assinaturas pelas mesmas actions, mudando so o `gateway`.
 *
 * Regras:
 * - conceder o Free e um downgrade: encerra tudo o que vale, porque o Free nao
 *   e uma assinatura de preco zero - e a ausencia de assinatura;
 * - conceder um plano diferente encerra o que vale e abre a nova agora;
 * - conceder o mesmo plano **empilha um periodo novo** no fim do que ja esta
 *   pago, sem tocar na linha corrente. Periodo por linha e o que mantem a
 *   franquia coerente quando a renovacao chega antes da hora.
 */
final class GrantPlan
{
    public function __construct(
        private readonly OpenSubscriptionPeriod $openPeriod,
        private readonly CancelActiveSubscriptions $cancelActive,
    ) {}

    public function __invoke(User $user, Plan $plan): ?Subscription
    {
        if ($plan->isFree()) {
            ($this->cancelActive)($user);

            return null;
        }

        $anchor = $user->activeSubscription() ?? $user->nextSubscription();

        if ($anchor === null || $anchor->plan_id !== $plan->getKey()) {
            ($this->cancelActive)($user);
        }

        return ($this->openPeriod)($user, $plan, 'manual');
    }
}
