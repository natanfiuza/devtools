<?php

use App\Models\BadgeEndpoint;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\EndpointCacheKey;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

/*
| Gestao dos endpoints JSON (F5, ADR-083/ADR-084).
|
| A criacao valida URL/SSRF e busca uma amostra: inseguro ou fora do contrato
| e 422; origem fora do ar ou DNS que nao resolve nao bloqueia. A URL de
| origem nao volta em props, flash ou `toArray()`.
*/

function endpointManagementFakeDns(array $ips = ['93.184.216.34']): void
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

function endpointManagementFakeJson(array $overrides = []): void
{
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(json_encode(array_merge([
            'schemaVersion' => 1,
            'label' => 'deploys',
            'message' => 'green',
        ], $overrides), JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']),
    ]);
}

function endpointManagementCreate(TestCase $test, User $user, array $data = []): BadgeEndpoint
{
    $test->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.endpoints.store'), array_merge([
            'name' => 'deploys',
            'url' => 'https://example.com/badge.json',
        ], $data))
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    return BadgeEndpoint::query()->where('name', $data['name'] ?? 'deploys')->sole();
}

it('protege as rotas de gestao', function () {
    $endpoint = BadgeEndpoint::factory()->create();

    $this->post(route('badgeforge.endpoints.store'))->assertRedirect(route('login'));
    $this->patch(route('badgeforge.endpoints.update', $endpoint))->assertRedirect(route('login'));
    $this->delete(route('badgeforge.endpoints.destroy', $endpoint))->assertRedirect(route('login'));
});

it('cria endpoint na conta Free e guarda a URL canonica', function () {
    endpointManagementFakeDns();
    endpointManagementFakeJson();

    $user = User::factory()->create();

    $endpoint = endpointManagementCreate($this, $user, [
        'url' => 'HTTPS://Example.COM.:443/badge.json?x=1',
    ]);

    expect($endpoint->user_id)->toBe($user->id)
        ->and($endpoint->name)->toBe('deploys')
        ->and($endpoint->url)->toBe('https://example.com/badge.json?x=1')
        ->and($endpoint->toArray())->not->toHaveKey('url');
});

it('recusa URLs que quebram o guard de SSRF', function (string $url) {
    endpointManagementFakeDns();
    endpointManagementFakeJson();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.endpoints.store'), ['name' => 'bad', 'url' => $url])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasErrors('url');

    expect(BadgeEndpoint::query()->count())->toBe(0);
})->with([
    'http' => ['http://example.com/badge.json'],
    'porta' => ['https://example.com:8443/badge.json'],
    'userinfo' => ['https://user:pass@example.com/badge.json'],
    'localhost' => ['https://localhost/badge.json'],
    'host sem ponto' => ['https://internal/badge.json'],
    'IP privado' => ['https://10.0.0.1/badge.json'],
    'metadata' => ['https://169.254.169.254/latest/meta-data'],
    'fragmento' => ['https://example.com/badge.json#x'],
]);

it('recusa host que resolve para IP privado', function () {
    endpointManagementFakeDns(['10.0.0.1']);
    endpointManagementFakeJson();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.endpoints.store'), [
            'name' => 'bad',
            'url' => 'https://example.com/badge.json',
        ])
        ->assertSessionHasErrors('url');

    Http::assertNothingSent();
    expect(BadgeEndpoint::query()->count())->toBe(0);
});

it('recusa payload fora do contrato ou redirect na amostra', function (string $body, int $status, array $headers) {
    endpointManagementFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response($body, $status, $headers)]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.endpoints.store'), [
            'name' => 'bad',
            'url' => 'https://example.com/badge.json',
        ])
        ->assertSessionHasErrors('url');

    expect(BadgeEndpoint::query()->count())->toBe(0);
})->with([
    'schema futuro' => ['{"schemaVersion":2,"label":"a","message":"b"}', 200, ['Content-Type' => 'application/json']],
    'json invalido' => ['{nope', 200, ['Content-Type' => 'application/json']],
    'content-type' => ['{"schemaVersion":1,"label":"a","message":"b"}', 200, ['Content-Type' => 'text/html']],
    'redirect' => ['', 302, ['Location' => 'https://evil.example/x.json']],
]);

it('tolera origem fora do ar e DNS que nao resolve', function () {
    $user = User::factory()->create();

    endpointManagementFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 503)]);

    endpointManagementCreate($this, $user, ['name' => 'fora-do-ar']);

    endpointManagementFakeDns([]);

    endpointManagementCreate($this, $user, ['name' => 'sem-dns', 'url' => 'https://sem-dns.example/badge.json']);

    expect(BadgeEndpoint::query()->count())->toBe(2);
});

it('exige nome unico por dono', function () {
    endpointManagementFakeDns();
    endpointManagementFakeJson();

    $user = User::factory()->create();
    endpointManagementCreate($this, $user);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.endpoints.store'), [
            'name' => 'deploys',
            'url' => 'https://example.com/other.json',
        ])
        ->assertSessionHasErrors('name');

    // O mesmo nome em outra conta e valido: a unicidade e por dono.
    $other = User::factory()->create();
    endpointManagementCreate($this, $other, [
        'name' => 'outro',
        'url' => 'https://example.com/other.json',
    ]);

    expect(BadgeEndpoint::query()->count())->toBe(2);
});

it('nao devolve a URL no input antigo de um 422', function () {
    endpointManagementFakeDns();
    endpointManagementFakeJson();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.endpoints.store'), [
            'name' => 'bad',
            'url' => 'https://user:pass@example.com/badge.json',
        ])
        ->assertSessionHasErrors('url');

    expect(session('_old_input'))->toHaveKey('name')
        ->and(session('_old_input'))->not->toHaveKey('url');
});

it('renomeia sem mudar a URL de origem', function () {
    endpointManagementFakeDns();
    endpointManagementFakeJson();

    $user = User::factory()->create();
    $endpoint = endpointManagementCreate($this, $user);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.endpoints.update', $endpoint), ['name' => 'producao'])
        ->assertSessionHasNoErrors();

    expect($endpoint->refresh()->name)->toBe('producao')
        ->and($endpoint->url)->toBe('https://example.com/badge.json');
});

it('responde 404 para endpoint de outra conta', function () {
    $endpoint = BadgeEndpoint::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('badgeforge.endpoints.update', $endpoint), ['name' => 'roubo'])
        ->assertNotFound();

    $this->actingAs($intruder)
        ->delete(route('badgeforge.endpoints.destroy', $endpoint))
        ->assertNotFound();

    expect(BadgeEndpoint::query()->count())->toBe(1);
});

it('exclui o endpoint e esquece a entrada de cache', function () {
    endpointManagementFakeDns();
    endpointManagementFakeJson();

    $user = User::factory()->create();
    $endpoint = endpointManagementCreate($this, $user);
    $key = EndpointCacheKey::for($endpoint->url);

    Cache::put($key, ['anything' => true], 300);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->delete(route('badgeforge.endpoints.destroy', $endpoint))
        ->assertSessionHasNoErrors();

    expect(BadgeEndpoint::query()->count())->toBe(0)
        ->and(Cache::has($key))->toBeFalse();
});
