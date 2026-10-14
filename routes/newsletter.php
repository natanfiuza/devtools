<?php

use App\Http\Controllers\NewsletterController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
| Newsletter: inscricao pelo rodape e paginas de confirmar/descadastar.
|
| Tudo sob o prefixo de idioma: sao paginas do site (ou voltam para uma) e o
| link do e-mail ja leva o idioma do inscrito. Ver ADR-024.
*/

Route::prefix('{locale}')
    ->whereIn('locale', Locales::slugs())
    ->group(function (): void {
        Route::post('newsletter', [NewsletterController::class, 'subscribe'])
            ->middleware('throttle:10,1')
            ->name('newsletter.subscribe');

        Route::get('newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])
            ->name('newsletter.confirm');

        Route::post('newsletter/confirm/{token}', [NewsletterController::class, 'confirmStore'])
            ->middleware('throttle:10,1')
            ->name('newsletter.confirm.store');

        Route::get('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
            ->name('newsletter.unsubscribe');

        Route::post('newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribeStore'])
            ->middleware('throttle:10,1')
            ->name('newsletter.unsubscribe.store');
    });
