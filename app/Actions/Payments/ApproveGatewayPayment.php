<?php

namespace App\Actions\Payments;

use App\Actions\Admin\RegisterPayment;
use App\Actions\Subscriptions\OpenSubscriptionPeriod;
use App\Enums\CheckoutMode;
use App\Enums\CheckoutStatus;
use App\Exceptions\Payments\PaymentVerificationException;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Payments\GatewayPayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Confirma um pagamento aprovado do gateway e abre o periodo (F6).
 *
 * A sessao de checkout e a foto do combinado: valor e moeda sao comparados com
 * ela, nunca com o preco atual. Pagamentos que nao casam com a foto nao liberam
 * nada - o job falha e o admin ve a anomalia.
 *
 * Idempotencia em duas camadas: a checagem por `(gateway, gateway_ref)` evita
 * trabalho repetido e o indice unico no banco e a garantia final contra dois
 * jobs simultaneos. A primeira cobranca abre a assinatura; as seguintes
 * empilham um periodo novo na mesma preapproval.
 */
final class ApproveGatewayPayment
{
    public function __construct(
        private readonly OpenSubscriptionPeriod $openPeriod,
        private readonly RegisterPayment $registerPayment,
    ) {}

    /**
     * @param  string|null  $expectedPreapprovalId  preapproval informada pelo
     *                                              webhook de cobranca autorizada
     * @return Payment|null `null` quando o pagamento nao pertence a um checkout
     */
    public function __invoke(GatewayPayment $payment, ?string $expectedPreapprovalId = null): ?Payment
    {
        $existing = $this->findPayment($payment->id);

        if ($existing instanceof Payment) {
            return $existing;
        }

        $session = $this->sessionFor($payment->externalReference, $expectedPreapprovalId);

        if (! $session instanceof CheckoutSession) {
            return null;
        }

        $session->loadMissing(['plan', 'user']);

        $plan = $session->plan;
        $user = $session->user;

        if ($plan->isFree() || ! $plan->active) {
            throw PaymentVerificationException::planUnavailable();
        }

        $preapprovalId = $this->preapprovalId($session, $expectedPreapprovalId);

        if ($payment->amountCents !== $session->amount_cents) {
            throw PaymentVerificationException::amountMismatch();
        }

        if (mb_strtoupper($payment->currency) !== mb_strtoupper($session->currency)) {
            throw PaymentVerificationException::currencyMismatch();
        }

        try {
            return DB::transaction(fn (): Payment => $this->approve($session, $user, $plan, $payment, $preapprovalId));
        } catch (UniqueConstraintViolationException $exception) {
            // Outro job gravou o mesmo pagamento entre a checagem e o insert.
            $winner = $this->findPayment($payment->id);

            if ($winner instanceof Payment) {
                return $winner;
            }

            throw $exception;
        }
    }

    private function approve(
        CheckoutSession $session,
        User $user,
        Plan $plan,
        GatewayPayment $payment,
        ?string $preapprovalId,
    ): Payment {
        $existing = $this->findPayment($payment->id);

        if ($existing instanceof Payment) {
            return $existing;
        }

        $subscription = ($this->openPeriod)($user, $plan, 'mercadopago', $preapprovalId);

        $registered = ($this->registerPayment)(
            user: $user,
            amountCents: $payment->amountCents,
            method: $payment->method,
            plan: $plan,
            paidAt: $payment->paidAt ?? now(),
            gateway: 'mercadopago',
            gatewayRef: $payment->id,
            subscription: $subscription,
            gatewayMethod: $payment->methodId,
            currency: $session->currency,
        );

        if ($preapprovalId !== null) {
            // Renovacao aprovada depois de uma falha: o aviso sai de novo so
            // se o provedor voltar a recusar num periodo futuro.
            Subscription::query()
                ->where('gateway', 'mercadopago')
                ->where('gateway_ref', $preapprovalId)
                ->whereNotNull('renewal_failed_at')
                ->update(['renewal_failed_at' => null]);
        }

        $session->forceFill([
            'status' => CheckoutStatus::Approved,
            'gateway_payment_id' => $payment->id,
            'payment_id' => $registered->getKey(),
            'subscription_id' => $subscription?->getKey(),
            'approved_at' => $session->approved_at ?? now(),
        ])->save();

        return $registered;
    }

    private function sessionFor(?string $externalReference, ?string $expectedPreapprovalId): ?CheckoutSession
    {
        if (is_string($externalReference) && preg_match('/^chk_([0-9A-HJKMNP-TV-Z]{26})$/', $externalReference, $matches) === 1) {
            return CheckoutSession::query()->where('ulid', $matches[1])->first();
        }

        if (is_string($expectedPreapprovalId) && $expectedPreapprovalId !== '') {
            return CheckoutSession::query()
                ->where('gateway', 'mercadopago')
                ->where('mode', CheckoutMode::Recurring->value)
                ->where('gateway_ref', $expectedPreapprovalId)
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }

    private function preapprovalId(CheckoutSession $session, ?string $expectedPreapprovalId): ?string
    {
        if ($session->mode !== CheckoutMode::Recurring) {
            return null;
        }

        $reference = $session->gateway_ref;

        if (! is_string($reference) || $reference === '') {
            throw PaymentVerificationException::missingPreapproval();
        }

        if (is_string($expectedPreapprovalId) && $expectedPreapprovalId !== '' && $expectedPreapprovalId !== $reference) {
            throw PaymentVerificationException::preapprovalMismatch();
        }

        return $reference;
    }

    private function findPayment(string $gatewayReference): ?Payment
    {
        return Payment::query()
            ->where('gateway', 'mercadopago')
            ->where('gateway_ref', $gatewayReference)
            ->first();
    }
}
