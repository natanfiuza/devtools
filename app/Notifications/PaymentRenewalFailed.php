<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Models\User;
use App\Support\Format;
use App\Support\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de renovacao recusada (F6).
 *
 * Sai uma vez por periodo, na primeira tentativa rejeitada. O acesso nao cai
 * no mesmo instante: o periodo pago continua valendo ate o fim, e e o que a
 * mensagem diz para a conta nao achar que perdeu tudo na hora.
 */
final class PaymentRenewalFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Subscription $subscription) {}

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
        $subscription = $this->subscription->loadMissing('plan');
        $periodEnd = $subscription->current_period_end;

        $message = (new MailMessage)
            ->subject(__('mail.payment.renewal_failed.subject'))
            ->greeting(__('mail.payment.renewal_failed.greeting', ['name' => $notifiable->name]))
            ->line(MailBrand::line('mail.payment.renewal_failed.intro'))
            ->line(__('mail.payment.renewal_failed.plan', [
                'plan' => $subscription->plan->name,
            ]));

        if ($periodEnd !== null) {
            $message->line(__('mail.payment.renewal_failed.period_end', [
                'date' => Format::date($periodEnd),
            ]));
        }

        return $message->action(__('mail.payment.renewal_failed.action'), route('dashboard.billing'));
    }
}
