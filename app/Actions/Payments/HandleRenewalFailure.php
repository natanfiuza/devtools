<?php

namespace App\Actions\Payments;

use App\Models\Subscription;
use App\Notifications\PaymentRenewalFailed;
use Throwable;

/**
 * Registra a primeira falha de renovacao de um periodo (F6).
 *
 * Um aviso por periodo, nao um por tentativa: o provedor pode tentar varias
 * vezes e o produto nao faz dunning alem disso. `renewal_failed_at` e o que
 * segura o aviso; a aprovacao seguinte limpa o campo e rearma o aviso para um
 * periodo futuro.
 *
 * Best-effort no e-mail: a falha ja esta gravada e o admin a enxerga mesmo se
 * a notificacao nao sair.
 */
final class HandleRenewalFailure
{
    public function __invoke(string $preapprovalId): int
    {
        $subscriptions = Subscription::query()
            ->where('gateway', 'mercadopago')
            ->where('gateway_ref', $preapprovalId)
            ->whereNull('renewal_canceled_at')
            ->current()
            ->with(['plan', 'user'])
            ->get();

        $notified = 0;

        foreach ($subscriptions as $subscription) {
            if ($subscription->renewal_failed_at !== null) {
                continue;
            }

            $subscription->forceFill(['renewal_failed_at' => now()])->save();
            $this->notify($subscription);
            $notified++;
        }

        return $notified;
    }

    private function notify(Subscription $subscription): void
    {
        try {
            $account = $subscription->user;

            if ($account->isAnonymized()) {
                return;
            }

            $account->notify(new PaymentRenewalFailed($subscription));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
