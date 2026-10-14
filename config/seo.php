<?php

/*
|--------------------------------------------------------------------------
| Compartilhamento (og:image)
|--------------------------------------------------------------------------
|
| A imagem padrao dos cards de compartilhamento e um PNG estatico em public/,
| desenhado por `php artisan seo:og-image` (app/Console/Commands/
| GenerateOgImage.php) e comitado. Em producao o comando nunca roda: o VPS nao
| precisa de GD nem de rede.
|
| Largura e altura ficam aqui porque as metas og:image:width/height saem daqui:
| assim elas nao mentem se a imagem for redesenhada - o teste confere o PNG de
| verdade (tests/Feature/SeoMetaTest.php).
|
| As fontes do desenho sao baixadas na primeira execucao do comando e ficam
| fora do git, em storage/app/og-fonts.
|
*/

return [

    'image' => [
        'file' => 'og-default.png',
        'width' => 1200,
        'height' => 630,
    ],

    'fonts' => [
        'path' => storage_path('app/og-fonts'),

        // Fontes variaveis do Google Fonts (mesmas familias do DESIGN).
        'sans' => [
            'file' => 'Inter.ttf',
            'url' => 'https://raw.githubusercontent.com/google/fonts/main/ofl/inter/Inter%5Bopsz%2Cwght%5D.ttf',
        ],
        'mono' => [
            'file' => 'JetBrainsMono.ttf',
            'url' => 'https://raw.githubusercontent.com/google/fonts/main/ofl/jetbrainsmono/JetBrainsMono%5Bwght%5D.ttf',
        ],
    ],

];
