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
        'subject' => 'Restablecer tu contraseña',
        'intro' => 'Pediste restablecer la contraseña de tu cuenta en :app.',
        'action' => 'Crear contraseña nueva',
        'expire' => 'El enlace vale durante :minutes minutos.',
        'ignore' => 'Si no fuiste tú, ignora este correo: tu contraseña sigue igual.',
    ],

    'verify' => [
        'subject' => 'Confirma tu correo',
        'intro' => 'Falta poco: confirma que este correo es tuyo para activar tu cuenta en :app.',
        'action' => 'Confirmar correo',
        'ignore' => 'Si no creaste la cuenta, ignora este correo.',
    ],

    'newsletter' => [
        'subject' => 'Confirma tu suscripción a la newsletter',
        'greeting' => 'Suscripción a la newsletter de :app',
        'intro' => 'Falta un clic para empezar a recibir la newsletter: solo confirma que este correo es tuyo.',
        'action' => 'Confirmar suscripción',
        'ignore' => 'Si no fuiste tú, ignora este correo: no se enviará nada.',
    ],
];
