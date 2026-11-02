<?php

use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/*
| Poda do cache estatico do BadgeForge (F2, ADR-077): o cache e sem teto e
| cada PNG apagado e gerado de novo sob demanda.
*/

const PRUNE_CACHE_DIR = 'framework/testing/badgeforge-prune';

beforeEach(function () {
    config(['badgeforge.png.cache_path' => PRUNE_CACHE_DIR]);

    File::deleteDirectory(storage_path(PRUNE_CACHE_DIR));
    File::ensureDirectoryExists(storage_path(PRUNE_CACHE_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(PRUNE_CACHE_DIR));
});

function pruneFile(string $name, int $hoursAgo): string
{
    $path = storage_path(PRUNE_CACHE_DIR).'/'.$name;

    File::put($path, 'png');
    touch($path, now()->subHours($hoursAgo)->getTimestamp());

    return $path;
}

it('remove os PNGs antigos e preserva os recentes', function () {
    $old = pruneFile('old.png', 72);
    $fresh = pruneFile('fresh.png', 1);
    $tmp = pruneFile('build.tmp', 72);

    $this->artisan('badgeforge:prune')->assertSuccessful();

    expect(File::exists($old))->toBeFalse()
        ->and(File::exists($fresh))->toBeTrue()
        ->and(File::exists($tmp))->toBeTrue();
});

it('respeita o limite configurado de horas', function () {
    config(['badgeforge.png.prune_after_hours' => 24]);

    $old = pruneFile('old.png', 30);
    $fresh = pruneFile('fresh.png', 12);

    $this->artisan('badgeforge:prune')->assertSuccessful();

    expect(File::exists($old))->toBeFalse()
        ->and(File::exists($fresh))->toBeTrue();
});

it('nao apaga nada no dry-run', function () {
    $old = pruneFile('old.png', 72);

    $this->artisan('badgeforge:prune', ['--dry-run' => true])
        ->expectsOutputToContain('1 PNG')
        ->assertSuccessful();

    expect(File::exists($old))->toBeTrue();
});

it('informa cache vazio quando o diretorio nao existe', function () {
    File::deleteDirectory(storage_path(PRUNE_CACHE_DIR));

    $this->artisan('badgeforge:prune')
        ->expectsOutputToContain('vazio')
        ->assertSuccessful();
});
