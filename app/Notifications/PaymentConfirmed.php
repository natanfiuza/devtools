<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use App\Support\Format;
use App\Support\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmacao enviada a conta quando um pagamento e registrado (ADR-044).
 *
 * O destinatario e a conta dona do pagamento, no idioma dela. A action que
 * registra o pagamento e quem dispara: registro fiscal e aviso sao coisas
 * separadas, e o aviso e best-effort.
 */
final class PaymentConfirmed extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $payment = $this->payment->loadMissing(['plan', 'subscription']);
        $periodEnd = $payment->subscription?->current_period_end;

        $message = (new MailMessage)
            ->subject(__('mail.payment.confirmed.subject'))
            ->greeting(__('mail.payment.confirmed.greeting', ['name' => $notifiable->name]))
            ->line(MailBrand::line('mail.payment.confirmed.intro'))
            ->line(__('mail.payment.confirmed.amount', [
                'amount' => Format::money($payment->amount_cents, $payment->currency),
            ]))
            ->line(__('mail.payment.confirmed.method', [
                'method' => __('account.billing.methods.'.$payment->method->value),
            ]))
            ->line(__('mail.payment.confirmed.paid_at', [
                'date' => Format::dateTime($payment->paid_at),
            ]));

        if ($payment->plan !== null) {
            $message->line(__('mail.payment.confirmed.plan', ['plan' => $payment->plan->name]));
        }

        if ($periodEnd !== null) {
            $message->line(__('mail.payment.confirmed.period_end', ['date' => Format::date($periodEnd)]));
        }

        return $message->action(__('mail.payment.confirmed.action'), route('dashboard.billing'));
    }
}
