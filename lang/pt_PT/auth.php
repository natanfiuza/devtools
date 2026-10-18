<?php

/*
| Strings das telas de acesso (namespace "auth" do vue-i18n) e as chaves que o
| Fortify consome no servidor: failed, password e throttle.
|
| Convencao de interpolacao: chaves lidas pelo servidor usam ":param" (Laravel);
| chaves lidas pelo Vue usam "{param}" (vue-i18n). Nenhuma chave e lida pelos
| dois lados quando tem parametro.
*/

return [
    // Consumidas pelo Fortify (servidor).
    'failed' => 'Estas credenciais não correspondem aos nossos registos.',
    'password' => 'A palavra-passe indicada está incorreta.',
    'throttle' => 'Demasiadas tentativas. Tente novamente dentro de :seconds segundos.',
    'suspended' => 'Esta conta está suspensa. Fale connosco se achar que é engano.',
    'reactivated' => 'Bem-vindo de volta! O pedido de eliminação foi cancelado e a sua conta foi reativada com sucesso.',

    'social' => [
        'divider' => 'ou',
        'providers' => [
            'google' => 'Google',
            'github' => 'GitHub',
        ],
        'continue_with' => 'Continuar com {provider}',
        'unavailable' => 'Entrar com :provider ainda não está disponível.',
        'failed' => 'Não foi possível concluir a entrada com :provider. Tente novamente.',
        'email_missing' => 'O :provider não indicou um e-mail. Crie uma conta com e-mail e palavra-passe.',
        'email_unverified' => 'Já existe uma conta com este e-mail. Entre com a sua palavra-passe.',
    ],

    'login' => [
        'title' => 'Entrar',
        'description' => 'Bem-vindo de volta. Entre para continuar.',
        'email' => 'E-mail',
        'email_placeholder' => "voce{'@'}exemplo.com",
        'password' => 'Palavra-passe',
        'password_placeholder' => 'A sua palavra-passe',
        'forgot' => 'Esqueci-me da palavra-passe',
        'remember' => 'Manter sessão iniciada',
        'submit' => 'Entrar',
        'no_account' => 'Ainda não tem conta?',
        'register' => 'Criar conta',
    ],

    'register' => [
        'title' => 'Criar conta',
        'description' => 'É rápido: nome, e-mail e palavra-passe.',
        'name' => 'Nome',
        'name_placeholder' => 'O seu nome',
        'email' => 'E-mail',
        'email_placeholder' => "voce{'@'}exemplo.com",
        'password' => 'Palavra-passe',
        'password_confirmation' => 'Confirme a palavra-passe',
        'locale' => 'Idioma principal',
        'locale_help' => 'Usado nos e-mails que lhe enviamos. Pode alterar mais tarde.',
        'newsletter' => 'Quero receber a newsletter com novidades e ferramentas novas.',
        'terms' => 'Ao criar a conta aceita os {terms} e a {privacy}.',
        'terms_link' => 'Termos de Utilização',
        'privacy_link' => 'Política de Privacidade',
        'submit' => 'Criar conta',
        'have_account' => 'Já tem conta?',
        'login' => 'Entrar',
    ],

    'forgot' => [
        'title' => 'Esqueci-me da palavra-passe',
        'description' => 'Indique o seu e-mail e enviamos a ligação para criar uma nova palavra-passe.',
        'email' => 'E-mail',
        'email_placeholder' => "voce{'@'}exemplo.com",
        'submit' => 'Enviar ligação',
        'back' => 'Voltar para a entrada',
    ],

    'reset' => [
        'title' => 'Criar nova palavra-passe',
        'description' => 'Escolha uma nova palavra-passe para a sua conta.',
        'email' => 'E-mail',
        'password' => 'Nova palavra-passe',
        'password_confirmation' => 'Confirme a nova palavra-passe',
        'submit' => 'Guardar palavra-passe',
    ],

    'verify' => [
        'title' => 'Confirme o seu e-mail',
        'description' => 'Enviámos uma ligação para {email}. Abra-a para libertar a sua conta.',
        'sent' => 'Ligação enviada de novo. Verifique a sua caixa de entrada.',
        'unverified' => 'O seu e-mail ainda não foi verificado.',
        'unverified_action' => 'Reenviar o e-mail de verificação.',
        'resend' => 'Reenviar a ligação',
        'logout' => 'Sair',
    ],

    'confirm' => [
        'title' => 'Confirme a sua palavra-passe',
        'description' => 'Esta é uma área sensível. Confirme a sua palavra-passe para continuar.',
        'password' => 'Palavra-passe',
        'submit' => 'Confirmar',
        'set_title' => 'Defina a sua palavra-passe',
        'set_description' => 'A sua conta foi criada com login social e ainda não tem palavra-passe. Defina uma para libertar as configurações de segurança.',
        'set_password' => 'Nova palavra-passe',
        'set_password_confirmation' => 'Confirme a nova palavra-passe',
        'set_submit' => 'Definir palavra-passe',
        'set_success' => 'Palavra-passe definida.',
    ],

    'two_factor' => [
        'title' => 'Autenticação de dois fatores',
        'description' => 'Confirme o acesso à sua conta introduzindo o código de autenticação fornecido pela sua aplicação.',
        'recovery_title' => 'Código de recuperação',
        'recovery_description' => 'Confirme o acesso à sua conta introduzindo um dos seus códigos de recuperação de emergência.',
        'code_label' => 'Código de 6 dígitos',
        'recovery_code_label' => 'Código de recuperação',
        'submit' => 'Confirmar',
        'use_recovery_code' => 'Utilizar um código de recuperação',
        'use_auth_code' => 'Utilizar um código da aplicação autenticadora',
    ],
];
