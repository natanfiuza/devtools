@include('notifications::email', [
    'level' => 'default',
    'greeting' => null,
    'salutation' => null,
    'introLines' => [
        \App\Support\MailBrand::line('mail.newsletter.intro'),
    ],
    'actionText' => __('mail.newsletter.action'),
    'actionUrl' => $confirmUrl,
    'displayableActionUrl' => str_replace(['mailto:', 'tel:'], '', $confirmUrl),
    'outroLines' => [
        __('mail.newsletter.ignore'),
    ],
])
