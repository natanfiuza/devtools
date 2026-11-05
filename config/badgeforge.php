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

    'static_endpoint' => '/api/badges/static',

    'provider_endpoint' => '/api/badges',

    'personal_endpoint' => '/b',

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
    | Logos personalizados (F3, ADR-078): o upload vira viewBox + lista de
    | paths normalizados no banco; o arquivo cru nunca e guardado nem servido.
    | O limite de logos e por conta: Free tem teto, pago e ilimitado
    | (`paid_limit` nulo). O teto de cache garante que excluir/renomear um logo
    | apareca em no maximo uma hora nos badges ja publicados.
    */
    'custom_logos' => [
        'free_limit' => 2,
        'paid_limit' => null,
        'name_max_length' => 80,
        'max_bytes' => 262144,
        'max_paths' => 200,
        'max_path_length' => 4000,
        'max_total_path_length' => 100000,
        'max_viewbox_extent' => 10000,
        'max_document_depth' => 8,
        'max_cache_seconds' => 3600,
    ],

    /*
    | Presets (F3, ADR-079): snapshot do formulario, sem URL publica.
    */
    'presets' => [
        'name_max_length' => 80,
        'limit' => null,
    ],

    /*
    | Gestao autenticada de logos/presets: limite de taxa das escritas e
    | cache do catalogo publico da API JSON (F8, ADR-088).
    */
    'management' => [
        'rate_limit' => 20,
        'rate_decay_seconds' => 60,
        'catalog_cache_seconds' => (int) env('BADGEFORGE_CATALOG_CACHE_SECONDS', 300),
    ],

    /*
    | Cap generoso por IP nas imagens anonimas que nao tem dono para limitar:
    | estatica, provider do catalogo e badge pessoal (F8, ADR-089). Nao
    | substitui os limites por dono do endpoint/custom: existe para conter
    | varredura e abuso que o CDN nao absorver, sem furar badge popular.
    */
    'serving' => [
        'rate_limit' => [
            'per_minute' => (int) env('BADGEFORGE_SERVING_LIMIT_PER_MINUTE', 6000),
            'decay_seconds' => 60,
        ],
    ],

    /*
    | Providers externos (F4, ADR-080): a chamada publica e anonima e o cache
    | e por URL resolvida. Nenhum token global existe em config/.env - a
    | credencial e sempre do dono do badge pessoal (ADR-068/ADR-081).
    */
    'providers' => [
        'cache_ttl' => 300,
        'stale_ttl' => 86400,
        'fallback_ttl' => 60,
        'connect_timeout' => 3,
        'timeout' => 5,
        'max_body_bytes' => 1048576,
        'max_param_length' => 200,
        'user_agent' => 'devtools-badgeforge/1.0',
        'unavailable_message' => 'unavailable',
        'unknown_message' => 'unknown',
        'refresh_rate_limit' => 10,
        'refresh_rate_decay_seconds' => 60,
    ],

    /*
    | Badges pessoais (F4, ADR-081): arquivos sob `personal/{username}` na
    | base abaixo; o banco guarda o path relativo, nunca o absoluto. O teto de
    | cache limita o tempo que uma exclusao/renomeacao leva para sumir de CDN.
    */
    'personal_badges' => [
        'name_max_length' => 80,
        'max_cache_seconds' => 3600,
        'credential_max_length' => 512,
        'prune_after_hours' => 48,
        'path' => 'app/data/tools/badgeforge',
    ],

    /*
    | Endpoint JSON (F5, ADR-083/ADR-084): o dono cadastra uma URL HTTPS e o
    | servidor busca o JSON no contrato shields v1. A URL nunca viaja ao
    | cliente; a imagem publica e o ULID. Toda chamada sai de um IP publico
    | resolvido e pinado - nunca de um destino arbitrario. Os limites por
    | plano vivem aqui e podem ser reduzidos por .env para smoke/operacao.
    */
    'endpoint' => [
        'path' => '/api/badges/endpoint',
        'name_max_length' => 80,
        'url_max_length' => 2048,
        'allowed_scheme' => 'https',
        'allowed_port' => 443,
        'max_url_length' => 2048,
        'max_host_length' => 253,
        'cache_ttl' => 300,
        'max_cache_ttl' => 3600,
        'stale_ttl' => 86400,
        'fallback_ttl' => 60,
        'connect_timeout' => 3,
        'timeout' => 5,
        'max_body_bytes' => 524288,
        'max_json_depth' => 32,
        'user_agent' => 'devtools-badgeforge/1.0',
        'unavailable_message' => 'unavailable',
        'follow_redirects' => false,
        'require_curl' => true,
        'rate_limit' => [
            'anonymous' => (int) env('BADGEFORGE_ENDPOINT_LIMIT_ANONYMOUS', 60),
            'free' => (int) env('BADGEFORGE_ENDPOINT_LIMIT_FREE', 300),
            'paid' => (int) env('BADGEFORGE_ENDPOINT_LIMIT_PAID', 3000),
            'decay_seconds' => 60,
        ],
        'blocked_ipv4_cidrs' => [
            '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
            '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
            '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
            '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        ],
        'blocked_ipv6_cidrs' => [
            '::/96', '::1/128', '::ffff:0:0/96', '64:ff9b::/96',
            '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8',
        ],
        'require_ipv6_global_unicast' => true,
    ],

    /*
    | Providers customizados (F7, ADR-087): o dono pago descreve um JSON
    | externo por template + JSON Pointer, sem credencial. A URL publica e o
    | ULID; o template fica no servidor. O probe usa o mesmo guard SSRF do
    | endpoint (scheme/porta/CIDRs/depth acima) e os TTLs daqui.
    */
    'custom_providers' => [
        'path' => '/api/badges/custom',
        'limit' => (int) env('BADGEFORGE_CUSTOM_PROVIDERS_LIMIT', 10),
        'name_max_length' => 80,
        'url_max_length' => 2048,
        'example_path_max_length' => 512,
        'slug_max_length' => 32,
        'label_max_length' => 80,
        'pointer_max_length' => 256,
        'max_metrics' => 10,
        'max_path_params' => 5,
        'param_max_length' => 200,
        'cache_ttl' => 300,
        'max_cache_ttl' => 3600,
        'stale_ttl' => 86400,
        'fallback_ttl' => 60,
        'connect_timeout' => 3,
        'timeout' => 5,
        'max_body_bytes' => 524288,
        'max_json_depth' => 32,
        'user_agent' => 'devtools-badgeforge/1.0',
        'unavailable_message' => 'unavailable',
        'rate_limit' => [
            'anonymous' => (int) env('BADGEFORGE_CUSTOM_LIMIT_ANONYMOUS', 60),
            'free' => (int) env('BADGEFORGE_CUSTOM_LIMIT_FREE', 300),
            'paid' => (int) env('BADGEFORGE_CUSTOM_LIMIT_PAID', 3000),
            'decay_seconds' => 60,
        ],
    ],

    /*
    | Analytics das imagens com dono (F7, ADR-086). O bruto guarda cada acesso
    | por ate 90 dias; o agregado diario sobrevive 400. IP/UA viram HMAC com
    | APP_KEY; o UA cru e truncado. GeoIP e opcional: sem o arquivo, pais fica
    | nulo e nada mais muda. Nenhum destino configuravel por usuario.
    */
    'analytics' => [
        'raw_retention_days' => 90,
        'daily_retention_days' => 400,
        'free_window_days' => 30,
        'paid_windows' => [7, 30, 90],
        'admin_windows' => [7, 30, 90, 400],
        'aggregate_days' => 7,
        'top_limit' => 10,
        'export_chunk' => 500,
        'user_agent_max_length' => 512,
        'geoip_path' => env(
            'BADGEFORGE_GEOIP_DB',
            storage_path('app/data/geoip/GeoLite2-Country.mmdb'),
        ),
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
