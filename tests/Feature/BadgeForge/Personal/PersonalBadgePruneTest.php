<?php

use App\Models\BadgeAsset;
use App\Models\User;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
| Poda dos arquivos de badges pessoais (F4, ADR-081): orfao antigo sai,
| arquivo ainda referenciado nunca sai, e `--dry-run` nao apaga nada.
*/

const PERSONAL_PRUNE_DIR = 'framework/testing/badgeforge-personal-prune';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => PERSONAL_PRUNE_DIR]);

    File::deleteDirectory(storage_path(PERSONAL_PRUNE_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(PERSONAL_PRUNE_DIR));
});

function personalPruneFile(BadgeAssetStorage $storage, string $relative, int $hoursAgo): void
{
    $storage->write($relative, '<svg></svg>');

    touch($storage->base().'/'.$relative, now()->subHours($hoursAgo)->getTimestamp());
}

it('remove orfao antigo e preserva o arquivo referenciado', function () {
    $storage = app(BadgeAssetStorage::class);
    $user = User::factory()->create(['username' => 'natanfiuza']);
    $asset = BadgeAsset::factory()->for($user)->create();

    $referenced = 'personal/natanfiuza/'.$asset->id.'.svg';
    $orphan = 'personal/natanfiuza/'.Str::ulid().'.svg';
    $recentOrphan = 'personal/natanfiuza/'.Str::ulid().'.svg';

    personalPruneFile($storage, $referenced, 72);
    personalPruneFile($storage, $orphan, 72);
    personalPruneFile($storage, $recentOrphan, 1);

    $asset->forceFill(['svg_path' => $referenced])->save();

    $this->artisan('badgeforge:prune')->assertSuccessful();

    expect($storage->read($referenced))->not->toBeNull()
        ->and($storage->read($orphan))->toBeNull()
        ->and($storage->read($recentOrphan))->not->toBeNull();
});

it('nao apaga nada no dry-run', function () {
    $storage = app(BadgeAssetStorage::class);
    $orphan = 'personal/natanfiuza/'.Str::ulid().'.svg';

    personalPruneFile($storage, $orphan, 72);

    $this->artisan('badgeforge:prune', ['--dry-run' => true])
        ->expectsOutputToContain('badges pessoais seriam removidos')
        ->assertSuccessful();

    expect($storage->read($orphan))->not->toBeNull();
});

it('ignora a poda pessoal quando o banco nao responde', function () {
    $storage = app(BadgeAssetStorage::class);
    $orphan = 'personal/natanfiuza/'.Str::ulid().'.svg';

    personalPruneFile($storage, $orphan, 72);

    Schema::rename('badge_assets', 'badge_assets_gone');

    try {
        $this->artisan('badgeforge:prune')->assertSuccessful();

        expect($storage->read($orphan))->not->toBeNull();
    } finally {
        Schema::rename('badge_assets_gone', 'badge_assets');
    }
});
