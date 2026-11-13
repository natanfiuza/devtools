<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * Limites de taxa das paletas (F4, ADR-123).
 *
 * A chave e a conta: as mutacoes partem de um dialogo no navegador e a recusa
 * volta como toast, nao como 429 cru. O upload de OG tem limite proprio para
 * nao transformar a geracao da imagem em gravacao em disco ilimitada.
 */
final class ColorsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('colors-management', function (Request $request): Limit {
            return $this->limit($request, 'management_per_minute', 'colors management');
        });

        RateLimiter::for('colors-og', function (Request $request): Limit {
            return $this->limit($request, 'og_per_minute', 'colors og');
        });

        // Historico/favoritos de cores (F5, ADR-127): escrita frequente,
        // limiter proprio para nao competir com a gestao de paletas.
        RateLimiter::for('colors-library', function (Request $request): Limit {
            return $this->limit($request, 'library_per_minute', 'colors library');
        });
    }

    private function limit(Request $request, string $key, string $bucket): Limit
    {
        $user = $request->user();
        $account = $user instanceof User
            ? 'user:'.$user->getKey()
            : 'guest:'.$request->ip();

        $perMinute = max(1, (int) config('colors.rates.'.$key, 60));

        return Limit::perMinute($perMinute)
            ->by($bucket.':'.$account)
            ->response(static function (Request $request) {
                if ($request->header('X-Inertia') !== null) {
                    Inertia::flash('toast', [
                        'type' => 'error',
                        'message' => __('colors.validation.rate_limited'),
                    ]);

                    return back();
                }

                return response()->json(['message' => __('colors.validation.rate_limited')], 429);
            });
    }
}
