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
        'subscribe' => 'S\'abonner maintenant',
    ],

    'pricing' => [
        'title' => 'Tarifs',
        'subtitle' => 'Choisissez le forfait adapté à votre flux. Aucun renouvellement automatique caché : enregistrez votre intention d\'abonnement et réservez votre identifiant exclusif.',
        'free' => 'Gratuit',
        'yearly_badge' => '2 mois offerts',
        'interval' => [
            'month' => 'par mois',
            'year' => 'par an',
        ],
        'features' => [
            'uses' => '{count} utilisations à vie',
            'tokens' => '{count} tokens par mois',
            'schema' => 'Schéma jusqu\'à {size}',
            'prompt' => 'Description jusqu\'à {count} caractères',
            'username' => "Réservation exclusive de {'@'}nomdutilisateur",
            'history' => 'Historique conservé dans AI SQL Builder (90 jours)',
            'priority' => 'Support prioritaire et accès anticipé',
        ],
        'advantages' => [
            'title' => 'Les avantages de l\'abonnement Pro',
            'subtitle' => 'Plus de capacité dans AI SQL Builder, réservation de votre identifiant sur la plateforme et priorité sur les nouveautés.',
            'items' => [
                'username' => [
                    'title' => "Votre {'@'}nomdutilisateur exclusif",
                    'body' => 'Réservez votre identifiant unique sur la plateforme pour votre profil et vos futurs liens de requêtes partagés.',
                ],
                'history' => [
                    'title' => 'Historique des requêtes sur 90 jours',
                    'body' => 'Consultez, réutilisez et copiez le SQL et le code ORM générés précédemment dans AI SQL Builder.',
                ],
                'quota' => [
                    'title' => 'Quota mensuel de 2M tokens et limites étendues',
                    'body' => 'Envoyez des schémas DDL jusqu\'à 64 Ko et des descriptions détaillées jusqu\'à 4 000 caractères par requête.',
                ],
                'priority' => [
                    'title' => 'Support prioritaire et accès anticipé',
                    'body' => 'Accès en avant-première aux nouveaux outils et aux nouvelles cibles de génération de code IA.',
                ],
                'discount' => [
                    'title' => '2 mois d\'économie sur le forfait annuel',
                    'body' => 'Avec le forfait annuel, payez l\'équivalent de 10 mois et profitez de 12 mois complets d\'accès Pro.',
                ],
            ],
        ],
    ],

    'subscribe' => [
        'title' => 'Intention d\'abonnement Pro',
        'subtitle' => "Choisissez entre le forfait mensuel ou annuel, réservez votre {'@'}nomdutilisateur exclusif et indiquez vos coordonnées pour activer l'abonnement.",
        'pending_title' => 'Demande d\'abonnement enregistrée',
        'pending_body' => "Nous avons bien reçu votre intention d'abonnement et votre {'@'}nomdutilisateur est déjà réservé sur votre compte. Nous vous contacterons pour finaliser l'activation.",
        'submitted_toast' => 'Intention d\'abonnement enregistrée avec succès.',
        'plan_label' => 'Choisissez votre forfait',
        'username_label' => 'Votre nom d\'utilisateur exclusif',
        'username_placeholder' => 'nomdutilisateur',
        'username_help' => 'Lettres minuscules, chiffres et tiret bas uniquement (3 à 30 caractères). Réservé immédiatement sur votre compte.',
        'phone_label' => 'Téléphone / WhatsApp',
        'phone_country_code' => 'Code pays',
        'phone_placeholder' => '+33 6 12 34 56 78',
        'document_label' => 'Document d\'identité (facultatif)',
        'document_placeholder' => 'Passeport, CNI ou identifiant fiscal',
        'redirect_countdown' => 'Vous serez redirigé vers la page d\'accueil dans {seconds}s.',
        'redirect_now' => 'Aller à l\'accueil maintenant',
        'redirect_cancel' => 'Rester sur cette page',
        'payment_method_label' => 'Mode de paiement préféré',
        'payment_methods' => [
            'pix' => 'Pix',
            'credit_card' => 'Carte bancaire',
            'boleto' => 'Boleto bancaire',
        ],
        'notes_label' => 'Remarques (facultatif)',
        'notes_placeholder' => 'Une préférence d\'horaire ou un détail sur la facturation',
        'submit' => 'Enregistrer l\'intention d\'abonnement',
        'update_submit' => 'Mettre à jour l\'intention d\'abonnement',
    ],
];
