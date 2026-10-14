<?php

/*
|--------------------------------------------------------------------------
| Registro de tools (textos)
|--------------------------------------------------------------------------
|
| O `manifest.json` de cada tool guarda so identidade e ciclo de vida; todo
| texto visivel mora aqui, por convencao `tools.<slug>.<campo>` (o slug e o
| nome do diretorio). Os nomes das tools ficam em ingles em todos os
| idiomas: sao nomes de produto, como aparecem na SPEC e no DESIGN.
|
*/

return [

    'status' => [
        'soon' => 'Próximamente',
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Codificación y hash',
        'security' => 'Seguridad',
        'time' => 'Fecha y hora',
        'text' => 'Texto',
    ],

    'json-viewer' => [
        'name' => 'JSON Viewer',
        'description' => 'Explora, formatea y valida JSON en árbol, con búsqueda y copia de cualquier ruta.',
        'keywords' => 'formatear, validar, árbol, minificar, ruta',
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Describe la consulta en lenguaje natural y recibe el SQL listo para tu dialecto.',
        'keywords' => 'lenguaje natural, generar consulta, base de datos',
    ],

    'json-to-code' => [
        'name' => 'JSON a código',
        'description' => 'Convierte JSON en tipos TypeScript, DTO de PHP y modelos de Python.',
        'keywords' => 'generar tipos, interface, dto, pydantic',
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formatea consultas SQL en los dialectos MySQL, PostgreSQL, SQL Server y SQLite.',
        'keywords' => 'embellecer, indentar, consulta',
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL y Hash',
        'description' => 'Codifica y decodifica Base64 y URL, y genera hashes MD5, SHA-1 y SHA-256.',
        'keywords' => 'codificar, decodificar, resumen, digest',
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Lee el encabezado y el payload de un JWT. La firma no se valida.',
        'keywords' => 'token, bearer, claims, autenticación',
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp y UUID',
        'description' => 'Convierte timestamps Unix y fechas, y genera valores UUID y ULID.',
        'keywords' => 'unix, epoch, fecha, guid',
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Prueba expresiones regulares con resaltado en vivo de coincidencias y grupos.',
        'keywords' => 'patrón, coincidencia, grupos',
    ],

];
