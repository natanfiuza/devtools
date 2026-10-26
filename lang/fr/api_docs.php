<?php

return [
    'meta' => [
        'title' => 'Documentation de l’API v1',
        'description' => 'Intégrez le générateur de requêtes SQL à vos systèmes via l’API REST avec authentification Bearer, traitement asynchrone et prise en charge de multiples dialectes et ORM.',
    ],

    'hero' => [
        'eyebrow' => 'REST API v1',
        'title' => 'Documentation de l’API',
        'subtitle' => 'Intégrez la génération de requêtes SQL, Query Builders et ORM directement dans votre backend, CLI, pipeline CI/CD ou flux d’automatisation comme n8n.',
        'base_url_label' => 'URL de base de l’API',
        'copy_base_url' => 'Copier l’URL de base',
        'copied' => 'Copié !',
        'manage_tokens' => 'Gérer les jetons API',
        'openapi_spec' => 'Télécharger OpenAPI 3.1 (YAML)',
    ],

    'auth' => [
        'title' => 'Authentification et flux asynchrone',
        'description' => 'Toutes les routes sous /api/v1 exigent un jeton personnel Laravel Sanctum (ability ai:query) envoyé dans l’en-tête Authorization.',
        'step_1_title' => '1. Créez votre jeton',
        'step_1_body' => 'Accédez à Paramètres > Jetons API dans votre compte, générez un jeton personnel et copiez la valeur affichée une seule fois.',
        'step_2_title' => '2. Envoyez les en-têtes HTTP',
        'step_2_body' => 'Incluez Authorization: Bearer VOTRE_JETON et Accept: application/json dans chaque appel HTTP.',
        'step_3_title' => '3. Gérez 200 (cache) et 202 (file d’attente)',
        'step_3_body' => 'Si une requête identique est déjà en cache, POST renvoie 200 (état done) immédiatement. Sinon, il renvoie 202 Accepted avec poll_url pour suivre l’état jusqu’à done ou failed.',
        'headers_label' => 'En-têtes requis',
    ],

    'examples' => [
        'title' => 'Exemples d’intégration',
        'description' => 'Exemples prêts à l’emploi pour envoyer un schéma, demander une requête et attendre le traitement asynchrone.',
        'copy' => 'Copier le code',
        'copied' => 'Copié !',
        'tabs' => [
            'curl' => 'cURL',
            'js' => 'JavaScript / TS',
            'php' => 'PHP',
            'python' => 'Python',
            'n8n' => 'n8n / HTTP',
        ],
        'n8n_note' => 'Dans n8n, utilisez un nœud HTTP Request avec Header Auth (Authorization: Bearer VOTRE_JETON). Si data.poll_url n’est pas nul (état queued ou processing), ajoutez un nœud Wait respectant poll_after_ms relié à un second HTTP Request interrogeant data.poll_url.',
    ],

    'endpoints' => [
        'title' => 'Points de terminaison disponibles',
        'description' => 'Référence complète des routes publiques de l’API v1.',
        'params_label' => 'Paramètres',
        'response_label' => 'Exemple de réponse',
        'field' => 'Champ',
        'type' => 'Type',
        'required' => 'Requis',
        'yes' => 'Oui',
        'no' => 'Non',
        'details' => 'Description',
        'targets_title' => 'Formats de sortie activés (output_target)',
        'targets_description' => 'Valeurs acceptées dans le champ output_target du point de terminaison POST /api/v1/sql-queries.',
        'target_slug' => 'Slug (output_target)',
        'target_language' => 'Langage',
        'target_dialects' => 'Dialectes pris en charge',
        'target_name' => 'Libellé',
        'items' => [
            'me' => [
                'summary' => 'Renvoie les informations du compte authentifié, le forfait actif et le solde de quota pour la période en cours.',
            ],
            'targets' => [
                'summary' => 'Liste tous les formats de sortie activés (SQL, Query Builders et ORM) ainsi que les dialectes acceptés par chacun.',
            ],
            'store_query' => [
                'summary' => 'Envoie un schéma et une description en langage naturel pour générer la requête dans le format choisi.',
                'schema_source_desc' => 'Format du schéma envoyé : ddl, json, migration ou models.',
                'dialect_desc' => 'Dialecte SQL de référence : mysql, pgsql, sqlsrv ou sqlite.',
                'output_target_desc' => 'Slug du format de sortie activé (ex. : sql, eloquent, prisma).',
                'schema_desc' => 'Contenu du schéma des tables de référence.',
                'description_desc' => 'Description en langage naturel de ce que la requête doit renvoyer.',
                'locale_desc' => 'Langue facultative de l’explication (pt_BR, pt_PT, en, es, fr).',
            ],
            'list_queries' => [
                'summary' => 'Liste l’historique paginé des requêtes générées par le compte authentifié.',
                'status_desc' => 'Filtre par état : queued, processing, done ou failed.',
                'page_desc' => 'Numéro de page (par défaut 1, {perPage} éléments par page).',
            ],
            'show_query' => [
                'summary' => 'Consulte l’état actuel et le résultat d’une requête spécifique via son ULID.',
                'ulid_desc' => 'Identifiant ULID renvoyé lors de la création de la requête.',
            ],
        ],
    ],

    'limits' => [
        'title' => 'Limites de débit (Rate Limits) et quotas',
        'description' => 'L’API applique des limites par minute et par heure par jeton authentifié, en plus du quota de requêtes de votre forfait.',
        'post_title' => 'Création de requêtes (POST /sql-queries)',
        'read_title' => 'Lecture et polling (GET /me, /output-targets, /sql-queries)',
        'per_minute' => '{count} requêtes / minute',
        'per_hour' => '{count} requêtes / heure',
        'headers_note' => 'Les réponses incluent X-RateLimit-Limit et X-RateLimit-Remaining. Lorsque la limite est atteinte, l’API répond 429 Too Many Requests avec l’en-tête Retry-After.',
    ],

    'errors' => [
        'title' => 'Codes d’état et erreurs',
        'description' => 'Toutes les erreurs renvoient un JSON standardisé contenant error.code, error.message et error.details.',
        'status' => 'État HTTP',
        'code' => 'error.code',
        'when' => 'Quand cela se produit',
        'items' => [
            '401' => 'Jeton absent, invalide ou déjà révoqué.',
            '402' => 'Quota de requêtes ou de jetons d’IA du forfait épuisé pour la période.',
            '403' => 'Jeton sans l’ability ai:query ou compte suspendu par un administrateur.',
            '404' => 'Requête introuvable ou appartenant à un autre compte.',
            '422' => 'Échec de validation sur les champs envoyés, schéma dépassant la limite ou dialecte incompatible.',
            '429' => 'Limite de requêtes par minute ou par heure dépassée.',
            '503' => 'Fournisseur d’IA temporairement indisponible ou non configuré.',
        ],
    ],
];
