<?php

/*
| Sino e preferencias de notificacao da conta (F7, ADR-142).
|
| Os textos de `kinds` descrevem o fato sem repetir o corpo do comentario: o
| aviso leva ao comentario, nao o reproduz.
*/

return [
    'bell' => [
        'label' => 'Notifications',
        'unread' => '{count} non lues',
    ],

    'title' => 'Notifications',
    'description' => 'Tout ce qui s\'est passé sur vos palettes et votre profil.',
    'empty' => 'Aucune notification pour le moment.',
    'mark_read' => 'Marquer comme lue',
    'mark_all_read' => 'Tout marquer comme lu',
    'unread_badge' => 'Nouvelle',
    'someone' => 'Quelqu\'un',

    'kinds' => [
        'palette_liked' => ':actor a aimé votre palette « :palette ».',
        'palette_liked_anonymous' => 'Quelqu\'un a aimé votre palette « :palette ».',
        'user_followed' => ':actor a commencé à vous suivre.',
        'palette_comment_approved' => ':actor a commenté votre palette « :palette ».',
        'comment_reply_approved' => ':actor a répondu à votre commentaire sur « :palette ».',
        'unknown' => 'Vous avez une nouvelle notification.',
    ],

    'preferences' => [
        'title' => 'Notifications par e-mail',
        'description' => 'La cloche montre toujours tout. Choisissez les types qui arrivent aussi par e-mail.',
        'email_on_like' => 'Mentions J\'aime sur mes palettes',
        'email_on_follow' => 'Nouveaux followers',
        'email_on_comment' => 'Commentaires sur mes palettes',
        'email_on_reply' => 'Réponses à mes commentaires',
        'saved' => 'Préférences enregistrées.',
    ],

    'pagination' => [
        'previous' => 'Précédent',
        'next' => 'Suivant',
        'status' => 'Page {page} sur {pages}',
    ],
];
