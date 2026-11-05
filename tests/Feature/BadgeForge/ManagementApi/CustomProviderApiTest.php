<?php

use App\Models\BadgeCustomProvider;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| CRUD de providers customizados pela API de gestao (F9, ADR-090).
|
| Criar/editar exige plano pago (403 `forbidden`); excluir segue permitido no
| downgrade. Template, placeholders e conjunto de slugs sao imutaveis, e a
| origem (`url_template`) nunca sai em resposta.
*/

/**
 * @param  list<string>  $ips
 */
function customProviderApiFakeDns(array $ips = ['93.184.216.34']): void
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

function customProviderApiFakeJson(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            json_encode(['stargazers_count' => 42, 'forks_count' => 7], JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);
}

function customProviderApiAccount(string $username = 'octocat', bool $paid = true): User
{
    $user = User::factory()->create(['username' => $username]);

    if ($paid) {
        $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

        Subscription::factory()->for($user)->for($plan)->create([
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
        ]);
    }

    return $user->refresh();
}

function customProviderApiToken(User $user, array $abilities = ['badgeforge:write']): string
{
    return $user->createToken('management-custom', $abilities)->plainTextToken;
}

/**
 * @return list<array<string, string>>
 */
function customProviderApiMetrics(): array
{
    return [
        ['slug' => 'stars', 'label' => 'Stars', 'pointer' => '/stargazers_count', 'formatter' => 'number'],
        ['slug' => 'forks', 'label' => 'Forks', 'pointer' => '/forks_count', 'formatter' => 'number'],
    ];
}

/**
 * @return array<string, mixed>
 */
function customProviderApiForm(string $name = 'github-stars'): array
{
    return [
        'name' => $name,
        'url_template' => 'https://example.com/repos/{owner}/{repo}.json',
        'path_params' => ['owner', 'repo'],
        'example_path' => 'natanfiuza/devtools',
        'metrics' => customProviderApiMetrics(),
    ];
}

function customProviderApiProvider(User $user, string $name = 'github-stars'): BadgeCustomProvider
{
    $provider = BadgeCustomProvider::factory()->for($user)->create([
        'name' => $name,
        'url_template' => 'https://example.com/repos/{owner}/{repo}.json',
    ]);

    $provider->definitions()->createMany([
        ['slug' => 'stars', 'label' => 'Stars', 'pointer' => '/stargazers_count', 'formatter' => 'number', 'sort' => 0],
        ['slug' => 'forks', 'label' => 'Forks', 'pointer' => '/forks_count', 'formatter' => 'number', 'sort' => 1],
    ]);

    return $provider->load('definitions');
}

it('cria provider pago com definições e sem devolver a origem', function () {
    customProviderApiFakeDns();
    customProviderApiFakeJson();

    $user = customProviderApiAccount();

    $response = $this->withToken(customProviderApiToken($user))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), customProviderApiForm())
        ->assertCreated();

    $provider = BadgeCustomProvider::query()->where('name', 'github-stars')->sole();

    expect($response->json('data'))->toHaveKeys(['id', 'name', 'enabled', 'pathParams', 'examplePath', 'definitions', 'createdAt'])
        ->and($response->json('data.id'))->toBe((string) $provider->id)
        ->and($response->json('data.pathParams'))->toBe(['owner', 'repo'])
        ->and($response->json('data.definitions'))->toHaveCount(2)
        ->and($response->json('data.definitions.0'))->toBe([
            'slug' => 'stars',
            'label' => 'Stars',
            'pointer' => '/stargazers_count',
            'formatter' => 'number',
        ])
        ->and((string) $response->getContent())->not->toContain('url_template')
        ->and((string) $response->getContent())->not->toContain('example.com');

    expect($provider->url_template)->toBe('https://example.com/repos/{owner}/{repo}.json')
        ->and($provider->metrics_version)->toBe(1);
});

it('bloqueia criar e editar no Free, mas permite excluir', function () {
    customProviderApiFakeDns();
    customProviderApiFakeJson();

    $free = customProviderApiAccount('freebie', paid: false);
    $provider = customProviderApiProvider($free);

    $this->withToken(customProviderApiToken($free))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), customProviderApiForm('outro'))
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');

    $this->withToken(customProviderApiToken($free))
        ->patchJson(route('api.v1.badgeforge.custom-providers.update', ['badgeCustomProvider' => $provider->id]), [
            'name' => 'renomeado',
            'metrics' => customProviderApiMetrics(),
        ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');

    expect($provider->refresh()->name)->toBe('github-stars');

    $this->withToken(customProviderApiToken($free))
        ->deleteJson(route('api.v1.badgeforge.custom-providers.destroy', ['badgeCustomProvider' => $provider->id]))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => (string) $provider->id, 'deleted' => true]]);

    expect(BadgeCustomProvider::query()->count())->toBe(0);
});

it('recusa template fora do contrato e amostra inválida no envelope', function (array $overrides, string $field) {
    customProviderApiFakeDns();
    customProviderApiFakeJson();

    $user = customProviderApiAccount();

    $this->withToken(customProviderApiToken($user))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), array_merge(customProviderApiForm(), $overrides))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.'.$field);

    expect(BadgeCustomProvider::query()->count())->toBe(0);
})->with([
    'http' => [['url_template' => 'http://example.com/repos/{owner}/{repo}.json'], 'url_template'],
    'placeholder extra' => [['url_template' => 'https://example.com/{owner}/{repo}/{branch}.json'], 'path_params'],
    'userinfo' => [['url_template' => 'https://user:pass@example.com/repos/{owner}/{repo}.json'], 'url_template'],
    'IP privado' => [['url_template' => 'https://10.0.0.1/repos/{owner}/{repo}.json'], 'url_template'],
]);

it('recusa pointer ausente na amostra da origem', function () {
    customProviderApiFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            '{"forks_count":7}',
            200,
            ['Content-Type' => 'application/json'],
        ),
    ]);

    $user = customProviderApiAccount();

    $this->withToken(customProviderApiToken($user))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), customProviderApiForm())
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.metrics');

    expect(BadgeCustomProvider::query()->count())->toBe(0);
});

it('tolera origem fora do ar e DNS que não resolve', function () {
    $user = customProviderApiAccount();

    customProviderApiFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 503)]);

    $this->withToken(customProviderApiToken($user))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), customProviderApiForm('fora-do-ar'))
        ->assertCreated();

    customProviderApiFakeDns([]);

    $this->withToken(customProviderApiToken($user))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), array_merge(
            customProviderApiForm('sem-dns'),
            ['url_template' => 'https://sem-dns.example/repos/{owner}/{repo}.json'],
        ))
        ->assertCreated();

    expect(BadgeCustomProvider::query()->count())->toBe(2);
});

it('respeita o limite de providers do plano', function () {
    customProviderApiFakeDns();
    customProviderApiFakeJson();
    config(['badgeforge.custom_providers.limit' => 1]);

    $user = customProviderApiAccount();
    customProviderApiProvider($user);

    $this->withToken(customProviderApiToken($user))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), customProviderApiForm('segundo'))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.name');

    expect(BadgeCustomProvider::query()->count())->toBe(1);
});

it('lista só os providers do dono, com definitions e sem a origem', function () {
    $user = customProviderApiAccount();
    $provider = customProviderApiProvider($user, 'meu');

    customProviderApiProvider(User::factory()->create(), 'alheio');

    $response = $this->withToken(customProviderApiToken($user, ['badgeforge:read']))
        ->getJson(route('api.v1.badgeforge.custom-providers.index'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', (string) $provider->id)
        ->assertJsonPath('data.0.name', 'meu')
        ->assertJsonStructure([
            'data' => [[
                'id', 'name', 'enabled', 'pathParams', 'examplePath', 'createdAt',
                'definitions' => [['slug', 'label', 'pointer', 'formatter']],
            ]],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'links' => ['next', 'previous'],
        ]);

    expect((string) $response->getContent())->not->toContain('url_template')
        ->and((string) $response->getContent())->not->toContain('example.com');
});

it('edita nome e métricas e sobe o metrics_version', function () {
    customProviderApiFakeDns();
    customProviderApiFakeJson();

    $user = customProviderApiAccount();
    $provider = customProviderApiProvider($user);

    $metrics = customProviderApiMetrics();
    $metrics[0]['label'] = 'Estrelas';
    $metrics[1]['formatter'] = 'text';

    $response = $this->withToken(customProviderApiToken($user))
        ->patchJson(route('api.v1.badgeforge.custom-providers.update', ['badgeCustomProvider' => $provider->id]), [
            'name' => 'renomeado',
            'metrics' => $metrics,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'renomeado')
        ->assertJsonPath('data.definitions.0.label', 'Estrelas')
        ->assertJsonPath('data.definitions.1.formatter', 'text');

    $provider->refresh();

    expect($provider->metrics_version)->toBe(2)
        ->and($provider->url_template)->toBe('https://example.com/repos/{owner}/{repo}.json')
        ->and((string) $response->getContent())->not->toContain('url_template')
        ->and((string) $response->getContent())->not->toContain('example.com');
});

it('recusa url_template no PATCH e conjunto de slugs diferente', function () {
    customProviderApiFakeDns();
    customProviderApiFakeJson();

    $user = customProviderApiAccount();
    $provider = customProviderApiProvider($user);
    $url = route('api.v1.badgeforge.custom-providers.update', ['badgeCustomProvider' => $provider->id]);

    $this->withToken(customProviderApiToken($user))
        ->patchJson($url, [
            'name' => 'renomeado',
            'url_template' => 'https://evil.example/x.json',
            'metrics' => customProviderApiMetrics(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.url_template');

    $metrics = customProviderApiMetrics();
    $metrics[1]['slug'] = 'watchers';

    $this->withToken(customProviderApiToken($user))
        ->patchJson($url, ['name' => 'renomeado', 'metrics' => $metrics])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.metrics');

    expect($provider->refresh()->name)->toBe('github-stars')
        ->and($provider->metrics_version)->toBe(1);
});

it('responde 404 no PATCH/DELETE de provider de outra conta', function () {
    $owner = customProviderApiAccount('owner');
    $intruder = customProviderApiAccount('intruder');
    $provider = customProviderApiProvider($owner);

    $this->withToken(customProviderApiToken($intruder))
        ->patchJson(route('api.v1.badgeforge.custom-providers.update', ['badgeCustomProvider' => $provider->id]), [
            'name' => 'roubo',
            'metrics' => customProviderApiMetrics(),
        ])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    $this->withToken(customProviderApiToken($intruder))
        ->deleteJson(route('api.v1.badgeforge.custom-providers.destroy', ['badgeCustomProvider' => $provider->id]))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect(BadgeCustomProvider::query()->count())->toBe(1);
});

it('recusa token só de leitura no CRUD de providers', function () {
    $user = customProviderApiAccount();

    $this->withToken(customProviderApiToken($user, ['badgeforge:read']))
        ->postJson(route('api.v1.badgeforge.custom-providers.store'), customProviderApiForm())
        ->assertStatus(403);

    expect(BadgeCustomProvider::query()->count())->toBe(0);
});
