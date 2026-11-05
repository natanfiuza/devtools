<?php

use App\Http\Controllers\Api\V1\BadgeForge\BadgeAssetController;
use App\Http\Controllers\Api\V1\BadgeForge\BadgeLogoController;
use App\Http\Controllers\Api\V1\BadgeForge\CatalogController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OutputTargetController;
use App\Http\Controllers\Api\V1\SqlQueryController;
use App\Http\Controllers\BadgeForge\CustomProviderBadgeController;
use App\Http\Controllers\BadgeForge\EndpointBadgeController;
use App\Http\Controllers\BadgeForge\ProviderBadgeController;
use App\Http\Controllers\BadgeForge\StaticBadgeController;
use App\Http\Middleware\TrackApiTokenUsage;
use Illuminate\Support\Facades\Route;

/*
| API publica v1 (ADR-050, ADR-057).
|
| Autenticacao por Bearer token do Sanctum, sem sessao e sem CSRF. A ability
| `ai:query` e exigida em toda a superficie: um token criado para outra
| finalidade (no futuro) nao le consulta nem conta.
|
| A cota e a mesma do plano (`usage_ledger`), e nao uma cota paralela. Os
| limites de taxa sao por token e ficam em `config/api.php`.
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'active', 'verified', 'abilities:ai:query', TrackApiTokenUsage::class])
    ->group(function (): void {
        Route::get('me', MeController::class)
            ->middleware('throttle:api-read')
            ->name('me');

        Route::get('output-targets', [OutputTargetController::class, 'index'])
            ->middleware('throttle:api-read')
            ->name('output-targets.index');

        Route::get('sql-queries', [SqlQueryController::class, 'index'])
            ->middleware('throttle:api-read')
            ->name('sql-queries.index');

        Route::post('sql-queries', [SqlQueryController::class, 'store'])
            ->middleware('throttle:api-sql-post')
            ->name('sql-queries.store');

        // O ULID so aceita o formato; um id inventado responde 404 pelo
        // envelope, sem consultar o banco.
        Route::get('sql-queries/{aiRequest}', [SqlQueryController::class, 'show'])
            ->whereUlid('aiRequest')
            ->middleware('throttle:api-read')
            ->name('sql-queries.show');
    });

/*
| BadgeForge - API JSON de gestao (F8, ADR-088).
|
| Fora do grupo `v1/` de proposito: o token do AI SQL Builder (`ai:query`)
| nao pode ganhar escrita em badges por heranca de grupo. O catalogo e
| anonimo e cacheavel; logos e badges exigem as abilities `badgeforge:*`,
| que sao emitidas pela tela `/settings/api-tokens`.
*/
Route::prefix('v1/badgeforge')
    ->name('api.v1.badgeforge.')
    ->group(function (): void {
        Route::get('providers', [CatalogController::class, 'providers'])
            ->middleware('throttle:api-read')
            ->name('providers');

        Route::get('styles', [CatalogController::class, 'styles'])
            ->middleware('throttle:api-read')
            ->name('styles');

        Route::middleware([
            'auth:sanctum',
            'active',
            'verified',
            'abilities:badgeforge:read',
            TrackApiTokenUsage::class,
            'throttle:api-read',
        ])->group(function (): void {
            Route::get('logos', [BadgeLogoController::class, 'index'])->name('logos.index');

            Route::get('badges', [BadgeAssetController::class, 'index'])->name('badges.index');
        });

        // A escrita tem grupo e ability proprios: `badgeforge:read` nao cria
        // nem exclui nada. O `whereUlid` faz um id inventado responder 404
        // antes de chegar ao banco.
        Route::middleware([
            'auth:sanctum',
            'active',
            'verified',
            'abilities:badgeforge:write',
            TrackApiTokenUsage::class,
            'throttle:api-write',
        ])->group(function (): void {
            Route::post('logos', [BadgeLogoController::class, 'store'])->name('logos.store');

            Route::delete('logos/{logo}', [BadgeLogoController::class, 'destroy'])
                ->whereUlid('logo')
                ->name('logos.destroy');

            Route::post('badges', [BadgeAssetController::class, 'store'])->name('badges.store');

            Route::delete('badges/{badgeAsset}', [BadgeAssetController::class, 'destroy'])
                ->whereUlid('badgeAsset')
                ->name('badges.destroy');
        });
    });

/*
| BadgeForge - badges estaticas (SPEC-BADGEFORGE §6, ADR-076).
|
| Stateless como o resto de `api/`: sem sessao e sem CSRF. Estaticas nao
| consomem cota (SPEC §11), mas as imagens anonimas ganham o cap por IP
| `badgeforge-images` (F8, ADR-089) - generoso, contra varredura. O `where`
| explicito em `message` e obrigatorio: o requisito padrao do Symfony exclui o
| ponto e usa quantificador possessivo, o que impediria `1.0.0.svg`.
*/
Route::prefix('badges')
    ->name('badges.')
    ->group(function (): void {
        Route::get('static/{label}/{message}.{ext}', StaticBadgeController::class)
            ->where('label', '[^/]+')
            ->where('message', '[^/]+')
            ->where('ext', 'svg|png')
            ->middleware('throttle:badgeforge-images')
            ->name('static');

        // F5: a URL publica leva so o ULID; a URL de origem fica no servidor.
        // Registrada antes do provider para que `endpoint` nao seja lido como
        // slug de provider (e o catalogo tambem o reserva).
        Route::get('endpoint/{badgeEndpoint}.{ext}', EndpointBadgeController::class)
            ->where('badgeEndpoint', '[0-9A-Za-z]{1,64}')
            ->where('ext', 'svg|png')
            ->middleware('throttle:badgeforge-endpoint')
            ->name('endpoint');

        // F7: provider customizado (ADR-087). Tambem vem antes do provider
        // generico; `custom` e slug reservado no catalogo. O path aceita `/`
        // (owner/repo) e o controller valida exatamente N segmentos.
        Route::get('custom/{customProvider}/{definition}/{path}.{ext}', CustomProviderBadgeController::class)
            ->where('customProvider', '[0-9A-Za-z]{1,64}')
            ->where('definition', '[a-z0-9][a-z0-9-]*')
            ->where('path', '.+')
            ->where('ext', 'svg|png')
            ->middleware('throttle:badgeforge-custom')
            ->name('custom');

        // F4: registrada depois da estatica de proposito. O Symfony casa na
        // ordem de registro, entao `/api/badges/static/...` continua vencendo
        // mesmo que `static` casasse o slug de provider. `{path}` aceita `/`
        // (pacote npm com escopo e `vendor/package`) e o controller separa a
        // extensao.
        Route::get('{provider}/{definition}/{path}', ProviderBadgeController::class)
            ->where('provider', '[a-z0-9][a-z0-9-]*')
            ->where('definition', '[a-z0-9][a-z0-9-]*')
            ->where('path', '.+\.(?:svg|png)')
            ->middleware('throttle:badgeforge-images')
            ->name('provider');
    });
