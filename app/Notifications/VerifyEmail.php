<?php

namespace App\Notifications;

use App\Support\Locales;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * E-mail de confirmacao de endereco.
 *
 * Substitui a notificacao do framework (textos em ingles presos no codigo) e
 * passa a usar lang/{locale}/mail.php. Vai na fila e sai no idioma da conta.
 */
class VerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.verify.subject'))
            ->line(__('mail.verify.intro', ['app' => config('app.name')]))
            ->action(__('mail.verify.action'), $url)
            ->line(__('mail.verify.ignore'));
    }

    /**
     * A assinatura cobre a URL inteira, entao o idioma entra como parametro
     * da rota - o mesmo que o link vai abrir.
     */
    protected function verificationUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable);
        }

        $expire = Config::get('auth.verification.expire', 60);

        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(is_numeric($expire) ? (int) $expire : 60),
            [
                'locale' => Locales::slug($notifiable->locale ?? Locales::fallback()),
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
