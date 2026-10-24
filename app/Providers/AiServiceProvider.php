<?php

namespace App\Providers;

use App\Contracts\Ai\LlmProvider;
use App\Models\User;
use App\Services\Ai\DeepSeekProvider;
use App\Support\Ai\AiConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Ligacao do provedor e limites de taxa do AI SQL Builder.
 *
 * O POST tem dois limites por conta (minuto e hora) e um por IP, porque uma
 * conta comprometida nao pode contornar o limite trocando de sessao e uma
 * mesma rede nao pode ser usada para multiplicar contas. O polling tem teto
 * proprio, mais alto: uma aba aberta consulta varias vezes por minuto.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmProvider::class, DeepSeekProvider::class);
    }

    public function boot(): void
    {
        AboutCommand::add('AI', function (): array {
            $model = trim((string) config('ai.model'));

            return [
                'Provider' => AiConfiguration::isAvailable() ? 'configured' : 'not configured',
                'Model' => $model !== '' ? $model : 'not configured',
            ];
        });

        RateLimiter::for('ai-post', function (Request $request): array {
            $user = $request->user();
            $account = $user instanceof User
                ? 'user:'.$user->getKey()
                : 'guest:'.$request->ip();

            return [
                Limit::perMinute(max(1, (int) config('ai.rate_limits.post_per_minute', 6)))
                    ->by('ai-post:minute:'.$account),
                Limit::perHour(max(1, (int) config('ai.rate_limits.post_per_hour', 60)))
                    ->by('ai-post:hour:'.$account),
                Limit::perMinute(max(1, (int) config('ai.rate_limits.post_per_minute_ip', 30)))
                    ->by('ai-post:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('ai-poll', function (Request $request): Limit {
            $user = $request->user();
            $account = $user instanceof User
                ? 'user:'.$user->getKey()
                : 'guest:'.$request->ip();

            return Limit::perMinute(max(1, (int) config('ai.rate_limits.poll_per_minute', 120)))
                ->by('ai-poll:'.$account);
        });
    }
}
