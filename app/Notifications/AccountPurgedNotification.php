<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificacao enviada quando a conta tem o periodo de carencia expirado e e
 * definitivamente expurgada e anonimizada.
 *
 * O envio e sincrono antes da anonimizacao definitiva dos dados para garantir
 * que o usuario receba a exportacao completa dos seus dados em anexo JSON
 * no seu e-mail original e no seu idioma de preferencia.
 */
class AccountPurgedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $jsonData,
        public readonly string $fileName
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
        $greeting = ! empty($notifiable->name)
            ? __('mail.account_purged.greeting', ['name' => $notifiable->name])
            : __('mail.account_purged.greeting_anonymous');

        return (new MailMessage)
            ->subject(MailBrand::line('mail.account_purged.subject'))
            ->greeting($greeting)
            ->line(MailBrand::line('mail.account_purged.intro'))
            ->line(__('mail.account_purged.anonymized'))
            ->line(__('mail.account_purged.attached'))
            ->attachData(
                $this->jsonData,
                $this->fileName,
                ['mime' => 'application/json']
            );
    }
}
