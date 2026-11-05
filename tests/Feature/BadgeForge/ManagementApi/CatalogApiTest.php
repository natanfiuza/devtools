<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Catalogo publico da API de gestao do BadgeForge (F8, ADR-088).
|
| Providers e estilos sao anonimos, cacheaveis e revalidaveis por ETag. O
| catalogo nunca expoe template de URL, clientes ou credenciais.
*/

it('serve o catálogo de providers sem token e sem segredos', function () {
    $response = $this->getJson(route('api.v1.badgeforge.providers'))->assertOk();

    $payload = $response->json();

    expect($payload['data'])->toBeArray()->not->toBeEmpty();

    $provider = $payload['data'][0];

    expect($provider)->toHaveKeys([
        'slug', 'name', 'category', 'requiresAuth', 'requiresUsername',
        'documentationUrl', 'definitions',
    ])->and($provider['definitions'])->toBeArray();

    $definition = $provider['definitions'][0];

    expect($definition)->toHaveKeys([
        'slug', 'name', 'label', 'supportsPrivate', 'examplePath',
        'pathPattern', 'params',
    ])->and($definition['params'][0])->toHaveKeys([
        'name', 'required', 'pattern', 'allowSlash', 'placeholderKey',
    ]);

    expect((string) $response->getContent())
        ->not->toContain('url_template')
        ->not->toContain('client_key')
        ->not->toContain('extractor_key')
        ->not->toContain('credential');
});

it('responde 304 com If-None-Match e cache público no catálogo', function () {
    $first = $this->getJson(route('api.v1.badgeforge.providers'))->assertOk();

    expect($first->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=300');

    $etag = $first->headers->get('ETag');

    expect($etag)->toBeString()->not->toBeEmpty();

    $this->withHeaders(['If-None-Match' => (string) $etag])
        ->getJson(route('api.v1.badgeforge.providers'))
        ->assertStatus(304)
        ->assertContent('');
});

it('serve a lista de estilos sem token', function () {
    $response = $this->getJson(route('api.v1.badgeforge.styles'))->assertOk();

    $styles = $response->json('data');

    expect($styles)->toBeArray()->not->toBeEmpty()
        ->and($styles)->toContain('flat')
        ->and($styles[0])->toBeString();
});
