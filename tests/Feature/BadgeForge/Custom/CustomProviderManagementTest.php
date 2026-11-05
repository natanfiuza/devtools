<?php

use App\Enums\CustomMetricFormatter;
use App\Models\BadgeCustomProvider;
use App\Models\BadgeCustomProviderDefinition;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Endpoint\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

/*
| Gestao dos providers customizados (F7, ADR-087).
|
| Criar/editar exige plano pago; excluir segue liberado no downgrade. O probe
| de cadastro valida o shape da origem (422) e tolera rede fora (salva). A
| origem nunca volta em props, flash ou `toArray()`.
*/

function customProviderFakeDns(array $ips = ['93.184.216.34']): void
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

function customProviderFakeJson(array $overrides = []): void
{
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(json_encode(array_merge([
            'stargazers_count' => 42,
            'forks_count' => 7,
        ], $overrides), JSON_THROW_ON_ERROR), 200, ['Content-Type' => 'application/json']),
    ]);
}

function customProviderAccount(bool $paid = true, string $email = 'custom@example.com'): User
{
    $user = User::factory()->create(['email' => $email]);

    if ($paid) {
        $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

        Subscription::factory()->for($user)->for($plan)->create([
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
        ]);
    }

    return $user->refresh();
}

/**
 * @return array<string, mixed>
 */
function customProviderForm(): array
{
    return [
        'name' => 'github-stars',
        'url_template' => 'https://example.com/repos/{owner}/{repo}.json',
        'path_params' => ['owner', 'repo'],
        'example_path' => 'natanfiuza/devtools',
        'metrics' => [
            [
                'slug' => 'stars',
                'label' => 'Stars',
                'pointer' => '/stargazers_count',
                'formatter' => 'number',
            ],
            [
                'slug' => 'forks',
                'label' => 'Forks',
                'pointer' => '/forks_count',
                'formatter' => 'number',
            ],
        ],
    ];
}

/**
 * @param  array<string, mixed>  $data
 */
function customProviderCreate(TestCase $test, User $user, array $data = []): BadgeCustomProvider
{
    $test->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), array_merge(customProviderForm(), $data))
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    return BadgeCustomProvider::query()
        ->where('name', $data['name'] ?? 'github-stars')
        ->sole();
}

it('protege as rotas de gestao', function () {
    $provider = BadgeCustomProvider::factory()->create();

    $this->post(route('badgeforge.custom-providers.store'))->assertRedirect(route('login'));
    $this->patch(route('badgeforge.custom-providers.update', $provider))->assertRedirect(route('login'));
    $this->delete(route('badgeforge.custom-providers.destroy', $provider))->assertRedirect(route('login'));
});

it('bloqueia criar e editar no Free, mas permite excluir', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount(paid: false);
    $provider = BadgeCustomProvider::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('badgeforge.custom-providers.store'), customProviderForm())
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('badgeforge.custom-providers.update', $provider), [
            'name' => 'renomeado',
            'metrics' => customProviderForm()['metrics'],
        ])
        ->assertForbidden();

    expect(BadgeCustomProvider::query()->count())->toBe(1);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->delete(route('badgeforge.custom-providers.destroy', $provider))
        ->assertSessionHasNoErrors();

    expect(BadgeCustomProvider::query()->count())->toBe(0);
});

it('cria provider pago com definicoes e esconde a origem', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();
    $provider = customProviderCreate($this, $user);

    expect($provider->user_id)->toBe($user->id)
        ->and($provider->name)->toBe('github-stars')
        ->and($provider->url_template)->toBe('https://example.com/repos/{owner}/{repo}.json')
        ->and($provider->path_params)->toBe(['owner', 'repo'])
        ->and($provider->example_path)->toBe('natanfiuza/devtools')
        ->and($provider->metrics_version)->toBe(1)
        ->and($provider->enabled)->toBeTrue()
        ->and($provider->definitions)->toHaveCount(2)
        ->and($provider->definitions->firstWhere('slug', 'stars')?->formatter)->toBe(CustomMetricFormatter::Number)
        ->and($provider->toArray())->not->toHaveKey('url_template');
});

it('respeita o limite de providers por conta', function () {
    customProviderFakeDns();
    customProviderFakeJson();
    config(['badgeforge.custom_providers.limit' => 1]);

    $user = customProviderAccount();
    customProviderCreate($this, $user);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), array_merge(customProviderForm(), [
            'name' => 'segundo',
        ]))
        ->assertSessionHasErrors('name');

    expect(BadgeCustomProvider::query()->count())->toBe(1);
});

it('recusa templates fora do contrato', function (array $overrides, string $field) {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), array_merge(customProviderForm(), $overrides))
        ->assertSessionHasErrors($field);

    expect(BadgeCustomProvider::query()->count())->toBe(0);
})->with([
    'http' => [['url_template' => 'http://example.com/repos/{owner}/{repo}.json'], 'url_template'],
    'placeholder na autoridade' => [['url_template' => 'https://{owner}.example.com/repos.json', 'path_params' => ['owner']], 'url_template'],
    'sem placeholder' => [['url_template' => 'https://example.com/repos.json', 'path_params' => ['owner', 'repo']], 'url_template'],
    'placeholder extra' => [['url_template' => 'https://example.com/{owner}/{repo}/{branch}.json'], 'path_params'],
    'params fora de ordem' => [['path_params' => ['repo', 'owner']], 'path_params'],
    'userinfo' => [['url_template' => 'https://user:pass@example.com/repos/{owner}/{repo}.json'], 'url_template'],
    'localhost' => [['url_template' => 'https://localhost/repos/{owner}/{repo}.json'], 'url_template'],
    'ip privado' => [['url_template' => 'https://10.0.0.1/repos/{owner}/{repo}.json'], 'url_template'],
]);

it('recusa host que resolve para IP privado', function () {
    customProviderFakeDns(['10.0.0.1']);
    customProviderFakeJson();

    $user = customProviderAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), customProviderForm())
        ->assertSessionHasErrors('url_template');

    Http::assertNothingSent();
    expect(BadgeCustomProvider::query()->count())->toBe(0);
});

it('recusa shape invalido da origem e pointer ausente ou nao escalar', function (string $body, array $metrics, string $field) {
    customProviderFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response($body, 200, ['Content-Type' => 'application/json'])]);

    $user = customProviderAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), array_merge(customProviderForm(), ['metrics' => $metrics]))
        ->assertSessionHasErrors($field);

    expect(BadgeCustomProvider::query()->count())->toBe(0);
})->with([
    'json invalido' => ['{nope', customProviderForm()['metrics'], 'url_template'],
    'pointer ausente' => [
        '{"forks_count":7}',
        [['slug' => 'stars', 'label' => 'Stars', 'pointer' => '/stargazers_count', 'formatter' => 'number']],
        'metrics',
    ],
    'pointer com objeto' => [
        '{"stargazers_count":{"value":42},"forks_count":7}',
        [['slug' => 'stars', 'label' => 'Stars', 'pointer' => '/stargazers_count', 'formatter' => 'number']],
        'metrics',
    ],
    'pointer nulo' => [
        '{"stargazers_count":null,"forks_count":7}',
        [['slug' => 'stars', 'label' => 'Stars', 'pointer' => '/stargazers_count', 'formatter' => 'number']],
        'metrics',
    ],
]);

it('recusa content-type fora de json na amostra', function () {
    customProviderFakeDns();
    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(
            '{"stargazers_count":42,"forks_count":7}',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $user = customProviderAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), customProviderForm())
        ->assertSessionHasErrors('url_template');

    expect(BadgeCustomProvider::query()->count())->toBe(0);
});

it('recusa redirect na amostra', function () {
    customProviderFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 302, ['Location' => 'https://evil.example/x.json'])]);

    $user = customProviderAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), customProviderForm())
        ->assertSessionHasErrors('url_template');

    expect(BadgeCustomProvider::query()->count())->toBe(0);
});

it('tolera origem fora do ar e DNS que nao resolve', function () {
    customProviderFakeDns();
    Http::preventStrayRequests();
    Http::fake(['example.com/*' => Http::response('', 503)]);

    $user = customProviderAccount();
    customProviderCreate($this, $user);

    customProviderFakeDns([]);
    customProviderCreate($this, $user, [
        'name' => 'sem-dns',
        'url_template' => 'https://sem-dns.example/repos/{owner}/{repo}.json',
    ]);

    expect(BadgeCustomProvider::query()->count())->toBe(2);
});

it('exige nome unico por dono', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();
    customProviderCreate($this, $user);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), array_merge(customProviderForm(), [
            'url_template' => 'https://example.com/other/{owner}/{repo}.json',
        ]))
        ->assertSessionHasErrors('name');

    expect(BadgeCustomProvider::query()->count())->toBe(1);
});

it('nao devolve o template no input antigo de um 422', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.custom-providers.store'), array_merge(customProviderForm(), [
            'url_template' => 'https://user:pass@example.com/repos/{owner}/{repo}.json',
        ]))
        ->assertSessionHasErrors('url_template');

    expect(session('_old_input'))->toHaveKey('name')
        ->and(session('_old_input'))->not->toHaveKey('url_template');
});

it('edita label, pointer e formatter e incrementa a versao do cache', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();
    $provider = customProviderCreate($this, $user);

    $metrics = [
        [
            'slug' => 'stars',
            'label' => 'Estrelas',
            'pointer' => '/forks_count',
            'formatter' => 'percent',
        ],
        [
            'slug' => 'forks',
            'label' => 'Bifurcacoes',
            'pointer' => '/forks_count',
            'formatter' => 'number',
        ],
    ];

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.custom-providers.update', $provider), [
            'name' => 'renomeado',
            'metrics' => $metrics,
        ])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    $provider->refresh();
    $stars = $provider->definitions()->where('slug', 'stars')->sole();
    $forks = $provider->definitions()->where('slug', 'forks')->sole();

    expect($provider->name)->toBe('renomeado')
        ->and($provider->metrics_version)->toBe(2)
        ->and($provider->url_template)->toBe('https://example.com/repos/{owner}/{repo}.json')
        ->and($stars->label)->toBe('Estrelas')
        ->and($stars->pointer)->toBe('/forks_count')
        ->and($stars->formatter)->toBe(CustomMetricFormatter::Percent)
        ->and($forks->label)->toBe('Bifurcacoes');
});

it('proibe trocar o template no update', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();
    $provider = customProviderCreate($this, $user);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.custom-providers.update', $provider), [
            'name' => 'outro',
            'url_template' => 'https://example.com/novo/{owner}/{repo}.json',
            'metrics' => customProviderForm()['metrics'],
        ])
        ->assertSessionHasErrors('url_template');

    expect($provider->refresh()->url_template)->toBe('https://example.com/repos/{owner}/{repo}.json');
});

it('exige o mesmo conjunto de metricas na edicao', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();
    $provider = customProviderCreate($this, $user);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.custom-providers.update', $provider), [
            'name' => 'renomeado',
            'metrics' => [
                [
                    'slug' => 'stars',
                    'label' => 'Stars',
                    'pointer' => '/stargazers_count',
                    'formatter' => 'number',
                ],
            ],
        ])
        ->assertSessionHasErrors('metrics');

    expect($provider->refresh()->metrics_version)->toBe(1)
        ->and($provider->definitions()->count())->toBe(2);
});

it('responde 404 para provider de outra conta', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $provider = BadgeCustomProvider::factory()->create();
    $intruder = customProviderAccount(email: 'intruso@example.com');

    $this->actingAs($intruder)
        ->patch(route('badgeforge.custom-providers.update', $provider), [
            'name' => 'roubo',
            'metrics' => customProviderForm()['metrics'],
        ])
        ->assertNotFound();

    $this->actingAs($intruder)
        ->delete(route('badgeforge.custom-providers.destroy', $provider))
        ->assertNotFound();

    expect(BadgeCustomProvider::query()->count())->toBe(1);
});

it('exclui o provider e as definicoes em cascade', function () {
    customProviderFakeDns();
    customProviderFakeJson();

    $user = customProviderAccount();
    $provider = customProviderCreate($this, $user);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->delete(route('badgeforge.custom-providers.destroy', $provider))
        ->assertSessionHasNoErrors();

    expect(BadgeCustomProvider::query()->count())->toBe(0)
        ->and(BadgeCustomProviderDefinition::query()->count())->toBe(0);
});
