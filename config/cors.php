<?php

/*
| CORS da API publica.
|
| O cliente tipico e servidor-a-servidor e nao depende de CORS. Origens de
| navegador so entram se `API_ALLOWED_ORIGINS` trouxer uma lista explicita -
| vazio significa "nenhuma origem" e nao "*". Credenciais ficam desligadas
| porque a autenticacao e Bearer token, sem cookie.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('API_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
