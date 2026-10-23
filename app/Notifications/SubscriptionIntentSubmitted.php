<?php

namespace App\Notifications;

use App\Models\SubscriptionIntent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso enviado ao e-mail de contato administrativo (`Legal::contactEmail()`)
 * quando um usuario registra ou atualiza uma intencao de assinatura Pro
 * (ADR-033).
 */
class SubscriptionIntentSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SubscriptionIntent $intent) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $intent = $this->intent->loadMissing(['user', 'plan']);

        $render = function () use ($intent): MailMessage {
            $message = (new MailMessage)
                ->subject(__('mail.intent.subject', [
                    'app' => config('app.name'),
                    'username' => $intent->username,
                ]))
                ->line(__('mail.intent.intro', [
                    'name' => $intent->user->name,
                    'email' => $intent->user->email,
                ]))
                ->line(__('mail.intent.details', [
                    'plan' => $intent->plan->name.' ('.$intent->plan->slug.')',
                    'username' => '@'.$intent->username,
                    'phone' => $intent->phone,
                    'document' => $intent->document ?: '—',
                    'method' => $intent->payment_method->value,
                ]));

            if ($intent->notes !== null && $intent->notes !== '') {
                $message->line(__('mail.intent.notes', ['notes' => $intent->notes]));
            }

            return $message->action(__('mail.intent.action'), route('admin.intents.index'));
        };

        $targetLocale = $this->locale ?? $intent->user->preferredLocale();

        if ($targetLocale !== null && $targetLocale !== '') {
            $previous = app()->getLocale();
            try {
                app()->setLocale($targetLocale);

                return $render();
            } finally {
                app()->setLocale($previous);
            }
        }

        return $render();
    }
}
