<?php

namespace App\Notifications;

use App\Support\Locales;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * E-mail de redefinicao de senha.
 *
 * Substitui a notificacao do framework, que monta o texto com chaves em ingles
 * presas no proprio codigo, e passa a usar lang/{locale}/mail.php. Vai na fila
 * e sai no idioma da conta: o User implementa HasLocalePreference.
 */
class ResetPassword extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.reset.subject'))
            ->line(__('mail.reset.intro', ['app' => config('app.name')]))
            ->action(__('mail.reset.action'), $url)
            ->line(__('mail.reset.expire', ['minutes' => $this->expireMinutes()]))
            ->line(__('mail.reset.ignore'));
    }

    /**
     * O link leva o idioma da conta: a rota de reset e prefixada e, dentro da
     * fila, nao existe "idioma da requisicao" para o URL::defaults resolver.
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return route('password.reset', [
            'locale' => Locales::slug($notifiable->locale ?? Locales::fallback()),
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    /**
     * Validade do link, em minutos - vem do config, nao do codigo.
     */
    private function expireMinutes(): int
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return is_numeric($minutes) ? (int) $minutes : 60;
    }
}
