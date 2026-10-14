<?php

namespace App\Providers;

use App\Support\Locales;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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

        $this->configureDefaults();
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
