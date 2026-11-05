<?php

namespace App\Providers;

use App\Models\BadgeCustomProvider;
use App\Models\BadgeEndpoint;
use App\Models\User;
use App\Support\BadgeForge\Analytics\CountryReader;
use App\Support\BadgeForge\Analytics\MaxMindCountryReader;
use App\Support\BadgeForge\Endpoint\DnsHostResolver;
use App\Support\BadgeForge\Endpoint\HostResolver;
use App\Support\BadgeForge\Providers\ProviderCatalog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

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
    public function register(): void
    {
        $this->app->singleton(ProviderCatalog::class);
        $this->app->bind(HostResolver::class, DnsHostResolver::class);

        // GeoLite2 e opcional: o leitor abre o arquivo na primeira consulta e
        // devolve `null` quando ele nao existe ou esta corrompido. O caminho
        // vem da config; testes trocam o binding por um leitor controlado.
        $this->app->singleton(CountryReader::class, static fn (): CountryReader => new MaxMindCountryReader(
            (string) config('badgeforge.analytics.geoip_path', ''),
        ));
    }

    public function boot(): void
    {
        // O refresh de badge pessoal dispara uma chamada externa: o limite e
        // separado do CRUD e responde JSON, porque a UI chama por fetch.
        RateLimiter::for('badgeforge-refresh', function (Request $request): Limit {
            $user = $request->user();
            $account = $user instanceof User
                ? 'user:'.$user->getKey()
                : 'guest:'.$request->ip();

            $decaySeconds = max(1, (int) config('badgeforge.providers.refresh_rate_decay_seconds', 60));

            return Limit::perMinutes(
                max(1, (int) ceil($decaySeconds / 60)),
                max(1, (int) config('badgeforge.providers.refresh_rate_limit', 10)),
            )
                ->by('badgeforge-refresh:'.$account)
                ->response(static fn (): JsonResponse => response()->json([
                    'message' => __('tools.badgeforge.management.throttled'),
                ], 429));
        });

        // A imagem de endpoint e anonima: o limite e por dono, derivado do
        // ULID na rota. O `ThrottleRequests` roda antes do `SubstituteBindings`,
        // entao aqui chega o ULID cru - nunca tipar o parametro como model.
        // ULID invalido/desconhecido ou banco fora cai no anonimo por IP.
        RateLimiter::for('badgeforge-endpoint', function (Request $request): Limit {
            $decaySeconds = max(1, (int) config('badgeforge.endpoint.rate_limit.decay_seconds', 60));
            $perMinute = max(1, (int) config('badgeforge.endpoint.rate_limit.anonymous', 60));
            $account = 'guest:'.$request->ip();

            $id = $request->route('badgeEndpoint');

            if (is_string($id) && Str::isUlid($id)) {
                try {
                    $endpoint = BadgeEndpoint::query()->with('user')->find($id);
                    $owner = $endpoint?->user;

                    if ($owner instanceof User) {
                        $isFree = $owner->effectivePlan()->isFree();
                        $account = 'user:'.$owner->getKey();
                        $perMinute = max(1, (int) config(
                            $isFree
                                ? 'badgeforge.endpoint.rate_limit.free'
                                : 'badgeforge.endpoint.rate_limit.paid',
                            $isFree ? 300 : 3_000,
                        ));
                    }
                } catch (Throwable) {
                    // Banco fora: segue no anonimo, sem 500.
                }
            }

            return Limit::perMinutes(
                max(1, (int) ceil($decaySeconds / 60)),
                $perMinute,
            )->by('badgeforge-endpoint:'.$account);
        });

        // Imagem de provider customizado (F7, ADR-087): mesmo desenho do
        // endpoint, com o limite do dono do ULID; invalido/desconhecido ou
        // banco fora cai no anonimo por IP.
        RateLimiter::for('badgeforge-custom', function (Request $request): Limit {
            $decaySeconds = max(1, (int) config('badgeforge.custom_providers.rate_limit.decay_seconds', 60));
            $perMinute = max(1, (int) config('badgeforge.custom_providers.rate_limit.anonymous', 60));
            $account = 'guest:'.$request->ip();

            $id = $request->route('customProvider');

            if (is_string($id) && Str::isUlid($id)) {
                try {
                    $provider = BadgeCustomProvider::query()->with('user')->find($id);
                    $owner = $provider?->user;

                    if ($owner instanceof User) {
                        $isFree = $owner->effectivePlan()->isFree();
                        $account = 'user:'.$owner->getKey();
                        $perMinute = max(1, (int) config(
                            $isFree
                                ? 'badgeforge.custom_providers.rate_limit.free'
                                : 'badgeforge.custom_providers.rate_limit.paid',
                            $isFree ? 300 : 3_000,
                        ));
                    }
                } catch (Throwable) {
                    // Banco fora: segue no anonimo, sem 500.
                }
            }

            return Limit::perMinutes(
                max(1, (int) ceil($decaySeconds / 60)),
                $perMinute,
            )->by('badgeforge-custom:'.$account);
        });

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
