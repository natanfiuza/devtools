<?php

/*
| Assuntos e corpos dos e-mails transacionais.
|
| Namespace SO do servidor: nao entra em config/locales.php e nao vai para o
| front. Por isso a interpolacao aqui e sempre ":param" (Laravel).
|
| Os e-mails saem no idioma da conta (HasLocalePreference no User), nao no
| idioma de quem pediu.
*/

return [
    'template' => [
        'greeting' => 'Bonjour !',
        'error_greeting' => 'Oups !',
        'salutation' => 'Cordialement,',
        'subcopy' => 'Si vous ne parvenez pas à cliquer sur le bouton « :actionText », copiez et collez l’adresse ci-dessous dans votre navigateur :',
        'rights' => 'Tous droits réservés.',
    ],

    'reset' => [
        'subject' => 'Réinitialiser votre mot de passe',
        'intro' => 'Vous avez demandé à réinitialiser le mot de passe de votre compte :app.',
        'action' => 'Créer un nouveau mot de passe',
        'expire' => 'Ce lien est valable :minutes minutes.',
        'ignore' => 'Si ce n’est pas vous, ignorez cet e-mail : votre mot de passe reste inchangé.',
    ],

    'verify' => [
        'subject' => 'Confirmez votre e-mail',
        'intro' => 'Dernière étape : confirmez que cet e-mail est bien le vôtre pour activer votre compte :app.',
        'action' => 'Confirmer l’e-mail',
        'ignore' => 'Si vous n’avez pas créé le compte, ignorez cet e-mail.',
    ],

    'newsletter' => [
        'subject' => 'Confirmez votre abonnement à la newsletter',
        'intro' => 'Un clic suffit pour commencer à recevoir la newsletter de :app : confirmez simplement que cet e-mail est le vôtre.',
        'action' => 'Confirmer l’abonnement',
        'ignore' => 'Si ce n’est pas vous, ignorez cet e-mail : rien ne sera envoyé.',
    ],

    'intent' => [
        'subject' => '[:app] Nouvelle intention d’abonnement (@:username)',
        'intro' => 'Nouvelle intention d’abonnement Pro enregistrée par :name (:email).',
        'details' => 'Forfait : :plan | Utilisateur : :username | Téléphone : :phone | Document : :document | Paiement : :method',
        'notes' => 'Remarques : :notes',
        'action' => 'Ouvrir les intentions dans l’administration',
    ],

    'payment' => [
        'confirmed' => [
            'subject' => 'Paiement confirmé',
            'greeting' => 'Bonjour, :name !',
            'intro' => 'Nous avons bien reçu votre paiement sur :app.',
            'amount' => 'Montant : :amount',
            'method' => 'Moyen de paiement : :method',
            'paid_at' => 'Date du paiement : :date',
            'plan' => 'Forfait : :plan',
            'period_end' => 'Votre abonnement est valable jusqu’au :date.',
            'action' => 'Voir les factures',
        ],
        'refunded' => [
            'subject' => 'Paiement remboursé',
            'greeting' => 'Bonjour, :name !',
            'intro' => 'Votre paiement sur :app a été remboursé.',
            'amount' => 'Montant remboursé : :amount',
            'method' => 'Moyen de paiement d’origine : :method',
            'paid_at' => 'Date du paiement d’origine : :date',
            'refunded_at' => 'Date du remboursement : :date',
            'plan' => 'Forfait : :plan',
            'subscription_canceled' => 'L’abonnement lié à ce paiement est terminé.',
            'action' => 'Voir les factures',
        ],
        'renewal_failed' => [
            'subject' => 'Nous n’avons pas pu renouveler votre abonnement',
            'greeting' => 'Bonjour, :name !',
            'intro' => 'Le prélèvement automatique de votre abonnement sur :app n’a pas été approuvé par la carte.',
            'plan' => 'Forfait : :plan',
            'period_end' => 'Votre accès reste actif jusqu’au :date.',
            'action' => 'Mettre à jour le paiement',
        ],
    ],

    'donation' => [
        'receipt' => [
            'subject' => 'Nous avons reçu votre don',
            'greeting' => 'Bonjour, :name !',
            'greeting_anonymous' => 'Bonjour !',
            'intro' => 'Merci de soutenir :app. Votre don est confirmé.',
            'amount' => 'Montant donné : :amount',
            'paid_at' => 'Confirmé le : :date',
            'note' => 'Un don ne donne pas accès aux forfaits et ne consomme pas de quota : c\'est un soutien au projet.',
        ],
    ],

    'account_purged' => [
        'subject' => 'Votre compte sur :app a été définitivement supprimé',
        'greeting' => 'Bonjour, :name !',
        'greeting_anonymous' => 'Bonjour !',
        'intro' => 'Le délai de grâce de 30 jours est terminé et votre compte sur :app a été définitivement supprimé.',
        'anonymized' => 'Toutes vos données personnelles identifiables ont été supprimées et votre compte a été anonymisé conformément aux lois sur la protection des données.',
        'attached' => 'Vous trouverez en pièce jointe une copie au format JSON contenant toutes les données exportées de votre compte avant son anonymisation.',
    ],
];
