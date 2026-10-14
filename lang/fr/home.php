<?php

return [
    'title' => 'Des outils de développeur qui restent discrets',
    'subtitle' => 'Formatez, convertissez et inspectez vos données dans le navigateur. Sans inscription, rien n\'est envoyé à un serveur.',

    'tools' => [
        'title' => 'Outils',
        'subtitle' => 'Tout tourne dans votre navigateur : ce que vous collez ici ne quitte jamais la machine.',
        'filter' => 'Filtrer par catégorie',
        'all' => 'Tous',
        'empty' => 'Aucun outil dans cette catégorie pour le moment.',
    ],

    'ai' => [
        'badge' => 'Bientôt',
        'title' => 'AI SQL Builder',
        'body' => 'Décrivez la requête en langage naturel et obtenez le SQL de votre base, plus Eloquent, Prisma et le reste de votre stack.',
        'note' => 'Nécessite un compte vérifié : la requête passe par le modèle et consomme le quota du forfait.',
        'preview_prompt' => 'Liste les 10 clients qui ont le plus acheté ces 30 derniers jours',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'cta' => [
        'register' => 'Créer un compte',
        'contact' => 'Écrire un e-mail',
    ],

    'pricing' => [
        'title' => 'Tarifs',
        'subtitle' => 'Sans renouvellement automatique : la facturation se fait par e-mail et le forfait vaut jusqu\'à la fin de la période.',
        'free' => 'Gratuit',
        'interval' => [
            'month' => 'par mois',
            'year' => 'par an',
        ],
        'features' => [
            'uses' => '{count} utilisations à vie',
            'tokens' => '{count} tokens par mois',
            'schema' => 'Schéma jusqu\'à {size}',
            'prompt' => 'Description jusqu\'à {count} caractères',
        ],
    ],
];
