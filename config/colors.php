<?php

/*
|--------------------------------------------------------------------------
| Colors
|--------------------------------------------------------------------------
|
| Limites, defaults e caminhos das paletas persistidas (F4, ADR-123 a
| ADR-126). Nenhum limite numerico deve ser fixado no codigo: tudo que o
| usuario pode pedir passa por aqui, com override por .env.
|
| `null` em `paid_limit` significa ilimitado. Arquivadas contam na cota:
| apenas a exclusao definitiva libera uma vaga (ADR-096).
|
*/

return [

    'palettes' => [
        'free_limit' => (int) env('COLORS_FREE_LIMIT', 25),

        'paid_limit' => env('COLORS_PAID_LIMIT') === null ? null : (int) env('COLORS_PAID_LIMIT'),

        'name_max_length' => (int) env('COLORS_PALETTE_NAME_MAX_LENGTH', 80),

        'description_max_length' => (int) env('COLORS_PALETTE_DESCRIPTION_MAX_LENGTH', 500),

        'per_page' => (int) env('COLORS_PALETTES_PER_PAGE', 20),

        /*
        | Quantas cores a lista de Minhas Paletas desenha no cartao. A paleta
        | continua com 5, 7, 9 ou 11: isto e so a previa.
        */
        'preview_colors' => (int) env('COLORS_PALETTE_PREVIEW_COLORS', 8),

        /*
        | Teto de paletas publicas no sitemap. O sitemap e montado a cada
        | requisicao; o limite protege o tempo de resposta enquanto o acervo
        | nao justifica um indice paginado proprio (ADR-124).
        */
        'sitemap_limit' => (int) env('COLORS_PALETTES_SITEMAP_LIMIT', 500),
    ],

    'tags' => [
        'per_palette' => (int) env('COLORS_TAGS_PER_PALETTE', 10),

        'name_max_length' => (int) env('COLORS_TAG_NAME_MAX_LENGTH', 30),
    ],

    /*
    | Biblioteca pessoal de cores (F5, ADR-127/ADR-132). O historico da conta
    | nao tem cota funcional; a pagina e o lote de importacao sao tetos de
    | transporte, nao de armazenamento.
    */
    'library' => [
        'history_per_page' => (int) env('COLORS_HISTORY_PER_PAGE', 100),

        'favorites_per_page' => (int) env('COLORS_FAVORITES_PER_PAGE', 100),

        'import_batch' => (int) env('COLORS_HISTORY_IMPORT_BATCH', 200),

        /*
        | O anonimo continua sem conta e sem requisicao. O historico local foi
        | reduzido para caber melhor na oferta de importar depois do login;
        | os favoritos seguem com o teto do ADR-118 (ADR-132).
        */
        'anonymous_history_limit' => (int) env('COLORS_ANONYMOUS_HISTORY_LIMIT', 20),

        'anonymous_favorites_limit' => (int) env('COLORS_ANONYMOUS_FAVORITES_LIMIT', 50),
    ],

    'slug' => [
        'max_length' => (int) env('COLORS_SLUG_MAX_LENGTH', 60),

        /*
        | Slugs que colidiriam com segmentos de rota. Se o nome gerar um
        | deles, o slug recebe o sufixo `-palette` (ADR-124).
        */
        'reserved' => [
            'p',
            'og',
            'create',
            'new',
            'edit',
            'tags',
            'duplicate',
            'archive',
            'restore',
            'admin',
            'api',
            'dashboard',
            'colors',
            'tools',
            'login',
            'register',
            'settings',
        ],
    ],

    'og' => [
        'width' => (int) env('COLORS_OG_WIDTH', 1200),
        'height' => (int) env('COLORS_OG_HEIGHT', 630),
        'max_bytes' => (int) env('COLORS_OG_MAX_BYTES', 2 * 1024 * 1024),
        'path' => env('COLORS_OG_PATH', 'app/data/og_paleta_image'),
        'cache_seconds' => (int) env('COLORS_OG_CACHE_SECONDS', 86400),
    ],

    /*
    | Limites de taxa por conta (ADR-123). A gestao cobre as mutacoes raras;
    | o upload de OG tem teto proprio porque gera arquivo em disco.
    */
    'rates' => [
        'management_per_minute' => (int) env('COLORS_MANAGEMENT_RATE_LIMIT', 60),
        'og_per_minute' => (int) env('COLORS_OG_RATE_LIMIT', 10),
        'library_per_minute' => (int) env('COLORS_LIBRARY_RATE_LIMIT', 120),
    ],

    /*
    | Versoes do algoritmo que produziram as cores efetivas salvas. A escala
    | vem da ADR-120 (`PALETTE_ALGORITHM_VERSION = 1`) e a extracao da
    | ADR-122; o valor persistido permite reabrir a paleta sem recalcular.
    */
    'algorithm_versions' => [
        'scale' => 1,
        'extraction' => 1,
    ],

];
