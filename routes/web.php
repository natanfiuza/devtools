<?php

use App\Http\Controllers\Auth\SetPasswordController;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// A raiz manda o visitante para o idioma dele (docs/SPEC.md secao 3).
Route::get('/', function (Request $request) {
    $slug = Locales::slug(Locales::preferred($request));
    $query = $request->getQueryString();

    return redirect()->to('/'.$slug.($query !== null ? '?'.$query : ''));
})->name('root');

// Paginas publicas: sempre prefixadas pelo idioma (SEO, hreflang).
Route::prefix('{locale}')
    ->whereIn('locale', Locales::slugs())
    ->group(function (): void {
        Route::inertia('/', 'Home')->name('home');
        Route::inertia('styleguide', 'Styleguide')->name('styleguide');
    });

// Definir a primeira senha: contas criadas por login social (ADR-023) chegam
// aqui quando o Fortify pede a senha em settings/security. O prefixo de idioma
// vale porque a tela de origem e a de confirmar senha, que e prefixed.
Route::prefix('{locale}')
    ->whereIn('locale', Locales::slugs())
    ->middleware(['auth', 'verified', 'throttle:6,1'])
    ->group(function (): void {
        Route::post('user/password/set', [SetPasswordController::class, 'store'])
            ->name('password.set');
    });

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth-social.php';
require __DIR__.'/newsletter.php';
