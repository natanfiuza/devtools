<?php

/*
| Messages et textes de la page publique de contact (Contactez-nous).
*/

return [
    'meta' => [
        'title' => 'Contact',
        'description' => 'Contactez l\'équipe de devtools. Envoyez vos questions, suggestions, signalez des bugs ou posez des questions sur les dons.',
    ],

    'title' => 'Contactez-nous',
    'subtitle' => 'Vous avez une question, une suggestion ou rencontré un problème ? Envoyez un message à notre équipe.',

    'fields' => [
        'name' => 'Nom',
        'name_placeholder' => 'Votre nom complet ou comment vous préférez être appelé',
        'email' => 'E-mail',
        'email_placeholder' => "votre{'@'}email.com",
        'email_hint' => 'Nous utiliserons cet e-mail exclusivement pour répondre à votre message.',
        'subject' => 'Objet',
        'subject_placeholder' => 'Sélectionnez le motif du contact',
        'message' => 'Message',
        'message_placeholder' => 'Décrivez votre question, suggestion ou signalement avec autant de détails que possible…',
        'characters' => '{count} / {max} caractères',
    ],

    'subjects' => [
        'general' => 'Question générale',
        'donation' => 'Problème avec un don / paiement',
        'feedback' => 'Suggestion ou amélioration',
        'bug' => 'Signaler un bug',
        'other' => 'Autre sujet',
    ],

    'submit' => 'Envoyer le message',
    'submitting' => 'Envoi en cours…',

    'success_title' => 'Message envoyé avec succès !',
    'success_body' => 'Nous avons bien reçu votre message et vous répondrons dès que possible à l\'adresse e-mail indiquée.',
    'send_another' => 'Envoyer un autre message',

    'sent_toast' => 'Votre message a été envoyé avec succès !',
    'throttle' => 'Trop de messages envoyés en peu de temps. Veuillez patienter :seconds secondes avant de réessayer.',
];
