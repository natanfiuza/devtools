<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGateway;
use App\Services\Payments\MercadoPagoGateway;
use App\Support\Payments\PixPayload;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

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
        AboutCommand::add('Payments', static function (): array {
            $online = app(PaymentGateway::class)->isAvailable();
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

        // Consulta sob demanda da doacao ("Ja paguei"). A chave e o IP e nao o
        // nome da rota: o prefixo de idioma muda a URI, mas nao o pagador.
        RateLimiter::for('donation-check', function (Request $request): Limit {
            return Limit::perMinute(max(1, (int) config('donations.check_per_minute', 5)))
                ->by('donation-check:'.$request->ip())
                ->response(function (): RedirectResponse {
                    Inertia::flash('toast', [
                        'type' => 'warning',
                        'message' => __('donations.check.throttled'),
                    ]);

                    return back();
                });
        });
    }
}
