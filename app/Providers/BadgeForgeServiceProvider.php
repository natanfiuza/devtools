<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * Limite de taxa da gestao do BadgeForge (ADR-078/ADR-079).
 *
 * A chave e a conta: uploads e mutacoes sao raros e o objetivo e conter um
 * cliente em loop, nao disputar banda entre integracoes. A recusa volta como
 * toast no `back()` em vez do 429 cru, porque as acoes partem de um dialogo no
 * navegador. Os numeros vivem em `config/badgeforge.php`.
 */
final class BadgeForgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('badgeforge-management', function (Request $request): Limit {
            $user = $request->user();
            $account = $user instanceof User
                ? 'user:'.$user->getKey()
                : 'guest:'.$request->ip();

            $decaySeconds = max(1, (int) config('badgeforge.management.rate_decay_seconds', 60));

            return Limit::perMinutes(
                max(1, (int) ceil($decaySeconds / 60)),
                max(1, (int) config('badgeforge.management.rate_limit', 20)),
            )
                ->by('badgeforge-management:'.$account)
                ->response(function (): RedirectResponse {
                    Inertia::flash('toast', [
                        'type' => 'warning',
                        'message' => __('tools.badgeforge.management.throttled'),
                    ]);

                    return back();
                });
        });
    }
}
