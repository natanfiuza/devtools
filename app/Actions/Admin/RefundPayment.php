<?php

namespace App\Actions\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\PaymentRefunded;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * Desde a ADR-054 a assinatura estornada recebe `refunded_at` e deixa de
 * creditar tokens na cota acumulativa - o credito sai sem lancamento no ledger.
 */
final class RefundPayment
{
    public function __invoke(Payment $payment): Payment
    {
        $wasPaid = $payment->status === PaymentStatus::Paid;

        if ($wasPaid) {
            $payment->forceFill(['status' => PaymentStatus::Refunded])->save();
        }

        $subscription = $payment->subscription ?? $this->unlinkedSubscription($payment);
        $canceledSubscription = false;

        // Futura tambem conta: o pagamento estornado era o que abria o proximo
        // periodo, e deixa-lo ativo daria acesso pago que nao existe mais.
        // O `refunded_at` tira o credito de tokens da linha inteira (ADR-054).
        if ($subscription !== null && ($subscription->isCurrent() || $subscription->isScheduled())) {
            $subscription->end(refunded: true);
            $canceledSubscription = true;
        } elseif ($subscription !== null && $subscription->refunded_at === null) {
            // Periodo ja vencido: o acesso ja acabou, mas o credito sai da cota.
            $subscription->forceFill(['refunded_at' => now()])->save();
        }

        if ($wasPaid) {
            $this->notifyRefund($payment, $canceledSubscription);
        }

        return $payment;
    }

    /**
     * Pagamento antigo, registrado antes de o plano ser concedido junto (ADR-054):
     * sem `subscription_id`, procura a assinatura vigente ou futura do mesmo
     * plano que nao pertence a nenhum outro pagamento. A mais recente vem
     * primeiro - e a que o admin concedeu a mao depois de receber.
     */
    private function unlinkedSubscription(Payment $payment): ?Subscription
    {
        if ($payment->plan_id === null) {
            return null;
        }

        return Subscription::query()
            ->where('user_id', $payment->user_id)
            ->where('plan_id', $payment->plan_id)
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function (Builder $query): void {
                $query->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now());
            })
            ->whereDoesntHave('payments')
            ->orderByDesc('current_period_start')
            ->orderByDesc('id')
            ->first();
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
