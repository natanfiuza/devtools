<?php

use App\Actions\Colors\ArchivePalette;
use App\Actions\Colors\CreatePalette;
use App\Actions\Colors\DeletePalette;
use App\Actions\Colors\DuplicatePalette;
use App\Actions\Colors\RestorePalette;
use App\Actions\Colors\UpdatePalette;
use App\Models\ColorPalette;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Colors\PaletteData;
use App\Support\Colors\PaletteOgStorage;
use App\Support\Colors\PaletteTags;
use Database\Factories\ColorPaletteFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
| Persistencia da paleta sem HTTP (F4, ADR-123/ADR-124).
|
| Cobre cores efetivas, cota sob lock, ciclo publicar/renomear/arquivar/
| restaurar/excluir, duplicacao com proveniencia e tags como entidade.
*/

beforeEach(function () {
    config(['colors.og.path' => 'framework/testing/colors-og']);
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/colors-og'));
});

function paletteActionData(array $overrides = []): PaletteData
{
    $colors = ColorPaletteFactory::scaleColors();

    return PaletteData::fromArray(array_merge([
        'name' => 'Minha Paleta',
        'description' => null,
        'visibility' => 'private',
        'kind' => 'scale',
        'algorithm_version' => 1,
        'colors' => $colors,
        'roles' => ColorPaletteFactory::rolesFromScale($colors['items']),
        'source' => null,
        'tags' => [],
    ], $overrides));
}

function palettePaidAccount(User $user): void
{
    $plan = Plan::query()->where('slug', 'pro-monthly')->sole();

    Subscription::factory()->for($user)->for($plan)->create([
        'current_period_start' => now()->subWeek(),
        'current_period_end' => now()->addWeeks(3),
    ]);
}

it('persiste cores efetivas, papeis e tags', function () {
    $user = User::factory()->create();
    $data = paletteActionData(['tags' => ['Marca', 'marca', 'Site']]);

    $palette = app(CreatePalette::class)($user, $data);

    expect($palette->colors)->toBe($data->colors)
        ->and($palette->roles)->toBe($data->roles)
        ->and($palette->colors_count)->toBe(11)
        ->and($palette->slug)->toBeNull()
        ->and($palette->visibility->value)->toBe('private')
        ->and($palette->tags)->toHaveCount(2);
});

it('gera slug apenas quando nasce publica', function () {
    $user = User::factory()->create();

    $palette = app(CreatePalette::class)($user, paletteActionData([
        'name' => 'Minha Paleta',
        'visibility' => 'public',
    ]));

    expect($palette->slug)->toBe('minha-paleta');
});

it('aplica a cota free contando arquivadas e libera ao excluir', function () {
    config(['colors.palettes.free_limit' => 1]);

    $user = User::factory()->create();
    $create = app(CreatePalette::class);

    $palette = $create($user, paletteActionData());
    app(ArchivePalette::class)($palette);

    expect(fn () => $create($user, paletteActionData(['name' => 'Outra'])))
        ->toThrow(ValidationException::class);

    app(DeletePalette::class)($palette);

    expect($create($user, paletteActionData(['name' => 'Outra']))->name)->toBe('Outra');
});

it('nao limita conta paga', function () {
    config(['colors.palettes.free_limit' => 1]);

    $user = User::factory()->create();
    palettePaidAccount($user);

    $create = app(CreatePalette::class);

    $create($user, paletteActionData(['name' => 'Uma']));
    $create($user, paletteActionData(['name' => 'Duas']));

    expect($user->colorPalettes()->count())->toBe(2);
});

it('renomear nao muda o slug e invalida a OG', function () {
    $user = User::factory()->create();
    $palette = app(CreatePalette::class)($user, paletteActionData([
        'visibility' => 'public',
    ]));

    $og = app(PaletteOgStorage::class);
    $og->put($palette->id, 'png');
    $palette->forceFill(['og_version' => 1])->save();

    $updated = app(UpdatePalette::class)($palette, paletteActionData([
        'name' => 'Nome Novo',
        'visibility' => 'public',
    ]));

    expect($updated->name)->toBe('Nome Novo')
        ->and($updated->slug)->toBe('minha-paleta')
        ->and($updated->og_version)->toBeNull()
        ->and($og->exists($palette->id))->toBeFalse();
});

it('publica uma paleta existente sem mudar o resto', function () {
    $user = User::factory()->create();
    $palette = app(CreatePalette::class)($user, paletteActionData());

    $updated = app(UpdatePalette::class)($palette, paletteActionData([
        'visibility' => 'public',
    ]));

    expect($updated->slug)->toBe('minha-paleta');
});

it('duplica copiando cores, papeis e tags e somando uso apenas de outra conta', function () {
    $owner = User::factory()->create();
    $source = app(CreatePalette::class)($owner, paletteActionData([
        'name' => 'Origem',
        'tags' => ['marca'],
    ]));

    $copy = app(DuplicatePalette::class)($owner, $source);

    expect($copy->name)->toBe('Origem'.__('colors.palette.copy_suffix'))
        ->and($copy->visibility->value)->toBe('private')
        ->and($copy->slug)->toBeNull()
        ->and($copy->duplicated_from_id)->toBe($source->id)
        ->and($copy->colors)->toBe($source->colors)
        ->and($copy->tags->pluck('name')->all())->toBe(['marca'])
        ->and($source->fresh()->duplications_count)->toBe(0);

    $other = User::factory()->create();

    app(DuplicatePalette::class)($other, $source);

    expect($source->fresh()->duplications_count)->toBe(1);
});

it('duplica com nova base usando o override', function () {
    $owner = User::factory()->create();
    $source = app(CreatePalette::class)($owner, paletteActionData(['name' => 'Origem']));

    $override = paletteActionData(['name' => 'Origem']);

    $copy = app(DuplicatePalette::class)(User::factory()->create(), $source, $override);

    expect($copy->colors)->toBe($override->colors)
        ->and($copy->kind->value)->toBe('scale');
});

it('arquiva liberando o slug e restaura reivindicando ou gerando outro', function () {
    $owner = User::factory()->create();
    $palette = app(CreatePalette::class)($owner, paletteActionData([
        'visibility' => 'public',
    ]));

    app(ArchivePalette::class)($palette);
    $palette->refresh();

    expect($palette->slug)->toBeNull()
        ->and($palette->archived_slug)->toBe('minha-paleta')
        ->and($palette->isArchived())->toBeTrue();

    $other = app(CreatePalette::class)($owner, paletteActionData([
        'name' => 'Minha Paleta',
        'visibility' => 'public',
    ]));

    expect($other->slug)->toBe('minha-paleta');

    app(RestorePalette::class)($palette);
    $palette->refresh();

    expect($palette->isArchived())->toBeFalse()
        ->and($palette->slug)->toBe('minha-paleta-2')
        ->and($palette->archived_slug)->toBeNull();
});

it('restaura o mesmo slug quando ninguem o tomou', function () {
    $owner = User::factory()->create();
    $palette = app(CreatePalette::class)($owner, paletteActionData([
        'visibility' => 'public',
    ]));

    app(ArchivePalette::class)($palette);
    app(RestorePalette::class)($palette);

    expect($palette->fresh()->slug)->toBe('minha-paleta');
});

it('exclui a linha e o arquivo OG', function () {
    $user = User::factory()->create();
    $palette = app(CreatePalette::class)($user, paletteActionData());

    $og = app(PaletteOgStorage::class);
    $og->put($palette->id, 'png');

    app(DeletePalette::class)($palette);

    expect(ColorPalette::query()->whereKey($palette->id)->exists())->toBeFalse()
        ->and($og->exists($palette->id))->toBeFalse();
});

it('renomeia, reordena e exclui tags', function () {
    $user = User::factory()->create();
    $tags = app(PaletteTags::class);

    $marca = $tags->create($user, 'marca');
    $site = $tags->create($user, 'site');

    expect($marca->position)->toBeLessThan($site->position);

    $tags->rename($marca, 'Marca Nova');
    expect($marca->fresh()->name)->toBe('Marca Nova')
        ->and($marca->fresh()->key)->toBe('marca nova');

    expect(fn () => $tags->rename($marca, 'Site'))
        ->toThrow(ValidationException::class);

    $tags->reorder($user, [$site->id, $marca->id]);
    expect($site->fresh()->position)->toBe(0)
        ->and($marca->fresh()->position)->toBe(1);

    $tags->delete($site);
    expect($user->colorTags()->count())->toBe(1);
});
