<?php

/*
| Paginas legais: Politica de Privacidade e Termos de Uso (namespace "legal").
|
| O e-mail de contato e a data da revisao vem de config/legal.php (nao ficam
| presos aqui). ":date" e montado no servidor (Laravel); "{app}" vem do Vue (vue-i18n).
*/

return [
    'updated' => 'Última actualización: :date',
    'contact' => 'Contacto',

    'privacy' => [
        'title' => 'Política de privacidad',
        'intro' => 'Esta página explica qué datos guarda {app}, para qué y durante cuánto tiempo. La mayoría de las herramientas funcionan en tu navegador; AI SQL Builder es la excepción y envía el esquema y la descripción que proporcionas a un proveedor de IA.',
        'sections' => [
            'data' => [
                'title' => 'Qué guardamos',
                'body' => 'Lo mínimo para una cuenta: nombre, correo, contraseña (como hash) y el idioma que usas. Si entras con Google o GitHub, guardamos el id de la cuenta en el proveedor y tu correo — nunca tu contraseña allí. Si te suscribes a la newsletter, guardamos el correo y el idioma de la suscripción. Cuando usas AI SQL Builder, guardamos los metadatos de la solicitud, el resultado generado y, durante un máximo de 30 días, una copia comprimida del esquema y la descripción para reutilizar solicitudes idénticas.',
            ],
            'purpose' => [
                'title' => 'Para qué lo usamos',
                'body' => 'Para mantener tu cuenta funcionando, enviar los correos que pediste (restablecimiento de contraseña, confirmación de correo, newsletter), generar los resultados de IA que pides y proteger el sitio contra abusos.',
            ],
            'browser' => [
                'title' => 'Dónde se procesan tus datos',
                'body' => 'Las herramientas de cliente funcionan en tu navegador: lo que pegas en ellas se procesa en tu máquina y no se envía a nuestros servidores. AI SQL Builder es la excepción: el esquema y la descripción que envías van a nuestro proveedor de IA (actualmente DeepSeek) para que el modelo genere el resultado. No enviamos los datos de tu cuenta al proveedor, y el SQL generado nunca se ejecuta en nuestros servidores.',
            ],
            'sharing' => [
                'title' => 'Con quién compartimos',
                'body' => 'No vendemos ni alquilamos tus datos. Compartimos solo con lo que hace funcionar el sitio: alojamiento y envío de correos, el proveedor de IA que usa AI SQL Builder (actualmente DeepSeek), además de Google o GitHub cuando eliges entrar con ellos. Si aceptas la medición, las páginas vistas también van a Google Analytics.',
            ],
            'cookies' => [
                'title' => 'Cookies',
                'body' => 'Las cookies esenciales hacen funcionar el sitio: sesión, idioma y preferencia de tema. Las de medición (Google Analytics) son opcionales y solo se instalan después de que aceptes; esa elección puede cambiarse en cualquier momento en «Cookies», en el pie de página. Nada de cookies publicitarias.',
            ],
            'retention' => [
                'title' => 'Durante cuánto tiempo',
                'body' => 'Los datos de la cuenta se quedan mientras exista la cuenta y desaparecen cuando la eliminas. Los datos de la newsletter se quedan hasta que te des de baja. Las solicitudes de IA y sus resultados se guardan para que la herramienta muestre el estado de la solicitud; la copia comprimida del esquema y la descripción permanece en caché hasta 30 días.',
            ],
            'rights' => [
                'title' => 'Tus derechos',
                'body' => 'Puedes ver y cambiar tus datos en los ajustes de la cuenta, y puedes pedir una copia o la eliminación por correo. Respondemos dentro del plazo legal.',
            ],
            'security' => [
                'title' => 'Seguridad',
                'body' => 'Las contraseñas se guardan como hash, el sitio funciona sobre HTTPS y el acceso al servidor es restringido. Ningún sistema es perfecto: si algo pasa, te avisamos.',
            ],
            'changes' => [
                'title' => 'Cambios en esta página',
                'body' => 'Este texto puede actualizarse a medida que el sitio evoluciona. La fecha de la última revisión está siempre al inicio de la página.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Términos de uso',
        'intro' => 'Las reglas para usar {app}. Al navegar o crear una cuenta, las aceptas.',
        'sections' => [
            'service' => [
                'title' => 'Qué es el servicio',
                'body' => '{app} es un conjunto de herramientas de desarrollo. La mayoría funcionan en tu navegador y se ofrecen tal como están y gratis, para uso personal o profesional; AI SQL Builder usa un proveedor de IA y la cuota del plan. Los resultados generados son una ayuda: la decisión final es tuya.',
            ],
            'account' => [
                'title' => 'Tu cuenta',
                'body' => 'Eres responsable de tu cuenta: cuida tu contraseña y tu acceso social. Las cuentas creadas con Google o GitHub no tienen contraseña y se accede a ellas con el proveedor.',
            ],
            'use' => [
                'title' => 'Uso aceptable',
                'body' => 'Usa las herramientas para fines lícitos. No ataques, sobrecargues ni extraigas datos del servicio, y no lo uses para romper sistemas de terceros. Los resultados generados (como SQL) son una ayuda: la decisión final es tuya.',
            ],
            'content' => [
                'title' => 'Contenido',
                'body' => 'Lo que pegas en las herramientas sigue siendo tuyo. Al usar AI SQL Builder, autorizas el envío del esquema y la descripción al proveedor de IA para generar el resultado. El sitio, la marca y el código siguen siendo nuestros.',
            ],
            'availability' => [
                'title' => 'Disponibilidad',
                'body' => 'Intentamos mantener el sitio en línea, pero no lo prometemos. Las funciones pueden cambiar o desaparecer.',
            ],
            'warranty' => [
                'title' => 'Sin garantías',
                'body' => 'El sitio se ofrece sin garantías. No nos hacemos responsables de daños o pérdidas derivados del uso de los resultados generados.',
            ],
            'changes' => [
                'title' => 'Cambios en los términos',
                'body' => 'Estos términos pueden cambiar. Seguir usando el sitio después de un cambio significa que aceptas la nueva versión.',
            ],
            'law' => [
                'title' => 'Ley aplicable',
                'body' => 'Estos términos se rigen por las leyes de Brasil, y los tribunales brasileños son competentes para resolver cualquier disputa.',
            ],
        ],
    ],
];
