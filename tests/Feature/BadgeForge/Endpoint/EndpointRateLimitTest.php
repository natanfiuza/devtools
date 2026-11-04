<?php

use App\Models\BadgeEndpoint;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Limite de taxa por dono (F5, ADR-084).
|
| O `ThrottleRequests` roda antes do `SubstituteBindings`, entao o limiter ve
| o ULID cru. Dono Free/pago conta na conta; ULID invalido/desconhecido cai no
| anonimo por IP. A recusa e 429 `rate_limited` no envelope, com headers.
*/

function endpointRatePaid(User $user): void
{
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);
}

function endpointRateFakeNetwork(): void
{
    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(json_encode([
            'schemaVersion' => 1,
            'label' => 'deploys',
            'message' => 'green',
        ], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']),
    ]);
}

function endpointRateUrl(BadgeEndpoint $endpoint): string
{
    return '/api/badges/endpoint/'.$endpoint->id.'.svg';
}

it('aplica o limite Free por dono', function () {
    config([
        'badgeforge.endpoint.rate_limit.free' => 2,
        'badgeforge.endpoint.rate_limit.paid' => 50,
        'badgeforge.endpoint.rate_limit.anonymous' => 50,
    ]);

    endpointRateFakeNetwork();

    $endpoint = BadgeEndpoint::factory()
        ->for(User::factory()->create())
        ->create(['name' => 'deploys']);

    $this->get(endpointRateUrl($endpoint))->assertOk();
    $this->get(endpointRateUrl($endpoint))->assertOk();

    $limited = $this->get(endpointRateUrl($endpoint))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');

    expect($limited->headers->get('X-RateLimit-Limit'))->toBe('2')
        ->and($limited->headers->get('Retry-After'))->not->toBeNull();
});

it('aplica o limite pago por dono', function () {
    config([
        'badgeforge.endpoint.rate_limit.free' => 50,
        'badgeforge.endpoint.rate_limit.paid' => 3,
        'badgeforge.endpoint.rate_limit.anonymous' => 50,
    ]);

    endpointRateFakeNetwork();

    $user = User::factory()->create();
    endpointRatePaid($user);

    $endpoint = BadgeEndpoint::factory()->for($user)->create(['name' => 'deploys']);

    $this->get(endpointRateUrl($endpoint))->assertOk();
    $this->get(endpointRateUrl($endpoint))->assertOk();
    $this->get(endpointRateUrl($endpoint))->assertOk();
    $this->get(endpointRateUrl($endpoint))->assertStatus(429);
});

it('cai no limite anonimo para ULID desconhecido ou invalido', function () {
    config([
        'badgeforge.endpoint.rate_limit.free' => 50,
        'badgeforge.endpoint.rate_limit.paid' => 50,
        'badgeforge.endpoint.rate_limit.anonymous' => 2,
    ]);

    $this->get('/api/badges/endpoint/01JZZZZZZZZZZZZZZZZZZZZZZZ.svg')->assertNotFound();
    $this->get('/api/badges/endpoint/01JZZZZZZZZZZZZZZZZZZZZZZY.svg')->assertNotFound();

    $this->get('/api/badges/endpoint/01JZZZZZZZZZZZZZZZZZZZZZZX.svg')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
});

it('conta o limite por dono e nao por ULID', function () {
    config([
        'badgeforge.endpoint.rate_limit.free' => 2,
        'badgeforge.endpoint.rate_limit.paid' => 50,
        'badgeforge.endpoint.rate_limit.anonymous' => 50,
    ]);

    endpointRateFakeNetwork();

    $user = User::factory()->create();
    $first = BadgeEndpoint::factory()->for($user)->create(['name' => 'one', 'url' => 'https://example.com/one.json']);
    $second = BadgeEndpoint::factory()->for($user)->create(['name' => 'two', 'url' => 'https://example.com/two.json']);

    $this->get(endpointRateUrl($first))->assertOk();
    $this->get(endpointRateUrl($second))->assertOk();
    $this->get(endpointRateUrl($first))->assertStatus(429);
});
