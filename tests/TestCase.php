<?php

namespace Tests;

use App\Support\Locales;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fora de uma requisicao nao existe slug na URL: sem este default,
        // route('login') e todo redirect do Fortify estouram
        // UrlGenerationException por falta do parametro {locale} (ADR-022).
        // Durante a requisicao quem manda e o middleware ResolveLocale.
        URL::defaults(['locale' => Locales::slug(Locales::fallback())]);

        // O build do Vite e um artefato do deploy (public/build nao vai para o
        // git): os testes nao podem depender dele estar atualizado.
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
