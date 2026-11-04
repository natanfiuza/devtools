<?php

use App\Http\Controllers\BadgeForge\BadgeForgeAccountController;
use App\Http\Controllers\BadgeForge\BadgePresetController;
use App\Http\Controllers\BadgeForge\CustomLogoController;
use App\Http\Controllers\BadgeForge\EndpointManagementController;
use App\Http\Controllers\BadgeForge\PersonalBadgeManagementController;
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

        // Badges pessoais (F4, ADR-081). A URL publica e stateless; aqui so
        // ficam as acoes do dono. Refresh tem throttle proprio porque gera
        // chamada externa.
        Route::post('badgeforge/badges', [PersonalBadgeManagementController::class, 'store'])
            ->name('badgeforge.badges.store');
        Route::patch('badgeforge/badges/{badgeAsset}', [PersonalBadgeManagementController::class, 'update'])
            ->whereUlid('badgeAsset')
            ->name('badgeforge.badges.update');
        Route::patch('badgeforge/badges/{badgeAsset}/credential', [PersonalBadgeManagementController::class, 'credential'])
            ->whereUlid('badgeAsset')
            ->name('badgeforge.badges.credential');
        Route::delete('badgeforge/badges/{badgeAsset}', [PersonalBadgeManagementController::class, 'destroy'])
            ->whereUlid('badgeAsset')
            ->name('badgeforge.badges.destroy');

        // Endpoints JSON (F5, ADR-083). A URL de origem so existe aqui e no
        // servidor; a imagem publica e a rota com ULID em `routes/api.php`.
        Route::post('badgeforge/endpoints', [EndpointManagementController::class, 'store'])
            ->name('badgeforge.endpoints.store');
        Route::patch('badgeforge/endpoints/{badgeEndpoint}', [EndpointManagementController::class, 'update'])
            ->whereUlid('badgeEndpoint')
            ->name('badgeforge.endpoints.update');
        Route::delete('badgeforge/endpoints/{badgeEndpoint}', [EndpointManagementController::class, 'destroy'])
            ->whereUlid('badgeEndpoint')
            ->name('badgeforge.endpoints.destroy');
    });

    Route::post('badgeforge/badges/{badgeAsset}/refresh', [PersonalBadgeManagementController::class, 'refresh'])
        ->whereUlid('badgeAsset')
        ->middleware('throttle:badgeforge-refresh')
        ->name('badgeforge.badges.refresh');
});
