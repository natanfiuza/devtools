<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Services\Payments\MercadoPagoGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Liga o contrato de pagamento ao cliente do provedor.
 *
 * A ligacao fica num provider (e nao num `new` espalhado) para que os testes
 * possam trocar o gateway por um fake sem tocar nas actions.
 */
final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, MercadoPagoGateway::class);
    }
}
