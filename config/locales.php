<?php

/*
|--------------------------------------------------------------------------
| Idiomas do site
|--------------------------------------------------------------------------
|
| Fonte unica dos idiomas suportados: o codigo e o diretorio em lang/, o
| slug e o prefixo visivel na URL. Os nomes exibidos (endonimos) ficam em
| lang/{locale}/locales.php para nao misturar codigo com conteudo.
|
| Ver docs/SPEC.md secao 3.
|
*/

return [

    'fallback' => 'en',

    'supported' => [
        'en' => [
            'slug' => 'en',
            'html' => 'en',
            'dir' => 'ltr',
        ],
        'pt_BR' => [
            'slug' => 'pt-br',
            'html' => 'pt-BR',
            'dir' => 'ltr',
        ],
        'pt_PT' => [
            'slug' => 'pt-pt',
            'html' => 'pt-PT',
            'dir' => 'ltr',
        ],
        'fr' => [
            'slug' => 'fr',
            'html' => 'fr',
            'dir' => 'ltr',
        ],
        'es' => [
            'slug' => 'es',
            'html' => 'es',
            'dir' => 'ltr',
        ],
    ],

    /*
    | Namespaces expostos ao frontend (um arquivo por namespace em lang/{locale}).
    | Apenas o idioma ativo e enviado, escopado por namespace.
    */
    'namespaces' => [
        'app',
        'admin',
        'account',
        'home',
        'tools',
        'settings',
        'styleguide',
        'locales',
        'auth',
        'ai',
        'legal',
        'newsletter',
        'donations',
        'contact',
        'api_docs',
        'errors',
    ],

];
