<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
| Sanctum da API publica (ADR-050).
|
| A API e servidor-a-servidor e so aceita Bearer token: `stateful` vazio diz
| para o Sanctum nao tentar cookie de SPA (nao ha `/sanctum/csrf-cookie` nem
| sessao nas rotas `api/*`). O guard `web` continua listado porque e o padrao do
| pacote; sem sessao iniciada ele simplesmente nao encontra usuario.
|
| `expiration` nulo: o token nao vence sozinho. Quem controla o prazo e o dono,
| revogando na tela de tokens; expirar em silencio quebraria uma integracao sem
| o cliente saber.
*/

return [

    'stateful' => [],

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
