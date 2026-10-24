<?php

/*
| Messages and text for the public contact page (Contact us).
*/

return [
    'meta' => [
        'title' => 'Contact',
        'description' => 'Get in touch with the devtools team. Send questions, feedback, report bugs or ask about donations.',
    ],

    'title' => 'Contact us',
    'subtitle' => 'Have questions, suggestions, or encountered an issue? Send a message to our team.',

    'fields' => [
        'name' => 'Name',
        'name_placeholder' => 'Your full name or what you like to be called',
        'email' => 'Email',
        'email_placeholder' => "your{'@'}email.com",
        'email_hint' => 'We will only use this email address to reply to your message.',
        'subject' => 'Subject',
        'subject_placeholder' => 'Select a reason for contacting us',
        'message' => 'Message',
        'message_placeholder' => 'Describe your question, feedback, or issue with as much detail as possible…',
        'characters' => '{count} / {max} characters',
    ],

    'subjects' => [
        'general' => 'General question',
        'donation' => 'Donation / payment issue',
        'feedback' => 'Suggestion or feedback',
        'bug' => 'Bug report',
        'other' => 'Other topic',
    ],

    'submit' => 'Send message',
    'submitting' => 'Sending…',

    'success_title' => 'Message sent successfully!',
    'success_body' => 'We received your message and will reply as soon as possible to the provided email address.',
    'send_another' => 'Send another message',

    'sent_toast' => 'Your message was sent successfully!',
    'throttle' => 'Too many messages sent in a short period. Please wait :seconds seconds before trying again.',
];
