<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Limites de taxa da API publica (ADR-050).
 *
 * A chave e o token, e nao a conta: duas integracoes da mesma conta nao
 * disputam a mesma janela. Quando nao ha token (rota publica ou pedido que
 * ainda vai levar 401), o IP separa os anonimos.
 */
class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api-sql-post', function (Request $request): array {
            $key = $this->key($request);

            return [
                Limit::perMinute(max(1, (int) config('api.rate_limits.post_per_minute', 30)))
                    ->by('api-sql-post:minute:'.$key),
                Limit::perHour(max(1, (int) config('api.rate_limits.post_per_hour', 300)))
                    ->by('api-sql-post:hour:'.$key),
            ];
        });

        RateLimiter::for('api-read', function (Request $request): Limit {
            return Limit::perMinute(max(1, (int) config('api.rate_limits.read_per_minute', 120)))
                ->by('api-read:'.$this->key($request));
        });
    }

    private function key(Request $request): string
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        return $token instanceof PersonalAccessToken
            ? 'token:'.$token->getKey()
            : 'ip:'.$request->ip();
    }
}
