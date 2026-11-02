<?php

/*
|--------------------------------------------------------------------------
| BadgeForge
|--------------------------------------------------------------------------
|
| Limites, defaults e catalogos da F1 (badges estaticas em SVG). Os ativos
| de geometria, metricas e cores ficam em resources/badgeforge/ porque sao
| lidos pelo PHP e pelo TypeScript - uma fonte, dois renderers.
|
| Nenhum limite numerico deve ser fixado no codigo: tudo que o usuario pode
| pedir passa por aqui.
|
*/

return [

    'assets_path' => resource_path('badgeforge'),

    'colors_path' => resource_path('badgeforge/colors.json'),

    'font_metrics_path' => resource_path('badgeforge/font-metrics.json'),

    'styles_path' => resource_path('badgeforge/styles.json'),

    'logos_path' => resource_path('badgeforge/logos'),

    'fonts_path' => resource_path('badgeforge/fonts'),

    'endpoint' => '/api/badges/static',

    'default_style' => 'flat',

    'logo_position' => 'left',

    'colors' => [
        'default' => '#9f9f9f',
        'label' => '#555555',
        'logo' => '#ffffff',
    ],

    'limits' => [
        'max_text_length' => 100,
        'logo_size' => ['min' => 8, 'max' => 20],
        'cache_seconds' => ['default' => 86400, 'min' => 300, 'max' => 86400],
        'link_max_length' => 2048,
    ],

    /*
    | PNG (F2, ADR-077): rasterizacao 1x pelo Imagick, com downscale
    | proporcional so acima de `max_width`. O cache e content-addressed pelo
    | sha256 do SVG; `cache_path` e relativo a `storage_path()`.
    */
    'png' => [
        'max_width' => 600,
        'cache_path' => 'app/data/tools/badgeforge/static',
        'prune_after_hours' => 48,
    ],

    /*
    | Logos embutidos: slug -> arquivo. O viewBox e o path sao lidos do
    | proprio SVG; o catalogo recusa arquivo que nao tenha exatamente um
    | <path> e o <svg> raiz.
    */
    'logos' => [
        'github' => ['name' => 'GitHub', 'file' => 'github.svg'],
        'gitlab' => ['name' => 'GitLab', 'file' => 'gitlab.svg'],
        'npm' => ['name' => 'npm', 'file' => 'npm.svg'],
        'composer' => ['name' => 'Composer', 'file' => 'composer.svg'],
        'docker' => ['name' => 'Docker', 'file' => 'docker.svg'],
        'laravel' => ['name' => 'Laravel', 'file' => 'laravel.svg'],
        'symfony' => ['name' => 'Symfony', 'file' => 'symfony.svg'],
        'php' => ['name' => 'PHP', 'file' => 'php.svg'],
        'python' => ['name' => 'Python', 'file' => 'python.svg'],
        'javascript' => ['name' => 'JavaScript', 'file' => 'javascript.svg'],
        'typescript' => ['name' => 'TypeScript', 'file' => 'typescript.svg'],
        'vue' => ['name' => 'Vue', 'file' => 'vue.svg'],
        'react' => ['name' => 'React', 'file' => 'react.svg'],
        'node' => ['name' => 'Node.js', 'file' => 'node.svg'],
        'vite' => ['name' => 'Vite', 'file' => 'vite.svg'],
        'tailwind' => ['name' => 'Tailwind CSS', 'file' => 'tailwind.svg'],
        'mysql' => ['name' => 'MySQL', 'file' => 'mysql.svg'],
        'postgresql' => ['name' => 'PostgreSQL', 'file' => 'postgresql.svg'],
        'redis' => ['name' => 'Redis', 'file' => 'redis.svg'],
        'phpunit' => ['name' => 'PHPUnit', 'file' => 'phpunit.svg'],
        'pest' => ['name' => 'Pest', 'file' => 'pest.svg'],
        'markdown' => ['name' => 'Markdown', 'file' => 'markdown.svg'],
        'json' => ['name' => 'JSON', 'file' => 'json.svg'],
        'html' => ['name' => 'HTML', 'file' => 'html.svg'],
        'gitpr' => ['name' => 'GitPR', 'file' => 'gitpr.svg'],
    ],

    /*
    | Fontes embutidas, escolhidas por `?font=`. A chave do slug e a mesma
    | usada em `families.<slug>` do font-metrics.json; `stack` e a pilha que
    | vai no atributo `font-family` do SVG (e no @font-face da web).
    | Os TTF normalizam qualquer `unitsPerEm` para 1000 na tabela de avancos.
    */
    'fonts' => [
        'fira-code' => [
            'name' => 'Fira Code',
            'family' => 'Fira Code',
            'stack' => 'Fira Code,DejaVu Sans,monospace',
            'regular' => 'fira-code/FiraCode-Regular.ttf',
            'bold' => 'fira-code/FiraCode-Bold.ttf',
        ],
        'libre-franklin' => [
            'name' => 'Libre Franklin',
            'family' => 'Libre Franklin',
            'stack' => 'Libre Franklin,DejaVu Sans,sans-serif',
            'regular' => 'libre-franklin/LibreFranklin-Regular.ttf',
            'bold' => 'libre-franklin/LibreFranklin-Bold.ttf',
        ],
        'space-grotesk' => [
            'name' => 'Space Grotesk',
            'family' => 'Space Grotesk',
            'stack' => 'Space Grotesk,DejaVu Sans,sans-serif',
            'regular' => 'space-grotesk/SpaceGrotesk-Regular.ttf',
            'bold' => 'space-grotesk/SpaceGrotesk-Bold.ttf',
        ],
    ],

];
