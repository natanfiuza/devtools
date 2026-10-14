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
        'subject' => 'Redefinir sua senha',
        'intro' => 'Você pediu para redefinir a senha da sua conta no :app.',
        'action' => 'Criar nova senha',
        'expire' => 'O link vale por :minutes minutos.',
        'ignore' => 'Se não foi você, ignore este e-mail: sua senha continua a mesma.',
    ],

    'verify' => [
        'subject' => 'Confirme seu e-mail',
        'intro' => 'Falta pouco: confirme que este e-mail é seu para liberar sua conta no :app.',
        'action' => 'Confirmar e-mail',
        'ignore' => 'Se você não criou a conta, ignore este e-mail.',
    ],

    'newsletter' => [
        'subject' => 'Confirme sua inscrição na newsletter',
        'greeting' => 'Inscrição na newsletter do :app',
        'intro' => 'Falta um clique para você começar a receber a newsletter: é só confirmar que este e-mail é seu.',
        'action' => 'Confirmar inscrição',
        'ignore' => 'Se não foi você, ignore este e-mail: nada será enviado.',
    ],
];
