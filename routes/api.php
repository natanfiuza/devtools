<?php

use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OutputTargetController;
use App\Http\Controllers\Api\V1\SqlQueryController;
use Illuminate\Support\Facades\Route;

/*
| API publica v1 (ADR-050).
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
    ->middleware(['auth:sanctum', 'active', 'verified', 'abilities:ai:query'])
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
