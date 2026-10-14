<?php

namespace App\Actions\Newsletter;

use App\Enums\NewsletterSource;
use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Inscreve um e-mail na newsletter com double opt-in (ADR-024).
 *
 * Idempotente: quem ja confirmou nao recebe nada; quem esta pendente ou
 * descadastrado ganha token novo e um e-mail novo.
 */
final class SubscribeToNewsletter
{
    /**
     * @return bool true quando um e-mail de confirmacao foi enviado
     */
    public function __invoke(string $email, NewsletterSource $source, ?string $locale = null): bool
    {
        $email = Str::lower(trim($email));

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber !== null && $subscriber->isConfirmed()) {
            return false;
        }

        if ($subscriber === null) {
            $subscriber = new NewsletterSubscriber([
                'email' => $email,
                'locale' => $locale ?? app()->getLocale(),
                // A origem guardada e a da primeira vez (primeiro toque).
                'source' => $source->value,
            ]);
        }

        if ($locale !== null && $subscriber->exists) {
            $subscriber->locale = $locale;
        }

        $subscriber->token = NewsletterSubscriber::newToken();
        $subscriber->unsubscribed_at = null;
        $subscriber->save();

        Mail::to($subscriber->email)->send(new NewsletterConfirmation($subscriber));

        return true;
    }
}
