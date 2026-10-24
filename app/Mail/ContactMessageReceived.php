<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notificacao de mensagem recebida pelo formulario publico de contato.
 *
 * Enviada a devtools@natanfiuza.dev.br (config/contact.php) de forma assincrona
 * pela fila, com replyTo configurado para o e-mail de quem enviou.
 */
class ContactMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage)
    {
        $this->locale(config('app.fallback_locale', 'en'));
    }

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'devtools');

        return new Envelope(
            subject: "[{$appName}] ".__('mail.contact.subject_prefix').": {$this->contactMessage->subject}",
            replyTo: [
                new Address($this->contactMessage->email, $this->contactMessage->name),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-message',
            with: [
                'contact' => $this->contactMessage,
            ],
        );
    }
}
