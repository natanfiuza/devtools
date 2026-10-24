<?php

namespace App\Actions\Contact;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;

/**
 * Cria o registro da mensagem de contato e agenda o e-mail assincrono para a administracao.
 */
final class CreateContactMessage
{
    /**
     * @param  array{name: string, email: string, subject: string, message: string, user_id?: int|null, ip_address?: string|null, user_agent?: string|null}  $data
     */
    public function __invoke(array $data): ContactMessage
    {
        $contactMessage = ContactMessage::create([
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);

        $recipient = config('contact.email');

        if (is_string($recipient) && $recipient !== '') {
            Mail::to($recipient)->queue(new ContactMessageReceived($contactMessage));
        }

        return $contactMessage;
    }
}
