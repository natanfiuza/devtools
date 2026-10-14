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

    /*
    | Textos da casca de qualquer tool (os paineis, a barra de acoes e os
    | estados). O que e de uma ferramenta so mora em `tools.<slug>.*`.
    */
    'ui' => [
        'input' => 'Entrée',
        'output' => 'Sortie',
        'resize' => 'Redimensionner les panneaux',
        'drop' => 'Déposez le fichier ici',

        'actions' => [
            'open' => 'Ouvrir un fichier',
            'example' => 'Exemple',
            'clear' => 'Effacer',
            'copy' => 'Copier',
            'copied' => 'Copié',
            'copy_manual' => 'Sélectionnez le texte et copiez-le',
            'download' => 'Télécharger',
        ],

        'states' => [
            'empty' => 'Collez, saisissez ou déposez un fichier JSON pour commencer.',
            'parsing' => 'Analyse...',
            'unavailable' => 'Outil indisponible.',
            'invalid' => 'JSON invalide à la ligne {line}, colonne {column}.',
            'invalid_no_position' => 'JSON invalide.',
            'stale_tree' => 'Arbre de la dernière version valide.',
            'too_large' => 'Le texte fait {size} et dépasse la limite de {limit}. L\'analyse est désactivée.',
            'file_too_large' => 'Le fichier fait {size} et dépasse la limite de {limit}.',
            'file_type' => 'Format non pris en charge. Envoyez un fichier .json ou .txt.',
            'file_unreadable' => 'Impossible de lire le fichier.',
            'drop_hint' => 'Déposez pour ouvrir le fichier.',
        ],

        'limits' => [
            'of' => 'sur {limit}',
        ],

        'shortcuts' => [
            'title' => 'Raccourcis',
        ],
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
        'intro' => 'Collez du JSON pour voir l\'arbre coloré, formater, minifier et copier le chemin de n\'importe quel nœud. Tout s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'format' => 'Formater',
        'minify' => 'Minifier',
        'sort_keys' => 'Trier les clés',
        'expand_all' => 'Tout déplier',
        'collapse_all' => 'Tout replier',
        'download_name' => 'document',

        'tree' => [
            'toggle' => 'Replier ou déplier',
            'copy_path' => 'Copier le chemin',
            'copied' => 'Chemin copié : {path}',
        ],

        'search' => [
            'placeholder' => 'Rechercher une clé ou une valeur',
            'label' => 'Rechercher dans l\'arbre',
            'clear' => 'Effacer la recherche',
            'results' => 'Résultats : {count}',
            'truncated' => 'Affichage des {count} premiers résultats.',
            'none' => 'Aucun résultat.',
        ],

        'stats' => [
            'keys' => 'Clés : {count}',
            'items' => 'Éléments : {count}',
        ],

        'shortcuts' => [
            'format' => 'Formater le document',
            'search' => 'Rechercher dans l\'arbre',
            'clear' => 'Effacer la recherche',
        ],
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
