<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Locales;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passkeys nao fazem parte da autenticacao do projeto (docs/SPEC.md secao 7);
        // o pacote continua instalado porque o Fortify depende dele.
        Passkeys::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O {locale} das rotas de autenticacao so aceita os slugs suportados.
        // As rotas do Fortify sao registradas no boot dele, que roda depois
        // deste (ordem do bootstrap/providers.php), entao o pattern global
        // pega exatamente o grupo do Fortify; as nossas continuam com whereIn.
        Route::pattern('locale', implode('|', Locales::slugs()));

        $this->configureAuthorization();

        $this->configureDefaults();

        // Em producao, toda URL gerada sai em https - inclusive as que nascem
        // fora de um request (sitemap, e-mails). Ver ADR-052.
        if (config('security.force_https', false)) {
            URL::forceScheme('https');
        }
    }

    /**
     * Porta do painel administrativo (ADR-025). As regras por alvo (nao
     * suspender, excluir ou entrar como um admin) vivem na UserPolicy.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->isAdmin());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
