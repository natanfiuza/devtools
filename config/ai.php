<?php

/*
|--------------------------------------------------------------------------
| AI SQL Builder
|--------------------------------------------------------------------------
|
| Tudo que o provedor precisa e que muda por ambiente vive aqui: modelo,
| endpoint, chave, tempos e tetos. Limites de **plano** (schema, descricao e
| cota) ficam na tabela `plans` - esta config e o teto tecnico do servidor, nao
| a regra comercial (CLAUDE.md).
|
| `base_url`, `model` e `api_key` vazios desligam a tool: a pagina responde,
| mas o formulario mostra "indisponivel" em vez de falhar no POST.
|
*/

return [

    'base_url' => env('AI_BASE_URL'),
    'model' => env('AI_MODEL'),
    'api_key' => env('AI_API_KEY'),

    // Tempo da requisicao ao provedor. A ordem da fila e: 60s do provedor <
    // 90s do job < 120s do worker < 150s do `retry_after`.
    'timeout' => (int) env('AI_TIMEOUT', 60),
    'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 10),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 2000),

    /*
    | Tetos tecnicos do payload. O plano pode ser menor; nunca maior.
    */
    'limits' => [
        'max_schema_bytes' => (int) env('AI_MAX_SCHEMA_BYTES', 65536),
        'max_description_chars' => (int) env('AI_MAX_DESCRIPTION_CHARS', 4000),
        'max_output_bytes' => (int) env('AI_MAX_OUTPUT_BYTES', 32768),
    ],

    /*
    | Estimativa de saida usada na reserva antes de o provedor responder. O
    | ajuste final vem do `usage` real da resposta.
    */
    'estimate_output_tokens' => (int) env('AI_ESTIMATE_OUTPUT_TOKENS', 900),

    // Quanto tempo a resposta fica no cache e por quanto tempo uma solicitacao
    // em voo bloqueia um pedido identico (dedupe).
    'cache_ttl_days' => (int) env('AI_CACHE_TTL_DAYS', 30),
    'inflight_ttl_seconds' => (int) env('AI_INFLIGHT_TTL_SECONDS', 600),

    /*
    | Job e retentativas. O construtor do job so carrega o ULID; os numeros
    | ficam aqui para o alinhamento com a fila ser conferido num lugar so.
    */
    'job' => [
        'timeout' => (int) env('AI_JOB_TIMEOUT', 90),
        'tries' => (int) env('AI_JOB_TRIES', 2),
        'backoff' => array_map(
            static fn (string $seconds): int => (int) $seconds,
            explode(',', (string) env('AI_JOB_BACKOFF', '5,15')),
        ),
        // Folga do `ai:reconcile` depois do timeout do job. Cobre a janela em
        // que o worker morreu sem executar `failed()`.
        'reconcile_grace_seconds' => (int) env('AI_RECONCILE_GRACE_SECONDS', 30),
    ],

    /*
    | Rate limit por conta e por IP. O polling tem teto proprio porque uma aba
    | aberta consulta varias vezes por minuto.
    */
    'rate_limits' => [
        'post_per_minute' => (int) env('AI_RATE_POST_PER_MINUTE', 6),
        'post_per_hour' => (int) env('AI_RATE_POST_PER_HOUR', 60),
        'post_per_minute_ip' => (int) env('AI_RATE_POST_PER_MINUTE_IP', 30),
        'poll_per_minute' => (int) env('AI_RATE_POLL_PER_MINUTE', 120),
    ],

    // Ritmo do polling na tela: comeca em 1,5s, dobra ate 5s e desiste em 2min.
    'polling' => [
        'initial_delay_ms' => (int) env('AI_POLL_INITIAL_MS', 1500),
        'max_delay_ms' => (int) env('AI_POLL_MAX_MS', 5000),
        'timeout_ms' => (int) env('AI_POLL_TIMEOUT_MS', 120000),
    ],

    // Arquivos versionados: `<target_slug>.v<N>.md` (ADR-016).
    'prompt_path' => resource_path('prompts'),

];
