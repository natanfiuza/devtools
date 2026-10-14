<?php

namespace App\Actions\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;

/**
 * Estorna um pagamento registrado a mao (ADR-027).
 *
 * Duas coisas acontecem juntas, e de proposito: o pagamento passa a
 * `refunded` (o dinheiro voltou) e a assinatura que ele abriu e encerrada (o
 * acesso para). Separar as duas abriria a porta para "estornei mas continua
 * com acesso" sem ninguem perceber.
 *
 * O lancamento **nao** entra no livro-razao do consumo: estorno de dinheiro e
 * `payments`, nao cota. Devolver consumo e a concessao (GrantQuota), que e
 * outro ato.
 *
 * A linha do pagamento nao e apagada: estorno e um estado, e o que foi
 * cobrado continua no historico (guarda fiscal, ADR-028).
 */
final class RefundPayment
{
    public function __invoke(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::Paid) {
            $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
        }

        $subscription = $payment->subscription;

        if ($subscription !== null && $subscription->isCurrent()) {
            $subscription->forceFill(['status' => SubscriptionStatus::Canceled])->save();
        }

        return $payment;
    }
}
