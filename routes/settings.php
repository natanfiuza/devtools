<?php

use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\ExportController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// `active` em toda a area autenticada: conta suspensa perde a sessao aberta
// (ADR-025).
Route::middleware(['auth', 'active'])->group(function () {
    // Redirect so de GET (Route::redirect aceita qualquer metodo e o
    // wayfinder gera uma definicao invalida para esse caso).
    Route::get('settings', fn () => redirect('/settings/profile'));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/photo', [ProfilePhotoController::class, 'update'])->name('profile.photo.update');
    Route::delete('settings/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('profile.photo.destroy');
});

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Exportar os proprios dados (ADR-028): download direto, sem tela no meio.
    Route::get('settings/export', ExportController::class)->name('profile.export');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    // Tokens da API publica (ADR-050): criar, listar e revogar. O texto puro
    // aparece uma vez, logo depois do POST.
    Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])
        ->name('api-tokens.index');
    Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])
        ->name('api-tokens.store');
    Route::delete('settings/api-tokens/{token}', [ApiTokenController::class, 'destroy'])
        ->whereNumber('token')
        ->name('api-tokens.destroy');
});
