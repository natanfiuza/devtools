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
