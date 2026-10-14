<?php

use Laravel\Fortify\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Fortify Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Fortify will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Fortify Password Broker
    |--------------------------------------------------------------------------
    |
    | Here you may specify which password broker Fortify can use when a user
    | is resetting their password. This configured value should match one
    | of your password brokers setup in your "auth" configuration file.
    |
    */

    'passwords' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    |
    | This value defines which model attribute should be considered as your
    | application's "username" field. Typically, this might be the email
    | address of the users but you are free to change this value here.
    |
    | Out of the box, Fortify expects forgot password and reset password
    | requests to have a field named 'email'. If the application uses
    | another name for the field you may define it below as needed.
    |
    */

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Lowercase Usernames
    |--------------------------------------------------------------------------
    |
    | This value defines whether usernames should be lowercased before saving
    | them in the database, as some database system string fields are case
    | sensitive. You may disable this for your application if necessary.
    |
    */

    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path
    |--------------------------------------------------------------------------
    |
    | Here you may configure the path where users will get redirected during
    | authentication or password reset when the operations are successful
    | and the user is authenticated. You are free to change this value.
    |
    */

    'home' => '/dashboard',

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Prefix / Subdomain
    |--------------------------------------------------------------------------
    |
    | Here you may specify which prefix Fortify will assign to all the routes
    | that it registers with the application. If necessary, you may change
    | subdomain under which all of the Fortify routes will be available.
    |
    */

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Paths
    |--------------------------------------------------------------------------
    |
    | O idioma e o primeiro segmento de toda rota de autenticacao
    | (docs/SPEC.md secao 3). O Fortify le cada caminho aqui via RoutePath::for
    | e o {locale} e validado pelo Route::pattern global definido no
    | AppServiceProvider (que roda antes do boot do Fortify).
    |
    | Os endpoints de maquina do login social (/auth/{provider}) e o /api ficam
    | fora do prefixo de proposito: a URL de callback e cadastrada uma unica
    | vez no provedor (ADR-022).
    |
    */

    'paths' => [
        'login' => '/{locale}/login',
        'logout' => '/{locale}/logout',
        'register' => '/{locale}/register',

        // O Fortify le a chave com config('fortify.paths.password.request'),
        // ou seja, notacao de ponto: 'password.request' seria interpretado como
        // caminho aninhado, entao o array abaixo precisa espelhar isso.
        'password' => [
            'request' => '/{locale}/forgot-password',
            'email' => '/{locale}/forgot-password',
            'reset' => '/{locale}/reset-password/{token}',
            'update' => '/{locale}/reset-password',
            'confirm' => '/{locale}/user/confirm-password',
            'confirmation' => '/{locale}/user/confirmed-password-status',
        ],

        'verification' => [
            'notice' => '/{locale}/email/verify',
            'verify' => '/{locale}/email/verify/{id}/{hash}',
            'send' => '/{locale}/email/verification-notification',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fortify Routes Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may specify which middleware Fortify will assign to the routes
    | that it registers with the application. If necessary, you may change
    | these middleware but typically this provided default is preferred.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | By default, Fortify will throttle logins to five requests per minute for
    | every email and IP address combination. However, if you would like to
    | specify a custom rate limiter to call then you may specify it here.
    |
    */

    'limiters' => [
        'login' => 'login',
    ],

    /*
    |--------------------------------------------------------------------------
    | Register View Routes
    |--------------------------------------------------------------------------
    |
    | Here you may specify if the routes returning views should be disabled as
    | you may not need them when building your own application. This may be
    | especially true if you're writing a custom single-page application.
    |
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of the Fortify features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features, or you can even remove all of these if you need to.
    |
    | Dois fatores e passkeys ficam de fora: a autenticacao do projeto e
    | e-mail/senha, Google e GitHub (docs/SPEC.md secao 7).
    |
    */

    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
    ],

];
