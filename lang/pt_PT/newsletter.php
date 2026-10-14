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
        'description' => 'Novidades das ferramentas, de vez em quando. Sem spam.',
        'email' => 'E-mail',
        'email_placeholder' => 'voce@exemplo.com',
        'submit' => 'Subscrever',
        'check_email' => 'Falta pouco: confirme a subscrição no e-mail que acabámos de enviar.',
        'already_confirmed' => 'Já está subscrito.',
    ],

    'confirm' => [
        'title_pending' => 'Confirme a sua subscrição',
        'body_pending' => 'Um clique e está pronto: confirmamos o seu e-mail e começa a receber a newsletter.',
        'action' => 'Confirmar subscrição',
        'title_confirmed' => 'Subscrição confirmada',
        'body_confirmed' => 'Está tudo certo: está na lista. Pode sair quando quiser, pela ligação de qualquer e-mail.',
        'title_invalid' => 'Ligação expirada',
        'body_invalid' => 'Esta ligação já não é válida. Subscreva de novo pelo rodapé do site.',
    ],

    'unsubscribe' => [
        'title_pending' => 'Cancelar subscrição',
        'body_pending' => 'Vamos sentir a sua falta. Confirme para deixar de receber a newsletter.',
        'action' => 'Cancelar subscrição',
        'title_unsubscribed' => 'Subscrição cancelada',
        'body_unsubscribed' => 'Pronto: já não recebe a newsletter. Se mudar de ideias, basta subscrever de novo.',
        'title_invalid' => 'Ligação expirada',
        'body_invalid' => 'Esta ligação já não é válida, por isso não há nada a cancelar.',
    ],

    'invalid_link' => 'Esta ligação já não é válida.',
    'back_home' => 'Voltar ao site',
];
