<?php

/*
| Donations via Pix (F6). Frontend namespace: the public donation page and the
| status page. The receipt e-mail lives in mail.php (server only).
*/

return [
    'title' => 'Make a donation',
    'subtitle' => 'The site is free and stays that way. If it helps you day to day, a donation of any amount keeps the tools online.',
    'meta' => [
        'title' => 'Donate to devtools',
        'description' => 'Support devtools with a Pix donation. Any amount helps keep the tools free and online.',
    ],

    'amount' => [
        'label' => 'Donation amount',
        'suggested' => 'Suggested amounts',
        'custom' => 'Other amount',
        'custom_hint' => 'Between {min} and {max}.',
        'invalid' => 'Choose an amount between {min} and {max}.',
    ],

    'donor' => [
        'title' => 'Who is donating',
        'name' => 'Name (optional)',
        'name_placeholder' => 'How you want to appear',
        'email' => 'E-mail for the receipt (optional)',
        'email_hint' => 'We only use the e-mail to send the receipt for this donation.',
        'anonymous' => 'Donate anonymously',
        'anonymous_hint' => 'No name. The e-mail, if provided, is only used for the receipt.',
    ],

    'submit' => 'Generate Pix',
    'processing' => 'Generating the Pix…',

    'info' => [
        'title' => 'Donations are unavailable right now',
        'description' => 'Pix is not configured here yet. Come back later or reach out through the contact page.',
    ],

    'pix' => [
        'title' => 'Pay with Pix',
        'description' => 'Open your bank app, choose Pix and scan the QR or paste the code below.',
        'copy' => 'Copy Pix code',
        'copied' => 'Code copied.',
        'copy_failed' => 'Could not copy. Select the code below instead.',
        'ticket' => 'Open in the bank app',
        'dynamic_note' => 'Confirmation arrives automatically once the payment is approved.',
        'static_note' => 'This is a static Pix: there is no automatic confirmation. Keep your receipt.',
    ],

    'status' => [
        'title' => 'Your donation',
        'amount' => 'Amount',
        'created_at' => 'Created on',
        'expires_at' => 'Valid until',
        'pending' => 'Awaiting payment',
        'paid' => 'Donation confirmed',
        'expired' => 'Pix expired',
        'refunded' => 'Donation refunded',
        'failed' => 'Donation not completed',
        'pending_hint' => 'As soon as the payment is approved, this page shows the confirmation.',
        'paid_hint' => 'Thank you for supporting the project! A donation does not grant plan access or consume quota: it is support for the site.',
        'expired_hint' => 'The QR expired and nothing was charged. You can generate a new donation whenever you like.',
        'refunded_hint' => 'This donation was refunded.',
        'failed_hint' => 'The payment was not completed. You can try again whenever you like.',
        'new' => 'Make another donation',
        'receipt' => 'If you provided an e-mail, the receipt will arrive in your inbox.',
    ],

    'errors' => [
        'unavailable' => 'Pix donations are not available right now.',
        'gateway' => 'We could not generate the Pix right now. Try again in a moment.',
        'payer' => 'We could not generate the Pix without a contact e-mail. Try again with your e-mail.',
    ],

    'pix_description' => 'Donation to :app',
];
