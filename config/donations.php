<?php

/*
|--------------------------------------------------------------------------
| Doacoes
|--------------------------------------------------------------------------
|
| Valores ficam aqui, nao no codigo (CLAUDE.md): a tela le os sugeridos e o
| intervalo permitido daqui. A moeda e a mesma do catalogo de planos.
|
| O Pix estatico e o fallback sem credenciais do provedor: chave, titular e
| cidade vem do ambiente e nunca do repositorio. Sem chave, a pagina de
| doacao fica em modo informativo (sem QR).
|
*/

return [

    'currency' => env('DONATIONS_CURRENCY', 'BRL'),

    // Valores sugeridos, em centavos. O valor livre aceita qualquer inteiro
    // dentro do intervalo abaixo.
    'suggested' => array_values(array_filter(array_map(
        static fn (string $value): int => (int) trim($value),
        explode(',', (string) env('DONATIONS_SUGGESTED_CENTS', '500,1000,2500,5000')),
    ), static fn (int $cents): bool => $cents > 0)),

    'min_cents' => (int) env('DONATIONS_MIN_CENTS', 100),
    'max_cents' => (int) env('DONATIONS_MAX_CENTS', 100000),

    // Quanto tempo o QR dinamico vale; depois a doacao expira e precisa ser
    // refeita. `?:` (e nao o default do `env()`) porque uma chave vazia no
    // `.env` devolve `''`, que vira 0 e encurtava o QR para 5 minutos; o piso
    // impede um prazo curto demais para o pagador escanear e pagar.
    'expires_minutes' => max(30, (int) (env('DONATIONS_EXPIRES_MINUTES') ?: 60)),

    // Intervalo do auto-refresh da pagina de status, em ms. 0 desliga.
    'status_poll_ms' => max(0, (int) env('DONATIONS_STATUS_POLL_MS', 15000)),

    // Teto de consultas sob demanda ao provedor por IP, por minuto.
    'check_per_minute' => max(1, (int) env('DONATIONS_CHECK_PER_MINUTE', 5)),

    // E-mail do recebedor quando o doador nao informa um. O provedor exige um
    // e-mail de pagador para criar o Pix.
    'payer_email' => env('DONATIONS_PAYER_EMAIL') ?: env('MAIL_FROM_ADDRESS'),

    // Recibo por e-mail so quando ha e-mail e esta ligado.
    'send_receipts' => (bool) env('DONATIONS_SEND_RECEIPTS', true),

    'static_pix' => [
        'key' => env('DONATIONS_PIX_KEY'),
        'holder' => env('DONATIONS_PIX_HOLDER'),
        'city' => env('DONATIONS_PIX_CITY'),
    ],

];
