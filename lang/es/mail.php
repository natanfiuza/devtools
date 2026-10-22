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
        'greeting' => '¡Hola!',
        'error_greeting' => '¡Ups!',
        'salutation' => 'Saludos,',
        'subcopy' => 'Si tienes problemas para hacer clic en el botón ":actionText", copia y pega la siguiente dirección en tu navegador:',
        'rights' => 'Todos los derechos reservados.',
    ],

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
        'intro' => 'Falta un clic para empezar a recibir la newsletter de :app: solo confirma que este correo es tuyo.',
        'action' => 'Confirmar suscripción',
        'ignore' => 'Si no fuiste tú, ignora este correo: no se enviará nada.',
    ],

    'intent' => [
        'subject' => '[:app] Nueva intención de suscripción (@:username)',
        'intro' => 'Nueva intención de suscripción Pro registrada por :name (:email).',
        'details' => 'Plan: :plan | Usuario: :username | Teléfono: :phone | Documento: :document | Pago: :method',
        'notes' => 'Observaciones: :notes',
        'action' => 'Abrir intenciones en el panel',
    ],

    'payment' => [
        'confirmed' => [
            'subject' => 'Pago confirmado',
            'greeting' => '¡Hola, :name!',
            'intro' => 'Recibimos tu pago en :app.',
            'amount' => 'Importe: :amount',
            'method' => 'Forma de pago: :method',
            'paid_at' => 'Fecha del pago: :date',
            'plan' => 'Plan: :plan',
            'period_end' => 'Tu suscripción es válida hasta el :date.',
            'action' => 'Ver facturas',
        ],
        'refunded' => [
            'subject' => 'Pago reembolsado',
            'greeting' => '¡Hola, :name!',
            'intro' => 'Tu pago en :app ha sido reembolsado.',
            'amount' => 'Importe reembolsado: :amount',
            'method' => 'Forma de pago original: :method',
            'paid_at' => 'Fecha del pago original: :date',
            'refunded_at' => 'Fecha del reembolso: :date',
            'plan' => 'Plan: :plan',
            'subscription_canceled' => 'La suscripción vinculada a este pago ha terminado.',
            'action' => 'Ver facturas',
        ],
        'renewal_failed' => [
            'subject' => 'No pudimos renovar tu suscripción',
            'greeting' => '¡Hola, :name!',
            'intro' => 'El cobro automático de tu suscripción en :app no fue aprobado por la tarjeta.',
            'plan' => 'Plan: :plan',
            'period_end' => 'Tu acceso se mantiene hasta el :date.',
            'action' => 'Actualizar pago',
        ],
    ],

    'donation' => [
        'receipt' => [
            'subject' => 'Hemos recibido tu donación',
            'greeting' => '¡Hola, :name!',
            'greeting_anonymous' => '¡Hola!',
            'intro' => 'Gracias por apoyar a :app. Tu donación está confirmada.',
            'amount' => 'Importe donado: :amount',
            'paid_at' => 'Confirmada el: :date',
            'note' => 'Una donación no da acceso a planes ni consume cuota: es un apoyo al proyecto.',
        ],
    ],
];
