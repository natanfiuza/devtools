<?php

/*
| Mensajes y textos de la pagina publica de contacto (Contactanos).
*/

return [
    'meta' => [
        'title' => 'Contacto',
        'description' => 'Ponte en contacto con el equipo de devtools. Envía tus dudas, sugerencias, reporta errores o consulta sobre donaciones.',
    ],

    'title' => 'Contáctanos',
    'subtitle' => '¿Tienes alguna duda, sugerencia o encontraste un problema? Envía un mensaje a nuestro equipo.',

    'fields' => [
        'name' => 'Nombre',
        'name_placeholder' => 'Tu nombre completo o cómo prefieres que te llamemos',
        'email' => 'Correo electrónico',
        'email_placeholder' => "tu{'@'}correo.com",
        'email_hint' => 'Utilizaremos este correo electrónico exclusivamente para responder a tu mensaje.',
        'subject' => 'Asunto',
        'subject_placeholder' => 'Selecciona el motivo del contacto',
        'message' => 'Mensaje',
        'message_placeholder' => 'Describe tu duda, sugerencia o reporte con el mayor detalle posible…',
        'characters' => '{count} / {max} caracteres',
    ],

    'subjects' => [
        'general' => 'Duda general',
        'donation' => 'Problema con donación / pago',
        'feedback' => 'Sugerencia o mejora',
        'bug' => 'Reportar error',
        'other' => 'Otro asunto',
    ],

    'submit' => 'Enviar mensaje',
    'submitting' => 'Enviando…',

    'success_title' => '¡Mensaje enviado con éxito!',
    'success_body' => 'Hemos recibido tu mensaje y te responderemos lo antes posible a la dirección de correo proporcionada.',
    'send_another' => 'Enviar otro mensaje',

    'sent_toast' => '¡Tu mensaje ha sido enviado con éxito!',
    'throttle' => 'Demasiados mensajes enviados en poco tiempo. Por favor, espere :seconds segundos antes de intentar nuevamente.',
];
