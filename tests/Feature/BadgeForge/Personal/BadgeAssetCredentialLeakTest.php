<?php

use App\Actions\Account\AnonymizeUser;
use App\Actions\Account\ExportUserData;
use App\Actions\BadgeForge\CreateBadgeAsset;
use App\Models\BadgeAsset;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/*
| O PAT de uma badge pessoal (F4, ADR-081) e write-only: existe cifrado na
| linha e nao aparece em props, resposta, flash, log, exportacao nem cache.
*/

const PERSONAL_LEAK_DIR = 'framework/testing/badgeforge-personal-leak';

const PERSONAL_LEAK_TOKEN = 'ghp_token_secreto_9f3a';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => PERSONAL_LEAK_DIR]);

    File::deleteDirectory(storage_path(PERSONAL_LEAK_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(PERSONAL_LEAK_DIR));
});

/**
 * @return array<string, mixed>
 */
function personalLeakGithubPayload(): array
{
    return [
        'stargazers_count' => 1234,
        'forks_count' => 56,
        'open_issues_count' => 7,
        'license' => ['spdx_id' => 'MIT'],
    ];
}

function personalLeakPaidUser(string $username = 'natanfiuza'): User
{
    $user = User::factory()->create(['username' => $username]);
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);

    return $user->refresh();
}

function personalLeakPrivateAsset(User $user): BadgeAsset
{
    return app(CreateBadgeAsset::class)($user, [
        'name' => 'Repo privado',
        'type' => 'provider',
        'provider_slug' => 'github',
        'definition_slug' => 'stars',
        'params' => ['owner' => 'natanfiuza', 'repo' => 'privado'],
        'private' => true,
        'credential' => PERSONAL_LEAK_TOKEN,
    ]);
}

it('grava o token cifrado e o esconde do modelo', function () {
    $asset = personalLeakPrivateAsset(personalLeakPaidUser());

    $raw = DB::table('badge_assets')->where('id', $asset->id)->value('credential');

    expect($raw)->toBeString()->not->toBe(PERSONAL_LEAK_TOKEN)
        ->and($asset->toArray())->not->toHaveKey('credential')
        ->and($asset->toArray())->not->toHaveKey('credential_fingerprint')
        ->and(json_encode($asset->toArray(), JSON_THROW_ON_ERROR))->not->toContain(PERSONAL_LEAK_TOKEN);
});

it('nao devolve o token no refresh nem no DOM da resposta', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalLeakGithubPayload())]);

    $user = personalLeakPaidUser();
    $asset = personalLeakPrivateAsset($user);

    $response = $this->actingAs($user)
        ->postJson(route('badgeforge.badges.refresh', $asset))
        ->assertOk();

    expect((string) $response->getContent())->not->toContain(PERSONAL_LEAK_TOKEN);
});

it('mantem o token fora do input devolvido num 422', function () {
    $user = personalLeakPaidUser();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->post(route('badgeforge.badges.store'), [
            'name' => 'Badge invalida',
            'type' => 'provider',
            'provider_slug' => 'nope',
            'definition_slug' => 'stars',
            'params' => ['owner' => 'a', 'repo' => 'b'],
            'private' => true,
            'credential' => PERSONAL_LEAK_TOKEN,
        ])
        ->assertSessionHasErrors('provider_slug');

    $old = session('_old_input');

    expect($old)->toBeArray()->not->toHaveKey('credential')
        ->and(json_encode($old, JSON_THROW_ON_ERROR))->not->toContain(PERSONAL_LEAK_TOKEN);
});

it('nao registra o token em log quando a credencial nao decifra', function () {
    Http::preventStrayRequests();
    Http::fake(['api.github.com/*' => Http::response(personalLeakGithubPayload())]);

    $logged = [];

    Log::listen(function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context, JSON_THROW_ON_ERROR);
    });

    $user = personalLeakPaidUser();
    $asset = personalLeakPrivateAsset($user);

    DB::table('badge_assets')->where('id', $asset->id)->update(['credential' => 'ciphertext-invalido']);

    $this->get(route('badges.personal', [
        'username' => 'natanfiuza',
        'badgeAsset' => $asset->id,
        'ext' => 'svg',
    ]))->assertOk();

    expect(implode("\n", $logged))->not->toContain(PERSONAL_LEAK_TOKEN);
});

it('exporta metadados sem o token', function () {
    $user = personalLeakPaidUser();
    personalLeakPrivateAsset($user);

    $export = app(ExportUserData::class)($user);

    $badges = $export['badgeforge']['badges'];

    expect($badges)->toHaveCount(1)
        ->and($badges[0]['credential_set'])->toBeTrue()
        ->and(json_encode($export, JSON_THROW_ON_ERROR))->not->toContain(PERSONAL_LEAK_TOKEN)
        ->and($badges[0])->not->toHaveKey('credential')
        ->and($badges[0])->not->toHaveKey('credential_fingerprint');
});

it('apaga arquivos e linhas na exclusao da conta', function () {
    $user = personalLeakPaidUser();
    $asset = personalLeakPrivateAsset($user);

    $storage = app(BadgeAssetStorage::class);
    $relative = 'personal/natanfiuza/'.$asset->id.'.svg';
    $storage->write($relative, '<svg></svg>');
    $asset->forceFill(['svg_path' => $relative])->save();

    app(AnonymizeUser::class)($user->refresh());

    expect($storage->read($relative))->toBeNull()
        ->and(BadgeAsset::query()->count())->toBe(0);
});

it('apaga o arquivo antigo quando o token e trocado', function () {
    $user = personalLeakPaidUser();
    $asset = personalLeakPrivateAsset($user);

    $storage = app(BadgeAssetStorage::class);
    $relative = 'personal/natanfiuza/'.$asset->id.'.svg';
    $storage->write($relative, '<svg></svg>');
    $asset->forceFill(['svg_path' => $relative])->save();

    $this->actingAs($user)
        ->from(route('dashboard.badgeforge'))
        ->patch(route('badgeforge.badges.credential', $asset), ['credential' => 'ghp_outro_token'])
        ->assertSessionHasNoErrors();

    expect($storage->read($relative))->toBeNull()
        ->and($asset->refresh()->svg_path)->toBeNull();
});
