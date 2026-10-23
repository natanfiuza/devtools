<?php

namespace App\Actions\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentConfirmed;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Registra um pagamento recebido por fora do site (venda manual, ADR-027).
 *
 * O `gateway` fica `manual` e nao ha `gateway_ref`: este pagamento nao veio de
 * lugar nenhum, foi a pessoa que recebeu o dinheiro que contou aqui. Quando o
 * Mercado Pago entrar (F6), os pagamentos dele nascem com o mesmo formato e
 * `gateway` diferente.
 *
 * Pagamento manual com plano pago **concede o plano** e fica vinculado a
 * assinatura aberta (ADR-054, revisa a ADR-027): plano escolhido e plano
 * pago, e o vinculo e o que faz o estorno encerrar o acesso. Cortesia sem
 * dinheiro continua em `GrantPlan`, pela tela da conta.
 */
final class RegisterPayment
{
    public function __construct(private readonly GrantPlan $grantPlan) {}

    /**
     * O pagamento do gateway chega aqui pela mesma porta do manual: a diferenca
     * esta no `gateway`, na referencia e no periodo ja aberto pela action de
     * aprovacao (`subscription`).
     */
    public function __invoke(
        User $user,
        int $amountCents,
        PaymentMethod $method,
        ?Plan $plan = null,
        ?CarbonInterface $paidAt = null,
        ?string $note = null,
        string $gateway = 'manual',
        ?string $gatewayRef = null,
        ?Subscription $subscription = null,
        ?string $gatewayMethod = null,
        ?string $currency = null,
    ): Payment {
        if ($subscription === null && $gateway === 'manual' && $plan !== null && ! $plan->isFree()) {
            $subscription = ($this->grantPlan)($user, $plan);
        }

        $payment = $user->payments()->create([
            'subscription_id' => $subscription?->getKey(),
            'plan_id' => $plan?->getKey(),
            'amount_cents' => $amountCents,
            'currency' => $currency ?? $plan->currency ?? config('billing.currency'),
            'method' => $method,
            'status' => PaymentStatus::Paid,
            'paid_at' => $paidAt ?? now(),
            'note' => $note,
            'gateway' => $gateway,
            'gateway_ref' => $gatewayRef,
            'gateway_method' => $gatewayMethod,
        ]);

        $this->notifyConfirmation($payment);

        return $payment;
    }

    /**
     * Avisa a conta dona do pagamento (ADR-044). Best-effort: o registro fiscal
     * ja esta gravado, e falha de e-mail nao pode desfaze-lo nem derrubar o
     * admin. Conta anonimizada ou excluida nao recebe.
     */
    private function notifyConfirmation(Payment $payment): void
    {
        try {
            $account = $payment->user;

            if ($account === null || $account->isAnonymized()) {
                return;
            }

            $account->notify(new PaymentConfirmed($payment));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
