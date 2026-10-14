<?php

/*
|--------------------------------------------------------------------------
| Paginas legais (Privacidade e Termos)
|--------------------------------------------------------------------------
|
| Dados que aparecem nas paginas legais e que nao podem ficar presos no
| codigo nem nos arquivos de idioma: o e-mail de contato e a data da ultima
| revisao do texto. O texto em si fica em lang/{locale}/legal.php.
|
| Ver docs/SPEC.md secao 10.
|
*/

return [

    // Contato para assuntos de privacidade (LGPD/GDPR).
    'contact_email' => env('LEGAL_CONTACT_EMAIL', config('mail.from.address')),

    // Data da ultima revisao dos textos, exibida nas duas paginas.
    'updated_at' => env('LEGAL_UPDATED_AT', '2026-10-13'),

];
