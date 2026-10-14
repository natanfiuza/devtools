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
    'failed' => 'Essas credenciais não conferem com os nossos registros.',
    'password' => 'A senha informada está incorreta.',
    'throttle' => 'Muitas tentativas. Tente de novo em :seconds segundos.',

    'social' => [
        'divider' => 'ou',
        'providers' => [
            'google' => 'Google',
            'github' => 'GitHub',
        ],
        'continue_with' => 'Continuar com {provider}',
        'unavailable' => 'Entrar com :provider ainda não está disponível.',
        'failed' => 'Não foi possível concluir a entrada com :provider. Tente de novo.',
        'email_missing' => 'O :provider não informou um e-mail. Crie uma conta com e-mail e senha.',
        'email_unverified' => 'Já existe uma conta com esse e-mail. Entre com sua senha.',
    ],

    'login' => [
        'title' => 'Entrar',
        'description' => 'Bem-vindo de volta. Entre para continuar.',
        'email' => 'E-mail',
        'email_placeholder' => 'voce@exemplo.com',
        'password' => 'Senha',
        'password_placeholder' => 'Sua senha',
        'forgot' => 'Esqueci minha senha',
        'remember' => 'Lembrar de mim',
        'submit' => 'Entrar',
        'no_account' => 'Ainda não tem conta?',
        'register' => 'Criar conta',
    ],

    'register' => [
        'title' => 'Criar conta',
        'description' => 'É rapidinho: nome, e-mail e senha.',
        'name' => 'Nome',
        'name_placeholder' => 'Seu nome',
        'email' => 'E-mail',
        'email_placeholder' => 'voce@exemplo.com',
        'password' => 'Senha',
        'password_confirmation' => 'Confirme a senha',
        'newsletter' => 'Quero receber a newsletter com novidades e ferramentas novas.',
        'terms' => 'Ao criar a conta você concorda com os {terms} e a {privacy}.',
        'terms_link' => 'Termos de Uso',
        'privacy_link' => 'Política de Privacidade',
        'submit' => 'Criar conta',
        'have_account' => 'Já tem conta?',
        'login' => 'Entrar',
    ],

    'forgot' => [
        'title' => 'Esqueci minha senha',
        'description' => 'Informe seu e-mail e enviamos o link para criar uma nova senha.',
        'email' => 'E-mail',
        'email_placeholder' => 'voce@exemplo.com',
        'submit' => 'Enviar link de redefinição',
        'back' => 'Voltar para o login',
    ],

    'reset' => [
        'title' => 'Criar uma nova senha',
        'description' => 'Escolha uma nova senha para sua conta.',
        'email' => 'E-mail',
        'password' => 'Nova senha',
        'password_confirmation' => 'Confirme a nova senha',
        'submit' => 'Salvar senha',
    ],

    'verify' => [
        'title' => 'Confirme seu e-mail',
        'description' => 'Enviamos um link para {email}. Abra-o para liberar sua conta.',
        'sent' => 'Link enviado de novo. Confira sua caixa de entrada.',
        'resend' => 'Reenviar o link',
        'logout' => 'Sair',
    ],

    'confirm' => [
        'title' => 'Confirme sua senha',
        'description' => 'Esta é uma área sensível. Confirme sua senha para continuar.',
        'password' => 'Senha',
        'submit' => 'Confirmar',
        'set_title' => 'Defina sua senha',
        'set_description' => 'Sua conta foi criada com login social e ainda não tem senha. Defina uma para liberar as configurações de segurança.',
        'set_password' => 'Nova senha',
        'set_password_confirmation' => 'Confirme a nova senha',
        'set_submit' => 'Definir senha',
        'set_success' => 'Senha definida.',
    ],
];
