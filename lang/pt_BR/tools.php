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
        'soon' => 'Em breve',
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Codificação e hash',
        'security' => 'Segurança',
        'time' => 'Data e hora',
        'text' => 'Texto',
    ],

    'json-viewer' => [
        'name' => 'JSON Viewer',
        'description' => 'Explore, formate e valide JSON em árvore, com busca e cópia de qualquer caminho.',
        'keywords' => 'formatar, validar, árvore, minificar, caminho',
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Descreva a consulta em linguagem natural e receba o SQL pronto para o seu dialeto.',
        'keywords' => 'linguagem natural, gerar consulta, banco de dados',
    ],

    'json-to-code' => [
        'name' => 'JSON para código',
        'description' => 'Transforme JSON em tipos TypeScript, DTOs PHP e modelos Python.',
        'keywords' => 'gerar tipos, interface, dto, pydantic',
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formate consultas SQL nos dialetos MySQL, PostgreSQL, SQL Server e SQLite.',
        'keywords' => 'embelezar, indentar, consulta',
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL e Hash',
        'description' => 'Codifique e decodifique Base64 e URL, e gere hashes MD5, SHA-1 e SHA-256.',
        'keywords' => 'codificar, decodificar, resumo, digest',
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Leia o cabeçalho e o payload de um JWT. A assinatura não é validada.',
        'keywords' => 'token, bearer, claims, autenticação',
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp e UUID',
        'description' => 'Converta timestamps Unix e datas, e gere valores UUID e ULID.',
        'keywords' => 'unix, epoch, data, guid',
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Teste expressões regulares com destaque ao vivo das correspondências e dos grupos.',
        'keywords' => 'padrão, correspondência, grupos',
    ],

];
