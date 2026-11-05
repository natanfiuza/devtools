<?php

use App\Models\BadgeCustomProvider;
use App\Models\BadgeEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
| Hardening da superficie de imagem (F8, ADR-089).
|
| A matriz e uniforme nos tres controllers com imagem publica: banco fora vira
| 503 `badge_unavailable` com `Retry-After` e `no-store`; recurso inexistente
| vira 404 `not_found` no envelope; nenhum dos dois volta HTML nem 500.
*/

it('responde 503 badge_unavailable quando o banco cai no endpoint', function () {
    $endpoint = BadgeEndpoint::factory()->create();

    Schema::rename('badge_endpoints', 'badge_endpoints_gone');

    try {
        $response = $this->get('/api/badges/endpoint/'.$endpoint->id.'.svg')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('error.code', 'badge_unavailable');
    } finally {
        Schema::rename('badge_endpoints_gone', 'badge_endpoints');
    }

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $response->headers->get('Content-Type'))->toContain('application/json');
});

it('responde 503 badge_unavailable quando o banco cai no provider customizado', function () {
    $provider = BadgeCustomProvider::factory()->create();

    Schema::rename('badge_custom_providers', 'badge_custom_providers_gone');

    try {
        $response = $this->get('/api/badges/custom/'.$provider->id.'/stars/natanfiuza/devtools.svg')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('error.code', 'badge_unavailable');
    } finally {
        Schema::rename('badge_custom_providers_gone', 'badge_custom_providers');
    }

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('responde 503 sem vazar detalhe de infraestrutura', function () {
    $endpoint = BadgeEndpoint::factory()->create();

    Schema::rename('badge_endpoints', 'badge_endpoints_gone');

    try {
        $body = (string) $this->get('/api/badges/endpoint/'.$endpoint->id.'.svg')
            ->assertStatus(503)
            ->getContent();
    } finally {
        Schema::rename('badge_endpoints_gone', 'badge_endpoints');
    }

    expect($body)->not->toContain('SQLSTATE')
        ->and($body)->not->toContain('badge_endpoints')
        ->and($body)->not->toContain($endpoint->id);
});

it('responde 404 de recurso no envelope em toda a superficie de imagem', function () {
    $this->get('/api/badges/endpoint/01JZZZZZZZZZZZZZZZZZZZZZZZ.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->get('/api/badges/custom/01JZZZZZZZZZZZZZZZZZZZZZZZ/stars/natanfiuza/devtools.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->get('/api/badges/nao-existe/nao-existe/x.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->get('/b/alice/not-a-ulid.svg')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});
