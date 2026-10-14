<?php

/*
| Newsletter com double opt-in (ADR-024). Namespace "newsletter" do vue-i18n.
|
| As mensagens que dependem de parametro (invalid_link, subscribe.*) sao
| montadas no servidor e chegam prontas ao front via flash.
*/

return [
    'form' => [
        'label' => 'Newsletter',
        'description' => 'News about the tools, once in a while. No spam.',
        'email' => 'Email',
        'email_placeholder' => "you{'@'}example.com",
        'submit' => 'Subscribe',
    ],

    // Server side: flashed on subscribe (no parameter).
    'subscribe' => [
        'check_email' => 'Almost there: confirm your subscription in the email we just sent.',
        'already_confirmed' => 'You are already subscribed.',
    ],

    'confirm' => [
        'title_pending' => 'Confirm your subscription',
        'body_pending' => 'One click and you are in. We confirm your email and you start receiving the newsletter.',
        'action' => 'Confirm subscription',
        'title_confirmed' => 'Subscription confirmed',
        'body_confirmed' => 'All set: you are on the list. You can unsubscribe from any email.',
        'title_invalid' => 'Link expired',
        'body_invalid' => 'This link is no longer valid. Subscribe again from the footer of the site.',
    ],

    'unsubscribe' => [
        'title_pending' => 'Unsubscribe',
        'body_pending' => 'We will miss you. Confirm and you stop receiving the newsletter.',
        'action' => 'Unsubscribe',
        'title_unsubscribed' => 'You are unsubscribed',
        'body_unsubscribed' => 'Done: you no longer receive the newsletter. If you change your mind, just subscribe again.',
        'title_invalid' => 'Link expired',
        'body_invalid' => 'This link is no longer valid, so there is nothing to unsubscribe.',
    ],

    'invalid_link' => 'This link is no longer valid.',
    'back_home' => 'Back to the site',
];
