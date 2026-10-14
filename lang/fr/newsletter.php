<?php

/*
| Newsletter com double opt-in (ADR-024). Namespace "newsletter" do vue-i18n.
|
| As mensagens que dependem de parametro (invalid_link, subscribe.*) sao
| montadas no servidor e chegam prontas ao front via flash.
*/

return [
    'form' => [
        'label' => 'Newsletter',
        'description' => 'Les nouveautés des outils, de temps en temps. Pas de spam.',
        'email' => 'E-mail',
        'email_placeholder' => 'vous@exemple.com',
        'submit' => 'S’abonner',
        'check_email' => 'Presque terminé : confirmez votre abonnement dans l’e-mail que nous venons d’envoyer.',
        'already_confirmed' => 'Vous êtes déjà abonné.',
    ],

    'confirm' => [
        'title_pending' => 'Confirmez votre abonnement',
        'body_pending' => 'Un clic et c’est fait : nous confirmons votre e-mail et vous recevez la newsletter.',
        'action' => 'Confirmer l’abonnement',
        'title_confirmed' => 'Abonnement confirmé',
        'body_confirmed' => 'Tout est prêt : vous êtes sur la liste. Vous pouvez partir quand vous voulez, via le lien de n’importe quel e-mail.',
        'title_invalid' => 'Lien expiré',
        'body_invalid' => 'Ce lien n’est plus valable. Abonnez-vous à nouveau depuis le pied de page du site.',
    ],

    'unsubscribe' => [
        'title_pending' => 'Se désabonner',
        'body_pending' => 'Vous allez nous manquer. Confirmez pour ne plus recevoir la newsletter.',
        'action' => 'Se désabonner',
        'title_unsubscribed' => 'Désabonnement confirmé',
        'body_unsubscribed' => 'C’est fait : vous ne recevez plus la newsletter. Si vous changez d’avis, il suffit de vous réabonner.',
        'title_invalid' => 'Lien expiré',
        'body_invalid' => 'Ce lien n’est plus valable : il n’y a rien à désabonner.',
    ],

    'invalid_link' => 'Ce lien n’est plus valable.',
    'back_home' => 'Retour au site',
];
