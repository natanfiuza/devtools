<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\User;
use App\Support\Format;
use App\Support\MailBrand;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso enviado a conta quando um pagamento e estornado (ADR-044).
 *
 * `canceledSubscription` so e verdadeiro quando o estorno encerrou a
 * assinatura vinculada; se a assinatura ja estava encerrada, o e-mail nao
 * mente dizendo que ela terminou agora. Como nao existe coluna `refunded_at`,
 * a data do estorno e o instante capturado pela action.
 */
final class PaymentRefunded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly bool $canceledSubscription,
        public readonly CarbonInterface $refundedAt,
    ) {}

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
        $payment = $this->payment->loadMissing('plan');

        $message = (new MailMessage)
            ->subject(__('mail.payment.refunded.subject'))
            ->greeting(__('mail.payment.refunded.greeting', ['name' => $notifiable->name]))
            ->line(MailBrand::line('mail.payment.refunded.intro'))
            ->line(__('mail.payment.refunded.amount', [
                'amount' => Format::money($payment->amount_cents, $payment->currency),
            ]))
            ->line(__('mail.payment.refunded.method', [
                'method' => __('account.billing.methods.'.$payment->method->value),
            ]))
            ->line(__('mail.payment.refunded.paid_at', [
                'date' => Format::dateTime($payment->paid_at),
            ]))
            ->line(__('mail.payment.refunded.refunded_at', [
                'date' => Format::dateTime($this->refundedAt),
            ]));

        if ($payment->plan !== null) {
            $message->line(__('mail.payment.refunded.plan', ['plan' => $payment->plan->name]));
        }

        if ($this->canceledSubscription) {
            $message->line(__('mail.payment.refunded.subscription_canceled'));
        }

        return $message->action(__('mail.payment.refunded.action'), route('dashboard.billing'));
    }
}
