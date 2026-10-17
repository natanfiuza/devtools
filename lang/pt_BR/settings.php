<?php

return [
    'title' => 'Configurações',
    'subtitle' => 'Gerencie seu perfil e as configurações da conta',

    'nav' => [
        'profile' => 'Perfil',
        'security' => 'Segurança',
        'appearance' => 'Aparência',
    ],

    'profile' => [
        'title' => 'Perfil',
        'description' => 'Atualize seu nome, seu identificador e seu e-mail',
        'name' => 'Nome',
        'name_placeholder' => 'Nome completo',
        'username' => 'Nome de usuário',
        'username_placeholder' => 'nomedeusuario',
        'username_help' => 'Apenas letras minúsculas, números e sublinhado (3 a 30 caracteres). Usado como seu identificador exclusivo na plataforma.',
        'email' => 'E-mail',
        'email_placeholder' => "voce{'@'}exemplo.com",
        'locale' => 'Idioma da conta',
        'locale_help' => 'O idioma dos e-mails que enviamos para você. A interface segue o idioma em que você está navegando.',
        'save' => 'Salvar',
    ],

    'security' => [
        'title' => 'Segurança',
        'description' => 'Mantenha a sua conta com uma senha longa e aleatória.',
        'current_password' => 'Senha atual',
        'current_password_placeholder' => 'Senha atual',
        'password' => 'Nova senha',
        'password_placeholder' => 'Nova senha',
        'password_confirmation' => 'Confirme a nova senha',
        'password_confirmation_placeholder' => 'Confirme a nova senha',
        'save' => 'Salvar',
    ],

    'appearance' => [
        'title' => 'Aparência',
        'description' => 'Escolha como o devtools aparece neste dispositivo.',
    ],
];
