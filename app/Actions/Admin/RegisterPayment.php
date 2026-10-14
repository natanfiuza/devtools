<?php

namespace App\Actions\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Registra um pagamento recebido por fora do site (venda manual, ADR-027).
 *
 * O `gateway` fica `manual` e nao ha `gateway_ref`: este pagamento nao veio de
 * lugar nenhum, foi a pessoa que recebeu o dinheiro que contou aqui. Quando o
 * Mercado Pago entrar (F6), os pagamentos dele nascem com o mesmo formato e
 * `gateway` diferente.
 *
 * Conceder o plano junto e opcional e deliberado: o registro fiscal e a
 * concessao de acesso sao duas coisas, e as vezes so uma delas acontece
 * (pagou o mes mas ja tinha acesso; ganhou acesso de cortesia sem pagar).
 */
final class RegisterPayment
{
    public function __construct(private readonly GrantPlan $grantPlan) {}

    public function __invoke(
        User $user,
        int $amountCents,
        PaymentMethod $method,
        ?Plan $plan = null,
        ?CarbonInterface $paidAt = null,
        ?string $note = null,
        bool $grantPlan = false,
    ): Payment {
        $subscription = $grantPlan && $plan !== null
            ? ($this->grantPlan)($user, $plan)
            : null;

        return $user->payments()->create([
            'subscription_id' => $subscription?->getKey(),
            'plan_id' => $plan?->getKey(),
            'amount_cents' => $amountCents,
            'currency' => $plan->currency ?? config('billing.currency'),
            'method' => $method,
            'status' => PaymentStatus::Paid,
            'paid_at' => $paidAt ?? now(),
            'note' => $note,
            'gateway' => 'manual',
        ]);
    }
}
