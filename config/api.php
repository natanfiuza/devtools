<?php

/*
|--------------------------------------------------------------------------
| API publica v1
|--------------------------------------------------------------------------
|
| Limites tecnicos do servidor, nao regra comercial: a cota continua vindo do
| plano via `usage_ledger`. Os numeros daqui sao por token, para que uma
| integracao nao consuma a janela de outra da mesma conta.
|
*/

return [

    'rate_limits' => [
        // POST cria solicitacao (e pode consumir cota): teto curto e teto
        // horario, como no POST da web.
        'post_per_minute' => (int) env('API_RATE_POST_PER_MINUTE', 30),
        'post_per_hour' => (int) env('API_RATE_POST_PER_HOUR', 300),

        // Leitura inclui o polling: uma integracao que acompanha varias
        // solicitacoes ao mesmo tempo precisa de folga.
        'read_per_minute' => (int) env('API_RATE_READ_PER_MINUTE', 120),
    ],

    'pagination' => [
        'per_page' => (int) env('API_PER_PAGE', 20),
    ],

];
