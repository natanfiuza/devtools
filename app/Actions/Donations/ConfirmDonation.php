<?php

namespace App\Actions\Donations;

use App\Enums\DonationStatus;
use App\Exceptions\Payments\PaymentVerificationException;
use App\Models\Donation;
use App\Notifications\DonationReceipt;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Marca a doacao como paga e envia o recibo (F6).
 *
 * Idempotente: so a transicao `pending -> paid` vale. Webhook repetido nao
 * muda o estado nem manda um segundo recibo. Valor e moeda sao conferidos com
 * a doacao gravada - o corpo do webhook nunca decide quanto foi pago.
 */
final class ConfirmDonation
{
    /**
     * @return bool `true` somente quando esta chamada fez a transicao
     */
    public function __invoke(
        Donation $donation,
        int $amountCents,
        string $currency,
        ?CarbonInterface $paidAt = null,
    ): bool {
        if ($amountCents !== $donation->amount_cents) {
            throw PaymentVerificationException::donationAmountMismatch();
        }

        if (mb_strtoupper($currency) !== mb_strtoupper($donation->currency)) {
            throw PaymentVerificationException::currencyMismatch();
        }

        $confirmed = DB::transaction(fn (): bool => $this->confirm($donation, $paidAt));

        if ($confirmed) {
            $this->sendReceipt($donation);
        }

        return $confirmed;
    }

    private function confirm(Donation $donation, ?CarbonInterface $paidAt): bool
    {
        $locked = Donation::query()
            ->whereKey($donation->getKey())
            ->lockForUpdate()
            ->first();

        if (! $locked instanceof Donation || $locked->status !== DonationStatus::Pending) {
            return false;
        }

        $locked->forceFill([
            'status' => DonationStatus::Paid,
            'paid_at' => $paidAt ?? now(),
        ])->save();

        // O chamador segue com o mesmo objeto (a pagina de status, por exemplo).
        $donation->setRawAttributes($locked->getAttributes(), true);

        return true;
    }

    /**
     * Recibo so com e-mail e com o envio ligado; doacao anonima segue valida.
     */
    private function sendReceipt(Donation $donation): void
    {
        if (! (bool) config('donations.send_receipts', true)) {
            return;
        }

        $email = $donation->email;

        if (! is_string($email) || trim($email) === '') {
            return;
        }

        Notification::route('mail', $email)
            ->notify((new DonationReceipt($donation))->locale($donation->locale));
    }
}
