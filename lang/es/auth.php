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
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña indicada es incorrecta.',
    'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds segundos.',
    'suspended' => 'Esta cuenta está suspendida. Escríbenos si crees que es un error.',
    'reactivated' => '¡Te damos la bienvenida de nuevo! Se canceló la solicitud de eliminación y tu cuenta se ha reactivado con éxito.',

    'social' => [
        'divider' => 'o',
        'providers' => [
            'google' => 'Google',
            'github' => 'GitHub',
        ],
        'continue_with' => 'Continuar con {provider}',
        'login_with' => 'Entrar con {provider}',
        'register_with' => 'Crear cuenta con {provider}',
        'unavailable' => 'Entrar con :provider todavía no está disponible.',
        'failed' => 'No pudimos completar la entrada con :provider. Inténtalo de nuevo.',
        'email_missing' => ':provider no compartió un correo. Crea una cuenta con correo y contraseña.',
        'email_unverified' => 'Ya existe una cuenta con ese correo. Entra con tu contraseña.',
    ],

    'login' => [
        'title' => 'Entrar',
        'description' => 'Bienvenido de vuelta. Entra para continuar.',
        'email' => 'Correo',
        'email_placeholder' => "tu{'@'}ejemplo.com",
        'password' => 'Contraseña',
        'password_placeholder' => 'Tu contraseña',
        'forgot' => 'Olvidé mi contraseña',
        'remember' => 'Recordarme',
        'submit' => 'Entrar',
        'no_account' => '¿Aún no tienes cuenta?',
        'register' => 'Crear cuenta',
    ],

    'register' => [
        'title' => 'Crear cuenta',
        'description' => 'Es rápido: nombre, correo y contraseña.',
        'name' => 'Nombre',
        'name_placeholder' => 'Tu nombre',
        'email' => 'Correo',
        'email_placeholder' => "tu{'@'}ejemplo.com",
        'password' => 'Contraseña',
        'password_confirmation' => 'Confirma la contraseña',
        'locale' => 'Idioma principal',
        'locale_help' => 'Se usa en los correos que te enviamos. Puedes cambiarlo más tarde.',
        'newsletter' => 'Quiero recibir la newsletter con novedades y herramientas nuevas.',
        'terms' => 'Al crear la cuenta aceptas los {terms} y la {privacy}.',
        'terms_link' => 'Términos de uso',
        'privacy_link' => 'Política de privacidad',
        'submit' => 'Crear cuenta',
        'have_account' => '¿Ya tienes cuenta?',
        'login' => 'Entrar',
    ],

    'forgot' => [
        'title' => 'Olvidé mi contraseña',
        'description' => 'Indica tu correo y te enviamos el enlace para crear una contraseña nueva.',
        'email' => 'Correo',
        'email_placeholder' => "tu{'@'}ejemplo.com",
        'submit' => 'Enviar enlace',
        'back' => 'Volver al acceso',
    ],

    'reset' => [
        'title' => 'Crear una contraseña nueva',
        'description' => 'Elige una contraseña nueva para tu cuenta.',
        'email' => 'Correo',
        'password' => 'Contraseña nueva',
        'password_confirmation' => 'Confirma la contraseña nueva',
        'submit' => 'Guardar contraseña',
    ],

    'verify' => [
        'title' => 'Confirma tu correo',
        'description' => 'Enviamos un enlace a {email}. Ábrelo para activar tu cuenta.',
        'sent' => 'Enlace enviado de nuevo. Revisa tu bandeja de entrada.',
        'unverified' => 'Tu correo aún no está verificado.',
        'unverified_action' => 'Reenviar el correo de verificación.',
        'resend' => 'Reenviar el enlace',
        'logout' => 'Salir',
    ],

    'confirm' => [
        'title' => 'Confirma tu contraseña',
        'description' => 'Zona sensible. Confirma tu contraseña para continuar.',
        'password' => 'Contraseña',
        'submit' => 'Confirmar',
        'set_title' => 'Define tu contraseña',
        'set_description' => 'Tu cuenta se creó con acceso social y todavía no tiene contraseña. Define una para desbloquear los ajustes de seguridad.',
        'set_password' => 'Contraseña nueva',
        'set_password_confirmation' => 'Confirma la contraseña nueva',
        'set_submit' => 'Definir contraseña',
        'set_success' => 'Contraseña definida.',
    ],

    'two_factor' => [
        'title' => 'Autenticación en dos pasos',
        'description' => 'Confirma el acceso a tu cuenta introduciendo el código de autenticación facilitado por tu aplicación.',
        'recovery_title' => 'Código de recuperación',
        'recovery_description' => 'Confirma el acceso a tu cuenta introduciendo uno de tus códigos de recuperación de emergencia.',
        'code_label' => 'Código de 6 dígitos',
        'recovery_code_label' => 'Código de recuperación',
        'submit' => 'Confirmar',
        'use_recovery_code' => 'Usar un código de recuperación',
        'use_auth_code' => 'Usar un código de la aplicación autenticadora',
    ],
];
