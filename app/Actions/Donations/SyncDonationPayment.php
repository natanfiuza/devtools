<?php

namespace App\Actions\Donations;

use App\Enums\DonationStatus;
use App\Enums\DonationSyncOutcome;
use App\Models\Donation;
use App\Support\Payments\GatewayPayment;

/**
 * Efeito local de um pagamento consultado no provedor (ADR-059).
 *
 * Webhook, botao "Ja paguei" e o comando `donations:check` passam por aqui para
 * que a doacao tenha um unico caminho de sincronizacao. O status cru e o
 * detalhe sao gravados antes de qualquer transicao - sao eles que explicam a
 * recusa na tela e no admin.
 *
 * Aprovado continua passando pelo `ConfirmDonation` (lock, valor e moeda).
 * Recusa e estorno usam update condicional para que um aviso atrasado nao
 * rebaixe uma doacao ja paga.
 */
final class SyncDonationPayment
{
    public function __construct(private readonly ConfirmDonation $confirmDonation) {}

    public function __invoke(Donation $donation, GatewayPayment $payment): DonationSyncOutcome
    {
        $donation->forceFill([
            'gateway_status' => mb_substr($payment->status, 0, 32),
            'gateway_status_detail' => $payment->statusDetail !== null
                ? mb_substr($payment->statusDetail, 0, 64)
                : null,
        ])->save();

        if ($payment->isRefunded()) {
            Donation::query()
                ->whereKey($donation->getKey())
                ->where('status', '!=', DonationStatus::Refunded->value)
                ->update(['status' => DonationStatus::Refunded->value]);

            $donation->refresh();

            return DonationSyncOutcome::Refunded;
        }

        if ($payment->isApproved()) {
            $confirmed = ($this->confirmDonation)(
                $donation,
                $payment->amountCents,
                $payment->currency,
                $payment->paidAt,
            );

            return $confirmed
                ? DonationSyncOutcome::Confirmed
                : DonationSyncOutcome::Unchanged;
        }

        if (in_array($payment->status, ['rejected', 'cancelled'], true)) {
            Donation::query()
                ->whereKey($donation->getKey())
                ->where('status', DonationStatus::Pending->value)
                ->update(['status' => DonationStatus::Failed->value]);

            $donation->refresh();

            return DonationSyncOutcome::Declined;
        }

        return DonationSyncOutcome::Unchanged;
    }
}
