<?php

return [
    'title' => 'Des outils de développeur qui restent discrets',
    'subtitle' => 'Formatez, convertissez et inspectez vos données dans le navigateur. Sans inscription pour les outils locaux.',

    'tools' => [
        'title' => 'Outils',
        'subtitle' => 'Les outils tournent dans votre navigateur : ce que vous collez ici ne quitte jamais la machine — sauf dans AI SQL Builder, qui envoie le schéma et la description au fournisseur d\'IA.',
        'filter' => 'Filtrer par catégorie',
        'all' => 'Tous',
        'empty' => 'Aucun outil dans cette catégorie pour le moment.',
    ],

    'ai' => [
        'badge' => 'Disponible',
        'title' => 'AI SQL Builder',
        'body' => 'Décrivez la requête en langage naturel et obtenez le SQL de votre base, plus Eloquent, Prisma et le reste de votre stack.',
        'note' => 'Nécessite un compte vérifié : la requête passe par le modèle et consomme le quota du forfait.',
        'cta' => 'Ouvrir AI SQL Builder',
        'preview_prompt' => 'Liste les 10 clients qui ont le plus acheté ces 30 derniers jours',
        'preview_sql' => "SELECT c.name, SUM(o.total) AS spent\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nWHERE o.created_at >= NOW() - INTERVAL '30 days'\nGROUP BY c.name\nORDER BY spent DESC\nLIMIT 10;",
    ],

    'api' => [
        'badge' => 'API REST v1',
        'title' => 'Automatisez et intégrez directement à votre flux de travail',
        'body' => 'Générez du SQL et du code ORM par programmation. Connectez devtools à vos pipelines CI/CD, scripts CLI, outils internes et automatisations n8n sans devoir copier-coller depuis l\'interface.',
        'note' => 'Authentification par jeton Bearer (Sanctum), traitement asynchrone et réponse instantanée en cas de cache.',
        'cta_docs' => 'Consulter la documentation de l\'API',
        'cta_tokens' => 'Gérer les jetons d\'API',
        'preview_title' => 'Exemple de requête cURL',
        'preview_code' => "curl -sS -X POST \"https://devtools.natanfiuza.dev.br/api/v1/sql-queries\" \\\n  -H \"Authorization: Bearer VOTRE_JETON\" \\\n  -H \"Accept: application/json\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\n    \"schema_source\": \"ddl\",\n    \"dialect\": \"mysql\",\n    \"output_target\": \"sql\",\n    \"schema\": \"CREATE TABLE users (id BIGINT PRIMARY KEY, name VARCHAR(120), active TINYINT(1));\",\n    \"description\": \"Lister les utilisateurs actifs triés par nom\"\n  }'",
    ],

    'donation' => [
        'badge' => 'Soutenez devtools',
        'title' => 'Aidez à maintenir les outils en ligne et gratuits pour tous',
        'body' => 'devtools est un projet indépendant conçu par des développeurs pour des développeurs. Tous les outils s\'exécutent à 100 % dans votre navigateur en toute confidentialité et sans publicité. Vos dons via Pix financent directement l\'hébergement des serveurs et les quotas de modèles IA.',
        'note' => 'Chaque contribution fait la différence et maintient la plateforme accessible à l\'ensemble de la communauté.',
        'cta' => 'Faire un don via Pix',
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
        'checkout' => [
            'unavailable' => 'Le paiement en ligne est actuellement indisponible. Vous pouvez enregistrer votre intérêt ci-dessous et nous vous contacterons.',
            'path_label' => 'Comment souhaitez-vous vous abonner ?',
            'paths' => [
                'one_time' => [
                    'title' => 'Payer maintenant',
                    'body' => 'Pix, carte ou boleto payés comptant. Débloque une période dès que le paiement est approuvé.',
                ],
                'recurring' => [
                    'title' => 'Renouvellement automatique par carte',
                    'body' => 'La carte est débitée à chaque période. L\'accès se débloque à la confirmation du premier paiement.',
                ],
                'manual' => [
                    'title' => 'Je préfère un accompagnement manuel',
                    'body' => 'Enregistrez votre demande et nous conviendrons du moyen de paiement avec vous.',
                ],
            ],
            'recurring_badge' => 'Renouvellement actif',
            'recurring_blocked' => 'Un renouvellement automatique est déjà actif sur ce forfait. Annulez-le dans l\'espace factures avant d\'en créer un autre.',
            'redirect_note' => 'Vous serez redirigé vers Mercado Pago pour finaliser le paiement. Rien n\'est débloqué avant la confirmation.',
            'one_time_action' => 'Payer maintenant',
            'recurring_action' => 'Activer le renouvellement automatique',
            'errors' => [
                'unavailable' => 'Le paiement en ligne est indisponible. Utilisez l\'accompagnement manuel pour finaliser l\'abonnement.',
                'recurring_active' => 'Un renouvellement automatique est déjà actif sur ce forfait. Annulez-le avant d\'en créer un autre.',
                'plan_unavailable' => 'Ce forfait n\'est pas disponible pour le paiement en ligne.',
                'gateway' => 'Impossible d\'ouvrir le paiement pour le moment. Réessayez dans un instant ou utilisez l\'accompagnement manuel.',
            ],
            'status' => [
                'processing' => [
                    'title' => 'Paiement en cours de traitement',
                    'body' => 'Nous attendons la confirmation du prestataire. L\'accès se débloque automatiquement dès que le paiement est approuvé.',
                ],
                'approved' => [
                    'title' => 'Paiement approuvé',
                    'body' => 'Votre nouvelle période est déjà disponible sur le compte. Vous pouvez tout suivre dans l\'espace factures.',
                ],
                'expired' => [
                    'title' => 'Paiement expiré',
                    'body' => 'Cette tentative de paiement a expiré sans confirmation. Choisissez un forfait et réessayez.',
                ],
                'failed' => [
                    'title' => 'Paiement non finalisé',
                    'body' => 'Nous n\'avons pas pu finaliser cette tentative. Aucun accès n\'a été débloqué ; vous pouvez réessayer ou utiliser l\'accompagnement manuel.',
                ],
            ],
        ],
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
