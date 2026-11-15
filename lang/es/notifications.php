<?php

/*
| Sino e preferencias de notificacao da conta (F7, ADR-142).
|
| Os textos de `kinds` descrevem o fato sem repetir o corpo do comentario: o
| aviso leva ao comentario, nao o reproduz.
*/

return [
    'bell' => [
        'label' => 'Notificaciones',
        'unread' => '{count} sin leer',
    ],

    'title' => 'Notificaciones',
    'description' => 'Todo lo que pasó en tus paletas y tu perfil.',
    'empty' => 'Aún no hay notificaciones.',
    'mark_read' => 'Marcar como leída',
    'mark_all_read' => 'Marcar todas como leídas',
    'unread_badge' => 'Nueva',
    'someone' => 'Alguien',

    // Interpolacion del vue-i18n (`{nombre}`), nunca la de Laravel (`:nombre`).
    'kinds' => [
        'palette_liked' => 'A {actor} le gustó tu paleta "{palette}".',
        'palette_liked_anonymous' => 'A alguien le gustó tu paleta "{palette}".',
        'user_followed' => '{actor} empezó a seguirte.',
        'palette_comment_approved' => '{actor} comentó en tu paleta "{palette}".',
        'comment_reply_approved' => '{actor} respondió a tu comentario en "{palette}".',
        'unknown' => 'Tienes una nueva notificación.',
    ],

    'preferences' => [
        'title' => 'Notificaciones por correo',
        'description' => 'La campana siempre muestra todo. Elige qué tipos también llegan por correo.',
        'email_on_like' => 'Me gusta en mis paletas',
        'email_on_follow' => 'Nuevos seguidores',
        'email_on_comment' => 'Comentarios en mis paletas',
        'email_on_reply' => 'Respuestas a mis comentarios',
        'saved' => 'Preferencias guardadas.',
    ],

    'pagination' => [
        'previous' => 'Anterior',
        'next' => 'Siguiente',
        'status' => 'Página {page} de {pages}',
    ],
];
