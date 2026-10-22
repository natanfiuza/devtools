<?php

namespace App\Notifications;

use App\Models\Donation;
use App\Support\Format;
use App\Support\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Recibo de doacao por e-mail (F6).
 *
 * Sai so na transicao `pending -> paid`, e so quando o doador deixou e-mail e
 * o envio esta ligado em `config/donations.php`. O idioma e o gravado na
 * doacao, nao o de uma requisicao que ja terminou.
 *
 * Doacao nao compra plano: o recibo agradece e registra o valor, sem link para
 * a area da conta.
 */
final class DonationReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Donation $donation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->donation->name;
        $greeting = is_string($name) && trim($name) !== ''
            ? __('mail.donation.receipt.greeting', ['name' => $name])
            : __('mail.donation.receipt.greeting_anonymous');

        $message = (new MailMessage)
            ->subject(__('mail.donation.receipt.subject'))
            ->greeting($greeting)
            ->line(MailBrand::line('mail.donation.receipt.intro'))
            ->line(__('mail.donation.receipt.amount', [
                'amount' => Format::money($this->donation->amount_cents, $this->donation->currency),
            ]))
            ->line(__('mail.donation.receipt.paid_at', [
                'date' => Format::dateTime($this->donation->paid_at),
            ]))
            ->line(__('mail.donation.receipt.note'));

        return $message;
    }
}
