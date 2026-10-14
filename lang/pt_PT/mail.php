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
    'reset' => [
        'subject' => 'Redefinir a sua palavra-passe',
        'intro' => 'Pediu para redefinir a palavra-passe da sua conta no :app.',
        'action' => 'Criar nova palavra-passe',
        'expire' => 'A ligação é válida durante :minutes minutos.',
        'ignore' => 'Se não foi você, ignore este e-mail: a sua palavra-passe continua igual.',
    ],

    'verify' => [
        'subject' => 'Confirme o seu e-mail',
        'intro' => 'Falta pouco: confirme que este e-mail é seu para libertar a sua conta no :app.',
        'action' => 'Confirmar e-mail',
        'ignore' => 'Se não criou a conta, ignore este e-mail.',
    ],

    'newsletter' => [
        'subject' => 'Confirme a sua subscrição da newsletter',
        'greeting' => 'Subscrição da newsletter do :app',
        'intro' => 'Falta um clique para começar a receber a newsletter: basta confirmar que este e-mail é seu.',
        'action' => 'Confirmar subscrição',
        'ignore' => 'Se não foi você, ignore este e-mail: nada será enviado.',
    ],
];
