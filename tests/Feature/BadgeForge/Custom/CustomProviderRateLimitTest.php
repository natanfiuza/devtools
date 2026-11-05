<?php

use App\Enums\CustomMetricFormatter;
use App\Models\BadgeCustomProvider;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Limite de taxa por dono (F7, ADR-087).
|
| O `ThrottleRequests` roda antes do `SubstituteBindings`, entao o limiter ve o
| ULID cru. Dono Free/pago conta na conta; ULID invalido/desconhecido cai no
| anonimo por IP. A recusa e 429 no envelope.
*/

function customRatePaid(User $user): void
{
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);
}

function customRateFakeNetwork(): void
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
            'stargazers_count' => 42,
            'forks_count' => 7,
        ], JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']),
    ]);
}

function customRateProvider(array $overrides = []): BadgeCustomProvider
{
    $provider = BadgeCustomProvider::factory()->create($overrides);

    $provider->definitions()->create([
        'slug' => 'stars',
        'label' => 'Stars',
        'pointer' => '/stargazers_count',
        'formatter' => CustomMetricFormatter::Number,
        'sort' => 0,
    ]);

    return $provider->refresh();
}

function customRateUrl(BadgeCustomProvider $provider): string
{
    return '/api/badges/custom/'.$provider->id.'/stars/natanfiuza/devtools.svg';
}

it('aplica o limite Free por dono', function () {
    config([
        'badgeforge.custom_providers.rate_limit.free' => 2,
        'badgeforge.custom_providers.rate_limit.paid' => 50,
        'badgeforge.custom_providers.rate_limit.anonymous' => 50,
    ]);

    customRateFakeNetwork();

    $provider = customRateProvider();

    $this->get(customRateUrl($provider))->assertOk();
    $this->get(customRateUrl($provider))->assertOk();

    $limited = $this->get(customRateUrl($provider))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');

    expect($limited->headers->get('X-RateLimit-Limit'))->toBe('2')
        ->and($limited->headers->get('Retry-After'))->not->toBeNull();
});

it('aplica o limite pago por dono', function () {
    config([
        'badgeforge.custom_providers.rate_limit.free' => 50,
        'badgeforge.custom_providers.rate_limit.paid' => 3,
        'badgeforge.custom_providers.rate_limit.anonymous' => 50,
    ]);

    customRateFakeNetwork();

    $user = User::factory()->create();
    customRatePaid($user);

    $provider = customRateProvider(['user_id' => $user->id]);

    $this->get(customRateUrl($provider))->assertOk();
    $this->get(customRateUrl($provider))->assertOk();
    $this->get(customRateUrl($provider))->assertOk();
    $this->get(customRateUrl($provider))->assertStatus(429);
});

it('cai no limite anonimo para ULID desconhecido ou invalido', function () {
    config([
        'badgeforge.custom_providers.rate_limit.free' => 50,
        'badgeforge.custom_providers.rate_limit.paid' => 50,
        'badgeforge.custom_providers.rate_limit.anonymous' => 2,
    ]);

    $this->get('/api/badges/custom/01JZZZZZZZZZZZZZZZZZZZZZZZ/stars/natanfiuza/devtools.svg')
        ->assertNotFound();
    $this->get('/api/badges/custom/01JZZZZZZZZZZZZZZZZZZZZZZY/stars/natanfiuza/devtools.svg')
        ->assertNotFound();

    $this->get('/api/badges/custom/01JZZZZZZZZZZZZZZZZZZZZZZX/stars/natanfiuza/devtools.svg')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
});

it('conta o limite por dono e nao por provider', function () {
    config([
        'badgeforge.custom_providers.rate_limit.free' => 2,
        'badgeforge.custom_providers.rate_limit.paid' => 50,
        'badgeforge.custom_providers.rate_limit.anonymous' => 50,
    ]);

    customRateFakeNetwork();

    $user = User::factory()->create();
    $first = customRateProvider([
        'user_id' => $user->id,
        'url_template' => 'https://example.com/one/{owner}/{repo}.json',
    ]);
    $second = customRateProvider([
        'user_id' => $user->id,
        'url_template' => 'https://example.com/two/{owner}/{repo}.json',
    ]);

    $this->get(customRateUrl($first))->assertOk();
    $this->get(customRateUrl($second))->assertOk();
    $this->get(customRateUrl($first))->assertStatus(429);
});
