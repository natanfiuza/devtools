<?php

use App\Models\BadgeAsset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
| Gestao das badges pessoais (F4, ADR-081).
|
| Criar/editar/renomear/trocar PAT/atualizar exige plano pago; excluir e a
| imagem publica continuam valendo depois de um downgrade. O PAT e write-only:
| fica cifrado na linha e nunca volta numa resposta.
*/

const PERSONAL_MANAGEMENT_DIR = 'framework/testing/badgeforge-personal-management';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => PERSONAL_MANAGEMENT_DIR]);

    File::deleteDirectory(storage_path(PERSONAL_MANAGEMENT_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(PERSONAL_MANAGEMENT_DIR));
});

function personalBadgeAccount(string $username = 'natanfiuza', bool $paid = true): User
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

/**
 * @return array<string, mixed>
 */
function personalBadgeForm(): array
{
    return [
        'label' => 'build',
        'message' => 'passing',
        'style' => 'flat',
        'color' => '#44cc11',
        'labelColor' => '#555555',
    ];
}

it('protege as rotas de gestao', function () {
    $asset = BadgeAsset::factory()->create();

    $this->post(route('badgeforge.badges.store'))->assertRedirect(route('login'));
    $this->patch(route('badgeforge.badges.update', $asset))->assertRedirect(route('login'));
    $this->patch(route('badgeforge.badges.credential', $asset))->assertRedirect(route('login'));
    $this->post(route('badgeforge.badges.refresh', $asset))->assertRedirect(route('login'));
    $this->delete(route('badgeforge.badges.destroy', $asset))->assertRedirect(route('login'));
});

it('recusa a criacao para conta free', function () {
    $user = personalBadgeAccount(paid: false);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Minha badge',
            'type' => 'static',
            'form' => personalBadgeForm(),
        ])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasErrors('badge');

    expect($user->badgeAssets()->count())->toBe(0);
});

it('exige username antes de publicar', function () {
    $user = User::factory()->create(['username' => null]);
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();
    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);

    $this->actingAs($user->refresh())
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Minha badge',
            'type' => 'static',
            'form' => personalBadgeForm(),
        ])
        ->assertSessionHasErrors('username');
});

it('cria uma badge estatica e grava o arquivo', function () {
    $user = personalBadgeAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Build do repo',
            'type' => 'static',
            'form' => personalBadgeForm(),
        ])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    $asset = BadgeAsset::query()->sole();

    expect($asset->user_id)->toBe($user->id)
        ->and($asset->type)->toBe('static')
        ->and($asset->private)->toBeFalse()
        ->and($asset->svg_path)->toBe('personal/natanfiuza/'.$asset->id.'.svg')
        ->and($asset->etag)->not->toBeNull()
        ->and($asset->last_generated_at)->not->toBeNull()
        ->and($asset->expires_at?->isFuture())->toBeTrue();

    $svg = app(BadgeAssetStorage::class)->read($asset->svg_path);

    expect($svg)->toContain('>build<')->toContain('>passing<');
});

it('cria uma badge de provider sem chamar o provider', function () {
    Http::preventStrayRequests();

    $user = personalBadgeAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Estrelas',
            'type' => 'provider',
            'provider_slug' => 'github',
            'definition_slug' => 'stars',
            'params' => ['owner' => 'natanfiuza', 'repo' => 'devtools'],
            'form' => personalBadgeForm(),
        ])
        ->assertSessionHasNoErrors();

    $asset = BadgeAsset::query()->sole();

    expect($asset->type)->toBe('provider')
        ->and($asset->provider_slug)->toBe('github')
        ->and($asset->definition_slug)->toBe('stars')
        ->and($asset->params)->toBe(['owner' => 'natanfiuza', 'repo' => 'devtools'])
        ->and($asset->svg_path)->toBeNull()
        ->and($asset->last_generated_at)->toBeNull();

    Http::assertNothingSent();
});

it('cria badge de provider sem params usando o path literal', function () {
    Http::preventStrayRequests();

    $user = personalBadgeAccount();

    foreach ([['php', 'v'], ['laravel', 'dt']] as [$provider, $definition]) {
        $this->actingAs($user)
            ->from(route('dashboard.badgeforge'))
            ->post(route('badgeforge.badges.store'), [
                'name' => 'Runtime '.$provider,
                'type' => 'provider',
                'provider_slug' => $provider,
                'definition_slug' => $definition,
                'params' => [],
                'form' => personalBadgeForm(),
            ])
            ->assertSessionHasNoErrors();
    }

    expect(BadgeAsset::query()->orderBy('id')->pluck('params')->all())->toBe([[], []])
        ->and(BadgeAsset::query()->count())->toBe(2);

    Http::assertNothingSent();
});

it('guarda o PAT cifrado e recusa credencial fora de badge privada', function () {
    $user = personalBadgeAccount();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Repo privado',
            'type' => 'provider',
            'provider_slug' => 'github',
            'definition_slug' => 'stars',
            'params' => ['owner' => 'natanfiuza', 'repo' => 'privado'],
            'private' => true,
            'credential' => 'ghp_segredo_123',
        ])
        ->assertSessionHasNoErrors();

    $asset = BadgeAsset::query()->sole();
    $raw = DB::table('badge_assets')->where('id', $asset->id)->value('credential');

    expect($asset->private)->toBeTrue()
        ->and($asset->credentialSet())->toBeTrue()
        ->and($raw)->toBeString()->not->toBe('ghp_segredo_123')
        ->and(Crypt::decryptString((string) $raw))->toBe('ghp_segredo_123')
        ->and($asset->credential_fingerprint)->toHaveLength(64)
        ->and($asset->toArray())->not->toHaveKeys(['credential', 'credential_fingerprint']);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Publica com token',
            'type' => 'provider',
            'provider_slug' => 'github',
            'definition_slug' => 'forks',
            'params' => ['owner' => 'natanfiuza', 'repo' => 'devtools'],
            'credential' => 'ghp_nao_deveria',
        ])
        ->assertSessionHasErrors('credential');
});

it('recusa privado onde a API nao suporta e exige o username do Docker', function () {
    $user = personalBadgeAccount();

    foreach ([
        ['packagist', 'dt', ['vendor' => 'laravel', 'package' => 'framework']],
        ['npm', 'dm', ['package' => 'vue']],
    ] as [$provider, $definition, $params]) {
        $this->actingAs($user)
            ->from(route('dashboard.badgeforge'))
            ->post(route('badgeforge.badges.store'), [
                'name' => 'Privada '.$provider,
                'type' => 'provider',
                'provider_slug' => $provider,
                'definition_slug' => $definition,
                'params' => $params,
                'private' => true,
                'credential' => 'token',
            ])
            ->assertSessionHasErrors('private');
    }

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Docker privado sem usuario',
            'type' => 'provider',
            'provider_slug' => 'docker',
            'definition_slug' => 'pulls',
            'params' => ['namespace' => 'natanfiuza', 'repository' => 'app'],
            'private' => true,
            'credential' => 'dckr_pat',
        ])
        ->assertSessionHasErrors('credential_username');

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Docker privado',
            'type' => 'provider',
            'provider_slug' => 'docker',
            'definition_slug' => 'pulls',
            'params' => ['namespace' => 'natanfiuza', 'repository' => 'app'],
            'private' => true,
            'credential' => 'dckr_pat',
            'credential_username' => 'natanfiuza',
        ])
        ->assertSessionHasNoErrors();

    expect(BadgeAsset::query()->where('name', 'Docker privado')->sole()->credential_username)->toBe('natanfiuza');
});

it('recusa provider, params ou nome invalidos', function () {
    $user = personalBadgeAccount();

    $invalid = [
        'provider desconhecido' => [
            'provider_slug' => 'nope', 'definition_slug' => 'stars', 'params' => ['owner' => 'a', 'repo' => 'b'],
        ],
        'definicao desconhecida' => [
            'provider_slug' => 'github', 'definition_slug' => 'nope', 'params' => ['owner' => 'a', 'repo' => 'b'],
        ],
        'param faltando' => [
            'provider_slug' => 'github', 'definition_slug' => 'stars', 'params' => ['owner' => 'a'],
        ],
        'param a mais' => [
            'provider_slug' => 'github', 'definition_slug' => 'stars', 'params' => ['owner' => 'a', 'repo' => 'b', 'x' => 'c'],
        ],
        'param a mais sem params' => [
            'provider_slug' => 'php', 'definition_slug' => 'v', 'params' => ['latest' => 'latest'],
        ],
    ];

    foreach ($invalid as $case => $payload) {
        $this->actingAs($user)
            ->from(route('dashboard.badgeforge'))
            ->post(route('badgeforge.badges.store'), [
                'name' => 'Caso '.$case,
                'type' => 'provider',
                ...$payload,
            ])
            ->assertSessionHasErrors();
    }

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => str_repeat('a', 200),
            'type' => 'static',
        ])
        ->assertSessionHasErrors('name');

    expect(BadgeAsset::query()->count())->toBe(0);
});

it('renomeia a propria badge', function () {
    $user = personalBadgeAccount();
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'Antiga']);

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.badges.update', $asset), ['name' => 'Nova'])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    expect($asset->refresh()->name)->toBe('Nova');
});

it('troca o PAT, invalida o arquivo e recalcula o fingerprint', function () {
    $user = personalBadgeAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->private()->create();

    $storage = app(BadgeAssetStorage::class);
    $relative = 'personal/natanfiuza/'.$asset->id.'.svg';
    $storage->write($relative, '<svg></svg>');

    $asset->forceFill(['svg_path' => $relative])->save();
    $before = $asset->credential_fingerprint;

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.badges.credential', $asset), ['credential' => 'ghp_novo_token'])
        ->assertRedirect(route('dashboard.badgeforge'))
        ->assertSessionHasNoErrors();

    $asset->refresh();

    expect($asset->svg_path)->toBeNull()
        ->and($storage->read($relative))->toBeNull()
        ->and($asset->credential_fingerprint)->not->toBe($before)
        ->and(Crypt::decryptString((string) DB::table('badge_assets')->where('id', $asset->id)->value('credential')))
        ->toBe('ghp_novo_token');
});

it('atualiza a badge de provider pelo POST do dono', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/*' => Http::response([
            'stargazers_count' => 1234,
            'forks_count' => 56,
            'open_issues_count' => 7,
            'license' => ['spdx_id' => 'MIT'],
        ]),
    ]);

    $user = personalBadgeAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->create();

    $response = $this->actingAs($user)
        ->postJson(route('badgeforge.badges.refresh', $asset))
        ->assertOk()
        ->assertJsonStructure(['url', 'pngUrl', 'generatedAt']);

    expect($response->json('url'))->toBe(route('badges.personal', [
        'username' => 'natanfiuza', 'badgeAsset' => $asset->id, 'ext' => 'svg',
    ]))
        ->and($asset->refresh()->svg_path)->toBe('personal/natanfiuza/'.$asset->id.'.svg')
        ->and($asset->expires_at?->isFuture())->toBeTrue()
        ->and(app(BadgeAssetStorage::class)->read($asset->svg_path))->toContain('>1.2k<');
});

it('recusa atualizar uma badge estatica', function () {
    $user = personalBadgeAccount();
    $asset = BadgeAsset::factory()->for($user)->static()->create();

    $this->actingAs($user)
        ->postJson(route('badgeforge.badges.refresh', $asset))
        ->assertStatus(422)
        ->assertJsonValidationErrors('badge');
});

it('responde 404 para badge de outra conta', function () {
    $owner = personalBadgeAccount('owner');
    $intruder = personalBadgeAccount('intruder');

    $asset = BadgeAsset::factory()->for($owner)->create(['name' => 'Antiga']);

    $this->actingAs($intruder)->patch(route('badgeforge.badges.update', $asset), ['name' => 'X'])->assertNotFound();
    $this->actingAs($intruder)->patch(route('badgeforge.badges.credential', $asset), ['credential' => 'x'])->assertNotFound();
    $this->actingAs($intruder)->post(route('badgeforge.badges.refresh', $asset))->assertNotFound();
    $this->actingAs($intruder)->delete(route('badgeforge.badges.destroy', $asset))->assertNotFound();

    expect(BadgeAsset::query()->count())->toBe(1);
});

it('depois do downgrade serve e exclui, mas bloqueia edicao', function () {
    $user = personalBadgeAccount();
    $asset = BadgeAsset::factory()->for($user)->static()->create(['name' => 'Sobrevivente']);

    $user->subscriptions()->delete();

    $this->actingAs($user->refresh())
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.badges.update', $asset), ['name' => 'Nova'])
        ->assertSessionHasErrors('badge');

    $this->get(route('badges.personal', [
        'username' => 'natanfiuza', 'badgeAsset' => $asset->id, 'ext' => 'svg',
    ]))->assertOk();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->delete(route('badgeforge.badges.destroy', $asset))
        ->assertSessionHasNoErrors();

    expect(BadgeAsset::query()->count())->toBe(0);
});

it('limita o refresh por conta', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.github.com/*' => Http::response([
            'stargazers_count' => 1,
            'forks_count' => 1,
            'open_issues_count' => 1,
            'license' => ['spdx_id' => 'MIT'],
        ]),
    ]);

    config(['badgeforge.providers.refresh_rate_limit' => 2]);

    $user = personalBadgeAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->create();

    $this->actingAs($user)->postJson(route('badgeforge.badges.refresh', $asset))->assertOk();
    $this->actingAs($user)->postJson(route('badgeforge.badges.refresh', $asset))->assertOk();

    $this->actingAs($user)
        ->postJson(route('badgeforge.badges.refresh', $asset))
        ->assertStatus(429)
        ->assertJsonStructure(['message']);
});
