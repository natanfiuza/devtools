<?php

/*
|--------------------------------------------------------------------------
| Configuracao de Contato
|--------------------------------------------------------------------------
|
| O e-mail de destino das mensagens enviadas pela pagina publica de contato.
| Pode ser sobrescrito via CONTACT_EMAIL no .env.
|
*/

return [

    'email' => env('CONTACT_EMAIL', 'devtools@natanfiuza.dev.br'),

];
