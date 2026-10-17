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
        'greeting' => 'Abonnement à la newsletter de :app',
        'intro' => 'Un clic suffit pour commencer à recevoir la newsletter : confirmez simplement que cet e-mail est le vôtre.',
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
];
