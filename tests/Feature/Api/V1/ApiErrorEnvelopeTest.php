<?php

use App\Models\UsageLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
| Envelope de erro da API v1 (ADR-042, ADR-050).
|
| Todo erro da superficie `api/*` responde `{error: {code, message}}`, com
| `details` apenas quando ajuda a corrigir o pedido. O `code` e o contrato
| estavel que o cliente programa; `message` e o fallback tecnico do servidor.
*/

beforeEach(function () {
    Queue::fake();

    config([
        'ai.base_url' => 'https://api.deepseek.test',
        'ai.model' => 'test-model',
        'ai.api_key' => 'test-key',
        'ai.estimate_output_tokens' => 900,
        'ai.inflight_ttl_seconds' => 600,
        'ai.polling.initial_delay_ms' => 1500,
        'api.rate_limits.post_per_minute' => 30,
        'api.rate_limits.post_per_hour' => 300,
        'api.rate_limits.read_per_minute' => 120,
    ]);
});

/**
 * @param  list<string>  $abilities
 */
function envelopeToken(User $user, array $abilities = ['ai:query']): string
{
    return $user->createToken('envelope', $abilities)->plainTextToken;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function envelopeBody(array $overrides = []): array
{
    return array_merge([
        'schema_source' => 'ddl',
        'dialect' => 'mysql',
        'output_target' => 'sql',
        'schema' => 'CREATE TABLE users (id int);',
        'description' => 'Liste os usuarios',
    ], $overrides);
}

/**
 * O envelope tem sempre um unico `error` na raiz e um `code` string.
 *
 * @return array<string, mixed>
 */
function envelopeError(TestResponse $response, string $code): array
{
    $body = $response->json();

    expect($body)->toBeArray()
        ->and(array_keys($body))->toBe(['error'])
        ->and($body['error'])->toHaveKey('code')
        ->and($body['error'])->toHaveKey('message')
        ->and($body['error']['code'])->toBe($code)
        ->and($body['error']['message'])->toBeString()->not->toBe('');

    return $body['error'];
}

it('responde 401 sem token', function () {
    $response = $this->getJson('/api/v1/me')->assertUnauthorized();

    $error = envelopeError($response, 'unauthenticated');

    expect(array_keys($error))->toBe(['code', 'message']);
});

it('responde 401 JSON mesmo sem o cabecalho Accept', function () {
    // Sem `Accept: application/json` o middleware de autenticacao tentaria
    // redirecionar para o login; a API precisa continuar no envelope.
    $response = $this->get('/api/v1/me')->assertUnauthorized();

    envelopeError($response, 'unauthenticated');
});

it('responde 403 quando o token nao tem a ability da API', function () {
    $user = User::factory()->create();

    $response = $this->withToken(envelopeToken($user, ['outra:coisa']))
        ->getJson('/api/v1/me')
        ->assertForbidden();

    $error = envelopeError($response, 'forbidden');

    expect(array_keys($error))->toBe(['code', 'message']);
});

it('responde 403 com codigo proprio para conta suspensa', function () {
    $user = User::factory()->create(['suspended_at' => now()]);

    $response = $this->withToken(envelopeToken($user))
        ->getJson('/api/v1/me')
        ->assertForbidden();

    envelopeError($response, 'account_suspended');
});

it('responde 404 para consulta inexistente sem confirmar o id', function () {
    $user = User::factory()->create();

    $response = $this->withToken(envelopeToken($user))
        ->getJson('/api/v1/sql-queries/'.Str::ulid())
        ->assertNotFound();

    $error = envelopeError($response, 'not_found');

    expect(array_keys($error))->toBe(['code', 'message']);
});

it('responde 422 com os campos invalidos em details', function () {
    $user = User::factory()->create();

    $response = $this->withToken(envelopeToken($user))
        ->postJson('/api/v1/sql-queries', envelopeBody(['description' => '']))
        ->assertStatus(422);

    $error = envelopeError($response, 'validation_error');

    expect(array_keys($error))->toBe(['code', 'message', 'details'])
        ->and($error['details'])->toHaveKey('errors')
        ->and($error['details']['errors'])->toHaveKey('description');
});

it('responde 402 com a unidade da cota em details', function () {
    $user = User::factory()->create();
    UsageLedger::factory()->count(5)->for($user)->create();

    $response = $this->withToken(envelopeToken($user))
        ->postJson('/api/v1/sql-queries', envelopeBody())
        ->assertStatus(402);

    $error = envelopeError($response, 'quota_exceeded');

    expect(array_keys($error))->toBe(['code', 'message', 'details'])
        ->and($error['details'])->toHaveKeys(['unit', 'required'])
        ->and($error['details']['unit'])->toBe('uses');
});

it('responde 429 com Retry-After e sem details', function () {
    config([
        'api.rate_limits.post_per_minute' => 1,
        'api.rate_limits.post_per_hour' => 1,
    ]);

    $user = User::factory()->create();
    $token = envelopeToken($user);

    $this->withToken($token)
        ->postJson('/api/v1/sql-queries', envelopeBody())
        ->assertStatus(202);

    $response = $this->withToken($token)
        ->postJson('/api/v1/sql-queries', envelopeBody(['description' => 'Outra descricao']))
        ->assertStatus(429);

    $error = envelopeError($response, 'rate_limited');

    expect(array_keys($error))->toBe(['code', 'message'])
        ->and($response->headers->has('Retry-After'))->toBeTrue();
});
