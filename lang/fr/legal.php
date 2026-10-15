<?php

/*
| Paginas legais: Politica de Privacidade e Termos de Uso (namespace "legal").
|
| O e-mail de contato e a data da revisao vem de config/legal.php (nao ficam
| presos aqui). ":date" e montado no servidor (Laravel); "{app}" vem do Vue (vue-i18n).
*/

return [
    'updated' => 'Dernière mise à jour : :date',
    'contact' => 'Contact',

    'privacy' => [
        'title' => 'Politique de confidentialité',
        'intro' => 'Cette page explique quelles données {app} conserve, pourquoi et pendant combien de temps. En bref : les outils fonctionnent dans votre navigateur et nous ne gardons que ce dont le compte a besoin.',
        'sections' => [
            'data' => [
                'title' => 'Ce que nous conservons',
                'body' => 'Le minimum pour un compte : nom, e-mail, mot de passe (sous forme de hash) et la langue que vous utilisez. Si vous vous connectez avec Google ou GitHub, nous conservons l’identifiant du compte chez le fournisseur et votre e-mail — jamais votre mot de passe chez lui. Si vous vous abonnez à la newsletter, nous conservons l’e-mail et la langue de l’abonnement.',
            ],
            'purpose' => [
                'title' => 'Ce que nous en faisons',
                'body' => 'Faire fonctionner votre compte, envoyer les e-mails que vous avez demandés (réinitialisation du mot de passe, confirmation d’e-mail, newsletter) et protéger le site contre les abus.',
            ],
            'browser' => [
                'title' => 'Les outils fonctionnent dans votre navigateur',
                'body' => 'Ce que vous collez dans un outil est traité dans votre navigateur et n’est pas envoyé à nos serveurs. Les données de compte ci-dessus sont la seule chose que nous conservons.',
            ],
            'sharing' => [
                'title' => 'Avec qui nous partageons',
                'body' => 'Nous ne vendons ni ne louons vos données. Nous partageons uniquement avec ce qui fait tourner le site : hébergement et envoi d’e-mails, plus Google ou GitHub lorsque vous choisissez de vous connecter avec eux. Si vous acceptez la mesure, les pages vues vont aussi à Google Analytics.',
            ],
            'cookies' => [
                'title' => 'Cookies',
                'body' => 'Les cookies essentiels font fonctionner le site : session, langue et préférence de thème. Les cookies de mesure (Google Analytics) sont facultatifs et ne sont déposés qu’après votre acceptation ; ce choix peut être modifié à tout moment dans « Cookies », au pied de page. Aucun cookie publicitaire.',
            ],
            'retention' => [
                'title' => 'Pendant combien de temps',
                'body' => 'Les données du compte restent tant que le compte existe et disparaissent lorsque vous supprimez le compte. Les données de la newsletter restent jusqu’à votre désabonnement.',
            ],
            'rights' => [
                'title' => 'Vos droits',
                'body' => 'Vous pouvez consulter et modifier vos données dans les paramètres du compte, et demander une copie ou la suppression par e-mail. Nous répondons dans le délai légal.',
            ],
            'security' => [
                'title' => 'Sécurité',
                'body' => 'Les mots de passe sont stockés sous forme de hash, le site fonctionne en HTTPS et l’accès au serveur est restreint. Aucun système n’est parfait : si quelque chose arrive, nous vous prévenons.',
            ],
            'changes' => [
                'title' => 'Modifications de cette page',
                'body' => 'Ce texte peut être mis à jour au fil de l’évolution du site. La date de la dernière révision figure toujours en haut de la page.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Conditions d’utilisation',
        'intro' => 'Les règles d’utilisation de {app}. En naviguant ou en créant un compte, vous les acceptez.',
        'sections' => [
            'service' => [
                'title' => 'Le service',
                'body' => '{app} est un ensemble d’outils de développement qui fonctionnent dans votre navigateur, fournis tels quels et gratuitement, pour un usage personnel ou professionnel.',
            ],
            'account' => [
                'title' => 'Votre compte',
                'body' => 'Vous êtes responsable de votre compte : gardez votre mot de passe et votre connexion sociale en sécurité. Les comptes créés avec Google ou GitHub n’ont pas de mot de passe et sont accessibles via le fournisseur.',
            ],
            'use' => [
                'title' => 'Usage acceptable',
                'body' => 'Utilisez les outils à des fins licites. N’attaquez pas, ne surchargez pas et n’aspirez pas le service, et ne l’utilisez pas pour casser des systèmes tiers. Les résultats générés (comme le SQL) sont une aide : la décision finale vous appartient.',
            ],
            'content' => [
                'title' => 'Contenu',
                'body' => 'Ce que vous collez dans les outils reste à vous. Le site, la marque et le code restent à nous.',
            ],
            'availability' => [
                'title' => 'Disponibilité',
                'body' => 'Nous essayons de garder le site en ligne, sans le promettre. Des fonctionnalités peuvent changer ou être arrêtées.',
            ],
            'warranty' => [
                'title' => 'Sans garantie',
                'body' => 'Le site est fourni sans garantie. Nous ne sommes pas responsables des dommages ou pertes résultant de l’utilisation des résultats générés.',
            ],
            'changes' => [
                'title' => 'Modifications des conditions',
                'body' => 'Ces conditions peuvent changer. Continuer à utiliser le site après une modification vaut acceptation de la nouvelle version.',
            ],
            'law' => [
                'title' => 'Droit applicable',
                'body' => 'Ces conditions sont régies par le droit brésilien, et les tribunaux brésiliens sont compétents pour tout litige.',
            ],
        ],
    ],
];
