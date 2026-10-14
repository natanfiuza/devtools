<?php

/*
|--------------------------------------------------------------------------
| Cobranca
|--------------------------------------------------------------------------
|
| Moeda padrao dos pagamentos registrados a mao. Os precos vivem na tabela
| `plans` (editaveis pelo painel); isto e so a moeda de um pagamento que nao
| aponta para plano nenhum.
|
| Ver ADR-027.
|
*/

return [

    'currency' => env('BILLING_CURRENCY', 'BRL'),

];
