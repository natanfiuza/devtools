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
        'description' => 'Novedades de las herramientas, de vez en cuando. Sin spam.',
        'email' => 'Correo',
        'email_placeholder' => 'tu@ejemplo.com',
        'submit' => 'Suscribirme',
        'check_email' => 'Casi listo: confirma la suscripción en el correo que acabamos de enviar.',
        'already_confirmed' => 'Ya estás suscrito.',
    ],

    'confirm' => [
        'title_pending' => 'Confirma tu suscripción',
        'body_pending' => 'Un clic y listo: confirmamos tu correo y empiezas a recibir la newsletter.',
        'action' => 'Confirmar suscripción',
        'title_confirmed' => 'Suscripción confirmada',
        'body_confirmed' => 'Todo listo: estás en la lista. Puedes salir cuando quieras, con el enlace de cualquier correo.',
        'title_invalid' => 'Enlace caducado',
        'body_invalid' => 'Este enlace ya no sirve. Suscríbete de nuevo desde el pie de página del sitio.',
    ],

    'unsubscribe' => [
        'title_pending' => 'Darse de baja',
        'body_pending' => 'Te vamos a extrañar. Confirma para dejar de recibir la newsletter.',
        'action' => 'Darse de baja',
        'title_unsubscribed' => 'Baja confirmada',
        'body_unsubscribed' => 'Listo: ya no recibes la newsletter. Si cambias de idea, solo tienes que suscribirte otra vez.',
        'title_invalid' => 'Enlace caducado',
        'body_invalid' => 'Este enlace ya no sirve, así que no hay nada que dar de baja.',
    ],

    'invalid_link' => 'Este enlace ya no sirve.',
    'back_home' => 'Volver al sitio',
];
