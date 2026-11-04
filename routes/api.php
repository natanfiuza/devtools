<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OutputTargetController;
use App\Http\Controllers\Api\V1\SqlQueryController;
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
| BadgeForge - badges estaticas (SPEC-BADGEFORGE §6, ADR-076).
|
| Stateless como o resto de `api/`: sem sessao, sem CSRF e sem throttle -
| estaticas nao consomem cota (SPEC §11). O `where` explicito em `message`
| e obrigatorio: o requisito padrao do Symfony exclui o ponto e usa
| quantificador possessivo, o que impediria `1.0.0.svg`.
*/
Route::prefix('badges')
    ->name('badges.')
    ->group(function (): void {
        Route::get('static/{label}/{message}.{ext}', StaticBadgeController::class)
            ->where('label', '[^/]+')
            ->where('message', '[^/]+')
            ->where('ext', 'svg|png')
            ->name('static');

        // F4: registrada depois da estatica de proposito. O Symfony casa na
        // ordem de registro, entao `/api/badges/static/...` continua vencendo
        // mesmo que `static` casasse o slug de provider. `{path}` aceita `/`
        // (pacote npm com escopo e `vendor/package`) e o controller separa a
        // extensao.
        Route::get('{provider}/{definition}/{path}', ProviderBadgeController::class)
            ->where('provider', '[a-z0-9][a-z0-9-]*')
            ->where('definition', '[a-z0-9][a-z0-9-]*')
            ->where('path', '.+\.(?:svg|png)')
            ->name('provider');
    });
