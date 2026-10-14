<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de confirmacao da newsletter (double opt-in, ADR-024).
 *
 * Vai na fila e no idioma do inscrito - nao no idioma de quem esta logado.
 */
class NewsletterConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public NewsletterSubscriber $subscriber)
    {
        $this->locale($subscriber->locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.newsletter.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.newsletter-confirmation',
            with: [
                'confirmUrl' => $this->confirmUrl(),
            ],
        );
    }

    /**
     * O link leva o idioma do inscrito na URL, entao a pagina de confirmacao
     * abre no idioma do e-mail.
     */
    private function confirmUrl(): string
    {
        return route('newsletter.confirm', [
            'locale' => Locales::slug($this->subscriber->locale),
            'token' => $this->subscriber->token,
        ]);
    }
}
