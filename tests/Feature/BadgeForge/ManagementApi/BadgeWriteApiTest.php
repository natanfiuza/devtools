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

uses(RefreshDatabase::class);

/*
| Escrita de badges pessoais pela API de gestao (F8, ADR-088).
|
| As regras continuam nas actions: plano pago, username, provider e
| credencial. A API so troca o `back()` da tela por 201 e o envelope.
*/

const BADGE_WRITE_API_DIR = 'framework/testing/badgeforge-write-api';

const BADGE_WRITE_API_TOKEN = 'ghp_api_secreto_7c1d';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => BADGE_WRITE_API_DIR]);

    File::deleteDirectory(storage_path(BADGE_WRITE_API_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(BADGE_WRITE_API_DIR));
});

function badgeWriteApiToken(User $user, array $abilities = ['badgeforge:write']): string
{
    return $user->createToken('management-write', $abilities)->plainTextToken;
}

function badgeWriteApiAccount(string $username = 'octocat', bool $paid = true): User
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
function badgeWriteApiForm(): array
{
    return [
        'label' => 'build',
        'message' => 'passing',
        'style' => 'flat',
        'color' => '#44cc11',
        'labelColor' => '#555555',
    ];
}

it('cria uma badge estática e devolve a URL pública', function () {
    $user = badgeWriteApiAccount();

    $response = $this->withToken(badgeWriteApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Build da API',
            'type' => 'static',
            'form' => badgeWriteApiForm(),
        ])
        ->assertCreated();

    $badge = $response->json('data');

    expect($badge)->toHaveKeys(['id', 'name', 'type', 'private', 'credentialSet', 'url', 'pngUrl'])
        ->and($badge['name'])->toBe('Build da API')
        ->and($badge['type'])->toBe('static')
        ->and($badge['credentialSet'])->toBeFalse()
        ->and($badge['url'])->toEndWith('/b/octocat/'.$badge['id'].'.svg');

    expect(BadgeAsset::query()->whereKey($badge['id'])->exists())->toBeTrue();
});

it('recusa a criação para conta Free no envelope', function () {
    $user = badgeWriteApiAccount(paid: false);

    $this->withToken(badgeWriteApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Free',
            'type' => 'static',
            'form' => badgeWriteApiForm(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.errors.badge.0', __('validation.badge_asset.paid_only'));

    expect($user->badgeAssets()->count())->toBe(0);
});

it('grava a credencial cifrada sem devolvê-la na resposta', function () {
    $user = badgeWriteApiAccount();

    $response = $this->withToken(badgeWriteApiToken($user))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Repo privado',
            'type' => 'provider',
            'provider_slug' => 'github',
            'definition_slug' => 'stars',
            'params' => ['owner' => 'octocat', 'repo' => 'privado'],
            'private' => true,
            'credential' => BADGE_WRITE_API_TOKEN,
        ])
        ->assertCreated();

    expect($response->json('data.credentialSet'))->toBeTrue()
        ->and((string) $response->getContent())->not->toContain(BADGE_WRITE_API_TOKEN)
        ->and((string) $response->getContent())->not->toContain('"credential"');
});

it('exclui a própria badge e responde 404 para a de outra conta', function () {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->create(['name' => 'minha']);

    $this->withToken(badgeWriteApiToken($user))
        ->deleteJson(route('api.v1.badgeforge.badges.destroy', ['badgeAsset' => $asset->id]))
        ->assertOk()
        ->assertExactJson(['data' => ['id' => (string) $asset->id, 'deleted' => true]]);

    expect(BadgeAsset::query()->whereKey($asset->id)->exists())->toBeFalse();

    $foreign = BadgeAsset::factory()->create();

    $this->withToken(badgeWriteApiToken($user))
        ->deleteJson(route('api.v1.badgeforge.badges.destroy', ['badgeAsset' => $foreign->id]))
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect(BadgeAsset::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('recusa um token só de leitura na escrita de badges', function () {
    $user = badgeWriteApiAccount();

    $this->withToken(badgeWriteApiToken($user, ['badgeforge:read']))
        ->postJson(route('api.v1.badgeforge.badges.store'), [
            'name' => 'Sem permissão',
            'type' => 'static',
            'form' => badgeWriteApiForm(),
        ])
        ->assertStatus(403);

    expect($user->badgeAssets()->count())->toBe(0);
});

it('renomeia badge estática e de provider pelo PATCH', function (string $state) {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->{$state}()->create(['name' => 'Antiga']);

    $response = $this->withToken(badgeWriteApiToken($user))
        ->patchJson(route('api.v1.badgeforge.badges.update', ['badgeAsset' => $asset->id]), [
            'name' => 'Nova',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $asset->id)
        ->assertJsonPath('data.name', 'Nova')
        ->assertJsonPath('data.type', $state === 'provider' ? 'provider' : 'static');

    expect($asset->refresh()->name)->toBe('Nova')
        // A representacao mutavel e so o nome: origem continua intacta.
        ->and($asset->provider_slug)->toBe($state === 'provider' ? 'github' : null)
        ->and($response->json('data'))->toHaveKeys(['id', 'name', 'type', 'url', 'pngUrl']);
})->with(['static', 'provider']);

it('aceita o PATCH com o mesmo nome (unique ignorando o próprio id)', function () {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->static()->create(['name' => 'Mesmo']);

    $this->withToken(badgeWriteApiToken($user))
        ->patchJson(route('api.v1.badgeforge.badges.update', ['badgeAsset' => $asset->id]), [
            'name' => 'Mesmo',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Mesmo');
});

it('valida o nome obrigatório no PATCH', function () {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->static()->create(['name' => 'Original']);

    $this->withToken(badgeWriteApiToken($user))
        ->patchJson(route('api.v1.badgeforge.badges.update', ['badgeAsset' => $asset->id]), [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonCount(1, 'error.details.errors.name');

    expect($asset->refresh()->name)->toBe('Original');
});

it('recusa o PATCH para conta Free no envelope', function () {
    $free = badgeWriteApiAccount('freebie', paid: false);
    $asset = BadgeAsset::factory()->for($free)->static()->create(['name' => 'Original']);

    $this->withToken(badgeWriteApiToken($free))
        ->patchJson(route('api.v1.badgeforge.badges.update', ['badgeAsset' => $asset->id]), [
            'name' => 'Tentativa',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.errors.badge.0', __('validation.badge_asset.paid_only'));

    expect($asset->refresh()->name)->toBe('Original');
});

it('rotaciona a credencial sem devolvê-la e apaga o cache do arquivo', function () {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->private()->create();

    $storage = app(BadgeAssetStorage::class);
    $relative = 'personal/octocat/'.$asset->id.'.svg';
    $storage->write($relative, '<svg></svg>');

    $asset->forceFill(['svg_path' => $relative])->save();
    $before = $asset->credential_fingerprint;

    $response = $this->withToken(badgeWriteApiToken($user))
        ->patchJson(route('api.v1.badgeforge.badges.credential', ['badgeAsset' => $asset->id]), [
            'credential' => 'ghp_rotacionado_9a',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $asset->id)
        ->assertJsonPath('data.credentialSet', true);

    $asset->refresh();

    expect($asset->svg_path)->toBeNull()
        ->and($storage->read($relative))->toBeNull()
        ->and($asset->credential_fingerprint)->not->toBe($before)
        ->and(Crypt::decryptString((string) DB::table('badge_assets')->where('id', $asset->id)->value('credential')))
        ->toBe('ghp_rotacionado_9a')
        ->and((string) $response->getContent())->not->toContain('ghp_rotacionado_9a')
        ->and((string) $response->getContent())->not->toContain('"credential"');
});

it('recusa credencial em badge estática', function () {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->static()->create();

    $this->withToken(badgeWriteApiToken($user))
        ->patchJson(route('api.v1.badgeforge.badges.credential', ['badgeAsset' => $asset->id]), [
            'credential' => 'ghp_nao_serve',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.errors.credential.0', __('validation.badge_asset.credential_not_allowed'));
});

it('responde 404 no PATCH/credencial de badge de outra conta', function () {
    $owner = badgeWriteApiAccount('owner');
    $intruder = badgeWriteApiAccount('intruder');

    $asset = BadgeAsset::factory()->for($owner)->provider()->private()->create(['name' => 'Antiga']);

    $this->withToken(badgeWriteApiToken($intruder))
        ->patchJson(route('api.v1.badgeforge.badges.update', ['badgeAsset' => $asset->id]), ['name' => 'X'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    $this->withToken(badgeWriteApiToken($intruder))
        ->patchJson(route('api.v1.badgeforge.badges.credential', ['badgeAsset' => $asset->id]), ['credential' => 'x'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    expect($asset->refresh()->name)->toBe('Antiga')
        ->and($asset->credential)->toBe('ghp_factory_token');
});

it('recusa um token só de leitura no PATCH e na credencial', function () {
    $user = badgeWriteApiAccount();
    $asset = BadgeAsset::factory()->for($user)->provider()->private()->create(['name' => 'Antiga']);

    $this->withToken(badgeWriteApiToken($user, ['badgeforge:read']))
        ->patchJson(route('api.v1.badgeforge.badges.update', ['badgeAsset' => $asset->id]), ['name' => 'X'])
        ->assertStatus(403);

    $this->withToken(badgeWriteApiToken($user, ['badgeforge:read']))
        ->patchJson(route('api.v1.badgeforge.badges.credential', ['badgeAsset' => $asset->id]), ['credential' => 'x'])
        ->assertStatus(403);

    expect($asset->refresh()->name)->toBe('Antiga');
});
