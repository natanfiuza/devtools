<?php

use App\Http\Controllers\Account\BillingController;
use App\Http\Controllers\Account\HistoryController;
use App\Http\Controllers\Account\OverviewController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscriptionIntentController;
use App\Http\Controllers\ToolController;
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
        Route::get('/', HomeController::class)->name('home');
        Route::get('pricing', PricingController::class)->name('pricing');
        Route::inertia('styleguide', 'Styleguide')->name('styleguide');

        // Paginas legais: conteudo proprio, entao continuam indexaveis e com
        // hreflang normal. Ficam linkadas no rodape do site.
        Route::get('privacy', [LegalController::class, 'privacy'])->name('privacy');
        Route::get('terms', [LegalController::class, 'terms'])->name('terms');

        // Rota generica das ferramentas (docs/SPEC.md secao 4): a pagina e uma
        // so, e o componente de cada tool entra por dentro dela. Sem restricao
        // de formato no slug - o 404 de tool que nao funciona e regra de
        // negocio, nao de URL.
        Route::get('tools/{slug}', ToolController::class)->name('tools.show');
    });

// Sitemap e robots: sao de todo o site, nao de um idioma, entao ficam fora do
// prefixo. O `robots.txt` de public/ saiu do repositorio - arquivo estatico
// vence a rota, no nginx e no `artisan serve`.
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('robots.txt', RobotsController::class)->name('robots');

// Definir a primeira senha: contas criadas por login social (ADR-023) chegam
// aqui quando o Fortify pede a senha em settings/security. O prefixo de idioma
// vale porque a tela de origem e a de confirmar senha, que e prefixed.
Route::prefix('{locale}')
    ->whereIn('locale', Locales::slugs())
    ->middleware(['auth', 'verified', 'active', 'throttle:6,1'])
    ->group(function (): void {
        Route::post('user/password/set', [SetPasswordController::class, 'store'])
            ->name('password.set');
    });

// Intencao de assinatura Pro (ADR-033): o GET aceita visitante para guardar a
// URL pretendida (`url.intended`) e redirecionar ao cadastro; o POST exige
// conta ativa (sem exigir `verified` para nao barrar quem acabou de cadastrar).
Route::get('subscribe', [SubscriptionIntentController::class, 'create'])
    ->middleware('active')
    ->name('subscribe');
Route::post('subscribe', [SubscriptionIntentController::class, 'store'])
    ->middleware(['auth', 'active'])
    ->name('subscribe.store');

// Area da conta: uma navegacao so - visao geral, historico, faturas - com as
// URLs de sempre (`/dashboard` continua sendo `/dashboard`).
//
// `active` em toda area autenticada: suspender derruba a sessao aberta na hora,
// e nao so barra o proximo login (ADR-025).
Route::middleware(['auth', 'verified', 'active'])->group(function (): void {
    Route::get('dashboard', OverviewController::class)->name('dashboard');
    Route::get('dashboard/history', HistoryController::class)->name('dashboard.history');
    Route::get('dashboard/billing', BillingController::class)->name('dashboard.billing');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth-social.php';
require __DIR__.'/newsletter.php';
require __DIR__.'/admin.php';
