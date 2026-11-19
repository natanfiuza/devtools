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

        /*
        | Rotulo da origem extraida (nome do arquivo). Tambem e o rotulo dado
        | a uma paleta importada de tokens (F5, Etapa 6).
        */
        'source_label_max_length' => (int) env('COLORS_SOURCE_LABEL_MAX_LENGTH', 80),

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

    /*
    | Sistemas de layout persistidos (F4, ADR-168 a ADR-174). A cota e a mesma
    | das paletas (conjunta, ADR-170); aqui ficam so os limites de formulario,
    | da previa, do sitemap e o teto tecnico do `state`. As versoes correntes
    | do estado/snapshot sao 1; `algorithm` e `suggestions` acompanham o motor e
    | o servidor so aceita o que o snapshot declarar dentro da faixa.
    */
    'layout_systems' => [
        'name_max_length' => (int) env('COLORS_LAYOUT_SYSTEM_NAME_MAX_LENGTH', 80),

        'description_max_length' => (int) env('COLORS_LAYOUT_SYSTEM_DESCRIPTION_MAX_LENGTH', 500),

        'per_page' => (int) env('COLORS_LAYOUT_SYSTEMS_PER_PAGE', 20),

        'preview_colors' => (int) env('COLORS_LAYOUT_SYSTEM_PREVIEW_COLORS', 6),

        'sitemap_limit' => (int) env('COLORS_LAYOUT_SYSTEMS_SITEMAP_LIMIT', 500),

        /*
        | Teto tecnico do JSON do estado. O retrato real fica em ~25 KB; o teto
        | existe para recusar payload abusivo antes de gravar.
        */
        'state_max_bytes' => (int) env('COLORS_LAYOUT_SYSTEM_STATE_MAX_BYTES', 256 * 1024),

        'input_max_length' => (int) env('COLORS_LAYOUT_SYSTEM_INPUT_MAX_LENGTH', 120),

        'versions' => [
            'state' => 1,
            'schema' => 1,
            'snapshot' => 1,
            'algorithm' => 1,
            'suggestions' => 1,
        ],
    ],

    'tags' => [
        'per_palette' => (int) env('COLORS_TAGS_PER_PALETTE', 10),

        'per_layout_system' => (int) env('COLORS_TAGS_PER_LAYOUT_SYSTEM', 10),

        'name_max_length' => (int) env('COLORS_TAG_NAME_MAX_LENGTH', 30),
    ],

    /*
    | Social das paletas publicas (F6, ADR-135 a ADR-137). O cookie anonimo e
    | first-party e cifrado; `cookie_days` e o unico prazo de retencao do
    | vinculo no navegador. O perfil publico pagina as paletas do autor com o
    | mesmo cuidado de consulta da lista da conta.
    */
    'social' => [
        'cookie_days' => (int) env('COLORS_ANON_COOKIE_DAYS', 365),

        'profile_per_page' => (int) env('COLORS_PROFILE_PER_PAGE', 12),
    ],

    /*
    | Comentarios de paletas publicas (F7, ADR-139 a ADR-143). A superficie
    | nasce desligada: producao so liga por decisao explicita. O limite de 255
    | caracteres e o da ADR-111; modelo e versoes vem daqui, nunca do codigo.
    | `cache_ttl_days` vazio significa linha permanente em `ai_cache`.
    */
    'comments' => [
        'enabled' => (bool) env('COLORS_COMMENTS_ENABLED', false),

        'body_max_length' => (int) env('COLORS_COMMENT_MAX_LENGTH', 255),

        'per_page' => (int) env('COLORS_COMMENTS_PER_PAGE', 20),

        'replies_limit' => (int) env('COLORS_COMMENT_REPLIES_LIMIT', 100),

        'moderation' => [
            'model' => env('COLORS_COMMENT_MODEL') ?: env('AI_MODEL'),

            'prompt_slug' => env('COLORS_COMMENT_PROMPT_SLUG', 'colors_comment_moderation'),

            'prompt_version' => (int) env('COLORS_COMMENT_PROMPT_VERSION', 1),

            'policy_version' => (int) env('COLORS_COMMENT_POLICY_VERSION', 1),

            'word_lists_path' => env('COLORS_COMMENT_WORD_LISTS_PATH')
                ?: resource_path('colors/moderation'),

            'cache_ttl_days' => env('COLORS_COMMENT_CACHE_TTL_DAYS') === null
                || env('COLORS_COMMENT_CACHE_TTL_DAYS') === ''
                    ? null
                    : (int) env('COLORS_COMMENT_CACHE_TTL_DAYS'),

            'job_tries' => (int) env('COLORS_COMMENT_JOB_TRIES', 3),

            'job_backoff_seconds' => (int) env('COLORS_COMMENT_JOB_BACKOFF', 30),

            'job_timeout' => (int) env('COLORS_COMMENT_JOB_TIMEOUT', 60),
        ],
    ],

    /*
    | Denuncias e notificacoes (F7, ADR-141/ADR-142). A paginacao vale para as
    | telas do admin e da conta; nada de numero fixo na interface.
    */
    'reports' => [
        'per_page' => (int) env('COLORS_REPORTS_PER_PAGE', 30),
    ],

    'notifications' => [
        'per_page' => (int) env('COLORS_NOTIFICATIONS_PER_PAGE', 20),
    ],

    /*
    | Presets do gerador (F5, ADR-093). Guardam apenas a entrada da geracao
    | (nome + cor base), nunca as cores derivadas. `limit = null` significa
    | sem limite funcional; um teto operacional, se necessario, vem daqui.
    */
    'presets' => [
        'name_max_length' => (int) env('COLORS_PRESET_NAME_MAX_LENGTH', 60),

        'limit' => env('COLORS_PRESETS_LIMIT') === null ? null : (int) env('COLORS_PRESETS_LIMIT'),
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

    /*
    | Importacao validada de design tokens (F5, Etapa 6). Os tetos valem para
    | a leitura no navegador: tamanho do arquivo, quantidade de tokens/nos,
    | profundidade da arvore, encadeamento de aliases e diagnostico exibidos.
    | Nenhum numero e fixado no cliente.
    */
    'tokens' => [
        'import' => [
            'max_bytes' => (int) env('COLORS_TOKEN_IMPORT_MAX_BYTES', 512 * 1024),

            'max_tokens' => (int) env('COLORS_TOKEN_IMPORT_MAX_TOKENS', 5000),

            'max_depth' => (int) env('COLORS_TOKEN_IMPORT_MAX_DEPTH', 40),

            'max_reference_depth' => (int) env('COLORS_TOKEN_IMPORT_MAX_REFERENCE_DEPTH', 10),

            'max_diagnostics' => (int) env('COLORS_TOKEN_IMPORT_MAX_DIAGNOSTICS', 20),
        ],
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
        'social_per_minute' => (int) env('COLORS_SOCIAL_RATE_LIMIT', 60),
        'comment_per_minute' => (int) env('COLORS_COMMENT_RATE_LIMIT', 10),
        'report_per_minute' => (int) env('COLORS_REPORT_RATE_LIMIT', 10),
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
