<?php

use App\Http\Controllers\Api\Colors\ColorFavoriteController;
use App\Http\Controllers\Api\Colors\ColorHistoryController;
use App\Http\Controllers\Api\Colors\PaletteFavoriteController;
use App\Http\Controllers\Colors\PaletteController;
use App\Http\Controllers\Colors\PaletteDuplicationController;
use App\Http\Controllers\Colors\PaletteIntentController;
use App\Http\Controllers\Colors\PaletteOgController;
use App\Http\Controllers\Colors\PaletteTagController;
use App\Http\Controllers\Colors\PublicPaletteController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

// Paletas persistidas (F4, ADR-123 a ADR-126).
//
// A area da conta segue a convencao do dashboard: sem prefixo de idioma
// (ADR-022), porque o dono ja escolheu o idioma na sessao. A pagina publica
// e a unica com `{locale}`, para canonical/hreflang e SEO.
Route::middleware(['auth', 'verified', 'active'])->group(function (): void {
    // Biblioteca pessoal de cores (F5, ADR-127). JSON consumido pelo
    // navegador; o anonimo continua no localStorage. As escritas usam um
    // limiter proprio porque o picker pode finalizar com frequencia.
    Route::prefix('api/tools/colors')
        ->name('api.tools.colors.')
        ->group(function (): void {
            Route::get('history', [ColorHistoryController::class, 'index'])
                ->name('history.index');
            Route::get('favorites', [ColorFavoriteController::class, 'index'])
                ->name('favorites.index');

            Route::middleware('throttle:colors-library')->group(function (): void {
                Route::post('history', [ColorHistoryController::class, 'store'])
                    ->name('history.store');
                Route::delete('history', [ColorHistoryController::class, 'destroy'])
                    ->name('history.destroy');
                Route::post('history/import', [ColorHistoryController::class, 'import'])
                    ->name('history.import');

                Route::post('favorites', [ColorFavoriteController::class, 'store'])
                    ->name('favorites.store');
                Route::delete('favorites', [ColorFavoriteController::class, 'destroy'])
                    ->name('favorites.destroy');

                // Paletas publicas de outras contas (F5, ADR-128). O vinculo
                // e da conta; a paleta nao e copiada.
                Route::post('palettes/{colorPalette}/favorite', [PaletteFavoriteController::class, 'store'])
                    ->whereUlid('colorPalette')
                    ->name('palettes.favorite.store');
                Route::delete('palettes/{colorPalette}/favorite', [PaletteFavoriteController::class, 'destroy'])
                    ->whereUlid('colorPalette')
                    ->name('palettes.favorite.destroy');
            });
        });

    Route::get('dashboard/colors/palettes', [PaletteController::class, 'index'])
        ->name('dashboard.colors.palettes.index');

    // Intencao de criar: nao ha formulario proprio, o gerador e a criacao.
    Route::get('dashboard/colors/palettes/create', [PaletteIntentController::class, 'create'])
        ->name('dashboard.colors.palettes.create');

    Route::middleware('throttle:colors-management')->group(function (): void {
        Route::post('dashboard/colors/palettes', [PaletteController::class, 'store'])
            ->name('dashboard.colors.palettes.store');

        Route::patch('dashboard/colors/palettes/{colorPalette}', [PaletteController::class, 'update'])
            ->whereUlid('colorPalette')
            ->name('dashboard.colors.palettes.update');

        Route::patch('dashboard/colors/palettes/{colorPalette}/archive', [PaletteController::class, 'archive'])
            ->whereUlid('colorPalette')
            ->name('dashboard.colors.palettes.archive');

        Route::patch('dashboard/colors/palettes/{colorPalette}/restore', [PaletteController::class, 'restore'])
            ->whereUlid('colorPalette')
            ->name('dashboard.colors.palettes.restore');

        Route::delete('dashboard/colors/palettes/{colorPalette}', [PaletteController::class, 'destroy'])
            ->whereUlid('colorPalette')
            ->name('dashboard.colors.palettes.destroy');

        Route::post('dashboard/colors/palettes/{colorPalette}/duplicate', [PaletteDuplicationController::class, 'store'])
            ->whereUlid('colorPalette')
            ->name('dashboard.colors.palettes.duplicate');

        // Tags da conta: reordenar vem antes do `{colorTag}` para nao ser
        // confundido com um ULID.
        Route::post('dashboard/colors/tags', [PaletteTagController::class, 'store'])
            ->name('dashboard.colors.tags.store');
        Route::patch('dashboard/colors/tags/reorder', [PaletteTagController::class, 'reorder'])
            ->name('dashboard.colors.tags.reorder');
        Route::patch('dashboard/colors/tags/{colorTag}', [PaletteTagController::class, 'update'])
            ->whereUlid('colorTag')
            ->name('dashboard.colors.tags.update');
        Route::delete('dashboard/colors/tags/{colorTag}', [PaletteTagController::class, 'destroy'])
            ->whereUlid('colorTag')
            ->name('dashboard.colors.tags.destroy');
    });

    // O upload de OG grava arquivo e tem teto proprio, mais baixo.
    Route::middleware('throttle:colors-og')->group(function (): void {
        Route::post('dashboard/colors/palettes/{colorPalette}/og', [PaletteOgController::class, 'store'])
            ->whereUlid('colorPalette')
            ->name('dashboard.colors.palettes.og.store');
        Route::delete('dashboard/colors/palettes/{colorPalette}/og', [PaletteOgController::class, 'destroy'])
            ->whereUlid('colorPalette')
            ->name('dashboard.colors.palettes.og.destroy');
    });

    Route::get('dashboard/colors/palettes/{colorPalette}/edit', [PaletteController::class, 'edit'])
        ->whereUlid('colorPalette')
        ->name('dashboard.colors.palettes.edit');
});

// Pagina publica compartilhada entre idiomas: o slug e global e imutavel.
Route::prefix('{locale}')
    ->whereIn('locale', Locales::slugs())
    ->group(function (): void {
        $slug = '[a-z0-9-]{1,60}';

        Route::get('colors/p/{slug}', [PublicPaletteController::class, 'show'])
            ->where('slug', $slug)
            ->name('colors.palettes.show');

        Route::get('colors/p/{slug}/og.png', [PaletteOgController::class, 'show'])
            ->where('slug', $slug)
            ->name('colors.palettes.og');

        // "Duplicar" exige conta; o GET e so a intencao que sobrevive ao
        // login e volta para a pagina com o fluxo aberto.
        Route::get('colors/p/{slug}/duplicate', [PaletteIntentController::class, 'duplicate'])
            ->where('slug', $slug)
            ->middleware(['auth', 'verified', 'active'])
            ->name('colors.palettes.duplicate.create');

        Route::post('colors/p/{slug}/duplicate', [PaletteDuplicationController::class, 'storePublic'])
            ->where('slug', $slug)
            ->middleware(['auth', 'verified', 'active', 'throttle:colors-management'])
            ->name('colors.palettes.duplicate.store');
    });
