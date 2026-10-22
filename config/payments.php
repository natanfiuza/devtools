<?php

/*
|--------------------------------------------------------------------------
| Pagamentos (Mercado Pago)
|--------------------------------------------------------------------------
|
| Credenciais e limites do provedor. Sem `access_token` o checkout online
| fica indisponivel e a pagina de assinatura oferece o atendimento manual -
| nunca ha segredo no codigo (CLAUDE.md).
|
| Valores e moeda continuam vindo do plano (`plans`), nunca daqui.
|
*/

return [

    // Unico provedor previsto na F6. O nome fica explicito para que um segundo
    // gateway um dia seja uma decisao, nao um efeito colateral.
    'gateway' => env('PAYMENTS_GATEWAY', 'mercadopago'),

    'mercadopago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
        'base_url' => env('MERCADO_PAGO_BASE_URL', 'https://api.mercadopago.com'),
        'timeout' => (int) env('MERCADO_PAGO_TIMEOUT', 20),
        'connect_timeout' => (int) env('MERCADO_PAGO_CONNECT_TIMEOUT', 5),
    ],

    'checkout' => [
        // Quanto tempo uma sessao de checkout e o QR de doacao valem. Depois
        // disso a leitura ja a trata como expirada, mesmo antes da varredura.
        'expires_minutes' => (int) env('PAYMENTS_CHECKOUT_EXPIRES_MINUTES', 60),

        // URL de retorno depois do provedor. Vazia resolve para a rota de
        // retorno do checkout da sessao.
        'back_url' => env('PAYMENTS_BACK_URL'),

        // URL publica do webhook. Vazia resolve para `route('webhooks.mercadopago')`.
        'webhook_url' => env('PAYMENTS_WEBHOOK_URL'),

        // Nome que aparece na fatura do cartao.
        'statement_descriptor' => env('PAYMENTS_STATEMENT_DESCRIPTOR', 'DEVTOOLS'),
    ],

];
