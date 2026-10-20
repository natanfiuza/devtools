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
        'soon' => 'Coming soon',
    ],

    /*
    | Textos da casca de qualquer tool (os paineis, a barra de acoes e os
    | estados). O que e de uma ferramenta so mora em `tools.<slug>.*`.
    */
    'ui' => [
        'input' => 'Input',
        'output' => 'Output',
        'resize' => 'Resize the panels',
        'drop' => 'Drop the file here',

        'actions' => [
            'open' => 'Open file',
            'example' => 'Example',
            'clear' => 'Clear',
            'copy' => 'Copy',
            'copied' => 'Copied',
            'copy_manual' => 'Select the text and copy it',
            'download' => 'Download',
        ],

        'states' => [
            'empty' => 'Paste, type or drop a file to start.',
            'parsing' => 'Processing...',
            'unavailable' => 'Tool unavailable.',
            'invalid' => 'Invalid input on line {line}, column {column}.',
            'invalid_no_position' => 'Invalid input.',
            'stale_output' => 'Output from the last valid input.',
            'too_large' => 'The text is {size} and goes past the {limit} limit: nothing will be processed.',
            'file_too_large' => 'The file is {size} and goes past the {limit} limit.',
            'file_type' => 'Unsupported format. Use a {formats} file.',
            'file_unreadable' => 'Could not read the file.',
            'drop_hint' => 'Drop to open the file.',
        ],

        'limits' => [
            'of' => 'of {limit}',
        ],

        'shortcuts' => [
            'title' => 'Shortcuts',
        ],
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Encoding and hashing',
        'security' => 'Security',
        'time' => 'Date and time',
        'text' => 'Text',
    ],

    'json-viewer' => [
        'name' => 'JSON Viewer',
        'description' => 'Explore, format and validate JSON as a tree, with search and copy of any path.',
        'keywords' => 'format, pretty print, minify, validate, tree, path',
        'intro' => 'Paste JSON to see the colour-coded tree, format, minify and copy the path of any node. Everything runs in your browser: nothing you paste here is sent to the server.',

        'format' => 'Format',
        'minify' => 'Minify',
        'sort_keys' => 'Sort keys',
        'expand_all' => 'Expand all',
        'collapse_all' => 'Collapse all',
        'download_name' => 'document',
        'file_types' => 'JSON or TXT',

        'tree' => [
            'toggle' => 'Collapse or expand',
            'copy_path' => 'Copy path',
            'copied' => 'Path copied: {path}',
        ],

        'search' => [
            'placeholder' => 'Search key or value',
            'label' => 'Search the tree',
            'clear' => 'Clear search',
            'results' => 'Results: {count}',
            'truncated' => 'Showing the first {count} results.',
            'none' => 'No results.',
        ],

        'stats' => [
            'keys' => 'Keys: {count}',
            'items' => 'Items: {count}',
        ],

        'shortcuts' => [
            'format' => 'Format the document',
            'search' => 'Search the tree',
            'clear' => 'Clear the search',
        ],
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Describe the query in plain language and get SQL ready for your dialect.',
        'keywords' => 'natural language, generate query, database',
        'intro' => 'Describe the query, paste the schema and pick the output: SQL, Eloquent, Prisma and more. This is the one tool that leaves the browser: the schema and the description go to the AI provider and spend the plan quota. The generated SQL is never executed here.',
    ],

    'json-to-code' => [
        'name' => 'JSON to code',
        'description' => 'Turn JSON into TypeScript types, PHP DTOs and Python models.',
        'keywords' => 'generate types, interface, dto, pydantic',
        'intro' => 'Paste JSON and pick a target: TypeScript interfaces, PHP DTOs with promoted properties, or Pydantic models. The inference runs in your browser: nothing you paste here is sent to the server.',

        'target' => 'Target language',
        'root_name' => 'Root type name',
        'download_name' => 'types',
        'file_types' => 'JSON or TXT',

        'targets' => [
            'typescript' => 'TypeScript',
            'php' => 'PHP (DTO)',
            'pydantic' => 'Pydantic',
        ],

        'states' => [
            'root_object' => 'The root must be a JSON object with at least one key.',
        ],

        'stats' => [
            'types' => 'Types: {count}',
        ],
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Format SQL queries for the MySQL, PostgreSQL, SQL Server and SQLite dialects.',
        'keywords' => 'beautify, indent, query',
        'intro' => 'Paste the query and pick a dialect: the SQL comes out indented on the right, and the case of the keywords is your call. The formatting runs in your browser: nothing you paste here is sent to the server.',

        'dialect' => 'Dialect',
        'keyword_case' => 'Keyword case',
        'download_name' => 'query',
        'file_types' => 'SQL or TXT',

        'dialects' => [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'sqlite' => 'SQLite',
        ],

        'cases' => [
            'upper' => 'UPPERCASE',
            'lower' => 'lowercase',
            'preserve' => 'Keep',
        ],
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL and Hash',
        'description' => 'Encode and decode Base64 and URL, and generate MD5, SHA-1, SHA-256 and SHA-512 hashes.',
        'keywords' => 'encode, decode, checksum, digest',
        'intro' => 'One input, three readings: the same text becomes Base64, becomes an encoded URL, or becomes a hash. Pick the tab on the right and the direction on the left. Everything runs in your browser: nothing you paste here is sent to the server.',

        'direction' => 'Direction',
        'file_types' => 'TXT',
        'row_copied' => '{label} copied',

        'directions' => [
            'encode' => 'Encode',
            'decode' => 'Decode',
        ],

        'modes' => [
            'base64' => 'Base64',
            'url' => 'URL',
            'hash' => 'Hash',
        ],

        'rows' => [
            'base64' => 'Base64',
            'urlSafe' => 'URL-safe Base64',
            'component' => 'Component',
            'form' => 'Form',
            'md5' => 'MD5',
            'sha1' => 'SHA-1',
            'sha256' => 'SHA-256',
            'sha512' => 'SHA-512',
        ],

        'states' => [
            'invalid_base64' => 'This is not the Base64 of a text: there is a character outside the alphabet, a stray character at the end, or bytes that are not UTF-8.',
            'invalid_url' => 'This is not a valid percent-encoded URL: there is a broken escape sequence or bytes that are not UTF-8.',
            'no_crypto' => 'This browser only exposes Web Crypto over a secure connection (HTTPS), so the three SHA lines have no value here. MD5 still works — but remember: MD5 is a checksum, not a signature.',
        ],
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Read the header and the payload of a JWT. The signature is not validated.',
        'keywords' => 'token, bearer, claims, authentication',
        'intro' => 'Paste the token (the Bearer prefix may come along) to read the header, the payload and the dates of the claims. The tool decodes and does not validate the signature, and everything runs in your browser: nothing you paste here is sent to the server.',

        'header' => 'Header',
        'payload' => 'Payload',
        'signature' => 'Signature',
        'signature_bytes' => '{count} bytes',
        'not_verified' => 'not verified',
        'block_copied' => '{label} copied',

        'warning' => 'This decoder does not validate the signature: it shows what is written inside the token, and nothing here proves the token is authentic.',

        'claims_title' => 'Dates',

        'claims' => [
            'expired' => 'expired',
            'not_yet' => 'not valid yet',
        ],

        'states' => [
            'shape' => 'This is not a JWT: a JWT is three parts separated by dots (header, payload and signature), and there are not three here.',
            'base64' => 'One of the parts is not valid base64url, or its bytes are not UTF-8.',
            'json' => 'The header or the payload is not a JSON object.',
        ],
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp and UUID',
        'description' => 'Convert Unix timestamps and dates, and generate UUID and ULID values.',
        'keywords' => 'unix, epoch, date, guid',
        'intro' => 'Paste an instant — Unix in seconds or milliseconds, a date, or a UUID v7/ULID — to see its six readings. The panel on the right also generates UUID v4, v7 and ULID. Everything runs in your browser: nothing you paste here is sent to the server.',

        'now' => 'Now',
        'kind' => 'Id type',
        'quantity' => 'Quantity',
        'generate' => 'Generate',
        'ids_title' => 'Generated ids: {count}',
        'copy_ids' => 'Copy all',
        'ids_copied' => '{count} ids copied',
        'id_copied' => 'Id copied',
        'row_copied' => '{label} copied',
        'recognised' => 'The pasted id carries this instant ({kind})',

        'kinds' => [
            'uuid_v4' => 'UUID v4',
            'uuid_v7' => 'UUID v7',
            'ulid' => 'ULID',
        ],

        'readings' => [
            'unix_seconds' => 'Unix (seconds)',
            'unix_ms' => 'Unix (milliseconds)',
            'iso_utc' => 'ISO 8601 (UTC)',
            'iso_local' => 'ISO 8601 (local)',
            'long_date' => 'Written-out date',
            'relative' => 'Relative time',
        ],

        'states' => [
            'unreadable' => 'No instant could be read here: use Unix in seconds or milliseconds, a date (ISO 8601) or a UUID v7/ULID.',
        ],
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Test regular expressions with live highlighting of matches and groups.',
        'keywords' => 'pattern, match, groups',
        'intro' => 'Type the regular expression in the top bar and the test text in the left panel: the matches come out highlighted, one by one, with numbered and named groups, and the replacement field shows the rewritten text. The search runs in your browser: nothing you paste here is sent to the server.',

        'pattern' => 'Regular expression',
        'replacement' => 'Replace with',
        'replacement_hint' => 'E.g. $1 or $<name>',
        'position' => 'Position {start}',
        'matches_count' => 'Matches: {count}',
        'matches_none' => 'No matches.',
        'truncated' => 'Showing the first {count} matches.',
        'replaced_title' => 'Replacement result',
        'file_types' => 'TXT',

        'flags' => [
            'i' => 'Ignore case',
            'm' => 'Multiline: ^ and $ on every line',
            's' => 'The dot also matches the newline',
            'u' => 'Unicode: match by code point',
        ],

        'states' => [
            'pattern' => 'The expression is not valid. The detail below is the browser engine message.',
            'pattern_empty' => 'Type the regular expression to test.',
            'timeout' => 'The search went past {seconds} seconds and was stopped: no result is shown. Narrow the expression to avoid the backtracking.',
        ],
    ],

    'markdown-to-pdf' => [
        'name' => 'Markdown to PDF',
        'description' => 'Convert Markdown to styled PDF documents with real-time preview, custom page formats, fonts and themes.',
        'keywords' => 'markdown, pdf, converter, document, export, gfm, print',
        'intro' => 'Paste or open Markdown to preview formatted documents in real time. Customise page sizes, fonts and themes, then download your PDF or print with vector precision. Everything runs in your browser: nothing you paste here is sent to the server.',

        'download_name' => 'document',
        'file_types' => 'MD or TXT',

        'controls' => [
            'format' => 'Format',
            'orientation' => 'Orientation',
            'margin' => 'Margin',
            'font' => 'Font',
            'font_size' => 'Size',
            'theme' => 'Theme',
        ],

        'formats' => [
            'a4' => 'A4',
            'letter' => 'Letter',
            'a5' => 'A5',
        ],

        'orientations' => [
            'portrait' => 'Portrait',
            'landscape' => 'Landscape',
        ],

        'margins' => [
            'normal' => 'Normal (20mm)',
            'compact' => 'Compact (10mm)',
            'wide' => 'Wide (30mm)',
        ],

        'fonts' => [
            'sans' => 'Sans-serif',
            'serif' => 'Serif',
            'mono' => 'Monospace',
            'fira-code' => 'Fira Code',
        ],

        'font_sizes' => [
            'sm' => 'Small',
            'md' => 'Medium',
            'lg' => 'Large',
        ],

        'themes' => [
            'modern' => 'Modern',
            'editorial' => 'Editorial',
            'minimal' => 'Minimalist B&W',
            'academic' => 'Academic',
        ],

        'actions' => [
            'download_pdf' => 'Download PDF',
            'print' => 'Print / Save as PDF',
            'generating' => 'Generating...',
        ],

        'stats' => [
            'words' => 'Words: {count}',
            'chars' => 'Characters: {count}',
        ],
    ],

];
