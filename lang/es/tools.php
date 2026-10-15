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
            'empty' => 'Pega, escribe o suelta un archivo para empezar.',
            'parsing' => 'Procesando...',
            'unavailable' => 'Herramienta no disponible.',
            'invalid' => 'Entrada no válida en la línea {line}, columna {column}.',
            'invalid_no_position' => 'Entrada no válida.',
            'stale_output' => 'Salida de la última entrada válida.',
            'too_large' => 'El texto ocupa {size} y supera el límite de {limit}: no se procesará nada.',
            'file_too_large' => 'El archivo ocupa {size} y supera el límite de {limit}.',
            'file_type' => 'Formato no admitido. Envía un archivo {formats}.',
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
        'file_types' => 'JSON o TXT',

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
            'format' => 'Formatear el documento',
            'search' => 'Buscar en el árbol',
            'clear' => 'Limpiar la búsqueda',
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
        'intro' => 'Pega un JSON y elige el destino: interfaces de TypeScript, DTO de PHP con propiedades promovidas o modelos de Pydantic. La inferencia se ejecuta en tu navegador: nada de lo que pegas aquí se envía al servidor.',

        'target' => 'Lenguaje de destino',
        'root_name' => 'Nombre del tipo raíz',
        'download_name' => 'tipos',
        'file_types' => 'JSON o TXT',

        'targets' => [
            'typescript' => 'TypeScript',
            'php' => 'PHP (DTO)',
            'pydantic' => 'Pydantic',
        ],

        'states' => [
            'root_object' => 'La raíz debe ser un objeto JSON con al menos una clave.',
        ],

        'stats' => [
            'types' => 'Tipos: {count}',
        ],
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formatea consultas SQL en los dialectos MySQL, PostgreSQL, SQL Server y SQLite.',
        'keywords' => 'embellecer, indentar, consulta',
        'intro' => 'Pega la consulta y elige el dialecto: el SQL sale indentado a la derecha, y el uso de mayúsculas en las palabras clave es tu elección. El formateo se ejecuta en tu navegador: nada de lo que pegas aquí se envía al servidor.',

        'dialect' => 'Dialecto',
        'keyword_case' => 'Mayúsculas de las palabras clave',
        'download_name' => 'consulta',
        'file_types' => 'SQL o TXT',

        'dialects' => [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'sqlite' => 'SQLite',
        ],

        'cases' => [
            'upper' => 'MAYÚSCULAS',
            'lower' => 'minúsculas',
            'preserve' => 'Mantener',
        ],
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL y Hash',
        'description' => 'Codifica y decodifica Base64 y URL, y genera los hashes MD5, SHA-1, SHA-256 y SHA-512.',
        'keywords' => 'codificar, decodificar, resumen, digest',
        'intro' => 'Una entrada, tres lecturas: el mismo texto se vuelve Base64, URL codificada o hash. Elige la pestaña a la derecha y la dirección a la izquierda. El cálculo corre en tu navegador: nada de lo que pegas aquí se envía al servidor.',

        'direction' => 'Dirección',
        'file_types' => 'TXT',
        'row_copied' => '{label} copiado',

        'directions' => [
            'encode' => 'Codificar',
            'decode' => 'Decodificar',
        ],

        'modes' => [
            'base64' => 'Base64',
            'url' => 'URL',
            'hash' => 'Hash',
        ],

        'rows' => [
            'base64' => 'Base64',
            'urlSafe' => 'Base64 URL-safe',
            'component' => 'Componente',
            'form' => 'Formulario',
            'md5' => 'MD5',
            'sha1' => 'SHA-1',
            'sha256' => 'SHA-256',
            'sha512' => 'SHA-512',
        ],

        'states' => [
            'invalid_base64' => 'Esto no es el Base64 de un texto: hay un carácter fuera del alfabeto, sobra un carácter suelto al final, o los bytes no forman UTF-8.',
            'invalid_url' => 'Esto no es una URL codificada válida: hay una secuencia de escape rota o bytes que no forman UTF-8.',
            'no_crypto' => 'Este navegador solo expone Web Crypto en conexión segura (HTTPS), así que las tres líneas SHA quedan sin valor aquí. El MD5 sigue funcionando, pero recuerda: MD5 es suma de comprobación, no firma.',
        ],
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Lee el encabezado y el payload de un JWT. La firma no se valida.',
        'keywords' => 'token, bearer, claims, autenticación',
        'intro' => 'Pega el token (el prefijo Bearer puede venir incluido) para leer el encabezado, el payload y las fechas de los claims. La herramienta decodifica y no valida la firma, y todo se ejecuta en tu navegador: nada de lo que pegas aquí se envía al servidor.',

        'header' => 'Encabezado',
        'payload' => 'Payload',
        'signature' => 'Firma',
        'signature_bytes' => '{count} bytes',
        'not_verified' => 'no verificada',
        'block_copied' => '{label} copiado',

        'warning' => 'Este decodificador no valida la firma: muestra lo que está escrito en el token, y nada de lo que hay aquí prueba que el token sea auténtico.',

        'claims_title' => 'Fechas',

        'claims' => [
            'expired' => 'expirado',
            'not_yet' => 'aún no vale',
        ],

        'states' => [
            'shape' => 'Esto no es un JWT: un JWT son tres partes separadas por puntos (encabezado, payload y firma), y aquí no hay tres.',
            'base64' => 'Alguna de las partes no es base64url válido, o sus bytes no forman UTF-8.',
            'json' => 'El encabezado o el payload no es un objeto JSON.',
        ],
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp y UUID',
        'description' => 'Convierte timestamps Unix y fechas, y genera valores UUID y ULID.',
        'keywords' => 'unix, epoch, fecha, guid',
        'intro' => 'Pega un instante — Unix en segundos o milisegundos, una fecha o un UUID v7/ULID — para ver sus seis lecturas. El panel de la derecha también genera UUID v4, v7 y ULID. Todo se ejecuta en tu navegador: nada de lo que pegas aquí se envía al servidor.',

        'now' => 'Ahora',
        'kind' => 'Tipo de id',
        'quantity' => 'Cantidad',
        'generate' => 'Generar',
        'ids_title' => 'Ids generados: {count}',
        'copy_ids' => 'Copiar todos',
        'ids_copied' => '{count} ids copiados',
        'id_copied' => 'Id copiado',
        'row_copied' => '{label} copiado',
        'recognised' => 'El id pegado guarda este instante ({kind})',

        'kinds' => [
            'uuid_v4' => 'UUID v4',
            'uuid_v7' => 'UUID v7',
            'ulid' => 'ULID',
        ],

        'readings' => [
            'unix_seconds' => 'Unix (segundos)',
            'unix_ms' => 'Unix (milisegundos)',
            'iso_utc' => 'ISO 8601 (UTC)',
            'iso_local' => 'ISO 8601 (local)',
            'long_date' => 'Fecha por extenso',
            'relative' => 'Tiempo relativo',
        ],

        'states' => [
            'unreadable' => 'Aquí no se pudo leer un instante: usa el Unix en segundos o milisegundos, una fecha (ISO 8601) o un UUID v7/ULID.',
        ],
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Prueba expresiones regulares con resaltado en vivo de coincidencias y grupos.',
        'keywords' => 'patrón, coincidencia, grupos',
        'intro' => 'Escribe la expresión regular en la barra de arriba y el texto de prueba en el panel izquierdo: las coincidencias salen resaltadas, una a una, con los grupos numerados y nombrados, y el campo de sustitución muestra el texto sustituido. La búsqueda se ejecuta en tu navegador: nada de lo que pegas aquí se envía al servidor.',

        'pattern' => 'Expresión regular',
        'replacement' => 'Sustituir por',
        'replacement_hint' => 'Ej.: $1 o $<nombre>',
        'position' => 'Posición {start}',
        'matches_count' => 'Coincidencias: {count}',
        'matches_none' => 'Ninguna coincidencia.',
        'truncated' => 'Mostrando las primeras {count} coincidencias.',
        'replaced_title' => 'Resultado de la sustitución',
        'file_types' => 'TXT',

        'flags' => [
            'i' => 'Ignorar mayúsculas y minúsculas',
            'm' => 'Multilínea: ^ y $ en cada línea',
            's' => 'El punto también coincide con el salto de línea',
            'u' => 'Unicode: coincidir por punto de código',
        ],

        'states' => [
            'pattern' => 'La expresión no es válida. El detalle de abajo es el mensaje del motor del navegador.',
            'pattern_empty' => 'Escribe la expresión regular para probar.',
            'timeout' => 'La búsqueda pasó de {seconds} segundos y se interrumpió: no se muestra ningún resultado. Restringe la expresión para evitar el retroceso.',
        ],
    ],

];
