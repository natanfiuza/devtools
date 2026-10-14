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

    /*
    | Textos da casca de qualquer tool (os paineis, a barra de acoes e os
    | estados). O que e de uma ferramenta so mora em `tools.<slug>.*`.
    */
    'ui' => [
        'input' => 'Entrada',
        'output' => 'Salida',
        'resize' => 'Redimensionar los paneles',
        'drop' => 'Suelta el archivo aquí',

        'actions' => [
            'open' => 'Abrir archivo',
            'example' => 'Ejemplo',
            'clear' => 'Limpiar',
            'copy' => 'Copiar',
            'copied' => 'Copiado',
            'copy_manual' => 'Selecciona el texto y cópialo',
            'download' => 'Descargar',
        ],

        'states' => [
            'empty' => 'Pega, escribe o suelta un archivo JSON para empezar.',
            'parsing' => 'Analizando...',
            'unavailable' => 'Herramienta no disponible.',
            'invalid' => 'JSON no válido en la línea {line}, columna {column}.',
            'invalid_no_position' => 'JSON no válido.',
            'too_large' => 'El texto ocupa {size} y supera el límite de {limit}. El análisis está desactivado.',
            'file_too_large' => 'El archivo ocupa {size} y supera el límite de {limit}.',
            'file_type' => 'Formato no admitido. Envía un archivo .json o .txt.',
            'file_unreadable' => 'No se pudo leer el archivo.',
            'drop_hint' => 'Suelta para abrir el archivo.',
        ],

        'limits' => [
            'of' => 'de {limit}',
        ],

        'shortcuts' => [
            'title' => 'Atajos',
        ],
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
        'intro' => 'Pega un JSON para ver el árbol coloreado, formatear, minimizar y copiar la ruta de cualquier nodo. Todo se ejecuta en tu navegador: nada de lo que pegas aquí se envía al servidor.',

        'format' => 'Formatear',
        'minify' => 'Minificar',
        'sort_keys' => 'Ordenar claves',
        'expand_all' => 'Expandir todo',
        'collapse_all' => 'Contraer todo',
        'download_name' => 'documento',

        'tree' => [
            'toggle' => 'Contraer o expandir',
            'copy_path' => 'Copiar ruta',
            'copied' => 'Ruta copiada: {path}',
        ],

        'search' => [
            'placeholder' => 'Buscar clave o valor',
            'label' => 'Buscar en el árbol',
            'clear' => 'Limpiar búsqueda',
            'results' => 'Resultados: {count}',
            'truncated' => 'Mostrando los primeros {count} resultados.',
            'none' => 'Sin resultados.',
        ],

        'stats' => [
            'keys' => 'Claves: {count}',
            'items' => 'Elementos: {count}',
        ],

        'shortcuts' => [
            'search' => 'Buscar en el árbol',
            'undo' => 'Deshacer en el editor',
            'paste' => 'Pegar en el editor',
        ],
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
