<?php

namespace App\Actions\Payments;

use App\Actions\Admin\RefundPayment;
use App\Contracts\Payments\PaymentGateway;
use App\Exceptions\Payments\PaymentGatewayException;
use App\Exceptions\Payments\PaymentVerificationException;
use App\Models\Payment;
use Throwable;

/**
 * Estorna um pagamento que veio do gateway (F6).
 *
 * O provedor vem primeiro: estorno recusado deixa tudo como estava. O
 * cancelamento da preapproval vem depois do estorno e e best-effort - o
 * dinheiro ja voltou, entao uma falha ali nao pode desfazer o estorno; ela vai
 * para o log e o admin cancela pela tela se precisar.
 *
 * No caminho de webhook (`refunded`/`charged_back`) o dinheiro ja voltou; a
 * acao pula a chamada de estorno e apenas sincroniza o estado local.
 */
final class RefundGatewayPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RefundPayment $refundPayment,
    ) {}

    public function __invoke(Payment $payment, bool $notifyGateway = true): Payment
    {
        $reference = $payment->gateway_ref;

        if ($notifyGateway) {
            if ($payment->gateway !== 'mercadopago' || ! is_string($reference) || $reference === '') {
                throw PaymentVerificationException::missingReference();
            }

            try {
                $this->gateway->refund($reference);
            } catch (PaymentGatewayException $exception) {
                if (! $exception->isAlreadyGone()) {
                    throw $exception;
                }
            }
        }

        $this->cancelPreapproval($payment);

        return ($this->refundPayment)($payment);
    }

    private function cancelPreapproval(Payment $payment): void
    {
        $reference = $payment->subscription?->gateway_ref;

        if (! is_string($reference) || $reference === '') {
            return;
        }

        try {
            $this->gateway->cancelPreapproval($reference);
        } catch (PaymentGatewayException $exception) {
            if (! $exception->isAlreadyGone()) {
                report($exception);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
