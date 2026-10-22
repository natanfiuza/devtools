<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureHistoryHasPaidPlan;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleConsent;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveLocale;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use App\Jobs\ExecuteCommand;
use App\Models\TaskSchedule;
use App\Support\ApiError;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Preferencias escritas pelo navegador (tema, idioma e consentimento)
        // chegam em claro: quem as grava e o proprio cliente.
        $middleware->encryptCookies(except: ['appearance', 'locale', 'sidebar_state', 'consent']);

        // O gateway nao tem sessao nem token CSRF: a autenticidade da
        // notificacao vem de `x-signature`, conferida no controller (F6).
        $middleware->validateCsrfTokens(except: ['webhooks/mercadopago']);

        // Proxies do nginx do VPS (ADR-052). A lista vem de TRUSTED_PROXIES e e
        // lida por request; sem ela, IP e esquema sao os da conexao direta.
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);

        // Nonce da CSP e headers de seguranca em toda resposta (ADR-052).
        $middleware->append(SecurityHeaders::class);

        // `api/*` fala JSON inclusive quando o cliente nao manda `Accept`
        // (ADR-050): sem isso o 401 tentaria redirecionar para o login.
        $middleware->api(prepend: [ForceJsonResponse::class]);

        // Conta suspensa perde a sessao (ADR-025). Fica como alias porque vale
        // so nas areas autenticadas: nas paginas publicas nao se consulta a
        // conta, e derrubar ali surpreenderia quem so esta lendo o site.
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'history.paid' => EnsureHistoryHasPaidPlan::class,
            // O Sanctum v4 nao registra estes aliases sozinho; a API publica
            // usa `abilities:ai:query` em toda rota (ADR-050).
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        $middleware->web(append: [
            // O idioma precisa ser resolvido antes das props compartilhadas.
            ResolveLocale::class,
            HandleAppearance::class,
            // So para limpar o cookie de consentimento invalido (ADR-013).
            HandleConsent::class,
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

        // Envelope unico da API: o `code` e o contrato que a tela traduz e que
        // o cliente da API v1 trata. Vale para `api/ai/*` (sessao, F5) e para
        // `api/v1/*` (token, F7).
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::from($exception);
        });

        // 404 do site ganha a pagina propria (F7). So troca HTML de navegacao:
        // API e qualquer requisicao que espera JSON seguem o caminho normal.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 404
                || $request->is('api/*')
                || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('Error', ['status' => 404])
                ->toResponse($request)
                ->setStatusCode(404);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        try {
            $tasks = TaskSchedule::where('is_active', true)->get();

            foreach ($tasks as $task) {
                $command = $task->command;
                $parameters = $task->parameters ?? [];
                $expression = $task->expression;

                if (empty($command)) {
                    continue;
                }

                $event = $schedule->call(function () use ($command, $parameters): void {
                    ExecuteCommand::dispatch($command, $parameters);
                });

                if (str_starts_with($expression, 'dailyAt:')) {
                    $time = substr($expression, 8);
                    $event->dailyAt($time);
                } elseif ($expression === 'hourly') {
                    $event->hourly();
                } elseif ($expression === 'everyMinute') {
                    $event->everyMinute();
                } else {
                    $event->cron($expression);
                }
            }
        } catch (Throwable $e) {
            // Em comandos como migrate inicial ou sem conexao de banco pronta, ignora silenciosamente
            Log::info('[Scheduler] Agendamento ignorado: '.$e->getMessage());
        }
    })
    ->create();
