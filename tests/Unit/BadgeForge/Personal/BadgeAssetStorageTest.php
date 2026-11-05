<?php

use App\Models\BadgeAsset;
use App\Models\User;
use App\Support\BadgeForge\Personal\BadgeAssetStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

/*
| Arquivos das badges pessoais (F4, ADR-081): o banco guarda path relativo sob
| `personal/`, a escrita e atomica e qualquer caminho fora do prefixo e
| ignorado - inclusive o que vier adulterado do banco.
*/

const PERSONAL_STORAGE_DIR = 'framework/testing/badgeforge-personal';

beforeEach(function () {
    config(['badgeforge.personal_badges.path' => PERSONAL_STORAGE_DIR]);

    File::deleteDirectory(storage_path(PERSONAL_STORAGE_DIR));
});

afterEach(function () {
    File::deleteDirectory(storage_path(PERSONAL_STORAGE_DIR));
});

function personalStorageAsset(?string $username = 'alice'): BadgeAsset
{
    $user = new User(['username' => $username]);
    $asset = new BadgeAsset(['id' => (string) Str::ulid()]);
    $asset->setRelation('user', $user);

    return $asset;
}

it('monta o path relativo sob personal com o username congelado', function () {
    $storage = app(BadgeAssetStorage::class);
    $asset = personalStorageAsset();

    expect($storage->pathFor($asset, 'svg'))->toBe('personal/alice/'.$asset->getKey().'.svg')
        ->and($storage->pathFor($asset, 'png'))->toBe('personal/alice/'.$asset->getKey().'.png');
});

it('recusa username ausente ou invalido', function (?string $username) {
    $storage = app(BadgeAssetStorage::class);

    expect($storage->pathFor(personalStorageAsset($username), 'svg'))->toBeNull();
})->with([
    'ausente' => [null],
    'vazio' => [''],
    'ponto' => ['.'],
    'dois pontos' => ['..'],
    'barra' => ['ana/clara'],
    'controle' => ["ana\nclara"],
]);

it('grava e le o arquivo de forma atomica', function () {
    $storage = app(BadgeAssetStorage::class);
    $relative = $storage->pathFor(personalStorageAsset(), 'svg');

    expect($relative)->not->toBeNull();

    $storage->write((string) $relative, '<svg></svg>');

    expect($storage->read($relative))->toBe('<svg></svg>')
        ->and(File::allFiles(storage_path(PERSONAL_STORAGE_DIR).'/personal/alice'))->toHaveCount(1)
        ->and(File::files(storage_path(PERSONAL_STORAGE_DIR).'/personal/alice')[0]->getExtension())->toBe('svg');
});

it('ignora caminho fora do prefixo personal', function (string $relative) {
    $storage = app(BadgeAssetStorage::class);

    expect($storage->read($relative))->toBeNull();

    $storage->delete($relative);

    expect(File::exists(storage_path(PERSONAL_STORAGE_DIR).'/../'.$relative))->toBeFalse();
})->with([
    'sem prefixo' => ['alice/badge.svg'],
    'traversal' => ['personal/alice/../../.env'],
    'barra invertida' => ['personal\\alice\\badge.svg'],
    'nulo' => ["personal/alice/badge\0.svg"],
]);

it('apaga o arquivo referenciado e tolera ausente', function () {
    $storage = app(BadgeAssetStorage::class);
    $relative = $storage->pathFor(personalStorageAsset(), 'png');

    $storage->write((string) $relative, 'png');

    expect($storage->read($relative))->toBe('png');

    $storage->delete($relative);

    expect($storage->read($relative))->toBeNull();

    // Repetir a exclusao nao pode falhar.
    $storage->delete($relative);
    $storage->delete(null);
});

it('so considera fresco quando o arquivo existe e a validade esta no futuro', function () {
    $storage = app(BadgeAssetStorage::class);
    $asset = personalStorageAsset();
    $relative = $storage->pathFor($asset, 'svg');

    $asset->expires_at = now()->addHour();

    expect($storage->isFresh($asset))->toBeFalse();

    $storage->write((string) $relative, '<svg></svg>');
    $asset->svg_path = $relative;

    expect($storage->isFresh($asset))->toBeTrue();

    $asset->expires_at = now()->subMinute();

    expect($storage->isFresh($asset))->toBeFalse();
});
