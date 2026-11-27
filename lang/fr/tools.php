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

    /*
    | Interface partagée entre outils. Le sélecteur de couleur est utilisé par
    | plusieurs outils : ses textes vivent ici et non dans `tools.<slug>.*`.
    */
    'shared' => [
        'color_picker' => [
            'color_input' => [
                'label' => 'Couleur',
                'placeholder' => '#7c3aed, rgb(124 58 237), oklch(54% 0.24 293)…',
                'hint' => 'Accepte HEX, RGB, HSL, OKLCH, OKLab et les noms CSS. La couleur s’applique pendant la saisie dès que le texte est valide.',
                'empty' => 'Saisissez une couleur pour commencer.',
                'syntax' => 'Ce format n’a pas pu être compris. Vérifiez le code et réessayez.',
                'range' => 'Une des valeurs est hors de la plage autorisée.',
                'unknown_name' => 'Aucune couleur CSS ne porte ce nom.',
            ],

            'area' => [
                'label' => 'Saturation et luminosité',
                'hint' => 'Les flèches ajustent de 1 point ; avec Maj, de 10.',
            ],

            'hue' => [
                'label' => 'Teinte',
            ],

            'alpha' => [
                'label' => 'Opacité',
            ],

            'preview' => [
                'eyedropper' => 'Pipette de l’écran',
            ],

            'channels' => [
                'title' => 'Canaux',
                'rgb' => 'RGB',
                'hsl' => 'HSL',
                'red' => 'Rouge',
                'green' => 'Vert',
                'blue' => 'Bleu',
                'hue' => 'Teinte',
                'saturation' => 'Saturation',
                'lightness' => 'Luminosité',
            ],

            'formats' => [
                'title' => 'Formats de couleur',
                'hex' => 'HEX',
                'hex8' => 'HEX8',
                'rgb' => 'RGB',
                'rgba' => 'RGBA',
                'hsl' => 'HSL',
                'hsla' => 'HSLA',
                'oklch' => 'OKLCH',
                'oklab' => 'OKLab',
                'name' => 'Nom CSS',
                'copy' => 'Copier {format}',
                'copied' => 'Copié : {value}',
                'name_hint' => 'Le nom CSS est la couleur la plus proche ; il peut différer légèrement du code.',
            ],
        ],

        'library' => [
            'history' => [
                'title' => 'Historique',
                'description' => [
                    'account' => 'Les dernières couleurs appliquées, enregistrées dans votre compte et partagées avec le générateur.',
                    'local' => 'Les dernières couleurs appliquées, uniquement dans ce navigateur. Connectez-vous pour les conserver dans votre compte.',
                ],
                'clear' => 'Effacer',
                'empty' => 'Aucune couleur appliquée pour l’instant.',
            ],

            'favorites' => [
                'title' => 'Favoris',
                'description' => [
                    'account' => 'Couleurs enregistrées dans votre compte, partagées entre le sélecteur de couleur et le générateur de palettes.',
                    'local' => 'Couleurs enregistrées uniquement dans ce navigateur.',
                ],
                'add' => 'Ajouter la couleur actuelle aux favoris',
                'remove' => 'Retirer des favoris',
                'clear' => 'Effacer',
                'empty' => 'Aucun favori pour l’instant.',
            ],

            'import' => [
                'button' => 'Importer l’historique du navigateur ({count})',
                'title' => 'Importer l’historique du navigateur',
                'description' => 'Envoyer {count} couleur(s) enregistrée(s) dans ce navigateur vers votre compte. La liste locale est effacée après l’import et les doublons sont ignorés.',
                'confirm' => 'Importer',
                'cancel' => 'Annuler',
                'busy' => 'Importation…',
                'success' => '{count} couleur(s) importée(s) dans votre compte.',
                'error' => 'L’import a échoué. L’historique local a été conservé ; réessayez.',
            ],

            'load_more' => 'Charger plus',
        ],

        'token_import' => [
            'button' => 'Importer des tokens',
            'applied' => 'Tokens appliqués.',
            'title' => 'Importer des tokens de design',
            'description' => 'Lisez le JSON natif de l’outil, W3C DTCG (Tokens Studio ou Figma Variables) ou Style Dictionary. Le fichier est lu dans votre navigateur et n’est jamais envoyé.',
            'choose' => 'Choisir un fichier JSON',
            'hint' => 'Jusqu’à {size}. Vous pouvez aussi déposer le fichier ici.',
            'reading' => 'Lecture du fichier…',

            'file_errors' => [
                'file_too_large' => 'Le fichier dépasse la limite de {limit}.',
                'file_type' => 'Choisissez un fichier .json.',
                'file_unreadable' => 'Impossible de lire le fichier. Essayez-en un autre.',
            ],

            'diagnostics' => [
                'title' => 'Diagnostic',
                'errors' => 'Erreurs',
                'warnings' => 'Avertissements',
                'hidden' => '{count} autre(s) diagnostic(s) non affiché(s).',
                'path' => 'dans {path}',
            ],

            'preview' => [
                'title' => 'Aperçu',
                'format' => 'Format',
                'kind' => 'Type',
                'base' => 'Couleur de base',
                'colors_label' => 'Couleurs',
                'colors' => '{count} couleurs',
                'roles' => 'Rôles sémantiques inclus',
                'no_roles' => 'Sans rôles sémantiques complets',
                'name' => 'Nom dans le fichier : {name}',
                'filename' => 'Fichier : {name}',
                'apply' => 'Appliquer',
                'cancel' => 'Annuler',
                'replace' => 'Choisir un autre fichier',
                'apply_hint' => 'La palette actuelle ne change qu’à l’application.',
            ],

            'formats' => [
                'native' => 'JSON de l’outil',
                'dtcg' => 'W3C DTCG',
                'style-dictionary' => 'Style Dictionary',
            ],

            'kinds' => [
                'scale' => 'Échelle de 11 tons',
                'extracted' => 'Palette extraite',
            ],

            'codes' => [
                'empty_file' => 'Le fichier est vide.',
                'too_large' => 'Le fichier dépasse la limite de {max}.',
                'invalid_json' => 'Le JSON n’est pas valide (ligne {line}, colonne {column}).',
                'root_not_object' => 'La racine du fichier doit être un objet JSON.',
                'unsupported_format' => 'Aucun token de couleur reconnaissable n’a été trouvé.',
                'ambiguous_format' => 'Le fichier mélange plusieurs styles de tokens.',
                'unsupported_format_version' => 'La version du format ({found}) n’est pas compatible ; cet outil lit la version {expected}.',
                'too_deep' => 'La structure dépasse la profondeur maximale de {max}.',
                'too_many_tokens' => 'Le fichier contient plus de {max} tokens.',
                'forbidden_key' => 'La clé « {key} » n’est pas autorisée.',
                'mixed_token_styles' => 'Des tokens de styles différents apparaissent dans le même groupe.',
                'invalid_structure' => 'La structure de la palette n’est pas reconnaissable.',
                'no_colors' => 'Le fichier ne contient aucune couleur à importer.',
                'ambiguous_palette' => 'Plusieurs palettes sont possibles ; n’en laissez qu’une à importer.',
                'unsupported_color_count' => 'Le groupe contient {count} couleurs ; utilisez 5, 7, 9 ou 11.',
                'missing_tones' => 'Des tons manquent dans l’échelle : {missing}.',
                'invalid_color' => 'Une valeur de couleur n’est pas un HEX valide.',
                'unknown_reference' => 'La référence « {reference} » n’existe pas dans le fichier.',
                'reference_not_color' => 'La référence « {reference} » ne pointe pas vers une couleur.',
                'reference_cycle' => 'Les références forment un cycle.',
                'reference_depth' => 'Les références dépassent la profondeur maximale de {max}.',
                'algorithm_version_mismatch' => 'Le fichier a été généré avec l’algorithme v{found} ; les couleurs seront conservées telles quelles.',
                'non_color_ignored' => 'Les tokens qui ne sont pas des couleurs ont été ignorés.',
                'semantic_incomplete' => 'Le bloc de rôles sémantiques est incomplet ; les rôles actuels seront conservés.',
                'extra_colors_ignored' => 'Les couleurs hors palette ont été ignorées.',
            ],
        ],
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Encodage et hachage',
        'security' => 'Sécurité',
        'time' => 'Date et heure',
        'text' => 'Texte',
        'badges' => 'Images',
        'colors' => 'Couleurs',
    ],

    'html-color-picker' => [
        'name' => 'HTML Color Picker',
        'description' => 'Choisissez une couleur, convertissez-la entre HEX, RGB, HSL et OKLCH et vérifiez le contraste WCAG avant de l’utiliser.',
        'keywords' => 'couleur, hex, rgb, hsl, oklch, contraste, wcag, pipette, nuances, teintes',
        'intro' => 'Choisissez une couleur à l’écran ou saisissez un code, ajustez-la dans la zone 2D et les curseurs, et vérifiez le contraste WCAG sur noir et blanc. Tout s’exécute dans votre navigateur : aucune couleur ne quitte la page.',

        'preview' => [
            'alpha' => '{percent} % d’opacité',
            'eyedropper' => 'Pipette de l’écran',
            'eyedropper_image' => 'Pipette depuis une image',
            'gamut' => 'Cette couleur est hors du gamut sRGB. La valeur saisie ({source}) a été réduite en chroma vers {value}, en conservant la luminosité et la teinte.',
        ],

        'image' => [
            'title' => 'Pipette depuis une image',
            'description' => 'L’image est lue dans votre navigateur et n’est jamais envoyée. Cliquez sur l’image ou utilisez les flèches pour choisir un pixel, puis confirmez.',
            'choose' => 'Choisir une image',
            'error' => 'Impossible de lire cette image. Essayez-en une autre.',
            'instructions' => 'Cliquez ou utilisez les flèches pour choisir un pixel ; Entrée applique.',
            'apply' => 'Utiliser cette couleur',
        ],

        'contrast' => [
            'title' => 'Contraste WCAG',
            'description' => 'L’opacité est composée sur le fond choisi avant le calcul.',
            'backdrop' => 'Fond',
            'white' => 'Blanc',
            'black' => 'Noir',
            'custom' => 'Personnalisé',
            'black_text' => 'Texte noir ({ratio}:1 {verdict})',
            'white_text' => 'Texte blanc ({ratio}:1 {verdict})',
            'pass_word' => 'réussit',
            'fail_word' => 'échoue',
            'aa_normal' => 'AA',
            'aaa_normal' => 'AAA',
            'aa_large' => 'AA grand',
            'aaa_large' => 'AAA grand',
            'large_note' => 'Grand texte : 18,66 px en gras ou 24 px normal.',
        ],

        'shades' => [
            'title' => 'Nuances sombres',
            'description' => 'Dix versions plus sombres, en OKLab, avec la même opacité.',
        ],

        'tints' => [
            'title' => 'Nuances claires',
            'description' => 'Dix versions plus claires, en OKLab, avec la même opacité.',
        ],

        'tabs' => [
            'contrast' => 'Contraste',
            'shades' => 'Nuances',
            'library' => 'Bibliothèque',
        ],
    ],

    'palette-generator' => [
        'name' => 'Color Palette Generator',
        'description' => 'Générez une échelle de 11 tons, explorez les harmonies et extrayez les couleurs dominantes d’une image ; exportez en CSS, SCSS, JSON et Tailwind.',
        'keywords' => 'palette, échelle de couleurs, tons, teintes, tailwind, variables css, scss, jetons de design, oklch, harmonies, couleurs dominantes, extraction d’image',
        'intro' => 'Choisissez une couleur de base et obtenez aussitôt une échelle de 11 tons en OKLCH, avec le contraste WCAG sur chaque ton et les valeurs converties de la base. Explorez les harmonies complémentaire, analogue, triadique, tétradique et monochromatique en OKLCH ou HSL, et extrayez jusqu’à 11 couleurs dominantes d’une image locale ou d’une URL HTTPS. Copiez un ton ou toute l’échelle et téléchargez-la en CSS, SCSS, JSON, Tailwind v3 ou Tailwind v4. Tout s’exécute dans votre navigateur : aucune couleur et aucune image ne quitte la page.',

        'tabs' => [
            'scale' => 'Échelle',
            'harmonies' => 'Harmonies',
            'extraction' => 'Extraction',
            'library' => 'Bibliothèque',
        ],

        'harmonies' => [
            'title' => 'Harmonies',
            'description' => 'Des couleurs qui s’accordent avec la base. Cliquez sur une pour en faire la nouvelle base.',
            'kind_label' => 'Type d’harmonie',
            'method_label' => 'Méthode',
            'method_oklch' => 'OKLCH',
            'method_hsl' => 'HSL',
            'kind_complementary' => 'Complémentaire',
            'kind_analogous' => 'Analogue',
            'kind_triadic' => 'Triadique',
            'kind_tetradic' => 'Tétradique',
            'kind_monochromatic' => 'Monochromatique',
            'base_badge' => 'Base',
            'clipped_badge' => 'Hors sRGB',
            'achromatic_note' => 'La base est achromatique : faire tourner la teinte ne crée aucune couleur nouvelle, l’harmonie est donc réduite.',
            'color_label' => 'Couleur {index} de l’harmonie',
            'copy' => 'Copier',
            'use_as_base' => 'Utiliser comme base',
            'copied' => 'Copié : {value}',
        ],

        'extraction' => [
            'title' => 'Couleurs dominantes de l’image',
            'description' => 'Extrayez les couleurs dominantes d’une image locale ou d’une URL HTTPS. Rien n’est envoyé au serveur.',
            'file_label' => 'Image',
            'file_hint' => 'JPEG, PNG, WebP, GIF ou AVIF, jusqu’à {size}.',
            'url_label' => 'URL de l’image',
            'url_placeholder' => 'https://exemple.com/photo.jpg',
            'url_hint' => 'Doit être en HTTPS et autoriser CORS ; si l’origine bloque, utilisez un fichier local.',
            'import_url' => 'Importer',
            'importing' => 'Importation…',
            'count_label' => 'Nombre de couleurs',
            'count_option' => '{count} couleurs',
            'cancel' => 'Annuler',
            'preview_alt' => 'Aperçu de l’image importée',
            'status' => [
                'reading' => 'Téléchargement de l’image…',
                'decoding' => 'Décodage…',
                'analyzing' => 'Extraction des couleurs dominantes…',
            ],
            'results_title' => 'Couleurs dominantes · {count}',
            'percentage' => '{value} % des pixels',
            'percentage_unknown' => 'poids non enregistré',
            'copy' => 'Copier',
            'copied' => 'Copié : {value}',
            'use_as_base' => 'Utiliser comme base',
            'use_file' => 'Utiliser un fichier local',
            'insufficient' => 'L’image contient moins de {requested} couleurs distinctes ; {found} ont été trouvées.',
            'algorithm_version' => 'Algorithme v{version}',
            'source_unknown' => 'source non enregistrée',
            'source_file' => 'Fichier : {name}',
            'source_url' => 'Image de {host}',
            'errors' => [
                'invalid_url' => 'L’URL n’est pas valide.',
                'not_https' => 'Seules les URL HTTPS sont acceptées.',
                'cors' => 'L’origine n’autorise pas la lecture directe (CORS). Utilisez un fichier local.',
                'network' => 'Impossible de télécharger l’image.',
                'timeout' => 'L’image a mis trop de temps à répondre. Réessayez.',
                'too_large' => 'L’image dépasse la limite de taille configurée.',
                'not_image' => 'L’adresse ne pointe pas vers une image acceptée (JPEG, PNG, WebP, GIF ou AVIF).',
                'decode_failed' => 'Impossible de décoder l’image.',
                'dimensions_too_large' => 'L’image dépasse les limites de dimensions configurées.',
                'too_many_pixels' => 'L’image contient trop de pixels pour être analysée.',
                'empty_image' => 'L’image n’a aucun pixel visible (tout est transparent).',
                'unavailable' => 'L’extraction n’est pas disponible dans ce navigateur.',
                'cancelled' => 'Importation annulée.',
            ],
        ],

        'base' => [
            'title' => 'Couleur de base',
            'badge' => 'Base',
            'copy' => 'Copier {format}',
            'copied' => 'Copié : {value}',
            'nearest' => 'Ton le plus proche : {tone}',
            'not_500' => 'La base n’est pas le ton 500. Elle reste intacte ; l’échelle ne change pas.',
            'matches_500' => 'La base est le ton 500 de l’échelle.',
            'clipped' => 'Chroma réduit pour tenir dans le sRGB.',
            'alpha_note' => 'Le contraste prend en compte le ton composé sur un fond de même polarité que le texte.',
        ],

        'scale' => [
            'title' => 'Échelle de 11 tons',
            'description' => 'Luminosité fixe en OKLCH ; le chroma suit la couleur de base.',
            'copy_scale' => 'Copier l’échelle',
            'copied_scale' => 'Échelle copiée : {count} tons',
            'tone_copy' => 'Copier {tone}',
            'tone_copied' => 'Copié : {value}',
            'contrast_black' => 'Texte noir {ratio}:1',
            'contrast_white' => 'Texte blanc {ratio}:1',
            'pass' => 'réussit {level}',
            'fail' => 'échoue {level}',
            'clipped_badge' => 'Chroma limité',
            'clipped_summary' => 'Tons à chroma limité : {tones}',
            'algorithm_version' => 'Algorithme v{version}',
            'base_tone_badge' => 'Le plus proche',
        ],

        'export' => [
            'title' => 'Exporter',
            'description' => 'Copiez ou téléchargez l’échelle prête pour votre projet.',
            'name_label' => 'Nom de la palette',
            'name_hint' => 'Utilisé dans les variables : {slug}',
            'name_invalid' => 'Le nom sera normalisé en {slug}.',
            'copy' => 'Copier',
            'copied' => '{name} copié',
            'download' => 'Télécharger',
            'semantic_included' => 'Les rôles sémantiques du prototype sont inclus.',
            'tabs' => [
                'css' => 'CSS',
                'scss' => 'SCSS',
                'json' => 'JSON',
                'dtcg' => 'DTCG',
                'style_dictionary' => 'Style Dictionary',
                'typescript' => 'TypeScript',
                'tailwind_v3' => 'Tailwind v3',
                'tailwind_v4' => 'Tailwind v4',
            ],
        ],

        'empty' => [
            'title' => 'Choisissez une couleur de base',
            'body' => 'Dès qu’une couleur valide existe, les 11 tons apparaissent ici.',
            'restore' => 'Restaurer l’exemple',
        ],

        'state' => [
            'stale' => 'La couleur saisie n’est pas valide ; l’échelle précédente reste affichée.',
        ],

        'save' => [
            'action' => 'Enregistrer la palette',
            'draft_restored' => 'Votre palette est revenue. Vérifiez-la et enregistrez.',
        ],
    ],

    'layout-harmonizer' => [
        'name' => 'Layout Harmonizer',
        'description' => 'Générez les tokens sémantiques de couleur d’une interface claire et sombre à partir d’une couleur source : 33 tokens, diagnostic de contraste et valeurs copiables.',
        'keywords' => 'layout, tokens sémantiques, design tokens, système de couleurs, thème clair, thème sombre, contraste, WCAG, OKLCH',
        'intro' => 'Choisissez une couleur source pour obtenir le tableau sémantique complet en thèmes clair et sombre : marque, surfaces, retours, contenu et interaction, chacun avec sa finalité et son contraste mesuré. Rien n’est généré avant une couleur valide, et rien ne quitte votre navigateur.',

        'seed' => [
            'title' => 'Couleur source',
            'description' => 'Le tableau est dérivé de cette couleur. Saisissez une valeur ou utilisez le sélecteur.',
            'empty_value' => 'Aucune couleur',
            'background_label' => 'Fond de composition',
            'background_hint' => 'Obligatoire car la source est transparente. Tout format convient : HEX, RGB ou OKLCH.',
            'background_invalid' => 'Ce fond n’est pas une couleur valide.',
            'background_alpha_invalid' => 'Le fond de composition doit être opaque : la transparence n’est pas acceptée ici.',
            'summary' => [
                'original' => 'Originale',
                'effective' => 'Effective',
                'background' => 'Fond',
                'strategy' => 'Stratégie',
                'secondary' => 'Secondaire',
                'alpha_note' => 'Avec transparence, la source effective est la couleur composée sur le fond.',
                'achromatic' => 'La source est achromatique : les rotations de teinte sont ramenées au ton neutre et le tableau reste achromatique.',
            ],
        ],

        'strategy' => [
            'label' => 'Stratégie d’harmonie',
            'hint' => 'Les 33 tokens ne changent pas ; la stratégie change la dérivation de la secondaire et de l’accent.',
            'analogous' => [
                'name' => 'Analogue',
                'description' => 'Secondaire à 30° de la source : voisines proches, peu de tension.',
            ],
            'complementary' => [
                'name' => 'Complémentaire',
                'description' => 'Secondaire à 180° de la source : contraste maximal.',
            ],
            'triadic' => [
                'name' => 'Triadique',
                'description' => 'Secondaire à 240° de la source : trois teintes équidistantes.',
            ],
            'monochromatic' => [
                'name' => 'Monochromatique',
                'description' => 'Secondaire sur la même teinte : variation uniquement en clarté et en chroma.',
            ],
        ],

        'view' => [
            'light' => 'Clair',
            'dark' => 'Sombre',
            'compare' => 'Comparer',
            'board_light' => 'Thème clair',
            'board_dark' => 'Thème sombre',
        ],

        'families' => [
            'brand' => 'Marque',
            'surface' => 'Surfaces',
            'feedback' => 'Retours',
            'content' => 'Contenu',
            'interaction' => 'Interaction',
        ],

        'tokens' => [
            'primary' => 'Couleur principale de la marque ; base des boutons pleins et de l’emphase.',
            'primary-variant' => 'Ton plus profond de la marque pour les grands blocs, distinct du survol.',
            'secondary' => 'Couleur d’appui pour les actions moins visibles.',
            'secondary-variant' => 'Ton plus profond de la secondaire pour les grandes surfaces.',
            'surface' => 'Fond par défaut des panneaux.',
            'surface-variant' => 'Fond discret pour un contenu regroupé.',
            'background' => 'Fond de page, la couche la plus externe.',
            'hairline' => 'Bordures et séparateurs.',
            'error' => 'Fond et texte de l’état d’erreur.',
            'success' => 'Fond et texte de l’état de réussite.',
            'warning' => 'Fond et texte de l’état d’avertissement.',
            'info' => 'Fond et texte de l’état d’information.',
            'on-primary' => 'Texte et icônes sur la primaire, le survol et l’actif.',
            'on-secondary' => 'Texte et icônes sur la secondaire et ses états.',
            'on-background' => 'Texte principal sur le fond de page.',
            'on-surface' => 'Texte sur les surfaces et les cartes.',
            'on-surface-variant' => 'Texte secondaire : légendes, aides et métadonnées.',
            'on-error' => 'Texte et icônes sur l’erreur.',
            'on-success' => 'Texte et icônes sur la réussite.',
            'on-warning' => 'Texte et icônes sur l’avertissement.',
            'on-info' => 'Texte et icônes sur l’information.',
            'primary-hover' => 'Primaire au survol.',
            'primary-active' => 'Primaire pendant l’appui.',
            'secondary-hover' => 'Secondaire au survol.',
            'secondary-active' => 'Secondaire pendant l’appui.',
            'link' => 'Liens dans le contenu.',
            'link-hover' => 'Lien au survol.',
            'link-active' => 'Lien pendant l’appui.',
            'focus-ring' => 'Anneau de focus clavier.',
            'accent' => 'Troisième couleur de l’harmonie, présente dans toutes les stratégies.',
            'on-accent' => 'Texte et icônes sur l’accent et ses états.',
            'accent-hover' => 'Accent au survol.',
            'accent-active' => 'Accent pendant l’appui.',
        ],

        'provenance' => [
            'seed' => 'De la source',
            'manual' => 'Manuel',
            'rule' => 'Par règle',
            'feedback' => 'Retour',
        ],

        'contrast' => [
            'aa' => 'AA',
            'aa_fail' => 'Échec AA',
            'against' => 'Sur {background}',
            'on' => 'sur',
            'summary_title' => 'Contraste AA',
            'all_pass' => 'Toutes les paires mesurées atteignent AA pour le texte normal.',
        ],

        'copy' => [
            'hex_copied' => 'Copié {token} : HEX',
            'oklch_copied' => 'Copié {token} : OKLCH',
            'hex_aria' => 'Copier le HEX de {token}',
            'oklch_aria' => 'Copier l’OKLCH de {token}',
        ],

        'clipped' => [
            'badge' => 'Chroma réduit',
            'note' => 'Le chroma demandé sort du sRGB et a été réduit à la couleur affichable la plus proche.',
        ],

        'states' => [
            'empty_title' => 'Pas encore de tableau',
            'empty_description' => 'Choisissez une couleur source pour générer les tableaux clair et sombre.',
            'invalid_seed' => 'La couleur saisie n’est pas valide ; le tableau précédent reste affiché.',
            'seed_alpha_without_background' => 'La source est transparente : indiquez un fond de composition pour générer le tableau.',
            'seed_background_alpha_invalid' => 'Le fond de la source est transparent : indiquez une couleur opaque.',
            'secondary_alpha_without_background' => 'La secondaire est transparente : indiquez un fond de composition opaque pour générer le tableau.',
            'invalid_strategy' => 'La stratégie choisie n’est pas reconnue ; le tableau précédent reste affiché.',
            'stale_board' => 'Le tableau affiché reflète la dernière saisie valide.',
        ],

        'edit' => [
            'title' => 'Modifier {token}',
            'open_aria' => 'Modifier {token}',
            'apply' => 'Appliquer',
            'cancel' => 'Annuler',
            'alpha_ignored' => "Les jetons sont opaques : l'alpha choisi est ignoré.",
        ],

        'lock' => [
            'lock' => 'Verrouiller',
            'unlock' => 'Déverrouiller',
            'lock_aria' => 'Verrouiller {token}',
            'unlock_aria' => 'Déverrouiller {token}',
        ],

        'restore' => [
            'token' => 'Restaurer le jeton',
            'token_aria' => 'Restaurer {token} en automatique',
            'theme' => 'Restaurer {theme} ({count})',
            'all' => 'Tout restaurer ({count})',
            'confirm_title' => 'Supprimer les modifications manuelles ?',
            'confirm_body' => 'Cela supprime les valeurs manuelles et les verrouillages de {count} jeton(s) dans ce périmètre et revient à la génération automatique.',
            'confirm' => 'Restaurer',
            'cancel' => 'Annuler',
        ],

        'secondary' => [
            'label' => 'Couleur secondaire (facultative)',
            'hint' => 'Vide conserve la secondaire automatique de la stratégie. Les variantes et les états restent dérivés par thème.',
            'invalid' => "Cette secondaire n'est pas une couleur valide ; la dernière valeur valide reste.",
            'background_label' => 'Fond de composition',
            'background_hint' => 'Obligatoire car la secondaire est transparente. Tout format convient : HEX, RGB ou OKLCH.',
            'background_invalid' => 'Ce fond n’est pas une couleur valide.',
            'background_alpha_invalid' => 'Le fond de composition doit être opaque : la transparence n’est pas acceptée ici.',
            'manual' => 'Secondaire manuelle',
            'auto' => 'Secondaire automatique',
            'reset' => "Revenir à l'automatique",
        ],

        'suggestions' => [
            'title' => 'Suggestions de contraste',
            'description' => "Chaque suggestion ne déplace que la couleur du texte, du plus petit pas de luminosité qui atteint la cible choisie contre tous les fonds de ce jeton. Rien n'est appliqué sans votre accord.",
            'badge' => 'Suggestion',
            'target' => [
                'aa' => 'Cible AA',
                'aaa' => 'Cible AAA',
            ],
            'none' => 'Chaque paire mesurée atteint la cible choisie.',
            'delta_lightness' => 'ΔL {delta}',
            'accept_theme' => 'Accepter en {theme}',
            'accept_both' => 'Accepter dans les deux thèmes',
            'inviable' => "Aucune luminosité n'atteint {target} contre tous les fonds de ce jeton.",
            'inviable_badge' => 'Inatteignable',
        ],

        'edit_notice' => [
            'discarded' => 'Les valeurs manuelles non verrouillées ont été supprimées car la graine, la stratégie ou la secondaire ont changé. Les valeurs verrouillées ont été conservées.',
        ],

        'feedback_preview' => [
            'title' => 'Paires de retour',
            'description' => 'Chaque couleur de retour avec la couleur de contenu mesurée par-dessus.',
        ],

        'a11y' => [
            'view_mode' => 'Vue du thème',
            'board_section' => 'Tableau : {theme}',
            'contrast_summary' => 'Diagnostic de contraste',
            'restore' => 'Restaurer la génération automatique',
            'suggestions' => 'Suggestions de contraste',
            'suggestion_target' => 'Cible de contraste des suggestions',
        ],

        'prototype' => [
            'title' => 'Prototype',
            'description' => 'Voyez les 33 jetons du système appliqués à une interface, dans le modèle et le thème choisis.',
            'template_label' => 'Modèle',
            'template_site' => 'Site',
            'template_mobile' => 'Mobile',
            'theme_label' => 'Thème',
            'theme_light' => 'Clair',
            'theme_dark' => 'Sombre',
            'preview_label' => 'Aperçu du prototype : {template}, {theme}',
        ],

        'save' => [
            'title' => 'Enregistrer le système de mise en page',
            'description' => 'Le tableau enregistré se rouvre exactement tel quel, sans recalcul.',
            'name_label' => 'Nom',
            'name_placeholder' => 'Mon système',
            'name_required' => 'Saisissez un nom.',
            'name_too_long' => 'Utilisez au maximum {max} caractères.',
            'name_from_palette' => 'Système de mise en page — {name}',
            'name_from_strategy' => '{strategy} — {hex}',
            'description_label' => 'Description (facultative)',
            'description_placeholder' => 'À quoi sert ce système ?',
            'description_too_long' => 'Utilisez au maximum {max} caractères.',
            'visibility_label' => 'Qui peut le voir',
            'visibility_private' => 'Privé',
            'visibility_private_hint' => 'Vous seul le voyez. Vous pourrez le publier plus tard.',
            'visibility_public' => 'Public',
            'visibility_public_hint' => 'Il obtient un lien public et apparaît dans la recherche.',
            'save_button' => 'Enregistrer',
            'submit' => 'Enregistrer le système',
            'submit_update' => 'Enregistrer les modifications',
            'save_as_new' => 'Enregistrer comme nouveau',
            'saving' => 'Enregistrement…',
            'cancel' => 'Annuler',
            'login_to_save' => 'Se connecter pour enregistrer',
            'limit_reached' => 'Vous avez atteint la limite de {limit} éléments. Archivez ou supprimez quelque chose pour enregistrer.',
        ],

        'draft' => [
            'title' => 'Reprendre le brouillon ?',
            'description' => 'Nous avons trouvé un tableau enregistré dans ce navigateur avant votre connexion. L’importer conserve votre travail et ne crée pas de système.',
            'unsupported_title' => 'Brouillon d’une version plus récente',
            'unsupported_description' => 'Ce brouillon a été créé par une version plus récente de l’outil et ne peut pas être importé ici. Vous pouvez le supprimer.',
            'import' => 'Importer le brouillon',
            'discard' => 'Ignorer',
            'discard_stored' => 'Supprimer le brouillon enregistré',
            'discard_title' => 'Supprimer le brouillon enregistré ?',
            'discard_body' => 'Le tableau enregistré dans ce navigateur est supprimé et l’outil démarre vide.',
        ],

        'origin' => [
            'from' => 'Créé à partir de la palette {name}.',
            'open' => 'Voir la palette',
            'remove' => 'Retirer l’origine',
        ],

        'lifecycle' => [
            'restore_disabled' => 'La restauration est indisponible pour ce système : l’état enregistré ne conserve pas les valeurs automatiques. Utilisez « Régénérer avec la version actuelle ».',
            'suggestions_off' => 'Les suggestions de contraste sont indisponibles pour ce système car la version de l’algorithme de suggestions a changé.',
            'pending_regeneration' => 'La graine, la stratégie ou la secondaire a changé. Le tableau reste dans l’état enregistré jusqu’à ce que vous régénériez.',
            'regenerate' => 'Régénérer avec la version actuelle',
            'regenerate_available' => 'Ce système a été enregistré avec une version précédente. Le tableau reste exactement tel qu’enregistré ; la régénération applique la version actuelle aux mêmes entrées.',
            'regenerate_confirm_title' => 'Régénérer avec la version actuelle ?',
            'regenerate_confirm_body' => 'Les jetons verrouillés sont conservés. {count} valeur(s) manuelle(s) ou acceptée(s) non verrouillée(s) seront perdues.',
            'readonly_notice' => 'Ce système utilise une version plus récente que cet éditeur. Le tableau est en lecture seule ; dupliquez-le pour continuer à modifier.',
        ],

        'guard' => [
            'title' => 'Quitter sans enregistrer ?',
            'description' => 'Vous avez des modifications qui n’ont pas été enregistrées. Si vous quittez maintenant, elles seront perdues.',
            'stay' => 'Continuer à modifier',
            'leave' => 'Quitter sans enregistrer',
        ],
        'export' => [
            'title' => 'Exporter',
            'description' => 'Téléchargez le tableau affiché sous forme d’instructions pour une IA, de variables CSS, ou des deux dans un ZIP. Tout est généré ici, dans votre navigateur, sans régénérer les couleurs.',
            'login_cta' => 'Se connecter pour exporter',
            'login_hint' => 'Créer un compte est gratuit. Votre tableau reste exactement tel quel — nul besoin de l’enregistrer pour exporter.',
            'language_label' => 'Langue du document',
            'prefix_label' => 'Préfixe CSS',
            'prefix_hint' => 'Les variables deviennent `--{prefix}-primary`, `--{prefix}-background`, etc.',
            'scope_label' => 'Portée CSS',
            'scope_root' => 'Racine du document (:root)',
            'scope_class' => 'Classe personnalisée',
            'scope_class_label' => 'Nom de la classe',
            'download_markdown' => 'Télécharger le Markdown',
            'download_css' => 'Télécharger le CSS',
            'download_zip' => 'Télécharger le ZIP',
            'pending_regeneration' => 'Les entrées ont changé depuis la dernière génération. Régénérez le tableau avant d’exporter.',
            'compatible_notice' => 'Cet état a été généré par un algorithme plus ancien. Les fichiers portent les versions enregistrées.',
            'readonly_notice' => 'Cet état est en lecture seule. Vous pouvez tout de même exporter les valeurs affichées.',
            'errors' => [
                'prefix_required' => 'Saisissez un préfixe.',
                'prefix_too_long' => 'Utilisez au maximum {max} caractères.',
                'prefix_invalid' => 'Utilisez des minuscules, des chiffres et des traits d’union simples, en commençant par une lettre.',
                'scope_required' => 'Saisissez une portée.',
                'scope_too_long' => 'Utilisez au maximum {max} caractères.',
                'scope_invalid' => 'Utilisez :root ou une classe simple comme .app.',
            ],
            'targets' => [
                'aa' => 'AA',
                'aaa' => 'AAA',
            ],
            'css' => [
                'header' => 'Layout Harmonizer — {name}',
                'meta' => '{language} · État {state} · Schéma {schema} · Algorithme {algorithm} · Suggestions {suggestions} · Instantané {snapshot}',
                'theme_default' => 'Thème par défaut : clair. Pour le sombre, définissez data-theme="dark" sur la portée.',
            ],
            'warnings' => [
                'title' => 'Avertissements',
                'none' => 'Aucun avertissement.',
                'contrast' => '{theme} : {foreground} sur {background} — {ratio} (cible {target})',
                'locked' => '{theme} : {token} est verrouillé.',
                'manual' => '{theme} : {token} a été défini manuellement ({hex}).',
                'accepted' => '{theme} : {token} ajusté de {from} à {to} (cible {target}).',
                'clipped' => '{theme} : chroma de {token} réduit pour tenir en sRGB.',
                'compatible' => 'Mode compatible : algorithme {algorithm} et suggestions {suggestions} différents de l’éditeur actuel.',
                'readonly' => 'État en lecture seule : schéma {schema}, algorithme {algorithm}, suggestions {suggestions}.',
                'limitations' => 'Les cibles de contraste sont indicatives : les échecs sont signalés et aucune couleur n’est modifiée automatiquement.',
            ],
            'md' => [
                'purpose' => 'Instructions de design pour une interface construite avec ce système de couleurs sémantique. Utilisez uniquement les tokens listés ici et conservez leurs finalités.',
                'metadata_title' => 'Métadonnées',
                'language' => 'Langue : {language}',
                'strategy' => 'Stratégie : {strategy}',
                'target' => 'Cible de contraste : {target}',
                'seed_original' => 'Couleur source : {hex}',
                'seed_effective' => 'Couleur effective : {hex}',
                'seed_background' => 'Fond de composition : {hex}',
                'secondary' => 'Secondaire : {mode}',
                'secondary_auto' => 'automatique',
                'secondary_manual' => 'manuelle',
                'secondary_original' => 'Secondaire d’origine : {hex}',
                'secondary_effective' => 'Secondaire effective : {hex}',
                'secondary_background' => 'Fond de composition de la secondaire : {hex}',
                'versions' => 'Versions : état {state}, schéma {schema}, algorithme {algorithm}, suggestions {suggestions}, instantané {snapshot}',
                'origin' => 'Origine : {name} (palette publique).',
                'instructions_title' => 'Instructions pour l’IA',
                'instructions_intro' => 'Appliquez les tokens ci-dessous à l’interface. N’inventez pas de couleurs et ne codez pas en dur des valeurs hors de cette liste.',
                'instructions_semantic' => 'Utilisez les tokens sémantiques plutôt que les couleurs brutes de la palette : `background` pour la page, `surface` pour les panneaux, `on-*` pour le texte et les icônes.',
                'instructions_manual' => 'Les tokens marqués comme manuels ou verrouillés doivent rester exactement tels quels ; ne les recalculez pas.',
                'instructions_contrast' => 'Gardez les paires listées au-dessus de la cible {target} ; lorsqu’une paire échoue, signalez-le au lieu de changer les couleurs.',
                'instructions_map' => 'Associez les fonds à `background` et `surface`, le texte à `on-background`, `on-surface` et `on-surface-variant`, les boutons à `primary`, `secondary` et `accent` avec leurs états `-hover` et `-active`, les liens à `link`, `link-hover` et `link-active`, le feedback à `error`, `success`, `warning` et `info` avec leurs `on-*`, et les contours de focus à `focus-ring`.',
                'themes_title' => 'Thèmes',
                'themes_note' => 'Le thème clair est celui par défaut ; le sombre s’applique quand l’interface déclare data-theme="dark".',
                'tokens_title' => 'Tokens — {theme}',
                'columns' => [
                    'token' => 'Token',
                    'purpose' => 'Finalité',
                    'hex' => 'HEX',
                    'oklch' => 'OKLCH',
                    'source' => 'Source',
                    'locked' => 'Verrouillé',
                    'foreground' => 'Premier plan',
                    'background' => 'Arrière-plan',
                    'ratio' => 'Ratio',
                    'aa' => 'AA',
                    'aaa' => 'AAA',
                ],
                'locked_yes' => 'Oui',
                'locked_no' => 'Non',
                'rules_title' => 'Règles d’application',
                'rules' => [
                    'backgrounds' => 'Utilisez `background` pour la page et `surface` pour les cartes et les panneaux ; `surface-variant` regroupe le contenu imbriqué et `hairline` trace les bordures et les séparateurs.',
                    'text' => 'Utilisez `on-background` pour le texte principal, `on-surface` pour le texte sur les cartes et `on-surface-variant` pour les légendes, indications et métadonnées.',
                    'buttons' => 'Les boutons pleins utilisent `primary` ; les actions secondaires utilisent `secondary` ; la troisième couleur d’harmonie est `accent`. Les états hover et pressé utilisent les tokens `-hover` et `-active` correspondants, avec le texte dans le token `on-*` de cette famille.',
                    'links' => 'Les liens utilisent `link`, `link-hover` et `link-active` dans le contenu, jamais une autre couleur de marque directement.',
                    'feedback' => 'Les messages de feedback utilisent `error`, `success`, `warning` ou `info` comme fond, avec le texte et les icônes dans le token `on-*` correspondant.',
                    'focus' => 'Le focus clavier utilise `focus-ring` comme contour, jamais une couleur absente de cette liste.',
                ],
                'contrast_title' => 'Contraste — {theme}',
                'pass' => 'Réussi',
                'fail' => 'Échec',
                'generated_title' => 'Généré',
                'generation_note' => 'Généré dans le navigateur à partir de l’instantané affiché (état {state}, instantané {snapshot}). Aucune IA n’a été appelée et rien n’a été envoyé au serveur.',
            ],
        ],
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
    'share-text' => [
        'name' => 'Partager du Texte',
        'description' => 'Partagez des textes, des notes et des extraits de code avec des liens courts par ULID et une compatibilité totale avec Hastebin.',
        'intro' => 'Partagez des notes, du code et des textes avec des liens courts par ULID. Les visiteurs anonymes bénéficient d\'une rétention de 72 heures et les utilisateurs inscrits profitent d\'un stockage permanent avec une compatibilité totale avec l\'API Hastebin.',
        'keywords' => 'hastebin, pastebin, partager texte, snippet, code, notes, ulid, raw',
        'placeholder' => 'Collez ou saisissez votre texte ici...',
        'actions' => [
            'save' => 'Enregistrer',
            'new' => 'Nouveau',
            'duplicate' => 'Dupliquer & Modifier',
            'raw' => 'Texte Brut (Raw)',
            'copy_link' => 'Copier le Lien',
            'copied' => 'Lien copié !',
            'history' => 'Historique',
        ],
        'badges' => [
            'anonymous' => 'Expire dans 72h (anonyme)',
            'permanent' => 'Permanent (votre compte)',
            'draft' => 'Brouillon non enregistré',
        ],
        'dialog' => [
            'title' => 'Texte Partagé avec Succès',
            'description' => 'Votre texte a été enregistré avec un identifiant unique et est prêt pour le partage.',
            'url_label' => 'Lien de partage :',
            'raw_url_label' => 'Lien direct (Raw) :',
            'close' => 'Fermer',
        ],
        'history' => [
            'title' => 'Vos Textes Partagés',
            'empty' => 'Aucun texte partagé pour l\'instant.',
            'open' => 'Ouvrir',
            'delete' => 'Supprimer',
            'confirm_delete' => 'Êtes-vous sûr de vouloir supprimer définitivement ce texte ?',
        ],
        'languages' => [
            'auto' => 'Texte Brut',
            'json' => 'JSON',
            'sql' => 'SQL',
            'markdown' => 'Markdown',
        ],
        'errors' => [
            'empty_content' => 'Le contenu ne peut pas être vide.',
            'size_exceeded' => 'Le texte dépasse la limite maximale autorisée de :max Mo.',
            'save_failed' => 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.',
            'not_found' => 'Texte introuvable ou expiré.',
        ],
        'messages' => [
            'deleted' => 'Texte supprimé avec succès.',
        ],
    ],

    'badgeforge' => [
        'name' => 'BadgeForge',
        'description' => 'Créez des badges SVG pour vos README avec aperçu en temps réel et extraits prêts pour Markdown, HTML et reStructuredText.',
        'keywords' => 'badge, shields, svg, readme, markdown, ci, couverture',
        'intro' => 'Composez le badge, vérifiez l\'aperçu et copiez l\'extrait. L\'image est servie en SVG par ce site ; rien de ce que vous saisissez ici ne quitte le navigateur, hormis l\'URL que vous copiez.',

        'fields' => [
            'label' => 'Libellé',
            'message' => 'Message',
            'style' => 'Style',
            'color' => 'Couleur',
            'label_color' => 'Couleur du libellé',
            'logo' => 'Logo',
            'logo_color' => 'Couleur du logo',
            'logo_size' => 'Taille du logo',
            'logo_position' => 'Position du logo',
            'link' => 'Lien',
            'cache_seconds' => 'Cache (secondes)',
            'font' => 'Police',
            'format' => 'Format',
        ],

        'placeholders' => [
            'label' => 'build',
            'message' => 'passing',
            'color' => '#9f9f9f ou green',
            'label_color' => '#555555',
            'logo_color' => '#ffffff',
            'link' => 'https://exemple.com',
        ],

        'positions' => [
            'left' => 'Gauche',
            'right' => 'Droite',
        ],

        'logo_none' => 'Aucun logo',

        'fonts' => [
            'default' => 'Police par défaut',
        ],

        'formats' => [
            'svg' => 'SVG',
            'png' => 'PNG',
        ],

        'sections' => [
            'content' => 'Contenu',
            'colors' => 'Couleurs',
            'logo' => 'Logo',
            'advanced' => 'Avancé',
        ],

        'preview' => [
            'title' => 'Aperçu',
            'dark' => 'Fond sombre',
            'light' => 'Fond clair',
            'empty' => 'Saisissez un libellé et un message pour voir le badge.',
            'dimensions' => '{width} × {height} px',
        ],

        'snippets' => [
            'title' => 'Intégrer',
            'url' => 'URL',
            'markdown' => 'Markdown',
            'html' => 'HTML',
            'rst' => 'reStructuredText',
        ],

        'actions' => [
            'sample' => 'Exemple',
            'reset' => 'Réinitialiser',
        ],

        'management' => [
            'logos_button' => 'Mes logos',
            'presets_button' => 'Préréglages',
            'login_cta' => 'Connectez-vous pour envoyer vos propres logos et enregistrer des préréglages.',
            'login_action' => 'Se connecter',
            'upgrade_cta' => 'Votre forfait autorise {limit} logos personnalisés. Passez à un forfait supérieur pour des logos illimités.',
            'upgrade' => 'Améliorer l\'offre',
            'limit_note' => 'Logos personnalisés utilisés : {used} sur {limit}.',
            'unlimited_note' => 'Logos personnalisés utilisés : {used} (illimité).',
            'preset_limit_note' => 'Presets utilisés : {used} sur {limit}.',
            'preset_unlimited_note' => 'Presets utilisés : {used} (illimités).',
            'logo_created' => 'Logo enregistré.',
            'logo_updated' => 'Logo renommé.',
            'logo_deleted' => 'Logo supprimé.',
            'preset_created' => 'Préréglage enregistré.',
            'preset_updated' => 'Préréglage renommé.',
            'preset_deleted' => 'Préréglage supprimé.',
            'throttled' => 'Trop d’actions. Patientez un instant et réessayez.',
            'badge_created' => 'Badge créé.',
            'badge_updated' => 'Badge renommé.',
            'badge_credential_updated' => 'Identifiant mis à jour. Le badge sera généré à nouveau.',
            'badge_deleted' => 'Badge supprimé.',
            'logos_title' => 'Mes logos',
            'logos_description' => 'Fichiers SVG constitués uniquement de tracés. Le fichier n’est pas stocké ; seuls les tracés normalisés le sont.',
            'presets_title' => 'Préréglages',
            'presets_description' => 'Captures du formulaire du constructeur. Appliquez-en une pour remplir à nouveau le formulaire.',
            'empty_logos' => 'Vous n’avez encore envoyé aucun logo.',
            'empty_presets' => 'Vous n’avez encore enregistré aucun préréglage.',
            'upload' => 'Envoyer un SVG',
            'uploading' => 'Envoi...',
            'upload_hint' => 'SVG jusqu’à {size} Ko avec viewBox et formes vectorielles simples (path, rect, circle, ellipse, polygon, polyline, line et g ; sans texte, style, transform ni images intégrées).',
            'logo_file' => 'Fichier SVG',
            'name' => 'Nom',
            'name_placeholder' => 'Mon logo',
            'preset_name_placeholder' => 'Mon préréglage',
            'save' => 'Enregistrer',
            'cancel' => 'Annuler',
            'rename' => 'Renommer',
            'delete' => 'Supprimer',
            'delete_title' => 'Supprimer « {name} » ?',
            'delete_description' => 'Cette action est irréversible.',
            'preview_loading' => 'Chargement de l’aperçu...',
            'preview_error' => 'Impossible de charger l’aperçu.',
            'path_count' => '{count} tracés',
            'preview' => 'Aperçu',
            'save_preset' => 'Enregistrer comme préréglage',
            'apply_preset' => 'Appliquer',
            'preset_orphaned' => 'Le logo de ce préréglage n’existe plus. Il sera appliqué sans logo.',
            'open_in_builder' => 'Ouvrir dans le constructeur',
            'manage_in_dashboard' => 'Gérer dans le tableau de bord',
        ],

        'modes' => [
            'static' => 'Statique',
            'provider' => 'Fournisseurs',
            'custom' => 'Personnalisé',
        ],
        'provider' => [
            'sections' => [
                'metric' => 'Métrique',
            ],
            'fields' => [
                'provider' => 'Fournisseur',
                'definition' => 'Métrique',
            ],
            'invalid_param' => 'Vérifiez les paramètres et réessayez.',
            'example' => 'Exemple : {path}',
            'cache_note' => 'Le serveur contrôle le cache des badges de fournisseur.',
            'preview_invalid' => 'Renseignez les paramètres pour afficher le badge.',
            'params' => [
                'owner' => 'Propriétaire',
                'repo' => 'Dépôt',
                'package' => 'Paquet',
                'vendor' => 'Vendor',
                'namespace' => 'Espace de noms',
                'repository' => 'Dépôt',
            ],
            'metrics' => [
                'github_stars' => 'Étoiles GitHub',
                'github_forks' => 'Forks GitHub',
                'github_issues' => 'Issues ouvertes GitHub',
                'github_license' => 'Licence GitHub',
                'npm_v' => 'Version npm',
                'npm_dm' => 'Téléchargements mensuels npm',
                'packagist_dt' => 'Téléchargements Packagist',
                'packagist_v' => 'Version Packagist',
                'docker_pulls' => 'Pulls Docker Hub',
                'codecov_coverage' => 'Couverture Codecov',
                'coveralls_coverage' => 'Couverture Coveralls',
                'laravel_v' => 'Version Laravel',
                'laravel_dt' => 'Téléchargements Laravel',
                'php_v' => 'Version PHP',
                'python_v' => 'Version Python',
            ],
        ],
        'custom_providers' => [
            'title' => 'Fournisseurs personnalisés',
            'description' => 'Badges à partir de tout document JSON public que vous mappez vous-même. L’URL source reste sur le serveur ; l’image utilise un identifiant opaque.',
            'create_button' => 'Nouveau fournisseur',
            'readonly_note' => 'Les fournisseurs personnalisés sont disponibles sur les offres payantes. Les badges existants continuent de fonctionner et peuvent être copiés ou supprimés.',
            'upgrade' => 'Améliorer l’offre',
            'limit_reached' => 'Vous avez atteint la limite de {limit} fournisseurs personnalisés.',
            'empty' => 'Vous n’avez pas encore de fournisseur personnalisé.',
            'disabled' => 'Désactivé',
            'copy_svg' => 'Copier le SVG',
            'copy_png' => 'Copier le PNG',
            'delete_description' => 'L’URL publique cesse de fonctionner et la copie en cache est supprimée.',
            'dialog_create_title' => 'Créer un fournisseur personnalisé',
            'dialog_edit_title' => 'Modifier le fournisseur personnalisé',
            'dialog_description' => 'Pointez vers un document JSON public et choisissez les valeurs avec JSON Pointer.',
            'template_immutable' => 'Le modèle source est immuable : pour le changer, supprimez et recréez. Les slugs des métriques ne peuvent pas non plus être renommés.',
            'add_metric' => 'Ajouter une métrique',
            'remove_metric' => 'Retirer la métrique',
            'create_submit' => 'Créer le fournisseur',
            'update_submit' => 'Enregistrer les modifications',
            'fields' => [
                'name' => 'Nom',
                'url_template' => 'Modèle source',
                'path_params' => 'Paramètres du chemin',
                'example_path' => 'Chemin d’exemple',
                'metrics' => 'Métriques',
                'metric_slug' => 'Slug',
                'metric_label' => 'Libellé',
                'pointer' => 'JSON Pointer',
                'formatter' => 'Format',
            ],
            'hints' => [
                'url_template' => 'URL HTTPS avec les marqueurs que vous déclarez, par exemple https://api.example.com/{\'{\'}owner{\'}\'}/{\'{\'}repo{\'}\'}.',
                'template_note' => 'Les marqueurs du chemin utilisent {\'{\'}name{\'}\'}. Le modèle est validé à l’enregistrement.',
                'path_params' => 'Marqueurs séparés par des virgules, dans l’ordre du chemin.',
                'example_path' => 'Un chemin réel utilisé pour valider le mappage à l’enregistrement.',
                'pointer' => 'RFC 6901, par exemple /stargazers_count ou /items/0/score.',
            ],
            'formatters' => [
                'text' => 'Texte',
                'number' => 'Nombre',
                'percent' => 'Pourcentage',
            ],
            'builder' => [
                'sections' => [
                    'metric' => 'Métrique',
                ],
                'fields' => [
                    'provider' => 'Fournisseur',
                    'definition' => 'Métrique',
                ],
                'invalid_param' => 'Vérifiez les paramètres et réessayez.',
                'example' => 'Exemple : {path}',
                'cache_note' => 'Le serveur gère le cache des badges de fournisseurs personnalisés.',
                'preview_invalid' => 'Renseignez les paramètres pour voir le badge.',
            ],
        ],
        'personal' => [
            'create_button' => 'Badge personnel',
            'title' => 'Badges personnels',
            'description' => 'Badges publiés sous votre nom d\'utilisateur, prêts à intégrer. Les badges de fournisseur se mettent à jour automatiquement.',
            'dialog_title' => 'Créer un badge personnel',
            'dialog_description' => 'Enregistrez le badge actuel sous votre nom d\'utilisateur et partagez son URL.',
            'name' => 'Nom',
            'name_placeholder' => 'Mon badge',
            'metric_note' => 'Métrique : {metric}',
            'private' => 'Privé',
            'private_hint' => 'Nécessite un jeton d\'accès pour lire un dépôt ou un paquet privé.',
            'credential' => 'Jeton d\'accès personnel',
            'credential_placeholder' => 'Plus jamais affiché après l\'enregistrement',
            'credential_username' => 'Nom d\'utilisateur du compte',
            'credential_username_placeholder' => 'Nom d\'utilisateur Docker',
            'params_invalid' => 'Corrigez les paramètres avant d\'enregistrer.',
            'create' => 'Créer le badge',
            'creating' => 'Création...',
            'upgrade_cta' => 'Les badges personnels sont disponibles avec les forfaits payants. Passez à un forfait supérieur pour publier des badges sous votre nom.',
            'upgrade' => 'Améliorer l\'offre',
            'readonly_note' => 'Votre forfait ne permet plus de créer des badges personnels. Les badges existants continuent de fonctionner et peuvent être copiés ou supprimés.',
            'empty' => 'Vous n\'avez pas encore de badge personnel. Créez-en un dans le constructeur.',
            'copy_svg' => 'Copier le SVG',
            'copy_png' => 'Copier le PNG',
            'refresh' => 'Actualiser',
            'refreshing' => 'Actualisation...',
            'refresh_failed' => 'Impossible d\'actualiser le badge. Réessayez plus tard.',
            'change_credential' => 'Changer le jeton',
            'credential_set' => 'Jeton d\'accès enregistré',
            'credential_missing' => 'Jeton d\'accès manquant',
            'credential_not_required' => 'Élément public',
        ],
        'endpoint' => [
            'cta_title' => 'Badge pour endpoint JSON',
            'cta_description' => 'Transformez une URL JSON en badge. Enregistrez l\'endpoint dans le panneau de votre compte ; l\'URL source reste sur le serveur.',
            'cta_action' => 'Gérer les endpoints',
            'create_button' => 'Nouvel endpoint',
            'title' => 'Badges d\'endpoint JSON',
            'description' => 'Badges générés à partir d\'une URL JSON que vous enregistrez. L\'URL source est stockée sur le serveur et n\'est jamais exposée ; l\'image publique utilise un identifiant opaque.',
            'readonly_note' => 'Vérifiez votre e-mail pour enregistrer des endpoints. Les badges existants continuent de fonctionner.',
            'empty' => 'Vous n\'avez pas encore de badge d\'endpoint JSON.',
            'dialog_title' => 'Enregistrer un endpoint JSON',
            'dialog_description' => 'L\'endpoint doit renvoyer le contrat JSON de shields via HTTPS.',
            'name' => 'Nom',
            'name_placeholder' => 'Déploiements',
            'url' => 'URL source',
            'url_placeholder' => 'https://example.com/badge.json',
            'url_hint' => 'HTTPS uniquement, IP publique et sans redirection. L\'URL est stockée sur le serveur et ne revient jamais au navigateur.',
            'create' => 'Enregistrer l\'endpoint',
            'creating' => 'Enregistrement...',
            'copy_svg' => 'Copier le SVG',
            'copy_png' => 'Copier le PNG',
            'delete_description' => 'L\'URL publique cesse de fonctionner et la copie en cache est supprimée. L\'URL source n\'est supprimée nulle part ailleurs.',
        ],
        'social_note' => 'Le style social utilise toujours ses propres couleurs.',
    ],

    'data-format-converter' => [
        'name' => 'Data Format Converter',
        'description' => 'Convertissez JSON, YAML, TOML et XML entre eux, avec un avertissement pour ce que le format cible ne peut pas représenter.',
        'keywords' => 'convertir, json, yaml, toml, xml, format',
        'intro' => 'Choisissez les formats d\'entrée et de sortie et collez le document : la conversion s\'exécute dans votre navigateur, et ce que la cible ne peut pas représenter apparaît comme un avertissement. Rien de ce que vous collez ici n\'est envoyé au serveur.',

        'from' => 'Format d\'entrée',
        'to' => 'Format de sortie',
        'swap' => 'Inverser',
        'download_name' => 'document',
        'file_types' => 'JSON, YAML, TOML, XML ou TXT',

        'formats' => [
            'json' => 'JSON',
            'yaml' => 'YAML',
            'toml' => 'TOML',
            'xml' => 'XML',
        ],

        'errors' => [
            'toml-root' => 'Le TOML exige une table à la racine : un tableau ou une valeur seule n\'a pas sa place.',
            'toml-mixed-array' => 'Le TOML n\'accepte pas une liste à types mélangés (à {key}).',
            'toml-invalid' => 'Le TOML a refusé le document (à {key}).',
            'xml-name' => 'La clé {key} n\'est pas un nom d\'élément XML valide.',
            'xml-attribute' => 'L\'attribut {key} doit être une valeur simple ; les objets et les listes ne peuvent pas devenir un attribut.',
            'xml-invalid' => 'L\'XML n\'a pas pu être écrit.',
        ],

        'losses' => [
            'title' => 'Ce qui a été laissé de côté',
            'date-as-text' => 'Les dates TOML deviennent du texte dans la cible : {count}.',
            'binary-as-base64' => 'Les données binaires deviennent du texte Base64 : {count}.',
            'bigint-as-text' => 'Les entiers plus grands que JavaScript ne représente deviennent du texte : {count}.',
            'comments-dropped' => 'Les commentaires ne sont pas conservés : {count}.',
            'null-dropped' => 'Les valeurs nulles n\'existent pas en TOML et ont été supprimées : {count}.',
            'null-as-empty-element' => 'Les valeurs nulles deviennent un élément vide en XML : {count}.',
            'non-finite-as-null' => 'NaN et Infinity n\'existent pas en JSON et sont devenus null : {count}.',
            'non-finite-as-text' => 'NaN et Infinity deviennent du texte en XML : {count}.',
            'mixed-content-flattened' => 'Le texte mêlé aux éléments a été joint avant les enfants : {count}.',
            'root-wrapped' => 'Le document a reçu un élément racine <root> : {count}.',
            'value-dropped' => 'Les valeurs sans représentation dans l\'arbre ont été supprimées : {count}.',
        ],
    ],

    'diff' => [
        'name' => 'Diff',
        'description' => 'Comparez deux textes ligne par ligne ou deux documents JSON par chemin, avec surlignage et rapport prêt à copier.',
        'keywords' => 'comparer, différence, diff, texte, json',
        'intro' => 'Collez l\'original et le modifié et voyez où ils divergent. En mode texte, la comparaison se fait ligne par ligne, avec les régions surlignées ; en mode JSON, elle est sémantique, clé par clé, et l\'ordre des clés ne compte pas. Tout s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'left' => 'Original',
        'right' => 'Modifié',
        'open_left' => 'Ouvrir l\'original',
        'open_right' => 'Ouvrir le modifié',
        'previous' => 'Différence précédente',
        'next' => 'Différence suivante',
        'summary' => [
            'text' => 'Ajoutées : {added} · Supprimées : {removed}',
            'json' => 'Ajoutées : {added} · Supprimées : {removed} · Modifiées : {changed}',
        ],
        'truncated' => 'Diff grossier : au-delà du budget d\'éditions.',
        'no_changes' => 'Aucune différence.',
        'changes_title' => 'Chemins modifiés',
        'invalid_side' => 'Le côté {side} n\'est pas du JSON valide : {message}',
        'download_name' => 'diff',
        'file_types' => 'TXT, JSON, YAML, TOML ou XML',

        'modes' => [
            'text' => 'Texte',
            'json' => 'JSON',
        ],

        'report' => [
            'title' => 'Diff',
            'text' => 'Texte',
            'json' => 'JSON',
            'original' => 'original',
            'modified' => 'modifié',
            'added' => 'Ajoutées',
            'removed' => 'Supprimées',
            'changed' => 'Modifiées',
            'no_changes' => 'Aucune différence.',
            'truncated' => 'Diff grossier : au-delà du budget d\'éditions.',
            'invalid' => 'JSON invalide',
            'line' => 'ligne',
            'column' => 'colonne',
        ],
    ],

    'cron-explainer' => [
        'name' => 'Explorateur de cron',
        'description' => 'Comprenez une expression cron champ par champ et voyez les dix prochaines exécutions dans le fuseau de votre navigateur.',
        'keywords' => 'cron, crontab, planification, prochaine exécution, horaire',
        'intro' => 'Collez l\'expression à cinq champs et voyez ce que chaque champ signifie et quand elle va s\'exécuter, dans le fuseau de votre navigateur. Les macros raccourcies (daily, hourly, monthly et les autres) sont acceptées ; les secondes et les extensions Quartz (L, W, #, ?) ne le sont pas. Tout s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'expression' => 'Expression',
        'hint' => 'Cinq champs : minute, heure, jour du mois, mois et jour de la semaine. Ex. : 0 9 * * 1-5',
        'zone' => 'Fuseau : {zone}',
        'exhausted' => 'L\'expression est valide mais ne tombe sur aucune date dans les prochaines années (comme le 30 février).',

        'panes' => [
            'expression' => 'Expression',
            'runs' => 'Prochaines exécutions',
        ],

        'states' => [
            'empty' => 'Collez une expression cron pour voir l\'explication et les prochaines exécutions.',
            'invalid' => 'Corrigez l\'expression pour voir les prochaines exécutions.',
        ],

        'status' => [
            'valid' => '{count} prochaines exécutions dans le fuseau {zone}.',
        ],

        'table' => [
            'field' => 'Champ',
            'value' => 'Valeur',
            'meaning' => 'Signification',
        ],

        'fields' => [
            'minute' => 'Minute',
            'hour' => 'Heure',
            'day_of_month' => 'Jour du mois',
            'month' => 'Mois',
            'day_of_week' => 'Jour de la semaine',
        ],

        'names' => [
            'months' => [
                1 => 'janvier',
                2 => 'février',
                3 => 'mars',
                4 => 'avril',
                5 => 'mai',
                6 => 'juin',
                7 => 'juillet',
                8 => 'août',
                9 => 'septembre',
                10 => 'octobre',
                11 => 'novembre',
                12 => 'décembre',
            ],
            'days' => [
                0 => 'dimanche',
                1 => 'lundi',
                2 => 'mardi',
                3 => 'mercredi',
                4 => 'jeudi',
                5 => 'vendredi',
                6 => 'samedi',
            ],
        ],

        'meanings' => [
            'any' => [
                'minute' => 'Chaque minute',
                'hour' => 'Chaque heure',
                'day_of_month' => 'Chaque jour du mois',
                'month' => 'Chaque mois',
                'day_of_week' => 'Chaque jour de la semaine',
            ],
            'step_star' => [
                'minute' => 'Toutes les {step} minutes',
                'hour' => 'Toutes les {step} heures',
                'day_of_month' => 'Tous les {step} jours',
                'month' => 'Tous les {step} mois',
                'day_of_week' => 'Tous les {step} jours de la semaine',
            ],
            'step_range' => [
                'minute' => 'Toutes les {step} minutes, de {from} à {to}',
                'hour' => 'Toutes les {step} heures, de {from} à {to}',
                'day_of_month' => 'Tous les {step} jours, du jour {from} au {to}',
                'month' => 'Tous les {step} mois, de {from} à {to}',
                'day_of_week' => 'Tous les {step} jours de la semaine, de {from} à {to}',
            ],
            'range' => 'De {from} à {to}',
            'at' => 'À {values}',
        ],

        'errors' => [
            'empty' => 'Saisissez une expression cron.',
            'field_count' => 'Une expression cron a cinq champs ; celle-ci en a {token}.',
            'unknown_macro' => 'Macro inconnue : {token}.',
            'macro_with_fields' => 'La macro {token} se suffit à elle-même ; n\'ajoutez pas de champs après.',
            'unknown_name' => 'Nom inconnu dans {field} : {token}.',
            'invalid_value' => 'Valeur hors de la plage dans {field} : {token}.',
            'invalid_range' => 'Plage invalide dans {field} : {token}.',
            'invalid_step' => 'Pas invalide dans {field} : {token}.',
            'invalid_syntax' => 'Impossible de lire {field} : {token}.',
        ],

        'report' => [
            'zone' => 'Fuseau',
            'runs' => 'Prochaines exécutions',
            'empty' => 'Aucune exécution dans l\'horizon.',
        ],
    ],

    'curl-to-code' => [
        'name' => 'cURL vers code',
        'description' => 'Convertissez une commande curl en code Laravel, fetch ou axios, avec un avertissement sur ce qui a été laissé de côté.',
        'keywords' => 'curl, http, requête, fetch, axios, laravel, api',
        'intro' => 'Collez une commande curl et obtenez la même requête en Laravel, fetch ou axios. Le cœur est couvert : méthode, en-têtes, corps, URL, authentification basique et les options de ligne de commande les plus courantes ; ce qui n\'est pas pris en charge apparaît dans l\'avertissement, jamais silencieusement. Tout s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'panes' => [
            'command' => 'Commande',
            'code' => 'Code',
        ],

        'tabs' => [
            'laravel' => 'Laravel',
            'fetch' => 'fetch',
            'axios' => 'axios',
        ],

        'unsupported' => 'Options non prises en charge (laissées de côté) : {options}.',
        'states' => [
            'empty' => 'Collez une commande curl pour voir le code.',
        ],

        'status' => [
            'ready' => '{method} {url}',
        ],

        'warnings' => [
            'credential_in_output' => 'Les identifiants de {user} ont fini dans le code. Préférez des variables d\'environnement.',
            'extra_url' => 'L\'URL {url} a été ignorée : la première l\'emporte.',
            'header_without_colon' => 'En-tête sans deux-points ignoré : {token}.',
            'data_from_file' => 'Lire le corps depuis un fichier n\'est pas pris en charge : {token}.',
        ],

        'errors' => [
            'empty' => 'Collez une commande curl.',
            'not_curl' => 'La commande doit commencer par curl.',
            'shell' => 'La commande n\'est pas fermée : {detail}',
            'missing_value' => 'L\'option {option} a besoin d\'une valeur.',
            'missing_url' => 'La commande n\'a pas d\'URL.',
        ],

        'shell_details' => [
            'dangling_escape' => 'il y a une barre oblique inversée en fin de ligne sans continuation.',
            'unterminated_single' => 'il y a des guillemets simples non fermés.',
            'unterminated_double' => 'il y a des guillemets doubles non fermés.',
        ],
    ],

    'env-example' => [
        'name' => '.env vers exemple',
        'description' => 'Transformez un .env en .env.example : seules les valeurs des clés sensibles sont retirées ; l\'ordre, les commentaires et le reste ne changent pas.',
        'keywords' => 'env, dotenv, environnement, exemple, secrets, config',
        'intro' => 'Collez ou ouvrez votre .env et obtenez un .env.example qui garde l\'ordre, les commentaires et les regroupements, avec la valeur des clés sensibles vidée. Une clé est considérée comme sensible d\'après les segments de son nom, ou lorsque la valeur est une URL avec un mot de passe. Tout s\'exécute dans votre navigateur : rien de ce que vous collez ici n\'est envoyé au serveur.',

        'panes' => [
            'source' => 'Votre .env',
            'example' => '.env.example',
        ],

        'file_types' => 'Fichier .env',
        'states' => [
            'empty' => 'Collez ou ouvrez un fichier .env pour générer le .env.example.',
        ],

        'notices' => [
            'masked' => 'Clés dont la valeur a été retirée',
            'masked_key' => '{key}, ligne {line}',
            'invalid' => 'Lignes conservées sans forme clé et valeur',
            'invalid_line' => 'Ligne {line}',
        ],

        'status' => [
            'summary' => 'Retirées : {masked} · Invalides : {invalid}',
        ],
    ],
];
