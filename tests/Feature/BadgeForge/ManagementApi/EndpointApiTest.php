<?php

use App\Models\BadgeEndpoint;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\EndpointCacheKey;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| CRUD de endpoints JSON pela API de gestao (F9, ADR-090).
|
| Espelha a tela: Free cadastra, o probe/SSRF fica na action e a URL de origem
| e imutavel e nunca devolvida. Recurso de outra conta responde 404.
*/

/**
 * @param  list<string>  $ips
 */
function endpointApiFakeDns(array $ips = ['93.184.216.34']): void
{
    app()->instance(HostResolver::class, new class($ips) implements HostResolver
    {
        /**
         * @param  list<string>  $ips
         */
        public function __construct(private readonly array $ips) {}

        public function resolve(string $host): array
        {
            return $this->ips;
        }
    });
}

function endpointApiFakeJson(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            json_encode([
                'schemaVersion' => 1,
                'label' => 'deploys',
                'message' => 'green',
            ], JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);
}

function endpointApiToken(User $user, array $abilities = ['badgeforge:write']): string
{
    return $user->createToken('management-endpoints', $abilities)->plainTextToken;
}

it('cadastra endpoint na conta Free sem devolver a URL de origem', function () {
    endpointApiFakeDns();
    endpointApiFakeJson();

    $user = User::factory()->create();

    $response = $this->withToken(endpointApiToken($user))
        ->postJson(route('api.v1.badgeforge.endpoints.store'), [
            'name' => 'deploys',
            'url' => 'https://example.com/badge.json',
        ])
        ->assertCreated();

    $endpoint = BadgeEndpoint::query()->sole();

    expect($response->json('data'))->toHaveKeys(['id', 'name', 'svgUrl', 'pngUrl', 'createdAt'])
        ->and($response->json('data.id'))->toBe((string) $endpoint->id)
        ->and($response->json('data.svgUrl'))->toEndWith('/api/badges/endpoint/'.$endpoint->id.'.svg')
        ->and((string) $response->getContent())->not->toContain('example.com')
        ->and($endpoint->user_id)->toBe($user->id)
        ->and($endpoint->url)->toBe('https://example.com/badge.json');
});

it('recusa URL que quebra o guard de SSRF no envelope', function (string $url) {
    endpointApiFakeDns();
    endpointApiFakeJson();

    $user = User::factory()->create();

    $this->withToken(endpointApiToken($user))
        ->postJson(route('api.v1.badgeforge.endpoints.store'), [
            'name' => 'bad',
            'url' => $url,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.url');

    expect(BadgeEndpoint::query()->count())->toBe(0);
})->with([
    'http' => ['http://example.com/badge.json'],
    'porta' => ['https://example.com:8443/badge.json'],
    'userinfo' => ['https://user:pass@example.com/badge.json'],
    'IP privado' => ['https://10.0.0.1/badge.json'],
    'metadata' => ['https://169.254.169.254/latest/meta-data'],
]);

it('tolera origem fora do ar e DNS que não resolve', function () {
    $user = User::factory()->create();

    endpointApiFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 503)]);

    $this->withToken(endpointApiToken($user))
        ->postJson(route('api.v1.badgeforge.endpoints.store'), [
            'name' => 'fora-do-ar',
            'url' => 'https://example.com/badge.json',
        ])
        ->assertCreated();

    endpointApiFakeDns([]);

    $this->withToken(endpointApiToken($user))
        ->postJson(route('api.v1.badgeforge.endpoints.store'), [
            'name' => 'sem-dns',
            'url' => 'https://sem-dns.example/badge.json',
        ])
        ->assertCreated();

    expect(BadgeEndpoint::query()->count())->toBe(2);
});

it('recusa payload fora do contrato na amostra', function () {
    endpointApiFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            '{"schemaVersion":2,"label":"a","message":"b"}',
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);

    $user = User::factory()->create();

    $this->withToken(endpointApiToken($user))
        ->postJson(route('api.v1.badgeforge.endpoints.store'), [
            'name' => 'bad',
            'url' => 'https://example.com/badge.json',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.url');

    expect(BadgeEndpoint::query()->count())->toBe(0);
});

it('lista só os endpoints do dono, sem a URL de origem', function () {
    $user = User::factory()->create();
    $endpoint = BadgeEndpoint::factory()->for($user)->create(['name' => 'meu']);

    BadgeEndpoint::factory()->create(['name' => 'alheio']);

    $response = $this->withToken(endpointApiToken($user, ['badgeforge:read']))
        ->getJson(route('api.v1.badgeforge.endpoints.index'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', (string) $endpoint->id)
        ->assertJsonPath('data.0.name', 'meu')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'svgUrl', 'pngUrl', 'createdAt']],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links' => ['next', 'previous'],
        ]);

    expect((string) $response->getContent())->not->toContain('badge.json')
        ->and((string) $response->getContent())->not->toContain('"url"');
});

it('renomeia pelo PATCH mantendo a URL de origem', function () {
    $user = User::factory()->create();
    $endpoint = BadgeEndpoint::factory()->for($user)->create([
        'name' => 'Antigo',
        'url' => 'https://example.com/badge.json',
    ]);

    $response = $this->withToken(endpointApiToken($user))
        ->patchJson(route('api.v1.badgeforge.endpoints.update', ['badgeEndpoint' => $endpoint->id]), [
            'name' => 'Novo',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Novo');

    expect($endpoint->refresh()->name)->toBe('Novo')
        ->and($endpoint->url)->toBe('https://example.com/badge.json')
        ->and((string) $response->getContent())->not->toContain('example.com');
});

it('exclui o endpoint e esquece a entrada de cache', function () {
    $user = User::factory()->create();
    $endpoint = BadgeEndpoint::factory()->for($user)->create([
        'url' => 'https://example.com/badge.json',
    ]);

    $key = EndpointCacheKey::for($endpoint->url);
    Cache::put($key, ['anything' => true], 300);

    $this->withToken(endpointApiToken($user))
        ->deleteJson(route('api.v1.badgeforge.endpoints.destroy', ['badgeEndpoint' => $endpoint->id]))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => (string) $endpoint->id, 'deleted' => true]]);

    expect(BadgeEndpoint::query()->count())->toBe(0)
        ->and(Cache::has($key))->toBeFalse();
});

it('responde 404 para endpoint de outra conta', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $endpoint = BadgeEndpoint::factory()->for($owner)->create(['name' => 'Antigo']);

    $this->withToken(endpointApiToken($intruder))
        ->patchJson(route('api.v1.badgeforge.endpoints.update', ['badgeEndpoint' => $endpoint->id]), ['name' => 'X'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect($endpoint->refresh()->name)->toBe('Antigo');
});

it('responde 404 na exclusão de endpoint de outra conta', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $endpoint = BadgeEndpoint::factory()->for($owner)->create();

    $this->withToken(endpointApiToken($intruder))
        ->deleteJson(route('api.v1.badgeforge.endpoints.destroy', ['badgeEndpoint' => $endpoint->id]))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect(BadgeEndpoint::query()->count())->toBe(1);
});

it('recusa token só de leitura no CRUD de endpoints', function () {
    $user = User::factory()->create();

    $this->withToken(endpointApiToken($user, ['badgeforge:read']))
        ->postJson(route('api.v1.badgeforge.endpoints.store'), [
            'name' => 'Sem permissão',
            'url' => 'https://example.com/badge.json',
        ])
        ->assertStatus(403);

    expect(BadgeEndpoint::query()->count())->toBe(0);
});
