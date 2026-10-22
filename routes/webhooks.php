<?php

use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Notificacoes do gateway (F6).
|
| Fica fora do `{locale}` e sem sessao de conta: quem chama e o provedor, nao
| um navegador. A isencao de CSRF esta em `bootstrap/app.php`; a autenticidade
| vem da assinatura `x-signature`, conferida no controller.
*/

Route::post('webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->name('webhooks.mercadopago');
