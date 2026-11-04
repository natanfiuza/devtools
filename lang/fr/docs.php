<?php

/*
| Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
|
| Os nomes de provider e os nomes de metrica sao marcas/nomes proprios e nao
| se traduzem: o texto da metrica vem de `tools.badgeforge.provider.metrics.*`.
| As traducoes abaixo cobrem apenas o texto explicativo da pagina.
*/

return [

    'badgeforge' => [

        'meta' => [
            'title' => 'Documentation BadgeForge',
            'description' => 'Badges dynamiques pour les étoiles GitHub, les versions npm, les téléchargements Packagist et les pulls Docker, avec des badges personnelles dont les identifiants sont liés à chaque badge.',
        ],

        'hero' => [
            'eyebrow' => 'BadgeForge',
            'title' => 'Des badges qui se mettent à jour seuls',
            'subtitle' => 'Les badges statiques sont dessinés sur le serveur. Les badges de provider lisent GitHub, npm, Packagist et Docker Hub de façon anonyme, avec une réponse en cache qui ne casse jamais l\'image de votre README.',
            'open_tool' => 'Ouvrir le générateur',
        ],

        'endpoints' => [
            'title' => 'Endpoints',
            'static' => 'Badges statiques',
            'provider' => 'Badges de provider',
            'personal' => 'Badges personnelles',
        ],

        'quick_start' => [
            'title' => 'Démarrer en une minute',
            'body' => 'Collez le Markdown dans votre README. GitHub affiche le SVG directement et chaque requête est mise en cache de notre côté.',
            'copy' => 'Copier',
            'copied' => 'Copié',
        ],

        'static' => [
            'title' => 'Badges statiques',
            'body' => 'Le libellé et le message font partie de l\'URL. Tous les styles, couleurs, logos et polices du générateur sont disponibles dans la query string.',
            'example_label' => 'Exemple',
        ],

        'providers' => [
            'title' => 'Badges de provider',
            'intro' => 'Chaque provider expose une courte liste de métriques. Le chemin qui suit la métrique reprend les coordonnées du provider : dépôt, paquet ou image.',
            'metric' => 'Métrique',
            'path' => 'Chemin',
            'access' => 'Accès',
            'public' => 'Public',
            'private' => 'Élément privé',
            'documentation' => 'Documentation de l\'API du provider',
            'unknown_note' => 'Une métrique inexistante, ou une réponse non reconnue par le provider, affiche « unknown » sur la badge.',
            'descriptions' => [
                'github' => 'Étoiles, forks, issues ouvertes et licence via l\'API du dépôt. Les tokens fine-grained fonctionnent pour les dépôts privés.',
                'npm' => 'Dernière version publiée et nombre de téléchargements mensuels. Le compteur de téléchargements est toujours public.',
                'packagist' => 'Téléchargements totaux et dernière version d\'un paquet Composer, toujours depuis le registre public.',
                'docker' => 'Nombre de pulls Docker Hub. Les images privées utilisent le nom d\'utilisateur et un personal access token.',
            ],
        ],

        'personal' => [
            'title' => 'Badges personnelles',
            'intro' => 'Les comptes payants publient leur propre badge à une URL opaque. L\'URL contient votre nom d\'utilisateur et le ULID de la badge — jamais un token.',
            'url_label' => 'Format de l\'URL',
            'username_required' => 'Définissez un nom d\'utilisateur sur votre compte avant de créer une badge personnelle.',
            'providers_title' => 'Éléments privés',
            'providers_body' => 'Une badge personnelle peut lire un dépôt, un paquet ou une image privée. L\'identifiant vous appartient, il est chiffré et lié uniquement à cette badge.',
            'github' => 'GitHub : un personal access token avec accès en lecture au dépôt (fine-grained ou classique).',
            'npm' => 'npm : un token pour un paquet privé. Le compteur de téléchargements (dm) est toujours public.',
            'docker' => 'Docker Hub : nom d\'utilisateur et personal access token, tous deux requis pour les images privées.',
            'packagist' => 'Packagist : public uniquement. Les paquets privés ne sont pas pris en charge.',
            'security_title' => 'Stockage de l\'identifiant',
            'security_body' => 'Le token est chiffré avec la clé de l\'application et n\'apparaît jamais dans l\'URL de l\'image, les props de la page, les logs ou les exports. Si la clé de l\'application change, la badge continue de servir la dernière bonne image jusqu\'à ce que vous redéfinissiez l\'identifiant.',
            'manage_cta' => 'Gérer les badges personnelles',
            'tool_cta' => 'Créer une badge personnelle dans le générateur',
            'refresh_note' => 'L\'image est stateless. Pour forcer une nouvelle génération, utilisez l\'action de refresh du tableau de bord ; l\'URL publique ignore ?refresh=1.',
        ],

        'cache' => [
            'title' => 'Cache, ETag et réponses périmées',
            'body' => 'Les réponses des providers sont mises en cache pendant {ttl} secondes. L\'image est servie avec ETag et Cache-Control, donc une seconde requête reçoit un 304 quand rien n\'a changé.',
            'stale_title' => 'Stale-if-error',
            'stale_body' => 'Quand le provider échoue ou nous limite, la dernière bonne réponse continue d\'être servie. Sans réponse précédente, la badge affiche un court message d\'indisponibilité au lieu d\'une erreur.',
            'refresh_title' => 'Refresh',
            'refresh_body' => 'Les URLs publiques de provider ne forcent jamais de refresh. Les badges personnelles ont une action de refresh que seul le propriétaire peut déclencher.',
        ],

        'errors' => [
            'title' => 'Erreurs et fallback',
            'intro' => 'Une URL de badge répond toujours avec une image. Les exceptions sont un chemin inexistant (404) et une demande de PNG sur un serveur sans Imagick (503).',
            'cached' => 'Provider hors ligne ou limité : l\'image en cache est servie (en-tête X-Badge-Cache: STALE).',
            'fallback' => 'Aucune image en cache : la badge affiche le libellé de la métrique avec « indisponible ».',
            'unknown' => 'Le provider a répondu mais la métrique est illisible : la badge affiche « unknown ».',
            'not_found' => 'Provider inconnu, métrique inconnue ou chemin invalide : 404.',
            'private' => 'Un élément privé sans identifiant n\'est jamais servi par une URL publique de provider.',
        ],

        'security' => [
            'title' => 'Sécurité et confidentialité',
            'anonymous' => 'Les métriques publiques sont lues de façon anonyme. Aucun token global de provider n\'existe dans la configuration ni dans l\'environnement.',
            'credential' => 'Les identifiants appartiennent au propriétaire de la badge, sont chiffrés au repos et ne valent que pour une seule badge.',
            'logs' => 'Les réponses des providers sont projetées avant la mise en cache : seuls les nombres affichés par la badge sont conservés. Descriptions, README et tokens n\'atteignent jamais le cache ni les logs.',
            'url' => 'Les URLs de badge personnelle ne contiennent que le nom d\'utilisateur et le ULID ; révoquer l\'accès revient à supprimer la badge.',
        ],

    ],

];
