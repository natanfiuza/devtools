@include('notifications::email', [
    'level' => 'default',
    'greeting' => __('mail.contact.greeting'),
    'salutation' => null,
    'introLines' => [
        __('mail.contact.intro'),
        __('mail.contact.sender', ['name' => $contact->name, 'email' => $contact->email]),
        __('mail.contact.subject_line', ['subject' => $contact->subject]),
        __('mail.contact.message_header'),
        $contact->message,
    ],
    'actionText' => null,
    'actionUrl' => null,
    'outroLines' => [
        __('mail.contact.meta', [
            'ip' => $contact->ip_address ?? 'N/A',
            'date' => $contact->created_at?->toIso8601String() ?? 'N/A',
        ]),
    ],
])
