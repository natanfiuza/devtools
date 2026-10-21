<?php

namespace App\Actions\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Notifications\PaymentRefunded;
use Throwable;

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
        $wasPaid = $payment->status === PaymentStatus::Paid;

        if ($wasPaid) {
            $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
        }

        $subscription = $payment->subscription;
        $canceledSubscription = false;

        if ($subscription !== null && $subscription->isCurrent()) {
            $subscription->forceFill(['status' => SubscriptionStatus::Canceled])->save();
            $canceledSubscription = true;
        }

        if ($wasPaid) {
            $this->notifyRefund($payment, $canceledSubscription);
        }

        return $payment;
    }

    /**
     * Avisa a conta dona do pagamento (ADR-044). Best-effort: o estorno ja esta
     * gravado, e falha de e-mail nao pode desfaze-lo nem derrubar o admin.
     * Conta anonimizada ou excluida nao recebe. Estorno repetido nao avisa de
     * novo - o status so muda na primeira vez.
     */
    private function notifyRefund(Payment $payment, bool $canceledSubscription): void
    {
        try {
            $account = $payment->user;

            if ($account === null || $account->isAnonymized()) {
                return;
            }

            $account->notify(new PaymentRefunded($payment, $canceledSubscription, now()));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
