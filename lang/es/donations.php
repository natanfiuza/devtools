<?php

/*
| Donaciones por Pix (F6). Namespace del frontend: la página pública y la
| página de estado. El correo del recibo vive en mail.php (solo servidor).
*/

return [
    'title' => 'Haz una donación',
    'subtitle' => 'El sitio es gratuito y sigue así. Si te ayuda en el día a día, una donación de cualquier importe mantiene las herramientas en línea.',
    'meta' => [
        'title' => 'Donar a devtools',
        'description' => 'Apoya a devtools con una donación por Pix. Cualquier importe ayuda a mantener las herramientas gratuitas y en línea.',
    ],

    'amount' => [
        'label' => 'Importe de la donación',
        'suggested' => 'Importes sugeridos',
        'custom' => 'Otro importe',
        'custom_hint' => 'Entre {min} y {max}.',
        'invalid' => 'Elige un importe entre {min} y {max}.',
    ],

    'donor' => [
        'title' => 'Quién dona',
        'name' => 'Nombre (opcional)',
        'name_placeholder' => 'Cómo quieres aparecer',
        'email' => 'Correo para el recibo (opcional)',
        'email_hint' => 'Solo usamos el correo para enviar el recibo de esta donación.',
        'anonymous' => 'Donar de forma anónima',
        'anonymous_hint' => 'Sin nombre. El correo, si lo indicas, solo sirve para el recibo.',
    ],

    'submit' => 'Generar Pix',
    'processing' => 'Generando el Pix…',

    'info' => [
        'title' => 'Donación no disponible por ahora',
        'description_lead' => 'Pix todavía no está configurado aquí. Vuelve más tarde o escríbenos desde la',
        'contact_link' => 'página de contacto',
        'description' => 'Pix todavía no está configurado aquí. Vuelve más tarde o escríbenos desde la página de contacto.',
    ],

    'pix' => [
        'title' => 'Paga con Pix',
        'description' => 'Abre la app de tu banco, elige Pix y escanea el QR o pega el código de abajo.',
        'copy' => 'Copiar código Pix',
        'copied' => 'Código copiado.',
        'copy_failed' => 'No se pudo copiar. Selecciona el código de abajo.',
        'ticket' => 'Abrir en la app del banco',
        'dynamic_note' => 'La confirmación llega automáticamente cuando se apruebe el pago.',
        'static_note' => 'Este es un Pix estático: no hay confirmación automática. Guarda el comprobante.',
    ],

    'status' => [
        'title' => 'Tu donación',
        'amount' => 'Importe',
        'created_at' => 'Creada el',
        'expires_at' => 'Válida hasta',
        'pending' => 'Esperando pago',
        'paid' => 'Donación confirmada',
        'expired' => 'Pix caducado',
        'refunded' => 'Donación devuelta',
        'failed' => 'Donación no completada',
        'pending_hint' => 'En cuanto se apruebe el pago, esta página muestra la confirmación.',
        'paid_hint' => '¡Gracias por apoyar el proyecto! Una donación no da acceso a planes ni consume cuota: es un apoyo al sitio.',
        'expired_hint' => 'El plazo del QR pasó y no se cobró nada. Puedes generar otra donación cuando quieras.',
        'refunded_hint' => 'El importe de esta donación fue devuelto.',
        'failed_hint' => 'El pago no se completó. Puedes intentarlo de nuevo cuando quieras.',
        'new' => 'Hacer otra donación',
        'receipt' => 'Si indicaste un correo, el recibo llegará a tu bandeja de entrada.',
    ],

    'errors' => [
        'unavailable' => 'Las donaciones por Pix no están disponibles por ahora.',
        'gateway' => 'No se pudo generar el Pix ahora. Inténtalo de nuevo en unos instantes.',
        'payer' => 'No se pudo generar el Pix sin un correo de contacto. Inténtalo de nuevo indicando tu correo.',
    ],

    'pix_description' => 'Donación para :app',
];
