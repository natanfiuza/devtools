<?php

/*
| Sino e preferencias de notificacao da conta (F7, ADR-142).
|
| Os textos de `kinds` descrevem o facto sem repetir o corpo do comentario: o
| aviso leva ao comentario, nao o reproduz.
*/

return [
    'bell' => [
        'label' => 'Notificações',
        'unread' => '{count} não lidas',
    ],

    'title' => 'Notificações',
    'description' => 'Tudo o que aconteceu nas suas paletas e no seu perfil.',
    'empty' => 'Nenhuma notificação ainda.',
    'mark_read' => 'Marcar como lida',
    'mark_all_read' => 'Marcar todas como lidas',
    'unread_badge' => 'Nova',
    'someone' => 'Alguém',

    'kinds' => [
        'palette_liked' => ':actor gostou da sua paleta ":palette".',
        'palette_liked_anonymous' => 'Alguém gostou da sua paleta ":palette".',
        'user_followed' => ':actor começou a seguir-te.',
        'palette_comment_approved' => ':actor comentou na sua paleta ":palette".',
        'comment_reply_approved' => ':actor respondeu ao seu comentário em ":palette".',
        'unknown' => 'Tem uma nova notificação.',
    ],

    'preferences' => [
        'title' => 'Notificações por e-mail',
        'description' => 'O sino mostra sempre tudo. Escolha que tipos também chegam por e-mail.',
        'email_on_like' => 'Gostos nas minhas paletas',
        'email_on_follow' => 'Novos seguidores',
        'email_on_comment' => 'Comentários nas minhas paletas',
        'email_on_reply' => 'Respostas aos meus comentários',
        'saved' => 'Preferências guardadas.',
    ],

    'pagination' => [
        'previous' => 'Anterior',
        'next' => 'Seguinte',
        'status' => 'Página {page} de {pages}',
    ],
];
