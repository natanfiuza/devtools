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
    // refeita.
    'expires_minutes' => (int) env('DONATIONS_EXPIRES_MINUTES', 60),

    // E-mail do recebedor quando o doador nao informa um. O provedor exige um
    // e-mail de pagador para criar o Pix.
    'payer_email' => env('DONATIONS_PAYER_EMAIL', config('mail.from.address')),

    // Recibo por e-mail so quando ha e-mail e esta ligado.
    'send_receipts' => (bool) env('DONATIONS_SEND_RECEIPTS', true),

    'static_pix' => [
        'key' => env('DONATIONS_PIX_KEY'),
        'holder' => env('DONATIONS_PIX_HOLDER'),
        'city' => env('DONATIONS_PIX_CITY'),
    ],

];
