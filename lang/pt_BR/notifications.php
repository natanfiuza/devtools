<?php

/*
| Sino e preferencias de notificacao da conta (F7, ADR-142).
|
| Os textos de `kinds` descrevem o fato sem repetir o corpo do comentario: o
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
        'palette_liked' => ':actor curtiu sua paleta ":palette".',
        'palette_liked_anonymous' => 'Alguém curtiu sua paleta ":palette".',
        'user_followed' => ':actor começou a seguir você.',
        'palette_comment_approved' => ':actor comentou na sua paleta ":palette".',
        'comment_reply_approved' => ':actor respondeu ao seu comentário em ":palette".',
        'unknown' => 'Você tem uma nova notificação.',
    ],

    'preferences' => [
        'title' => 'Notificações por e-mail',
        'description' => 'O sino sempre mostra tudo. Escolha quais tipos também chegam por e-mail.',
        'email_on_like' => 'Curtidas nas minhas paletas',
        'email_on_follow' => 'Novos seguidores',
        'email_on_comment' => 'Comentários nas minhas paletas',
        'email_on_reply' => 'Respostas aos meus comentários',
        'saved' => 'Preferências salvas.',
    ],

    'pagination' => [
        'previous' => 'Anterior',
        'next' => 'Próxima',
        'status' => 'Página {page} de {pages}',
    ],
];
