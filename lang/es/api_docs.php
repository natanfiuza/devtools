<?php

return [
    'meta' => [
        'title' => 'Documentación de la API v1',
        'description' => 'Integra el generador de consultas SQL con tus sistemas mediante la API REST con autenticación Bearer, procesamiento asíncrono y soporte para múltiples dialectos y ORMs.',
    ],

    'hero' => [
        'eyebrow' => 'REST API v1',
        'title' => 'Documentación de la API',
        'subtitle' => 'Integra la generación de consultas SQL, Query Builders y ORMs directamente en tu backend, CLI, pipeline de CI/CD o flujos de automatización como n8n.',
        'base_url_label' => 'URL base de la API',
        'copy_base_url' => 'Copiar URL base',
        'copied' => '¡Copiado!',
        'manage_tokens' => 'Gestionar tokens de API',
        'openapi_spec' => 'Descargar OpenAPI 3.1 (YAML)',
    ],

    'auth' => [
        'title' => 'Autenticación y flujo asíncrono',
        'description' => 'Todas las rutas bajo /api/v1 requieren un token personal de Laravel Sanctum (ability ai:query) enviado en el encabezado Authorization.',
        'step_1_title' => '1. Crea tu token',
        'step_1_body' => 'Ve a Ajustes > Tokens de API en tu cuenta, genera un token personal y copia el valor que se muestra una sola vez.',
        'step_2_title' => '2. Envía los encabezados HTTP',
        'step_2_body' => 'Incluye Authorization: Bearer TU_TOKEN y Accept: application/json en todas las llamadas HTTP.',
        'step_3_title' => '3. Maneja 200 (caché) y 202 (cola)',
        'step_3_body' => 'Si una consulta idéntica ya está en caché, POST devuelve 200 (estado done) de inmediato. De lo contrario, devuelve 202 Accepted con poll_url para consultar hasta done o failed.',
        'headers_label' => 'Encabezados obligatorios',
    ],

    'examples' => [
        'title' => 'Ejemplos de integración',
        'description' => 'Fragmentos listos para enviar un esquema, solicitar una consulta y esperar el procesamiento asíncrono.',
        'copy' => 'Copiar código',
        'copied' => '¡Copiado!',
        'tabs' => [
            'curl' => 'cURL',
            'js' => 'JavaScript / TS',
            'php' => 'PHP',
            'python' => 'Python',
            'n8n' => 'n8n / HTTP',
        ],
        'n8n_note' => 'En n8n, usa un nodo HTTP Request con Header Auth (Authorization: Bearer TU_TOKEN). Si data.poll_url no es nulo (estado queued o processing), añade un nodo Wait respetando poll_after_ms conectado a un segundo HTTP Request consultando data.poll_url.',
    ],

    'endpoints' => [
        'title' => 'Endpoints disponibles',
        'description' => 'Referencia completa de las rutas públicas de la API v1.',
        'params_label' => 'Parámetros',
        'response_label' => 'Respuesta de ejemplo',
        'field' => 'Campo',
        'type' => 'Tipo',
        'required' => 'Obligatorio',
        'yes' => 'Sí',
        'no' => 'No',
        'details' => 'Descripción',
        'targets_title' => 'Formatos de salida habilitados (output_target)',
        'targets_description' => 'Valores aceptados en el campo output_target del endpoint POST /api/v1/sql-queries.',
        'target_slug' => 'Slug (output_target)',
        'target_language' => 'Lenguaje',
        'target_dialects' => 'Dialectos soportados',
        'target_name' => 'Etiqueta',
        'items' => [
            'me' => [
                'summary' => 'Devuelve los datos de la cuenta autenticada, el plan activo y el saldo de cuota en el período actual.',
            ],
            'targets' => [
                'summary' => 'Lista todos los formatos de salida habilitados (SQL, Query Builders y ORMs) y los dialectos que admite cada uno.',
            ],
            'store_query' => [
                'summary' => 'Envía un esquema y una descripción en lenguaje natural para generar la consulta en el formato elegido.',
                'schema_source_desc' => 'Formato del esquema enviado: ddl, json, migration o models.',
                'dialect_desc' => 'Dialecto SQL de referencia: mysql, pgsql, sqlsrv o sqlite.',
                'output_target_desc' => 'Slug del formato de salida habilitado (ej.: sql, eloquent, prisma).',
                'schema_desc' => 'Contenido del esquema de las tablas de referencia.',
                'description_desc' => 'Descripción en lenguaje natural de lo que debe devolver la consulta.',
                'locale_desc' => 'Idioma opcional de la explicación (pt_BR, pt_PT, en, es, fr).',
            ],
            'list_queries' => [
                'summary' => 'Lista el historial paginado de consultas generadas por la cuenta autenticada.',
                'status_desc' => 'Filtra por estado: queued, processing, done o failed.',
                'page_desc' => 'Número de página (por defecto 1, {perPage} elementos por página).',
            ],
            'show_query' => [
                'summary' => 'Consulta el estado actual y el resultado de una petición específica por su ULID.',
                'ulid_desc' => 'Identificador ULID devuelto al crear la consulta.',
            ],
        ],
    ],

    'limits' => [
        'title' => 'Límites de tasa (Rate Limits) y cuotas',
        'description' => 'La API aplica límites por minuto y por hora por token autenticado, además de la cuota de consultas de tu plan.',
        'post_title' => 'Creación de consultas (POST /sql-queries)',
        'read_title' => 'Lectura y polling (GET /me, /output-targets, /sql-queries)',
        'per_minute' => '{count} peticiones / minuto',
        'per_hour' => '{count} peticiones / hora',
        'headers_note' => 'Las respuestas incluyen X-RateLimit-Limit y X-RateLimit-Remaining. Cuando se supera el límite, la API responde 429 Too Many Requests con el encabezado Retry-After.',
    ],

    'errors' => [
        'title' => 'Códigos de estado y errores',
        'description' => 'Todos los fallos devuelven un JSON estandarizado con error.code, error.message y error.details.',
        'status' => 'Estado HTTP',
        'code' => 'error.code',
        'when' => 'Cuándo ocurre',
        'items' => [
            '401' => 'Token ausente, inválido o ya revocado.',
            '402' => 'Cuota de peticiones o tokens de IA del plan agotada en el período.',
            '403' => 'Token sin la ability ai:query o cuenta suspendida por un administrador.',
            '404' => 'Consulta no encontrada o perteneciente a otra cuenta.',
            '422' => 'Fallo de validación en los campos enviados, esquema por encima del límite o dialecto incompatible.',
            '429' => 'Límite de peticiones por minuto o por hora excedido.',
            '503' => 'Proveedor de IA temporalmente no disponible o no configurado.',
        ],
    ],
];
