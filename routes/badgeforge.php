<?php

use App\Http\Controllers\BadgeForge\BadgeForgeAccountController;
use App\Http\Controllers\BadgeForge\BadgePresetController;
use App\Http\Controllers\BadgeForge\CustomLogoController;
use Illuminate\Support\Facades\Route;

// Gestao autenticada do BadgeForge (F3, ADR-078/ADR-079).
//
// A geracao e o preview do badge continuam no navegador; estas rotas existem
// apenas para as acoes explicitas de conta: enviar/renomear/excluir logo,
// salvar/renomear/excluir preset e buscar os paths de um logo ao seleciona-lo.
// `active` acompanha o resto da area autenticada (ADR-025).
Route::middleware(['auth', 'verified', 'active'])->group(function (): void {
    Route::get('dashboard/badgeforge', BadgeForgeAccountController::class)
        ->name('dashboard.badgeforge');

    Route::middleware('throttle:badgeforge-management')->group(function (): void {
        Route::post('badgeforge/logos', [CustomLogoController::class, 'store'])
            ->name('badgeforge.logos.store');
    });

    // A leitura sob demanda alimenta o preview do builder; fica fora do
    // throttle de escrita e so responde ao dono (404 para o resto).
    Route::get('badgeforge/logos/{badgeCustomLogo}', [CustomLogoController::class, 'show'])
        ->whereUlid('badgeCustomLogo')
        ->name('badgeforge.logos.show');

    Route::middleware('throttle:badgeforge-management')->group(function (): void {
        Route::patch('badgeforge/logos/{badgeCustomLogo}', [CustomLogoController::class, 'update'])
            ->whereUlid('badgeCustomLogo')
            ->name('badgeforge.logos.update');
        Route::delete('badgeforge/logos/{badgeCustomLogo}', [CustomLogoController::class, 'destroy'])
            ->whereUlid('badgeCustomLogo')
            ->name('badgeforge.logos.destroy');

        Route::post('badgeforge/presets', [BadgePresetController::class, 'store'])
            ->name('badgeforge.presets.store');
        Route::patch('badgeforge/presets/{badgePreset}', [BadgePresetController::class, 'update'])
            ->whereUlid('badgePreset')
            ->name('badgeforge.presets.update');
        Route::delete('badgeforge/presets/{badgePreset}', [BadgePresetController::class, 'destroy'])
            ->whereUlid('badgePreset')
            ->name('badgeforge.presets.destroy');
    });
});
