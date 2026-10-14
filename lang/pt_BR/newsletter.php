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
        'submit' => 'Inscrever',
        'check_email' => 'Quase lá: confirme a inscrição no e-mail que acabamos de enviar.',
        'already_confirmed' => 'Você já está inscrito.',
    ],

    'confirm' => [
        'title_pending' => 'Confirme sua inscrição',
        'body_pending' => 'Um clique e pronto: confirmamos seu e-mail e você passa a receber a newsletter.',
        'action' => 'Confirmar inscrição',
        'title_confirmed' => 'Inscrição confirmada',
        'body_confirmed' => 'Tudo certo: você está na lista. Dá para sair quando quiser, pelo link de qualquer e-mail.',
        'title_invalid' => 'Link expirado',
        'body_invalid' => 'Esse link não vale mais. Inscreva-se de novo pelo rodapé do site.',
    ],

    'unsubscribe' => [
        'title_pending' => 'Descadastrar',
        'body_pending' => 'Vamos sentir sua falta. Confirme para parar de receber a newsletter.',
        'action' => 'Descadastrar',
        'title_unsubscribed' => 'Descadastro concluído',
        'body_unsubscribed' => 'Pronto: você não recebe mais a newsletter. Se mudar de ideia, é só se inscrever de novo.',
        'title_invalid' => 'Link expirado',
        'body_invalid' => 'Esse link não vale mais, então não há o que descadastrar.',
    ],

    'invalid_link' => 'Esse link não vale mais.',
    'back_home' => 'Voltar para o site',
];
