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
        'unread' => '{count} unread',
    ],

    'title' => 'Notifications',
    'description' => 'Everything that happened on your palettes and profile.',
    'empty' => 'No notifications yet.',
    'mark_read' => 'Mark as read',
    'mark_all_read' => 'Mark all as read',
    'unread_badge' => 'New',
    'someone' => 'Someone',

    // vue-i18n interpolation (`{name}`), never Laravel's (`:name`).
    'kinds' => [
        'palette_liked' => '{actor} liked your palette "{palette}".',
        'palette_liked_anonymous' => 'Someone liked your palette "{palette}".',
        'user_followed' => '{actor} started following you.',
        'palette_comment_approved' => '{actor} commented on your palette "{palette}".',
        'comment_reply_approved' => '{actor} replied to your comment on "{palette}".',
        'unknown' => 'You have a new notification.',
    ],

    'preferences' => [
        'title' => 'Email notifications',
        'description' => 'The bell always shows everything. Choose which types also reach your email.',
        'email_on_like' => 'Likes on my palettes',
        'email_on_follow' => 'New followers',
        'email_on_comment' => 'Comments on my palettes',
        'email_on_reply' => 'Replies to my comments',
        'saved' => 'Preferences saved.',
    ],

    'pagination' => [
        'previous' => 'Previous',
        'next' => 'Next',
        'status' => 'Page {page} of {pages}',
    ],
];
