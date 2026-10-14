<?php

namespace App\Actions\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * Concede um plano a uma conta (venda manual, ADR-027).
 *
 * Nao existe cobranca aqui: quem concede esta dizendo que o dinheiro entrou
 * por fora (pix, transferencia) ou que o acesso e cortesia. O gateway entra na
 * F6 e vai escrever assinaturas do mesmo jeito, mudando so o `gateway`.
 *
 * Regras:
 * - conceder o Free e um downgrade: encerra a assinatura vigente, porque o
 *   Free nao e uma assinatura de preco zero - e a ausencia de assinatura;
 * - conceder um plano diferente encerra a assinatura vigente e abre a nova;
 * - conceder o mesmo plano **estende o prazo** a partir do fim vigente sem
 *   mexer no inicio do periodo. Preservar o inicio e o que mantem a franquia
 *   coerente: a janela da cota e contada do inicio dela, e reiniciar a janela
 *   no meio daria uma franquia nova em cima de um periodo que ainda nao
 *   acabou. Franquia nova a cada periodo entra com o gateway (F6).
 */
final class GrantPlan
{
    public function __invoke(User $user, Plan $plan): ?Subscription
    {
        if ($plan->isFree()) {
            $this->cancelCurrent($user);

            return null;
        }

        $current = $user->activeSubscription();

        if ($current !== null && $current->plan_id === $plan->getKey()) {
            $current->forceFill([
                'current_period_end' => $plan->periodEndFrom(
                    $current->current_period_end ?? now(),
                ),
                // Concessao manual nao passa por gateway: se a assinatura veio
                // de um, a renovacao manual assume a partir daqui.
                'gateway' => 'manual',
                'gateway_ref' => null,
            ])->save();

            return $current;
        }

        $this->cancelCurrent($user);

        $start = now();

        return $user->subscriptions()->create([
            'plan_id' => $plan->getKey(),
            'gateway' => 'manual',
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $start,
            'current_period_end' => $plan->periodEndFrom($start),
        ]);
    }

    /**
     * Encerra a assinatura vigente, se houver. O historico fica: a linha
     * cancelada continua contando o que ja valeu.
     */
    private function cancelCurrent(User $user): void
    {
        $current = $user->activeSubscription();

        if ($current === null) {
            return;
        }

        $current->forceFill(['status' => SubscriptionStatus::Canceled])->save();
    }
}
