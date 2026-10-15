<?php

/*
| Paginas legais: Politica de Privacidade e Termos de Uso (namespace "legal").
|
| O e-mail de contato e a data da revisao vem de config/legal.php (nao ficam
| presos aqui). ":date" e montado no servidor (Laravel); "{app}" vem do Vue (vue-i18n).
*/

return [
    'updated' => 'Last updated: :date',
    'contact' => 'Contact',

    'privacy' => [
        'title' => 'Privacy Policy',
        'intro' => 'This page explains which data {app} keeps, why and for how long. In short: the tools run in your browser and we only keep what the account needs.',
        'sections' => [
            'data' => [
                'title' => 'What we keep',
                'body' => 'The minimum for an account: name, email, password (as a hash) and the language you use. If you sign in with Google or GitHub, we keep the provider account id and your email — never your password there. If you subscribe to the newsletter, we keep your email and the language of the subscription.',
            ],
            'purpose' => [
                'title' => 'What we use it for',
                'body' => 'To keep your account working, to send the emails you asked for (password reset, email confirmation, newsletter) and to keep the site safe against abuse.',
            ],
            'browser' => [
                'title' => 'The tools run in your browser',
                'body' => 'What you paste into a tool is processed in your browser and is not sent to our servers. The account data above is the only thing we store.',
            ],
            'sharing' => [
                'title' => 'With whom we share',
                'body' => 'We do not sell or rent your data. We share only with the providers that keep the site running: hosting and email delivery, plus Google or GitHub when you choose to sign in with them. If you accept measurement, page views also go to Google Analytics.',
            ],
            'cookies' => [
                'title' => 'Cookies',
                'body' => 'Essential cookies keep the site working: session, language and theme preference. Measurement cookies (Google Analytics) are optional and load only after you accept; you can change that choice at any time in "Cookies", in the footer. No advertising cookies.',
            ],
            'retention' => [
                'title' => 'How long we keep it',
                'body' => 'Account data stays while the account exists and goes away when you delete the account. Newsletter data stays until you unsubscribe.',
            ],
            'rights' => [
                'title' => 'Your rights',
                'body' => 'You can see and change your data in the account settings, and you can ask for a copy or for deletion by email. We answer within the legal deadline.',
            ],
            'security' => [
                'title' => 'Security',
                'body' => 'Passwords are stored as a hash, the site runs over HTTPS and access to the server is restricted. No system is perfect: if something happens, we tell you.',
            ],
            'changes' => [
                'title' => 'Changes to this page',
                'body' => 'This text may be updated as the site evolves. The date of the last review is always at the top of the page.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Terms of Use',
        'intro' => 'The rules for using {app}. By browsing or creating an account, you agree to them.',
        'sections' => [
            'service' => [
                'title' => 'What the service is',
                'body' => '{app} is a set of development tools that run in your browser, offered as is and free of charge, for personal or professional use.',
            ],
            'account' => [
                'title' => 'Your account',
                'body' => 'You are responsible for your account: keep your password and your social login safe. Accounts created with Google or GitHub have no password and are accessed through the provider.',
            ],
            'use' => [
                'title' => 'Acceptable use',
                'body' => 'Use the tools for lawful purposes. Do not attack, overload or scrape the service, and do not use it to break other systems. Generated results (such as SQL) are aids: the final call is yours.',
            ],
            'content' => [
                'title' => 'Content',
                'body' => 'What you paste into the tools stays yours. The site, the brand and the code stay ours.',
            ],
            'availability' => [
                'title' => 'Availability',
                'body' => 'We try to keep the site up, but we do not promise it. Features may change or be discontinued.',
            ],
            'warranty' => [
                'title' => 'No warranty',
                'body' => 'The site is provided without warranties. We are not responsible for damages or losses arising from the use of the results it generates.',
            ],
            'changes' => [
                'title' => 'Changes to the terms',
                'body' => 'These terms may change. Continuing to use the site after a change means you agree with the new version.',
            ],
            'law' => [
                'title' => 'Applicable law',
                'body' => 'These terms are governed by Brazilian law, and the courts of Brazil are competent to settle any dispute.',
            ],
        ],
    ],
];
