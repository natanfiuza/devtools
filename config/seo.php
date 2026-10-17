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
| verdade (tests/Feature/SeoMetaTest.php). O card e quadrado (1200x1200): o
| WhatsApp exibe o preview numa area quadrada e corta imagens horizontais.
|
| A versao entra na URL do og:image (`?v=2`) para o cache das redes buscar o
| arquivo novo depois de um redesenho; incrementar junto com o comando.
|
| As fontes do desenho sao baixadas na primeira execucao do comando e ficam
| fora do git, em storage/app/og-fonts.
|
*/

return [

    'image' => [
        'file' => 'og-default.png',

        // Uma imagem por tool que funciona: `public/<tools_dir>/<slug>.png`,
        // desenhada pelo mesmo comando e comitada junto com a padrao. A pagina
        // da tool usa a dela; sem o arquivo, cai na padrao (App\Support\Seo).
        'tools_dir' => 'og',

        'width' => 1200,
        'height' => 1200,

        // Muda a query string do og:image; o arquivo em disco e o mesmo.
        // Incrementar quando os PNGs forem redesenhados.
        'version' => 2,
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
