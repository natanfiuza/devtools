<?php

use App\Models\OutputTarget;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Pagina publica de documentacao da API v1 (`/{locale}/api-docs`, ADR-057) e
| especificacao OpenAPI (`/openapi.yaml`).
*/

it('renderiza a documentacao da API nos cinco idiomas com URL base, limites e alvos habilitados', function () {
    OutputTarget::factory()->create([
        'slug' => 'alvo-oculto',
        'enabled' => false,
    ]);

    foreach (Locales::all() as $locale) {
        $this->get('/'.$locale['slug'].'/api-docs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ApiDocs')
                ->where('baseUrl', rtrim((string) config('app.url'), '/').'/api/v1')
                ->where('openApiUrl', route('openapi'))
                ->where('tokensSettingsUrl', route('api-tokens.index'))
                ->where('rateLimits.postPerMinute', (int) config('api.rate_limits.post_per_minute', 30))
                ->where('rateLimits.postPerHour', (int) config('api.rate_limits.post_per_hour', 300))
                ->where('rateLimits.readPerMinute', (int) config('api.rate_limits.read_per_minute', 120))
                ->has('targets'));
    }
});

it('serve a especificacao openapi.yaml com o content-type esperado', function () {
    $response = $this->get('/openapi.yaml')->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('application/yaml')
        ->and($response->getContent())
        ->toContain('openapi: 3.1.0')
        ->toContain('/sql-queries');
});
