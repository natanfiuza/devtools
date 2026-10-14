<?php

return [
    'title' => 'Ajustes',
    'subtitle' => 'Gestiona tu perfil y la configuración de tu cuenta',

    'nav' => [
        'profile' => 'Perfil',
        'security' => 'Seguridad',
        'appearance' => 'Apariencia',
    ],

    'profile' => [
        'title' => 'Perfil',
        'description' => 'Actualiza tu nombre y tu correo electrónico',
        'name' => 'Nombre',
        'name_placeholder' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'email_placeholder' => "tu{'@'}ejemplo.com",
        'locale' => 'Idioma de la cuenta',
        'locale_help' => 'El idioma de los correos que te enviamos. La interfaz sigue el idioma en el que navegas.',
        'save' => 'Guardar',
    ],

    'security' => [
        'title' => 'Seguridad',
        'description' => 'Mantén tu cuenta con una contraseña larga y aleatoria.',
        'current_password' => 'Contraseña actual',
        'current_password_placeholder' => 'Contraseña actual',
        'password' => 'Nueva contraseña',
        'password_placeholder' => 'Nueva contraseña',
        'password_confirmation' => 'Confirma la nueva contraseña',
        'password_confirmation_placeholder' => 'Confirma la nueva contraseña',
        'save' => 'Guardar',
    ],

    'appearance' => [
        'title' => 'Apariencia',
        'description' => 'Elige cómo se ve devtools en este dispositivo.',
    ],
];
