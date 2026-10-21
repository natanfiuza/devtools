<?php

/*
| Assuntos e corpos dos e-mails transacionais.
|
| Namespace SO do servidor: nao entra em config/locales.php e nao vai para o
| front. Por isso a interpolacao aqui e sempre ":param" (Laravel).
|
| Os e-mails saem no idioma da conta (HasLocalePreference no User), nao no
| idioma de quem pediu.
*/

return [
    'template' => [
        'greeting' => 'Hello!',
        'error_greeting' => 'Whoops!',
        'salutation' => 'Regards,',
        'subcopy' => 'If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below into your web browser:',
        'rights' => 'All rights reserved.',
    ],

    'reset' => [
        'subject' => 'Reset your password',
        'intro' => 'You asked to reset the password of your :app account.',
        'action' => 'Create a new password',
        'expire' => 'This link is valid for :minutes minutes.',
        'ignore' => 'If it was not you, just ignore this email: your password stays the same.',
    ],

    'verify' => [
        'subject' => 'Confirm your email',
        'intro' => 'One step left: confirm this email is yours to unlock your :app account.',
        'action' => 'Confirm email',
        'ignore' => 'If you did not create the account, just ignore this email.',
    ],

    'newsletter' => [
        'subject' => 'Confirm your newsletter subscription',
        'intro' => 'One click left to start receiving the :app newsletter: just confirm this email is yours.',
        'action' => 'Confirm subscription',
        'ignore' => 'If it was not you, just ignore this email: nothing will be sent.',
    ],

    'intent' => [
        'subject' => '[:app] New subscription intent (@:username)',
        'intro' => 'New Pro subscription intent submitted by :name (:email).',
        'details' => 'Plan: :plan | Username: :username | Phone: :phone | Document: :document | Payment: :method',
        'notes' => 'Notes: :notes',
        'action' => 'Open intents in admin panel',
    ],

    'payment' => [
        'confirmed' => [
            'subject' => 'Payment confirmed',
            'greeting' => 'Hello, :name!',
            'intro' => 'We received your payment on :app.',
            'amount' => 'Amount: :amount',
            'method' => 'Payment method: :method',
            'paid_at' => 'Payment date: :date',
            'plan' => 'Plan: :plan',
            'period_end' => 'Your subscription is valid until :date.',
            'action' => 'View invoices',
        ],
        'refunded' => [
            'subject' => 'Payment refunded',
            'greeting' => 'Hello, :name!',
            'intro' => 'Your payment on :app has been refunded.',
            'amount' => 'Refunded amount: :amount',
            'method' => 'Original payment method: :method',
            'paid_at' => 'Original payment date: :date',
            'refunded_at' => 'Refund date: :date',
            'plan' => 'Plan: :plan',
            'subscription_canceled' => 'The subscription linked to this payment has ended.',
            'action' => 'View invoices',
        ],
    ],
];
