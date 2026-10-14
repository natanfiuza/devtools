<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Preferencias escritas pelo navegador (tema e idioma) chegam em claro.
        $middleware->encryptCookies(except: ['appearance', 'locale', 'sidebar_state']);

        // Conta suspensa perde a sessao (ADR-025). Fica como alias porque vale
        // so nas areas autenticadas: nas paginas publicas nao se consulta a
        // conta, e derrubar ali surpreenderia quem so esta lendo o site.
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
        ]);

        $middleware->web(append: [
            // O idioma precisa ser resolvido antes das props compartilhadas.
            ResolveLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Dentro do grupo o ResolveLocale ficaria depois dos middlewares que o
        // framework prioriza - e o Authenticate e um deles. Sem isto, o
        // visitante sem sessao que abre /dashboard e mandado para o login antes
        // de o idioma ser resolvido: o route('login') do redirect sai sem o
        // {locale} (UrlGenerationException no servidor) ou no idioma errado.
        // Entra logo antes do Authenticate, depois do StartSession, que e de
        // onde saem a sessao e o cookie de idioma.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveLocale::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
