<?php

use App\Http\Controllers\Account\BillingController;
use App\Http\Controllers\Account\HistoryController;
use App\Http\Controllers\Account\OverviewController;
use App\Http\Controllers\Api\AiQueryController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscriptionIntentController;
use App\Http\Controllers\ToolController;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

        // Doacao por Pix (F6): publica e indexavel, porque e conteudo do site.
        // O status usa ULID e fica fora do indice (e uma pagina de pagamento).
        Route::get('donate', [DonationController::class, 'index'])->name('donate');
        Route::post('donate', [DonationController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('donate.store');
        Route::get('donate/{donation}', [DonationController::class, 'status'])
            ->name('donate.status');

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

// API do AI SQL Builder (ADR-042). Fica fora do prefixo de idioma porque o
// cliente manda `Accept: application/json` e o idioma nao muda o recurso. Usa
// a sessao e o CSRF do grupo `web`; nao ha token de API nesta fase.
Route::prefix('api/ai')
    ->name('api.ai.')
    ->middleware(['auth', 'verified', 'active'])
    ->group(function (): void {
        Route::post('queries', [AiQueryController::class, 'store'])
            ->middleware('throttle:ai-post')
            ->name('queries.store');

        Route::get('history', [AiQueryController::class, 'history'])
            ->name('history');

        Route::get('queries/{aiRequest}', [AiQueryController::class, 'show'])
            ->whereUlid('aiRequest')
            ->middleware('throttle:ai-poll')
            ->name('queries.show');
    });

// Foto de perfil publica servida por UUID direto de storage/app/data/user_profile_image:
Route::get('users/profile/photo/{uuid}', [ProfilePhotoController::class, 'show'])
    ->name('users.profile.photo');

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

// Checkout online (F6): o POST cria a sessao e manda o navegador ao provedor;
// o GET de retorno e so a porta de volta. Nenhum dos dois confirma pagamento -
// isso e trabalho do webhook.
Route::post('subscribe/checkout', [CheckoutController::class, 'store'])
    ->middleware(['auth', 'active'])
    ->name('subscribe.checkout.store');
Route::get('subscribe/checkout/{checkoutSession}/return', [CheckoutController::class, 'return'])
    ->middleware(['auth', 'active'])
    ->name('subscribe.checkout.return');

// Area da conta: uma navegacao so - visao geral, historico, faturas - com as
// URLs de sempre (`/dashboard` continua sendo `/dashboard`).
//
// `active` em toda area autenticada: suspender derruba a sessao aberta na hora,
// e nao so barra o proximo login (ADR-025).
Route::middleware(['auth', 'verified', 'active'])->group(function (): void {
    Route::get('dashboard', OverviewController::class)->name('dashboard');
    Route::get('dashboard/history', [HistoryController::class, 'index'])->name('dashboard.history');

    // Escritas do historico: so plano pago (ADR-051). O GET acima fica aberto
    // para o Free ver o CTA de upgrade.
    Route::middleware('history.paid')->group(function (): void {
        Route::patch('dashboard/history/preference', [HistoryController::class, 'updatePreference'])
            ->name('dashboard.history.preference');
        Route::delete('dashboard/history', [HistoryController::class, 'destroy'])
            ->name('dashboard.history.destroy');
    });
    Route::get('dashboard/billing', BillingController::class)->name('dashboard.billing');

    // Encerrar a renovacao automatica (F6): o gateway e chamado antes do banco,
    // entao a recusa dele nao deixa a conta achando que cancelou.
    Route::post('dashboard/billing/cancel-recurring', [BillingController::class, 'cancelRecurring'])
        ->name('dashboard.billing.cancel-recurring');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth-social.php';
require __DIR__.'/newsletter.php';
require __DIR__.'/admin.php';
require __DIR__.'/webhooks.php';

// Ultimo recurso para um caminho que nao casou com rota nenhuma. Passa pelo
// grupo web (idioma, props compartilhadas), entao a 404 sai inteira e no idioma
// do prefixo da URL (ResolveLocale le o primeiro segmento). A API nunca cai
// aqui: o abort vira o envelope JSON pelo handler.
Route::fallback(function (Request $request) {
    abort_if($request->is('api/*'), 404);

    return Inertia::render('Error', ['status' => 404])
        ->toResponse($request)
        ->setStatusCode(404);
})->name('fallback.root');
