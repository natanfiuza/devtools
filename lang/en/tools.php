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
            'empty' => 'Paste, type or drop a JSON file to start.',
            'parsing' => 'Parsing...',
            'unavailable' => 'Tool unavailable.',
            'invalid' => 'Invalid JSON on line {line}, column {column}.',
            'invalid_no_position' => 'Invalid JSON.',
            'too_large' => 'The text is {size} and goes past the {limit} limit. Parsing is off.',
            'file_too_large' => 'The file is {size} and goes past the {limit} limit.',
            'file_type' => 'Unsupported format. Use a .json or .txt file.',
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
            'search' => 'Search the tree',
            'undo' => 'Undo in the editor',
            'paste' => 'Paste into the editor',
        ],
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Describe the query in plain language and get SQL ready for your dialect.',
        'keywords' => 'natural language, generate query, database',
    ],

    'json-to-code' => [
        'name' => 'JSON to code',
        'description' => 'Turn JSON into TypeScript types, PHP DTOs and Python models.',
        'keywords' => 'generate types, interface, dto, pydantic',
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Format SQL queries for the MySQL, PostgreSQL, SQL Server and SQLite dialects.',
        'keywords' => 'beautify, indent, query',
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL and Hash',
        'description' => 'Encode and decode Base64 and URL, and generate MD5, SHA-1 and SHA-256 hashes.',
        'keywords' => 'encode, decode, checksum, digest',
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Read the header and the payload of a JWT. The signature is not validated.',
        'keywords' => 'token, bearer, claims, authentication',
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp and UUID',
        'description' => 'Convert Unix timestamps and dates, and generate UUID and ULID values.',
        'keywords' => 'unix, epoch, date, guid',
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Test regular expressions with live highlighting of matches and groups.',
        'keywords' => 'pattern, match, groups',
    ],

];
