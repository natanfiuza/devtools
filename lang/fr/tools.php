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
        'soon' => 'Bientôt disponible',
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Encodage et hachage',
        'security' => 'Sécurité',
        'time' => 'Date et heure',
        'text' => 'Texte',
    ],

    'json-viewer' => [
        'name' => 'JSON Viewer',
        'description' => 'Explorez, formatez et validez du JSON en arbre, avec recherche et copie de n\'importe quel chemin.',
        'keywords' => 'formater, valider, arbre, minifier, chemin',
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Décrivez la requête en langage naturel et recevez le SQL prêt pour votre dialecte.',
        'keywords' => 'langage naturel, générer une requête, base de données',
    ],

    'json-to-code' => [
        'name' => 'JSON vers code',
        'description' => 'Transformez du JSON en types TypeScript, DTO PHP et modèles Python.',
        'keywords' => 'générer des types, interface, dto, pydantic',
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formatez vos requêtes SQL pour les dialectes MySQL, PostgreSQL, SQL Server et SQLite.',
        'keywords' => 'embellir, indenter, requête',
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL et Hash',
        'description' => 'Encodez et décodez en Base64 et en URL, et générez des empreintes MD5, SHA-1 et SHA-256.',
        'keywords' => 'encoder, décoder, empreinte, digest',
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Lisez l\'en-tête et la charge utile d\'un JWT. La signature n\'est pas validée.',
        'keywords' => 'token, bearer, claims, authentification',
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp et UUID',
        'description' => 'Convertissez des horodatages Unix et des dates, et générez des UUID et des ULID.',
        'keywords' => 'unix, epoch, date, guid',
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Testez des expressions régulières avec surlignage en direct des correspondances et des groupes.',
        'keywords' => 'motif, correspondance, groupes',
    ],

];
