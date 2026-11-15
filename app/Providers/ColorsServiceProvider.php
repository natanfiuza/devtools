<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Colors\AnonymousIdentity;
use Closure;
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

        // Curtir e seguir (F6, ADR-135/ADR-136). A conta tem chave propria; o
        // visitante e limitado por IP em HMAC, sem IP em texto no cache.
        RateLimiter::for('colors-social', function (Request $request): Limit {
            $user = $request->user();
            $account = $user instanceof User
                ? 'user:'.$user->getKey()
                : 'ip:'.AnonymousIdentity::ipKey($request);

            $perMinute = max(1, (int) config('colors.rates.social_per_minute', 60));

            return Limit::perMinute($perMinute)
                ->by('colors social:'.$account)
                ->response($this->rejection());
        });

        // Comentarios e respostas (F7, ADR-139): escrita exige conta, mas o
        // limiter aceita IP como fallback para nao virar chave unica em erro.
        RateLimiter::for('colors-comments', function (Request $request): Limit {
            $user = $request->user();
            $account = $user instanceof User
                ? 'user:'.$user->getKey()
                : 'ip:'.AnonymousIdentity::ipKey($request);

            $perMinute = max(1, (int) config('colors.rates.comment_per_minute', 10));

            return Limit::perMinute($perMinute)
                ->by('colors comments:'.$account)
                ->response($this->rejection());
        });

        // Denuncias (F7, ADR-141): mesma chave da conta, bucket separado, para
        // denunciar nao consumir o teto de comentar.
        RateLimiter::for('colors-reports', function (Request $request): Limit {
            $user = $request->user();
            $account = $user instanceof User
                ? 'user:'.$user->getKey()
                : 'ip:'.AnonymousIdentity::ipKey($request);

            $perMinute = max(1, (int) config('colors.rates.report_per_minute', 10));

            return Limit::perMinute($perMinute)
                ->by('colors reports:'.$account)
                ->response($this->rejection());
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
            ->response($this->rejection());
    }

    private function rejection(): Closure
    {
        return static function (Request $request) {
            if ($request->header('X-Inertia') !== null) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => __('colors.validation.rate_limited'),
                ]);

                return back();
            }

            return response()->json(['message' => __('colors.validation.rate_limited')], 429);
        };
    }
}
