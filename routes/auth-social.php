<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Support\SocialProviders;
use Illuminate\Support\Facades\Route;

/*
| Login social (Google e GitHub). Ver ADR-023.
|
| Endpoints de maquina, de proposito FORA do prefixo de idioma (como /api e
| /webhooks): a URL de callback e cadastrada uma unica vez no console do
| provedor, e o idioma do visitante viaja na sessao (ADR-022).
*/

Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', SocialProviders::all())
    ->middleware('throttle:10,1')
    ->name('auth.redirect');

Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', SocialProviders::all())
    ->middleware('throttle:10,1')
    ->name('auth.callback');
