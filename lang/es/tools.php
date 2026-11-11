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

    /*
    | Superficie compartida entre herramientas. El selector de color lo usan
    | varias herramientas, asi que sus textos viven aqui y no en `tools.<slug>.*`.
    */
    'shared' => [
        'color_picker' => [
            'color_input' => [
                'label' => 'Color',
                'placeholder' => '#7c3aed, rgb(124 58 237), oklch(54% 0.24 293)…',
                'hint' => 'Acepta HEX, RGB, HSL, OKLCH, OKLab y nombres CSS. El color se aplica mientras escribes en cuanto el texto es válido.',
                'empty' => 'Escribe un color para empezar.',
                'syntax' => 'No se pudo entender ese formato. Revisa el código e inténtalo de nuevo.',
                'range' => 'Uno de los valores está fuera del intervalo permitido.',
                'unknown_name' => 'No existe ningún color CSS con ese nombre.',
            ],

            'area' => [
                'label' => 'Saturación y brillo',
                'hint' => 'Las flechas ajustan 1 punto; con Mayús, 10.',
            ],

            'hue' => [
                'label' => 'Matiz',
            ],

            'alpha' => [
                'label' => 'Opacidad',
            ],

            'channels' => [
                'title' => 'Canales',
                'rgb' => 'RGB',
                'hsl' => 'HSL',
                'red' => 'Rojo',
                'green' => 'Verde',
                'blue' => 'Azul',
                'hue' => 'Matiz',
                'saturation' => 'Saturación',
                'lightness' => 'Luminosidad',
            ],

            'formats' => [
                'title' => 'Formatos del color',
                'hex' => 'HEX',
                'hex8' => 'HEX8',
                'rgb' => 'RGB',
                'rgba' => 'RGBA',
                'hsl' => 'HSL',
                'hsla' => 'HSLA',
                'oklch' => 'OKLCH',
                'oklab' => 'OKLab',
                'name' => 'Nombre CSS',
                'copy' => 'Copiar {format}',
                'copied' => 'Copiado: {value}',
                'name_hint' => 'El nombre CSS es el color más cercano; puede diferir un poco del código.',
            ],
        ],
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Codificación y hash',
        'security' => 'Seguridad',
        'time' => 'Fecha y hora',
        'text' => 'Texto',
        'badges' => 'Imágenes',
        'colors' => 'Colores',
    ],

    'html-color-picker' => [
        'name' => 'HTML Color Picker',
        'description' => 'Elige un color, conviértelo entre HEX, RGB, HSL y OKLCH y comprueba el contraste WCAG antes de usarlo.',
        'keywords' => 'color, hex, rgb, hsl, oklch, contraste, wcag, cuentagotas, tonos, matices',
        'intro' => 'Elige un color en pantalla o escribe un código, ajústalo en el área 2D y los deslizadores, y comprueba el contraste WCAG contra negro y blanco. Todo se ejecuta en tu navegador: ningún color sale de la página.',

        'preview' => [
            'alpha' => '{percent}% de opacidad',
            'eyedropper' => 'Cuentagotas de pantalla',
            'eyedropper_image' => 'Cuentagotas desde una imagen',
            'gamut' => 'Ese color está fuera del gamut sRGB. El valor que escribiste ({source}) se redujo en croma a {value}, manteniendo la luminosidad y el matiz.',
        ],

        'image' => [
            'title' => 'Cuentagotas desde una imagen',
            'description' => 'La imagen se lee en tu navegador y nunca se envía. Haz clic en la imagen o usa las flechas para elegir un píxel y confirma.',
            'choose' => 'Elegir una imagen',
            'error' => 'No se pudo leer esa imagen. Prueba con otra.',
            'instructions' => 'Haz clic o usa las flechas para elegir un píxel; Enter aplica.',
            'apply' => 'Usar este color',
        ],

        'contrast' => [
            'title' => 'Contraste WCAG',
            'description' => 'La opacidad se compone sobre el fondo elegido antes del cálculo.',
            'backdrop' => 'Fondo',
            'white' => 'Blanco',
            'black' => 'Negro',
            'custom' => 'Personalizado',
            'black_text' => 'Texto negro ({ratio}:1 {verdict})',
            'white_text' => 'Texto blanco ({ratio}:1 {verdict})',
            'pass_word' => 'pasa',
            'fail_word' => 'no pasa',
            'aa_normal' => 'AA',
            'aaa_normal' => 'AAA',
            'aa_large' => 'AA grande',
            'aaa_large' => 'AAA grande',
            'large_note' => 'Texto grande: 18,66 px en negrita o 24 px normal.',
        ],

        'shades' => [
            'title' => 'Tonos',
            'description' => 'Diez versiones más oscuras, en OKLab, con la misma opacidad.',
        ],

        'tints' => [
            'title' => 'Matices',
            'description' => 'Diez versiones más claras, en OKLab, con la misma opacidad.',
        ],

        'history' => [
            'title' => 'Historial',
            'description' => 'Los últimos colores que aplicaste, solo en este navegador.',
            'clear' => 'Limpiar',
            'empty' => 'Todavía no aplicaste ningún color.',
        ],

        'favorites' => [
            'title' => 'Favoritos',
            'description' => 'Colores guardados solo en este navegador.',
            'add' => 'Añadir el color actual a favoritos',
            'remove' => 'Quitar de favoritos',
            'clear' => 'Limpiar',
            'empty' => 'Todavía no hay favoritos.',
        ],

        'tabs' => [
            'contrast' => 'Contraste',
            'shades' => 'Tonos',
            'library' => 'Biblioteca',
        ],
    ],

    'palette-generator' => [
        'name' => 'Color Palette Generator',
        'description' => 'Genera una escala de 11 tonos, explora armonías y extrae los colores dominantes de una imagen; expórtalos a CSS, SCSS, JSON y Tailwind.',
        'keywords' => 'paleta, escala de colores, tonos, matices, tailwind, variables css, scss, tokens de diseño, oklch, armonías, colores dominantes, extracción de imagen',
        'intro' => 'Elige un color base y obtén al instante una escala de 11 tonos en OKLCH, con contraste WCAG en cada tono y los valores convertidos de la base. Explora las armonías complementaria, análoga, tríada, tétrada y monocromática en OKLCH o HSL, y extrae hasta 11 colores dominantes de una imagen local o de una URL HTTPS. Copia un tono o la escala completa y descárgala en CSS, SCSS, JSON, Tailwind v3 o Tailwind v4. Todo se ejecuta en tu navegador: ningún color y ninguna imagen sale de la página.',

        'tabs' => [
            'scale' => 'Escala',
            'harmonies' => 'Armonías',
            'extraction' => 'Extracción',
        ],

        'harmonies' => [
            'title' => 'Armonías',
            'description' => 'Colores que combinan con la base. Haz clic en uno para convertirlo en la nueva base.',
            'kind_label' => 'Tipo de armonía',
            'method_label' => 'Método',
            'method_oklch' => 'OKLCH',
            'method_hsl' => 'HSL',
            'kind_complementary' => 'Complementaria',
            'kind_analogous' => 'Análoga',
            'kind_triadic' => 'Tríada',
            'kind_tetradic' => 'Tétrada',
            'kind_monochromatic' => 'Monocromática',
            'base_badge' => 'Base',
            'clipped_badge' => 'Fuera de sRGB',
            'achromatic_note' => 'La base es acromática: girar el matiz no crea colores nuevos, así que la armonía queda reducida.',
            'color_label' => 'Color {index} de la armonía',
            'copy' => 'Copiar',
            'use_as_base' => 'Usar como base',
            'copied' => 'Copiado: {value}',
        ],

        'extraction' => [
            'title' => 'Colores dominantes de la imagen',
            'description' => 'Extrae los colores dominantes de una imagen local o de una URL HTTPS. Nada se envía al servidor.',
            'file_label' => 'Imagen',
            'file_hint' => 'JPEG, PNG, WebP, GIF o AVIF, hasta {size}.',
            'url_label' => 'URL de la imagen',
            'url_placeholder' => 'https://ejemplo.com/foto.jpg',
            'url_hint' => 'Debe ser HTTPS y permitir CORS; si el origen lo bloquea, usa un archivo local.',
            'import_url' => 'Importar',
            'importing' => 'Importando…',
            'count_label' => 'Cantidad de colores',
            'count_option' => '{count} colores',
            'cancel' => 'Cancelar',
            'preview_alt' => 'Vista previa de la imagen importada',
            'status' => [
                'reading' => 'Descargando la imagen…',
                'decoding' => 'Decodificando…',
                'analyzing' => 'Extrayendo los colores dominantes…',
            ],
            'results_title' => 'Colores dominantes · {count}',
            'percentage' => '{value}% de los píxeles',
            'percentage_unknown' => 'peso no guardado',
            'copy' => 'Copiar',
            'copied' => 'Copiado: {value}',
            'use_as_base' => 'Usar como base',
            'use_file' => 'Usar un archivo local',
            'insufficient' => 'La imagen tiene menos de {requested} colores distintos; se encontraron {found}.',
            'algorithm_version' => 'Algoritmo v{version}',
            'source_unknown' => 'origen no registrada',
            'source_file' => 'Archivo: {name}',
            'source_url' => 'Imagen de {host}',
            'errors' => [
                'invalid_url' => 'La URL no es válida.',
                'not_https' => 'Solo se aceptan URLs HTTPS.',
                'cors' => 'El origen no permite la lectura directa (CORS). Usa un archivo local.',
                'network' => 'No fue posible descargar la imagen.',
                'timeout' => 'La imagen tardó demasiado en responder. Inténtalo de nuevo.',
                'too_large' => 'La imagen supera el límite de tamaño configurado.',
                'not_image' => 'La dirección no apunta a una imagen aceptada (JPEG, PNG, WebP, GIF o AVIF).',
                'decode_failed' => 'No fue posible decodificar la imagen.',
                'dimensions_too_large' => 'La imagen supera los límites de dimensión configurados.',
                'too_many_pixels' => 'La imagen tiene demasiados píxeles para analizarla.',
                'empty_image' => 'La imagen no tiene píxeles visibles (todo transparente).',
                'unavailable' => 'La extracción no está disponible en este navegador.',
                'cancelled' => 'Importación cancelada.',
            ],
        ],

        'base' => [
            'title' => 'Color base',
            'badge' => 'Base',
            'copy' => 'Copiar {format}',
            'copied' => 'Copiado: {value}',
            'nearest' => 'Tono más cercano: {tone}',
            'not_500' => 'La base no es el tono 500. Se mantuvo intacta; la escala sigue igual.',
            'matches_500' => 'La base es el tono 500 de la escala.',
            'clipped' => 'Croma reducido para caber en sRGB.',
            'alpha_note' => 'El contraste considera el tono compuesto sobre el fondo de la misma polaridad del texto.',
        ],

        'scale' => [
            'title' => 'Escala de 11 tonos',
            'description' => 'Luminosidad fija en OKLCH; el croma acompaña al color base.',
            'copy_scale' => 'Copiar escala',
            'copied_scale' => 'Escala copiada: {count} tonos',
            'tone_copy' => 'Copiar {tone}',
            'tone_copied' => 'Copiado: {value}',
            'contrast_black' => 'Texto negro {ratio}:1',
            'contrast_white' => 'Texto blanco {ratio}:1',
            'pass' => 'pasa {level}',
            'fail' => 'no pasa {level}',
            'clipped_badge' => 'Croma limitado',
            'clipped_summary' => 'Tonos con croma limitado: {tones}',
            'algorithm_version' => 'Algoritmo v{version}',
            'base_tone_badge' => 'Más cercano',
        ],

        'export' => [
            'title' => 'Exportar',
            'description' => 'Copia o descarga la escala lista para tu proyecto.',
            'name_label' => 'Nombre de la paleta',
            'name_hint' => 'Se usa en las variables: {slug}',
            'name_invalid' => 'El nombre se normalizará a {slug}.',
            'copy' => 'Copiar',
            'copied' => '{name} copiado',
            'download' => 'Descargar',
            'tabs' => [
                'css' => 'CSS',
                'scss' => 'SCSS',
                'json' => 'JSON',
                'tailwind_v3' => 'Tailwind v3',
                'tailwind_v4' => 'Tailwind v4',
            ],
        ],

        'empty' => [
            'title' => 'Elige un color base',
            'body' => 'En cuanto haya un color válido, los 11 tonos aparecen aquí.',
            'restore' => 'Restaurar ejemplo',
        ],

        'state' => [
            'stale' => 'El color escrito no es válido; la escala anterior sigue en pantalla.',
        ],

        'save' => [
            'action' => 'Guardar paleta',
            'draft_restored' => 'Tu paleta ha vuelto. Revísala y guárdala.',
        ],
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
        'intro' => 'Describe la consulta, pega el esquema y elige la salida: SQL, Eloquent, Prisma y más. Es la única herramienta que sale del navegador: el esquema y la descripción van al proveedor de IA y consumen la cuota del plan. El SQL generado nunca se ejecuta aquí.',
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

    'markdown-to-pdf' => [
        'name' => 'Markdown to PDF',
        'description' => 'Convierte Markdown en documentos PDF estilizados con vista previa en tiempo real, formatos de página, fuentes y temas.',
        'keywords' => 'markdown, pdf, convertidor, documento, exportar, gfm, imprimir',
        'intro' => 'Pega o abre Markdown para previsualizar el documento formateado en tiempo real. Personaliza tamaños de página, fuentes y temas, y luego descarga tu PDF o imprime con precisión vectorial. Todo funciona en tu navegador: nada de lo que pegues aquí se envía al servidor.',

        'download_name' => 'documento',
        'file_types' => 'MD o TXT',

        'controls' => [
            'format' => 'Formato',
            'orientation' => 'Orientación',
            'margin' => 'Margen',
            'font' => 'Fuente',
            'font_size' => 'Tamaño',
            'theme' => 'Tema',
        ],

        'formats' => [
            'a4' => 'A4',
            'letter' => 'Carta',
            'a5' => 'A5',
        ],

        'orientations' => [
            'portrait' => 'Vertical',
            'landscape' => 'Horizontal',
        ],

        'margins' => [
            'normal' => 'Normal (20mm)',
            'compact' => 'Compacto (10mm)',
            'wide' => 'Ancho (30mm)',
        ],

        'fonts' => [
            'sans' => 'Sans-serif',
            'serif' => 'Serif',
            'mono' => 'Monospace',
            'fira-code' => 'Fira Code',
        ],

        'font_sizes' => [
            'sm' => 'Pequeño',
            'md' => 'Mediano',
            'lg' => 'Grande',
        ],

        'themes' => [
            'modern' => 'Moderno',
            'editorial' => 'Editorial',
            'minimal' => 'Minimalista B&N',
            'academic' => 'Académico',
        ],

        'actions' => [
            'download_pdf' => 'Descargar PDF',
            'print' => 'Imprimir / Guardar como PDF',
            'generating' => 'Generando...',
        ],

        'stats' => [
            'words' => 'Palabras: {count}',
            'chars' => 'Caracteres: {count}',
        ],
    ],

    'base64-image' => [
        'name' => 'Base64 Image Encoder / Decoder',
        'description' => 'Convierte imágenes en Base64 Data URI (texto sin formato, etiqueta img o CSS) y decodifica Base64 de vuelta a imagen.',
        'intro' => 'Convierte imágenes en Base64 Data URI con soporte para etiquetas img y CSS, o decodifica cadenas Base64 a imágenes con vista previa y descarga.',
        'keywords' => 'imagen, base64, data-uri, codificador, decodificador, convertir, css, html',
        'upload_title' => 'Haz clic para seleccionar o arrastra una imagen',
        'upload_subtitle' => 'Compatible con PNG, JPG, GIF, WebP, SVG, BMP, AVIF y otros formatos',
        'max_limit_badge' => 'Límite: {limit}',
        'pro_plan_badge' => 'Plan Pro (Hasta 25 MB)',
        'converting' => 'Convirtiendo imagen en el servidor...',
        'converting_hint' => 'Por favor, espera mientras se procesa el archivo.',
        'new_image' => 'Nueva Imagen',
        'original_size' => 'Tamaño original',
        'base64_size' => 'Tamaño Base64',
        'retention_info' => 'Historial disponible por {hours}h',
        'retention_info_day' => 'Historial disponible por {days} día',
        'retention_info_days' => 'Historial disponible por {days} días',
        'direct_download_notice_title' => 'Archivo grande convertido',
        'direct_download_notice_desc' => 'Para proteger la memoria del navegador, la descarga del archivo .txt con Data URI se inició directamente.',
        'copy' => 'Copiar',
        'copied' => '¡Copiado!',
        'download_txt' => 'Descargar .txt',
        'download_json' => 'Descargar .json',
        'download_xml' => 'Descargar .xml',
        'download_image' => 'Descargar Imagen',
        'preview_img' => 'Visualizar',
        'size' => 'Tamaño',
        'example' => 'Ejemplo',
        'clear' => 'Limpiar',
        'decoder_input_label' => 'Pega la cadena Base64 o Data URI:',
        'decoder_output_label' => 'Imagen Decodificada:',
        'decoder_placeholder' => 'data:image/png;base64,iVBORw0KGgo...',
        'decoder_empty_prompt' => 'Ninguna imagen decodificada todavía.',
        'decoder_empty_hint' => 'Pega una cadena Base64 válida a la izquierda para visualizarla y descargarla.',
        'history_empty_title' => 'No hay conversiones recientes',
        'history_empty_desc' => 'Las imágenes que conviertas aparecerán aquí durante su período de retención.',
        'active_badge' => 'Activo',
        'expired_badge' => 'Expirado',
        'confirm_delete' => '¿Estás seguro de que deseas eliminar esta conversión del historial?',
        'tabs' => [
            'encoder' => 'Codificador (Imagen → Base64)',
            'decoder' => 'Decodificador (Base64 → Imagen)',
            'history' => 'Historial',
        ],
        'formats' => [
            'data_uri' => 'Data URI / Texto Sin Formato',
            'img_tag' => 'Etiqueta HTML <img>',
            'css_bg' => 'CSS background-image',
            'json' => 'JSON',
            'xml' => 'XML',
        ],
        'errors' => [
            'title' => 'Error de conversión',
            'invalid_image' => 'El archivo subido no es una imagen válida.',
            'file_too_large' => 'El archivo supera el límite máximo permitido de {limit}.',
            'conversion_failed' => 'Ocurrió un error al procesar la imagen. Inténtalo de nuevo.',
            'invalid_base64' => 'La cadena Base64 proporcionada es inválida o está dañada.',
            'file_expired' => 'El archivo ha expirado y ya fue eliminado del servidor.',
        ],
    ],
    'share-text' => [
        'name' => 'Compartir Texto',
        'description' => 'Comparte textos, notas y fragmentos de código con enlaces cortos por ULID y compatibilidad total con Hastebin.',
        'intro' => 'Comparte notas, código y textos con enlaces cortos por ULID. Los visitantes anónimos tienen retención de 72 horas y los usuarios registrados disfrutan de almacenamiento permanente con total compatibilidad con la API de Hastebin.',
        'keywords' => 'hastebin, pastebin, compartir texto, snippet, codigo, notas, ulid, raw',
        'placeholder' => 'Pega o escribe tu texto aquí...',
        'actions' => [
            'save' => 'Guardar',
            'new' => 'Nuevo',
            'duplicate' => 'Duplicar y Editar',
            'raw' => 'Texto Plano (Raw)',
            'copy_link' => 'Copiar Enlace',
            'copied' => '¡Enlace copiado!',
            'history' => 'Historial',
        ],
        'badges' => [
            'anonymous' => 'Expira en 72h (anónimo)',
            'permanent' => 'Permanente (tu cuenta)',
            'draft' => 'Borrador sin guardar',
        ],
        'dialog' => [
            'title' => 'Texto Compartido con Éxito',
            'description' => 'Tu texto ha sido guardado con un identificador único y está listo para compartir.',
            'url_label' => 'Enlace para compartir:',
            'raw_url_label' => 'Enlace directo (Raw):',
            'close' => 'Cerrar',
        ],
        'history' => [
            'title' => 'Tus Textos Compartidos',
            'empty' => 'Aún no hay textos compartidos.',
            'open' => 'Abrir',
            'delete' => 'Eliminar',
            'confirm_delete' => '¿Estás seguro de que deseas eliminar permanentemente este texto?',
        ],
        'languages' => [
            'auto' => 'Texto Plano',
            'json' => 'JSON',
            'sql' => 'SQL',
            'markdown' => 'Markdown',
        ],
        'errors' => [
            'empty_content' => 'El contenido no puede estar vacío.',
            'size_exceeded' => 'El texto supera el límite máximo permitido de :max MB.',
            'save_failed' => 'Ocurrió un error al guardar el texto. Inténtalo de nuevo.',
            'not_found' => 'Texto no encontrado o expirado.',
        ],
        'messages' => [
            'deleted' => 'Texto eliminado con éxito.',
        ],
    ],

    'badgeforge' => [
        'name' => 'BadgeForge',
        'description' => 'Crea insignias SVG para tus README con vista previa en tiempo real y fragmentos listos para Markdown, HTML y reStructuredText.',
        'keywords' => 'insignia, badge, shields, svg, readme, markdown, ci, cobertura',
        'intro' => 'Arma la insignia, revisa la vista previa y copia el fragmento. La imagen se sirve como SVG desde este sitio; nada de lo que escribes aquí sale del navegador salvo la URL que copias.',

        'fields' => [
            'label' => 'Etiqueta',
            'message' => 'Mensaje',
            'style' => 'Estilo',
            'color' => 'Color',
            'label_color' => 'Color de la etiqueta',
            'logo' => 'Logo',
            'logo_color' => 'Color del logo',
            'logo_size' => 'Tamaño del logo',
            'logo_position' => 'Posición del logo',
            'link' => 'Enlace',
            'cache_seconds' => 'Caché (segundos)',
            'font' => 'Fuente',
            'format' => 'Formato',
        ],

        'placeholders' => [
            'label' => 'build',
            'message' => 'passing',
            'color' => '#9f9f9f o green',
            'label_color' => '#555555',
            'logo_color' => '#ffffff',
            'link' => 'https://ejemplo.com',
        ],

        'positions' => [
            'left' => 'Izquierda',
            'right' => 'Derecha',
        ],

        'logo_none' => 'Sin logo',

        'fonts' => [
            'default' => 'Predeterminada del estilo',
        ],

        'formats' => [
            'svg' => 'SVG',
            'png' => 'PNG',
        ],

        'sections' => [
            'content' => 'Contenido',
            'colors' => 'Colores',
            'logo' => 'Logo',
            'advanced' => 'Avanzado',
        ],

        'preview' => [
            'title' => 'Vista previa',
            'dark' => 'Fondo oscuro',
            'light' => 'Fondo claro',
            'empty' => 'Escribe una etiqueta y un mensaje para ver la insignia.',
            'dimensions' => '{width} × {height} px',
        ],

        'snippets' => [
            'title' => 'Insertar',
            'url' => 'URL',
            'markdown' => 'Markdown',
            'html' => 'HTML',
            'rst' => 'reStructuredText',
        ],

        'actions' => [
            'sample' => 'Ejemplo',
            'reset' => 'Limpiar',
        ],

        'management' => [
            'logos_button' => 'Mis logos',
            'presets_button' => 'Preajustes',
            'login_cta' => 'Inicia sesión para subir tus propios logos y guardar preajustes.',
            'login_action' => 'Iniciar sesión',
            'upgrade_cta' => 'Tu plan permite {limit} logos personalizados. Mejora el plan para logos ilimitados.',
            'upgrade' => 'Mejorar plan',
            'limit_note' => 'Logos personalizados usados: {used} de {limit}.',
            'unlimited_note' => 'Logos personalizados usados: {used} (ilimitado).',
            'preset_limit_note' => 'Presets usados: {used} de {limit}.',
            'preset_unlimited_note' => 'Presets usados: {used} (ilimitados).',
            'logo_created' => 'Logo guardado.',
            'logo_updated' => 'Logo renombrado.',
            'logo_deleted' => 'Logo eliminado.',
            'preset_created' => 'Preajuste guardado.',
            'preset_updated' => 'Preajuste renombrado.',
            'preset_deleted' => 'Preajuste eliminado.',
            'throttled' => 'Demasiadas acciones. Espera un momento e inténtalo de nuevo.',
            'badge_created' => 'Insignia creada.',
            'badge_updated' => 'Insignia renombrada.',
            'badge_credential_updated' => 'Credencial actualizada. La insignia se generará de nuevo.',
            'badge_deleted' => 'Insignia eliminada.',
            'logos_title' => 'Mis logos',
            'logos_description' => 'Archivos SVG formados solo por trazados. El archivo no se almacena; solo quedan los trazados normalizados.',
            'presets_title' => 'Preajustes',
            'presets_description' => 'Copias del formulario del constructor. Aplica uno para volver a rellenar el formulario.',
            'empty_logos' => 'Todavía no has subido ningún logo.',
            'empty_presets' => 'Todavía no has guardado ningún preajuste.',
            'upload' => 'Subir SVG',
            'uploading' => 'Subiendo...',
            'upload_hint' => 'SVG de hasta {size} KB.',
            'logo_file' => 'Archivo SVG',
            'name' => 'Nombre',
            'name_placeholder' => 'Mi logo',
            'preset_name_placeholder' => 'Mi preajuste',
            'save' => 'Guardar',
            'cancel' => 'Cancelar',
            'rename' => 'Renombrar',
            'delete' => 'Eliminar',
            'delete_title' => '¿Eliminar "{name}"?',
            'delete_description' => 'Esta acción no se puede deshacer.',
            'preview_loading' => 'Cargando vista previa...',
            'preview_error' => 'No se pudo cargar la vista previa.',
            'path_count' => '{count} trazados',
            'preview' => 'Vista previa',
            'save_preset' => 'Guardar como preajuste',
            'apply_preset' => 'Aplicar',
            'preset_orphaned' => 'El logo de este preajuste ya no existe. Se aplicará sin logo.',
            'open_in_builder' => 'Abrir en el constructor',
            'manage_in_dashboard' => 'Gestionar en el panel',
        ],

        'modes' => [
            'static' => 'Estática',
            'provider' => 'Proveedores',
            'custom' => 'Personalizado',
        ],
        'provider' => [
            'sections' => [
                'metric' => 'Métrica',
            ],
            'fields' => [
                'provider' => 'Proveedor',
                'definition' => 'Métrica',
            ],
            'invalid_param' => 'Revisa los parámetros e inténtalo de nuevo.',
            'example' => 'Ejemplo: {path}',
            'cache_note' => 'El servidor controla la caché de las insignias de proveedor.',
            'preview_invalid' => 'Completa los parámetros para ver la insignia.',
            'params' => [
                'owner' => 'Propietario',
                'repo' => 'Repositorio',
                'package' => 'Paquete',
                'vendor' => 'Vendor',
                'namespace' => 'Espacio de nombres',
                'repository' => 'Repositorio',
            ],
            'metrics' => [
                'github_stars' => 'Estrellas de GitHub',
                'github_forks' => 'Forks de GitHub',
                'github_issues' => 'Issues abiertas de GitHub',
                'github_license' => 'Licencia de GitHub',
                'npm_v' => 'Versión de npm',
                'npm_dm' => 'Descargas mensuales de npm',
                'packagist_dt' => 'Descargas de Packagist',
                'packagist_v' => 'Versión de Packagist',
                'docker_pulls' => 'Pulls de Docker Hub',
                'codecov_coverage' => 'Cobertura de Codecov',
                'coveralls_coverage' => 'Cobertura de Coveralls',
                'laravel_v' => 'Versión de Laravel',
                'laravel_dt' => 'Descargas de Laravel',
                'php_v' => 'Versión de PHP',
                'python_v' => 'Versión de Python',
            ],
        ],
        'custom_providers' => [
            'title' => 'Proveedores personalizados',
            'description' => 'Insignias a partir de cualquier documento JSON público que mapees tú. La URL de origen se queda en el servidor; la imagen usa un identificador opaco.',
            'create_button' => 'Nuevo proveedor',
            'readonly_note' => 'Los proveedores personalizados están disponibles en los planes de pago. Las insignias existentes siguen funcionando y se pueden copiar o eliminar.',
            'upgrade' => 'Mejorar plan',
            'limit_reached' => 'Alcanzaste el límite de {limit} proveedores personalizados.',
            'empty' => 'Aún no tienes proveedores personalizados.',
            'disabled' => 'Desactivado',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'delete_description' => 'La URL pública deja de funcionar y la copia en caché se descarta.',
            'dialog_create_title' => 'Crear proveedor personalizado',
            'dialog_edit_title' => 'Editar proveedor personalizado',
            'dialog_description' => 'Apunta a un documento JSON público y elige los valores con JSON Pointer.',
            'template_immutable' => 'La plantilla de origen es inmutable: para cambiarla, elimina y crea de nuevo. Los slugs de las métricas tampoco se pueden renombrar.',
            'add_metric' => 'Añadir métrica',
            'remove_metric' => 'Quitar métrica',
            'create_submit' => 'Crear proveedor',
            'update_submit' => 'Guardar cambios',
            'fields' => [
                'name' => 'Nombre',
                'url_template' => 'Plantilla de origen',
                'path_params' => 'Parámetros del path',
                'example_path' => 'Path de ejemplo',
                'metrics' => 'Métricas',
                'metric_slug' => 'Slug',
                'metric_label' => 'Etiqueta',
                'pointer' => 'JSON Pointer',
                'formatter' => 'Formato',
            ],
            'hints' => [
                'url_template' => 'URL HTTPS con los marcadores que declares, por ejemplo https://api.example.com/{\'{\'}owner{\'}\'}/{\'{\'}repo{\'}\'}.',
                'template_note' => 'Los marcadores del path usan {\'{\'}name{\'}\'}. La plantilla se valida al guardar.',
                'path_params' => 'Marcadores separados por comas, en el orden del path.',
                'example_path' => 'Un path real usado para validar el mapeo al guardar.',
                'pointer' => 'RFC 6901, por ejemplo /stargazers_count o /items/0/score.',
            ],
            'formatters' => [
                'text' => 'Texto',
                'number' => 'Número',
                'percent' => 'Porcentaje',
            ],
            'builder' => [
                'sections' => [
                    'metric' => 'Métrica',
                ],
                'fields' => [
                    'provider' => 'Proveedor',
                    'definition' => 'Métrica',
                ],
                'invalid_param' => 'Revisa los parámetros y vuelve a intentarlo.',
                'example' => 'Ejemplo: {path}',
                'cache_note' => 'El servidor controla la caché de las insignias de proveedores personalizados.',
                'preview_invalid' => 'Completa los parámetros para ver la insignia.',
            ],
        ],
        'personal' => [
            'create_button' => 'Insignia personal',
            'title' => 'Insignias personales',
            'description' => 'Insignias publicadas con tu nombre de usuario, listas para incrustar. Las de proveedor se actualizan solas.',
            'dialog_title' => 'Crear insignia personal',
            'dialog_description' => 'Guarda la insignia actual con tu nombre de usuario y comparte su URL.',
            'name' => 'Nombre',
            'name_placeholder' => 'Mi insignia',
            'metric_note' => 'Métrica: {metric}',
            'private' => 'Privada',
            'private_hint' => 'Requiere un token de acceso para leer un repositorio o paquete privado.',
            'credential' => 'Token de acceso personal',
            'credential_placeholder' => 'No se vuelve a mostrar después de guardar',
            'credential_username' => 'Usuario de la cuenta',
            'credential_username_placeholder' => 'Usuario de Docker',
            'params_invalid' => 'Corrige los parámetros antes de guardar.',
            'create' => 'Crear insignia',
            'creating' => 'Creando...',
            'upgrade_cta' => 'Las insignias personales están disponibles en planes de pago. Mejora tu plan para publicarlas con tu nombre de usuario.',
            'upgrade' => 'Mejorar plan',
            'readonly_note' => 'Tu plan ya no permite crear insignias personales. Las existentes siguen funcionando y se pueden copiar o eliminar.',
            'empty' => 'Aún no tienes insignias personales. Crea una en el constructor.',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'refresh' => 'Actualizar',
            'refreshing' => 'Actualizando...',
            'refresh_failed' => 'No se pudo actualizar la insignia. Inténtalo más tarde.',
            'change_credential' => 'Cambiar token',
            'credential_set' => 'Token de acceso guardado',
            'credential_missing' => 'Falta el token de acceso',
            'credential_not_required' => 'Elemento público',
        ],
        'endpoint' => [
            'cta_title' => 'Badge de endpoint JSON',
            'cta_description' => 'Convierte una URL JSON en un badge. Registra el endpoint en el panel de tu cuenta; la URL de origen se queda en el servidor.',
            'cta_action' => 'Gestionar endpoints',
            'create_button' => 'Nuevo endpoint',
            'title' => 'Badges de endpoint JSON',
            'description' => 'Badges generados a partir de una URL JSON que tú registras. La URL de origen se guarda en el servidor y nunca se expone; la imagen pública usa un identificador opaco.',
            'readonly_note' => 'Verifica tu correo para registrar endpoints. Los badges existentes siguen funcionando.',
            'empty' => 'Todavía no tienes badges de endpoint JSON.',
            'dialog_title' => 'Registrar endpoint JSON',
            'dialog_description' => 'El endpoint debe devolver el contrato JSON de shields mediante HTTPS.',
            'name' => 'Nombre',
            'name_placeholder' => 'Despliegues',
            'url' => 'URL de origen',
            'url_placeholder' => 'https://example.com/badge.json',
            'url_hint' => 'Solo HTTPS, IP pública y sin redirecciones. La URL se guarda en el servidor y nunca vuelve al navegador.',
            'create' => 'Registrar endpoint',
            'creating' => 'Registrando...',
            'copy_svg' => 'Copiar SVG',
            'copy_png' => 'Copiar PNG',
            'delete_description' => 'La URL pública deja de funcionar y la copia en caché se descarta. La URL de origen no se borra en ningún otro sitio.',
        ],
        'social_note' => 'El estilo social siempre usa sus propios colores.',
    ],
];
