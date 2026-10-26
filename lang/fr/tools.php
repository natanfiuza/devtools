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
            'empty' => 'Collez, saisissez ou déposez un fichier pour commencer.',
            'parsing' => 'Traitement...',
            'unavailable' => 'Outil indisponible.',
            'invalid' => 'Entrée invalide à la ligne {line}, colonne {column}.',
            'invalid_no_position' => 'Entrée invalide.',
            'stale_output' => 'Résultat de la dernière entrée valide.',
            'too_large' => 'Le texte fait {size} et dépasse la limite de {limit} : rien ne sera traité.',
            'file_too_large' => 'Le fichier fait {size} et dépasse la limite de {limit}.',
            'file_type' => 'Format non pris en charge. Envoyez un fichier {formats}.',
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
        'file_types' => 'JSON ou TXT',

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
        'intro' => 'Décrivez la requête, collez le schéma et choisissez la sortie : SQL, Eloquent, Prisma et plus encore. C\'est le seul outil qui quitte le navigateur : le schéma et la description partent chez le fournisseur d\'IA et consomment le quota du forfait. Le SQL généré n\'est jamais exécuté ici.',
    ],

    'json-to-code' => [
        'name' => 'JSON vers code',
        'description' => 'Transformez du JSON en types TypeScript, DTO PHP et modèles Python.',
        'keywords' => 'générer des types, interface, dto, pydantic',
        'intro' => 'Collez du JSON et choisissez la cible : interfaces TypeScript, DTO PHP avec propriétés promues ou modèles Pydantic. L\'inférence s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'target' => 'Langage cible',
        'root_name' => 'Nom du type racine',
        'download_name' => 'types',
        'file_types' => 'JSON ou TXT',

        'targets' => [
            'typescript' => 'TypeScript',
            'php' => 'PHP (DTO)',
            'pydantic' => 'Pydantic',
        ],

        'states' => [
            'root_object' => 'La racine doit être un objet JSON avec au moins une clé.',
        ],

        'stats' => [
            'types' => 'Types : {count}',
        ],
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Formatez vos requêtes SQL pour les dialectes MySQL, PostgreSQL, SQL Server et SQLite.',
        'keywords' => 'embellir, indenter, requête',
        'intro' => 'Collez la requête et choisissez le dialecte : le SQL ressort indenté à droite, et la casse des mots-clés est votre choix. Le formatage s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'dialect' => 'Dialecte',
        'keyword_case' => 'Casse des mots-clés',
        'download_name' => 'requete',
        'file_types' => 'SQL ou TXT',

        'dialects' => [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'sqlite' => 'SQLite',
        ],

        'cases' => [
            'upper' => 'MAJUSCULES',
            'lower' => 'minuscules',
            'preserve' => 'Conserver',
        ],
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL et Hash',
        'description' => 'Encodez et décodez en Base64 et en URL, et générez des empreintes MD5, SHA-1, SHA-256 et SHA-512.',
        'keywords' => 'encoder, décoder, empreinte, digest',
        'intro' => 'Une entrée, trois lectures : le même texte devient du Base64, une URL encodée ou une empreinte. Choisissez l\'onglet à droite et le sens à gauche. Le calcul s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'direction' => 'Sens',
        'file_types' => 'TXT',
        'row_copied' => '{label} copié',

        'directions' => [
            'encode' => 'Encoder',
            'decode' => 'Décoder',
        ],

        'modes' => [
            'base64' => 'Base64',
            'url' => 'URL',
            'hash' => 'Hash',
        ],

        'rows' => [
            'base64' => 'Base64',
            'urlSafe' => 'Base64 URL-safe',
            'component' => 'Composant',
            'form' => 'Formulaire',
            'md5' => 'MD5',
            'sha1' => 'SHA-1',
            'sha256' => 'SHA-256',
            'sha512' => 'SHA-512',
        ],

        'states' => [
            'invalid_base64' => 'Ce n\'est pas le Base64 d\'un texte : il y a un caractère hors de l\'alphabet, un caractère orphelin à la fin, ou des octets qui ne forment pas de l\'UTF-8.',
            'invalid_url' => 'Ce n\'est pas une URL encodée valide : il y a une séquence d\'échappement cassée ou des octets qui ne forment pas de l\'UTF-8.',
            'no_crypto' => 'Ce navigateur n\'expose Web Crypto que sur une connexion sécurisée (HTTPS) : les trois lignes SHA restent donc vides ici. Le MD5 fonctionne toujours — mais rappel : le MD5 est une somme de contrôle, pas une signature.',
        ],
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Lisez l\'en-tête et la charge utile d\'un JWT. La signature n\'est pas validée.',
        'keywords' => 'token, bearer, claims, authentification',
        'intro' => 'Collez le jeton (le préfixe Bearer peut venir avec) pour lire l\'en-tête, la charge utile et les dates des claims. L\'outil décode et ne valide pas la signature, et tout s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'header' => 'En-tête',
        'payload' => 'Charge utile',
        'signature' => 'Signature',
        'signature_bytes' => '{count} octets',
        'not_verified' => 'non vérifiée',
        'block_copied' => '{label} copié',

        'warning' => 'Ce décodeur ne valide pas la signature : il montre ce qui est écrit dans le jeton, et rien ici ne prouve que le jeton est authentique.',

        'claims_title' => 'Dates',

        'claims' => [
            'expired' => 'expiré',
            'not_yet' => 'pas encore valide',
        ],

        'states' => [
            'shape' => 'Ce n\'est pas un JWT : un JWT, ce sont trois parties séparées par des points (en-tête, charge utile et signature), et il n\'y en a pas trois ici.',
            'base64' => 'L\'une des parties n\'est pas du base64url valide, ou ses octets ne forment pas de l\'UTF-8.',
            'json' => 'L\'en-tête ou la charge utile n\'est pas un objet JSON.',
        ],
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp et UUID',
        'description' => 'Convertissez des horodatages Unix et des dates, et générez des UUID et des ULID.',
        'keywords' => 'unix, epoch, date, guid',
        'intro' => 'Collez un instant — Unix en secondes ou millisecondes, une date, ou un UUID v7/ULID — pour voir ses six lectures. Le panneau de droite génère aussi des UUID v4, v7 et ULID. Tout s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'now' => 'Maintenant',
        'kind' => 'Type d\'id',
        'quantity' => 'Quantité',
        'generate' => 'Générer',
        'ids_title' => 'Ids générés : {count}',
        'copy_ids' => 'Tout copier',
        'ids_copied' => '{count} ids copiés',
        'id_copied' => 'Id copié',
        'row_copied' => '{label} copié',
        'recognised' => 'L\'id collé porte cet instant ({kind})',

        'kinds' => [
            'uuid_v4' => 'UUID v4',
            'uuid_v7' => 'UUID v7',
            'ulid' => 'ULID',
        ],

        'readings' => [
            'unix_seconds' => 'Unix (secondes)',
            'unix_ms' => 'Unix (millisecondes)',
            'iso_utc' => 'ISO 8601 (UTC)',
            'iso_local' => 'ISO 8601 (local)',
            'long_date' => 'Date en toutes lettres',
            'relative' => 'Temps relatif',
        ],

        'states' => [
            'unreadable' => 'Impossible de lire un instant ici : utilisez l\'Unix en secondes ou millisecondes, une date (ISO 8601) ou un UUID v7/ULID.',
        ],
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Testez des expressions régulières avec surlignage en direct des correspondances et des groupes.',
        'keywords' => 'motif, correspondance, groupes',
        'intro' => 'Saisissez l\'expression régulière dans la barre du haut et le texte de test dans le panneau de gauche : les correspondances ressortent surlignées, une par une, avec les groupes numérotés et nommés, et le champ de remplacement montre le texte remplacé. La recherche s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'pattern' => 'Expression régulière',
        'replacement' => 'Remplacer par',
        'replacement_hint' => 'Ex. : $1 ou $<nom>',
        'position' => 'Position {start}',
        'matches_count' => 'Correspondances : {count}',
        'matches_none' => 'Aucune correspondance.',
        'truncated' => 'Affichage des {count} premières correspondances.',
        'replaced_title' => 'Résultat du remplacement',
        'file_types' => 'TXT',

        'flags' => [
            'i' => 'Ignorer la casse',
            'm' => 'Multiligne : ^ et $ sur chaque ligne',
            's' => 'Le point correspond aussi au saut de ligne',
            'u' => 'Unicode : correspondance par point de code',
        ],

        'states' => [
            'pattern' => 'L\'expression n\'est pas valide. Le détail ci-dessous est le message du moteur du navigateur.',
            'pattern_empty' => 'Saisissez l\'expression régulière à tester.',
            'timeout' => 'La recherche a dépassé {seconds} secondes et a été interrompue : aucun résultat n\'est affiché. Restreignez l\'expression pour éviter le retour arrière.',
        ],
    ],

    'markdown-to-pdf' => [
        'name' => 'Markdown to PDF',
        'description' => 'Convertissez Markdown en documents PDF stylisés avec aperçu en temps réel, formats de page, polices et thèmes.',
        'keywords' => 'markdown, pdf, convertisseur, document, exporter, gfm, imprimer',
        'intro' => 'Collez ou ouvrez du Markdown pour prévisualiser le document formaté en temps réel. Personnalisez les formats de page, les polices et les thèmes, puis téléchargez votre PDF ou imprimez avec une précision vectorielle. Tout fonctionne dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'download_name' => 'document',
        'file_types' => 'MD ou TXT',

        'controls' => [
            'format' => 'Format',
            'orientation' => 'Orientation',
            'margin' => 'Marge',
            'font' => 'Police',
            'font_size' => 'Taille',
            'theme' => 'Thème',
        ],

        'formats' => [
            'a4' => 'A4',
            'letter' => 'Lettre',
            'a5' => 'A5',
        ],

        'orientations' => [
            'portrait' => 'Portrait',
            'landscape' => 'Paysage',
        ],

        'margins' => [
            'normal' => 'Normale (20mm)',
            'compact' => 'Compacte (10mm)',
            'wide' => 'Large (30mm)',
        ],

        'fonts' => [
            'sans' => 'Sans-serif',
            'serif' => 'Serif',
            'mono' => 'Monospace',
            'fira-code' => 'Fira Code',
        ],

        'font_sizes' => [
            'sm' => 'Petite',
            'md' => 'Moyenne',
            'lg' => 'Grande',
        ],

        'themes' => [
            'modern' => 'Moderne',
            'editorial' => 'Éditorial',
            'minimal' => 'Minimaliste N&B',
            'academic' => 'Académique',
        ],

        'actions' => [
            'download_pdf' => 'Télécharger le PDF',
            'print' => 'Imprimer / Enregistrer en PDF',
            'generating' => 'Génération...',
        ],

        'stats' => [
            'words' => 'Mots : {count}',
            'chars' => 'Caractères : {count}',
        ],
    ],

    'base64-image' => [
        'name' => 'Base64 Image Encoder / Decoder',
        'description' => 'Convertissez des images en Base64 Data URI (texte brut, balise img ou CSS) et décodez du Base64 en image.',
        'intro' => 'Convertissez des images en Base64 Data URI avec prise en charge des balises img et CSS, ou décodez des chaînes Base64 en images avec prévisualisation et téléchargement.',
        'keywords' => 'image, base64, data-uri, encodeur, décodeur, convertir, css, html',
        'upload_title' => 'Cliquez pour sélectionner ou glissez-déposez une image',
        'upload_subtitle' => 'Prend en charge PNG, JPG, GIF, WebP, SVG, BMP, AVIF et autres formats',
        'max_limit_badge' => 'Limite : {limit}',
        'pro_plan_badge' => 'Plan Pro (Jusqu\'à 25 Mo)',
        'converting' => 'Conversion de l\'image sur le serveur...',
        'converting_hint' => 'Veuillez patienter pendant le traitement du fichier.',
        'new_image' => 'Nouvelle image',
        'original_size' => 'Taille originale',
        'base64_size' => 'Taille Base64',
        'retention_info' => 'Historique conservé pendant {hours}h',
        'retention_info_day' => 'Historique conservé pendant {days} jour',
        'retention_info_days' => 'Historique conservé pendant {days} jours',
        'direct_download_notice_title' => 'Fichier volumineux converti',
        'direct_download_notice_desc' => 'Pour protéger le navigateur, le téléchargement direct du fichier .txt avec le Data URI a commencé.',
        'copy' => 'Copier',
        'copied' => 'Copié !',
        'download_txt' => 'Télécharger .txt',
        'download_json' => 'Télécharger .json',
        'download_xml' => 'Télécharger .xml',
        'download_image' => 'Télécharger l\'image',
        'preview_img' => 'Aperçu',
        'size' => 'Taille',
        'example' => 'Exemple',
        'clear' => 'Effacer',
        'decoder_input_label' => 'Collez la chaîne Base64 ou Data URI :',
        'decoder_output_label' => 'Image Décodée :',
        'decoder_placeholder' => 'data:image/png;base64,iVBORw0KGgo...',
        'decoder_empty_prompt' => 'Aucune image décodée pour le moment.',
        'decoder_empty_hint' => 'Collez une chaîne Base64 valide à gauche pour la prévisualiser et la télécharger.',
        'history_empty_title' => 'Aucune conversion récente',
        'history_empty_desc' => 'Les images que vous convertissez apparaîtront ici pendant leur durée de rétention.',
        'active_badge' => 'Actif',
        'expired_badge' => 'Expiré',
        'confirm_delete' => 'Êtes-vous sûr de vouloir supprimer cette conversion de l\'historique ?',
        'tabs' => [
            'encoder' => 'Encodeur (Image → Base64)',
            'decoder' => 'Décodeur (Base64 → Image)',
            'history' => 'Historique',
        ],
        'formats' => [
            'data_uri' => 'Data URI / Texte Brut',
            'img_tag' => 'Balise <img> HTML',
            'css_bg' => 'CSS background-image',
            'json' => 'JSON',
            'xml' => 'XML',
        ],
        'errors' => [
            'title' => 'Erreur de conversion',
            'invalid_image' => 'Le fichier téléversé n\'est pas une image valide.',
            'file_too_large' => 'Le fichier dépasse la limite maximale autorisée de {limit}.',
            'conversion_failed' => 'Une erreur est survenue lors du traitement de l\'image. Veuillez réessayer.',
            'invalid_base64' => 'La chaîne Base64 fournie est invalide ou corrompue.',
            'file_expired' => 'Le fichier a expiré et a été supprimé du serveur.',
        ],
    ],
];
