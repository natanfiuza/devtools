<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Services\Payments\MercadoPagoGateway;
use App\Support\Payments\PixPayload;
use Illuminate\Foundation\Console\AboutCommand;
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

    public function boot(): void
    {
        AboutCommand::add('Payments', function (): array {
            $online = $this->app->make(PaymentGateway::class)->isAvailable();
            $webhookSecret = trim((string) config('payments.mercadopago.webhook_secret')) !== '';
            $staticPix = PixPayload::isConfigured();

            return [
                'Gateway' => (string) config('payments.gateway', 'mercadopago'),
                'Mercado Pago Token' => $online ? 'configured' : 'not configured',
                'Mercado Pago Webhook Secret' => $webhookSecret ? 'configured' : 'not configured',
                'Donation Mode' => $online
                    ? 'dynamic (mercadopago)'
                    : ($staticPix ? 'static pix' : 'unavailable'),
            ];
        });
    }
}
