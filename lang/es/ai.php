<?php

/*
| AI SQL Builder (namespace "ai").
|
| A tela traduz o codigo de erro devolvido pela API; a mensagem tecnica do
| servidor e so fallback. Nenhum texto aqui cita modelo ou provedor: isso e
| configuracao, nao interface.
*/

return [
    'form' => [
        'title' => 'Describe la consulta',
        'schema_source' => 'Origen del esquema',
        'schema' => 'Esquema',
        'schema_placeholder' => 'Pega las tablas, columnas y relaciones que la consulta puede usar.',
        'description' => '¿Qué debe hacer la consulta?',
        'description_placeholder' => 'Ejemplo: lista los 10 clientes que más gastaron en los últimos 30 días.',
        'dialect' => 'Dialecto',
        'target' => 'Salida',
        'submit' => 'Generar',
        'submitting' => 'Enviando...',
        'schema_limit' => 'Esquema: {used} de {limit}',
        'description_limit' => 'Descripción: {used} de {limit}',
        'over_limit' => 'Esto supera el límite de tu plan.',
        'required' => 'Completa el esquema y la descripción.',
        'sensitive' => 'No pegues contraseñas, tokens ni datos personales.',
    ],

    'sources' => [
        'ddl' => 'DDL',
        'json' => 'JSON',
        'migration' => 'Migración Laravel',
        'models' => 'Modelos SQLModel',
    ],

    'dialects' => [
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL',
        'sqlsrv' => 'SQL Server',
        'sqlite' => 'SQLite',
    ],

    'targets' => [
        'sql' => 'SQL',
        'laravel_query_builder' => 'Laravel Query Builder',
        'eloquent' => 'Eloquent',
        'sqlmodel' => 'SQLModel',
        'sqlalchemy' => 'SQLAlchemy',
        'django_orm' => 'Django ORM',
        'prisma' => 'Prisma',
        'drizzle' => 'Drizzle ORM',
        'typeorm' => 'TypeORM',
    ],

    'states' => [
        'idle' => 'Listo cuando quieras.',
        'queued' => 'En la cola...',
        'processing' => 'Generando...',
        'done' => 'Resultado listo.',
        'failed' => 'No se pudo generar el resultado.',
        'timeout' => 'Está tardando más de lo esperado. Puedes consultar de nuevo.',
        'retry' => 'Consultar de nuevo',
        'poll_hint' => 'Puedes mantener esta pestaña abierta; el resultado aparece aquí.',
    ],

    'guest' => [
        'title' => 'Inicia sesión para usar AI SQL Builder',
        'body' => 'AI SQL Builder necesita una cuenta verificada: el esquema y la descripción pasan por el proveedor de IA y consumen la cuota del plan.',
        'cta' => 'Iniciar sesión',
    ],

    'unverified' => [
        'title' => 'Confirma tu correo para usar la IA',
        'body' => 'Enviamos un enlace de confirmación a tu correo. Confírmalo antes de generar una consulta.',
        'cta' => 'Reenviar confirmación',
        'sent' => 'Confirmación enviada. Revisa tu bandeja de entrada.',
        'failed' => 'No se pudo enviar la confirmación. Inténtalo más tarde.',
    ],

    'suspended' => [
        'title' => 'Cuenta suspendida',
        'body' => 'Esta cuenta no puede usar AI SQL Builder ahora mismo. Contacta con el soporte.',
    ],

    'unavailable' => [
        'title' => 'IA no disponible',
        'body' => 'El servicio de IA no está configurado ahora mismo. Inténtalo más tarde.',
    ],

    'quota' => [
        'title' => 'Cuota agotada',
        'body_uses' => 'Has usado todos los {limit} usos gratuitos de esta cuenta.',
        'body_tokens' => 'Has usado todos los {limit} tokens de este periodo.',
        'remaining_uses' => 'Quedan {remaining} de {limit} usos',
        'remaining_tokens' => 'Quedan {remaining} de {limit} tokens',
        'cta' => 'Ver planes',
    ],

    'result' => [
        'title' => 'Resultado',
        'copy' => 'Copiar',
        'copied' => 'Copiado',
        'download' => 'Descargar',
        'download_name' => 'consulta',
        'assumptions' => 'Supuestos',
        'warnings' => 'Avisos',
        'tables' => 'Tablas usadas',
        'tokens_in' => 'Entrada: {count} tokens',
        'tokens_out' => 'Salida: {count} tokens',
        'cache_hit' => 'Resultado en caché',
        'clarification' => 'El modelo necesita más información',
        'empty' => 'Nada generado todavía.',
    ],

    'errors' => [
        'generic' => 'Algo salió mal. Inténtalo de nuevo.',
        'network' => 'No se pudo contactar con el servidor. Revisa tu conexión e inténtalo de nuevo.',
        'validation' => 'Revisa los campos del formulario e inténtalo de nuevo.',
        'unauthenticated' => 'Tu sesión ha caducado. Inicia sesión de nuevo.',
        'forbidden' => 'Esta cuenta no puede usar AI SQL Builder.',
        'email_unverified' => 'Confirma tu correo antes de usar la IA.',
        'account_suspended' => 'Esta cuenta está suspendida.',
        'not_found' => 'Solicitud no encontrada.',
        'csrf' => 'Tu sesión ha caducado. Recarga la página e inténtalo de nuevo.',
        'rate_limited' => 'Demasiadas solicitudes. Inténtalo de nuevo en {seconds} segundos.',
        'quota_exhausted' => 'Cuota agotada. Consulta los planes para continuar.',
        'unavailable' => 'El servicio de IA no está disponible ahora mismo.',
        'timeout' => 'La solicitud tardó demasiado. Inténtalo de nuevo.',
        'provider_unavailable' => 'El proveedor de IA no está disponible ahora mismo. Inténtalo más tarde.',
        'invalid_response' => 'La IA devolvió una respuesta inválida.',
        'unsafe_sql' => 'El SQL generado fue bloqueado por la comprobación de seguridad.',
        'invalid_contract' => 'La respuesta de la IA no tiene el formato esperado.',
        'internal' => 'Error interno al procesar la solicitud.',
        'schema_too_large' => 'El esquema supera el límite de {limit} de tu plan.',
        'description_too_long' => 'La descripción supera el límite de {limit} caracteres de tu plan.',
        'unsupported_dialect' => 'La salida elegida no admite este dialecto.',
    ],

    'warnings' => [
        'review' => 'Revisa el resultado antes de usarlo. La consulta nunca se ejecuta aquí.',
        'sensitive' => 'No pegues contraseñas, tokens ni datos personales en el esquema.',
    ],

    'history' => [
        'title' => 'Consultas Anteriores',
        'search_placeholder' => 'Buscar en el esquema, prompt o título...',
        'loading' => 'Cargando historial...',
        'error' => 'No se pudo cargar el historial de consultas.',
        'empty' => 'Aún no se ha realizado ninguna consulta.',
        'no_search_results' => 'No se encontraron consultas para "{query}".',
        'untitled' => 'Consulta sin título',
        'collapse' => 'Plegar historial',
        'expand' => 'Desplegar historial',
    ],
];
