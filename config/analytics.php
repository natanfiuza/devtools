<?php

/*
|--------------------------------------------------------------------------
| Medicao (Google Analytics)
|--------------------------------------------------------------------------
|
| A tag do GA4 so entra na pagina depois do aceite no banner e so no cliente
| (ADR-013). Sem id configurado nada e injetado, nem com aceite: e assim que
| o ambiente de testes e o de desenvolvimento ficam sem medicao.
|
| O nome do cookie e a validade sao a fonte unica dos dois lados: o servidor
| le a decisao dele e manda o nome nas props compartilhadas para o cliente
| gravar exatamente o mesmo cookie.
|
| Ver docs/SPEC.md secao 10.
|
*/

return [

    // Id de medicao do GA4 (G-XXXXXXXXXX). Vazio desliga a medicao.
    'ga4_id' => env('ANALYTICS_GA4_ID', ''),

    // Cookie da decisao do visitante (aceito ou recusado).
    'cookie' => 'consent',

    // Validade do cookie, em dias. Sem cookie a decisao e nula e o banner
    // volta a aparecer - por isso o prazo longo.
    'cookie_days' => (int) env('ANALYTICS_CONSENT_DAYS', 180),

];
