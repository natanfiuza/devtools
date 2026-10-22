<?php

/*
| AI SQL Builder (namespace "ai").
|
| A tela traduz o codigo de erro devolvido pela API; a mensagem tecnica do
| servidor e so fallback. Nenhum texto aqui cita modelo ou provedor: isso e
| configuracao, nao interface.
*/

return [
    'form' => [
        'title' => 'Décrivez la requête',
        'schema_source' => 'Source du schéma',
        'schema' => 'Schéma',
        'schema_placeholder' => 'Collez les tables, colonnes et relations que la requête peut utiliser.',
        'description' => 'Que doit faire la requête ?',
        'description_placeholder' => 'Exemple : listez les 10 clients qui ont le plus dépensé ces 30 derniers jours.',
        'dialect' => 'Dialecte',
        'target' => 'Sortie',
        'submit' => 'Générer',
        'submitting' => 'Envoi...',
        'schema_limit' => 'Schéma : {used} sur {limit}',
        'description_limit' => 'Description : {used} sur {limit}',
        'over_limit' => 'Cela dépasse la limite de votre forfait.',
        'required' => 'Remplissez le schéma et la description.',
        'sensitive' => 'Ne collez pas de mots de passe, de tokens ni de données personnelles.',
    ],

    'sources' => [
        'ddl' => 'DDL',
        'json' => 'JSON',
        'migration' => 'Migration Laravel',
        'models' => 'Modèles SQLModel',
    ],

    'dialects' => [
        'mysql' => 'MySQL',
        'pgsql' => 'PostgreSQL',
        'sqlsrv' => 'SQL Server',
        'sqlite' => 'SQLite',
    ],

    'targets' => [
        'sql' => 'SQL',
        'laravel_query_builder' => 'Laravel Query Builder',
        'eloquent' => 'Eloquent',
        'sqlmodel' => 'SQLModel',
        'sqlalchemy' => 'SQLAlchemy',
        'django_orm' => 'Django ORM',
        'prisma' => 'Prisma',
        'drizzle' => 'Drizzle ORM',
        'typeorm' => 'TypeORM',
    ],

    'states' => [
        'idle' => 'Prêt quand vous voulez.',
        'queued' => 'En file d\'attente...',
        'processing' => 'Génération...',
        'done' => 'Résultat prêt.',
        'failed' => 'Impossible de générer le résultat.',
        'timeout' => 'Cela prend plus de temps que prévu. Vous pouvez réessayer.',
        'retry' => 'Réessayer',
        'poll_hint' => 'Vous pouvez garder cet onglet ouvert ; le résultat apparaît ici.',
    ],

    'guest' => [
        'title' => 'Connectez-vous pour utiliser AI SQL Builder',
        'body' => 'AI SQL Builder nécessite un compte vérifié : le schéma et la description passent par le fournisseur d\'IA et consomment le quota du forfait.',
        'cta' => 'Se connecter',
    ],

    'unverified' => [
        'title' => 'Confirmez votre e-mail pour utiliser l\'IA',
        'body' => 'Nous avons envoyé un lien de confirmation à votre adresse e-mail. Confirmez-le avant de générer une requête.',
        'cta' => 'Renvoyer la confirmation',
        'sent' => 'Confirmation envoyée. Vérifiez votre boîte de réception.',
        'failed' => 'Impossible d\'envoyer la confirmation. Réessayez plus tard.',
    ],

    'suspended' => [
        'title' => 'Compte suspendu',
        'body' => 'Ce compte ne peut pas utiliser AI SQL Builder pour le moment. Contactez le support.',
    ],

    'unavailable' => [
        'title' => 'IA indisponible',
        'body' => 'Le service d\'IA n\'est pas configuré pour le moment. Réessayez plus tard.',
    ],

    'quota' => [
        'title' => 'Quota épuisé',
        'body_uses' => 'Vous avez utilisé les {limit} utilisations gratuites de ce compte.',
        'body_tokens' => 'Vous avez utilisé tous les {limit} tokens de cette période.',
        'remaining_uses' => 'Il reste {remaining} utilisations sur {limit}',
        'remaining_tokens' => 'Il reste {remaining} tokens sur {limit}',
        'cta' => 'Voir les forfaits',
    ],

    'result' => [
        'title' => 'Résultat',
        'copy' => 'Copier',
        'copied' => 'Copié',
        'download' => 'Télécharger',
        'download_name' => 'requete',
        'assumptions' => 'Hypothèses',
        'warnings' => 'Avertissements',
        'tables' => 'Tables utilisées',
        'tokens_in' => 'Entrée : {count} tokens',
        'tokens_out' => 'Sortie : {count} tokens',
        'cache_hit' => 'Résultat en cache',
        'clarification' => 'Le modèle a besoin de plus d\'informations',
        'empty' => 'Rien de généré pour l\'instant.',
    ],

    'errors' => [
        'generic' => 'Une erreur est survenue. Réessayez.',
        'network' => 'Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.',
        'validation' => 'Vérifiez les champs du formulaire et réessayez.',
        'unauthenticated' => 'Votre session a expiré. Reconnectez-vous.',
        'forbidden' => 'Ce compte ne peut pas utiliser AI SQL Builder.',
        'email_unverified' => 'Confirmez votre e-mail avant d\'utiliser l\'IA.',
        'account_suspended' => 'Ce compte est suspendu.',
        'not_found' => 'Requête introuvable.',
        'csrf' => 'Votre session a expiré. Rechargez la page et réessayez.',
        'rate_limited' => 'Trop de requêtes. Réessayez dans {seconds} secondes.',
        'quota_exhausted' => 'Quota épuisé. Consultez les forfaits pour continuer.',
        'unavailable' => 'Le service d\'IA est indisponible pour le moment.',
        'timeout' => 'La requête a pris trop de temps. Réessayez.',
        'provider_unavailable' => 'Le fournisseur d\'IA est indisponible pour le moment. Réessayez plus tard.',
        'invalid_response' => 'L\'IA a renvoyé une réponse invalide.',
        'unsafe_sql' => 'Le SQL généré a été bloqué par le contrôle de sécurité.',
        'invalid_contract' => 'La réponse de l\'IA ne correspond pas au format attendu.',
        'internal' => 'Erreur interne lors du traitement de la requête.',
        'schema_too_large' => 'Le schéma dépasse la limite de {limit} de votre forfait.',
        'description_too_long' => 'La description dépasse la limite de {limit} caractères de votre forfait.',
        'unsupported_dialect' => 'La sortie choisie n\'accepte pas ce dialecte.',
    ],

    'warnings' => [
        'review' => 'Relisez le résultat avant de l\'utiliser. La requête n\'est jamais exécutée ici.',
        'sensitive' => 'Ne collez pas de mots de passe, de tokens ni de données personnelles dans le schéma.',
    ],

    'history' => [
        'title' => 'Requêtes Précédentes',
        'search_placeholder' => 'Rechercher dans le schéma, prompt ou titre...',
        'loading' => 'Chargement de l\'historique...',
        'error' => 'Impossible de charger l\'historique des requêtes.',
        'empty' => 'Aucune requête effectuée pour le moment.',
        'no_search_results' => 'Aucune requête trouvée pour "{query}".',
        'untitled' => 'Requête sans titre',
        'collapse' => 'Réduire l\'historique',
        'expand' => 'Développer l\'historique',
    ],
];
