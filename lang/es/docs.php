<?php

/*
| Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
|
| Os nomes de provider e os nomes de metrica sao marcas/nomes proprios e nao
| se traduzem: o texto da metrica vem de `tools.badgeforge.provider.metrics.*`.
| As traducoes abaixo cobrem apenas o texto explicativo da pagina.
*/

return [

    'badgeforge' => [

        'meta' => [
            'title' => 'Documentación de BadgeForge',
            'description' => 'Badges dinámicas para estrellas de GitHub, versiones de npm, descargas de Packagist y pulls de Docker, además de badges personales con credencial vinculada a cada badge.',
        ],

        'hero' => [
            'eyebrow' => 'BadgeForge',
            'title' => 'Badges que se actualizan solas',
            'subtitle' => 'Las badges estáticas se dibujan en el servidor. Las de provider leen GitHub, npm, Packagist y Docker Hub de forma anónima, con una respuesta en caché que nunca rompe la imagen de tu README.',
            'open_tool' => 'Abrir el generador',
        ],

        'endpoints' => [
            'title' => 'Endpoints',
            'static' => 'Badges estáticas',
            'provider' => 'Badges de provider',
            'personal' => 'Badges personales',
            'json' => 'Badges de endpoint JSON',
        ],

        'quick_start' => [
            'title' => 'Empieza en un minuto',
            'body' => 'Pega el Markdown en tu README. GitHub renderiza el SVG directamente y cada petición queda en caché de nuestro lado.',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
        ],

        'static' => [
            'title' => 'Badges estáticas',
            'body' => 'La etiqueta y el mensaje forman parte de la URL. Todos los estilos, colores, logos y fuentes del generador están disponibles en la query string.',
            'example_label' => 'Ejemplo',
        ],

        'providers' => [
            'title' => 'Badges de provider',
            'intro' => 'Cada provider expone una lista corta de métricas. La ruta después de la métrica sigue al propio provider: repositorio, paquete o imagen.',
            'metric' => 'Métrica',
            'path' => 'Ruta',
            'access' => 'Acceso',
            'public' => 'Público',
            'private' => 'Elemento privado',
            'documentation' => 'Documentación de la API del provider',
            'unknown_note' => 'Una métrica inexistente, o una respuesta que el provider no reconoce, muestra la badge como «unknown».',
            'descriptions' => [
                'github' => 'Estrellas, forks, issues abiertas y licencia desde la API del repositorio. Los tokens fine-grained funcionan para repositorios privados.',
                'npm' => 'Última versión publicada y descargas mensuales. El contador de descargas es siempre público.',
                'packagist' => 'Descargas totales y última versión de un paquete Composer, siempre desde el registro público.',
                'docker' => 'Recuento de pulls de Docker Hub. Las imágenes privadas usan el nombre de usuario más un personal access token.',
            ],
        ],

        'personal' => [
            'title' => 'Badges personales',
            'intro' => 'Las cuentas de pago publican su propia badge en una URL opaca. La URL lleva tu nombre de usuario y el ULID de la badge — nunca un token.',
            'url_label' => 'Formato de la URL',
            'username_required' => 'Define un nombre de usuario en tu cuenta antes de crear una badge personal.',
            'providers_title' => 'Elementos privados',
            'providers_body' => 'Una badge personal puede leer un repositorio, paquete o imagen privada. La credencial es tuya, se guarda cifrada y está vinculada solo a esa badge.',
            'github' => 'GitHub: un personal access token con lectura en el repositorio (fine-grained o clásico).',
            'npm' => 'npm: un token para un paquete privado. El contador de descargas (dm) es siempre público.',
            'docker' => 'Docker Hub: nombre de usuario más un personal access token; ambos son obligatorios para imágenes privadas.',
            'packagist' => 'Packagist: solo público. Los paquetes privados no están soportados.',
            'security_title' => 'Cómo se guarda la credencial',
            'security_body' => 'El token se cifra con la clave de la aplicación y nunca aparece en la URL de la imagen, en las props de la página, en los logs ni en las exportaciones. Si se cambia la clave de la aplicación, la badge sigue sirviendo la última imagen buena hasta que vuelvas a definir la credencial.',
            'manage_cta' => 'Gestionar badges personales',
            'tool_cta' => 'Crear una badge personal en el generador',
            'refresh_note' => 'La imagen es stateless. Para forzar una nueva generación usa la acción de refresh del panel; la URL pública ignora ?refresh=1.',
        ],

        'endpoint' => [
            'title' => 'Badges de endpoint JSON',
            'intro' => 'Registra una URL HTTPS en tu panel y obtén una URL de badge opaca. La URL de origen nunca aparece en la imagen, y quien lee tu README no necesita una cuenta para ver la badge.',
            'url_label' => 'Formato de la URL',
            'contract_title' => 'JSON esperado',
            'contract_body' => 'El origen debe responder con el esquema shields.io versión 1: schemaVersion 1, label y message no vacíos, y color/labelColor opcionales que se usan como valores por defecto — un parámetro de la query siempre gana. Los demás campos se ignoran.',
            'cache_title' => 'Caché y respaldo',
            'cache_body' => 'La respuesta se guarda {ttl} segundos. Si el origen cae o nos limita, se sigue sirviendo la última respuesta buena (STALE); sin respuesta previa, la badge muestra “unavailable”, nunca un error.',
            'limits_title' => 'Límite de peticiones',
            'limits_anonymous' => 'Badge desconocida o anónima: {limit} peticiones por minuto por IP.',
            'limits_free' => 'Propietario con cuenta gratuita: {limit} peticiones por minuto.',
            'limits_paid' => 'Propietario con cuenta de pago: {limit} peticiones por minuto.',
            'security_title' => 'Por qué es seguro',
            'security_body' => 'Solo HTTPS en el puerto 443, sin redirecciones, y todas las IP resueltas deben ser públicas. La conexión se fija a la IP validada, así que un cambio de DNS entre la comprobación y la petición no puede llegar a una dirección interna. La URL de origen queda en el servidor: no está en la página, ni en los logs, ni en la URL de la imagen.',
            'manage_cta' => 'Gestionar endpoints JSON',
        ],
        'cache' => [
            'title' => 'Caché, ETag y respuestas antiguas',
            'body' => 'Las respuestas de provider se guardan en caché durante {ttl} segundos. La imagen sale con ETag y Cache-Control, así que una segunda petición recibe 304 cuando nada cambió.',
            'stale_title' => 'Stale-if-error',
            'stale_body' => 'Cuando el provider falla o nos limita la tasa, la última respuesta buena sigue sirviéndose. Sin respuesta anterior, la badge muestra un mensaje corto de indisponibilidad en lugar de un error.',
            'refresh_title' => 'Refresh',
            'refresh_body' => 'Las URLs públicas de provider nunca fuerzan un refresh. Las badges personales tienen una acción de refresh que solo el dueño puede lanzar.',
        ],

        'errors' => [
            'title' => 'Errores y fallback',
            'intro' => 'Una URL de badge siempre responde con una imagen. Las excepciones son una ruta inexistente (404) y una petición de PNG en un servidor sin Imagick (503).',
            'cached' => 'Provider caído o con límite de tasa: se sirve la imagen en caché (cabecera X-Badge-Cache: STALE).',
            'fallback' => 'Todavía sin imagen en caché: la badge muestra la etiqueta de la métrica con «no disponible».',
            'unknown' => 'El provider respondió pero la métrica no se puede leer: la badge muestra «unknown».',
            'not_found' => 'Provider desconocido, métrica desconocida o ruta inválida: 404.',
            'private' => 'Un elemento privado sin credencial nunca se sirve por una URL pública de provider.',
        ],

        'security' => [
            'title' => 'Seguridad y privacidad',
            'anonymous' => 'Las métricas públicas se leen de forma anónima. No existe ningún token global de provider en la configuración ni en el entorno.',
            'credential' => 'Las credenciales son del dueño de la badge, se cifran en reposo y valen para una sola badge.',
            'logs' => 'Las respuestas de provider se proyectan antes de la caché: solo se guardan los números que muestra la badge. Descripciones, READMEs y tokens nunca llegan a la caché ni a los logs.',
            'url' => 'Las URLs de badge personal solo llevan el nombre de usuario y el ULID; revocar el acceso es borrar la badge.',
        ],

    ],

];
