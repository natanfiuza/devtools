<?php

use App\Actions\Colors\ArchiveLayoutSystem;
use App\Actions\Colors\CreateLayoutSystem;
use App\Actions\Colors\DeleteLayoutSystem;
use App\Actions\Colors\DuplicateLayoutSystem;
use App\Actions\Colors\RestoreLayoutSystem;
use App\Actions\Colors\UpdateLayoutSystem;
use App\Enums\PaletteVisibility;
use App\Models\ColorPalette;
use App\Models\LayoutSystem;
use App\Models\User;
use App\Support\Colors\LayoutSystemData;
use Database\Factories\LayoutSystemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
| Persistencia do sistema de layout sem HTTP (F4, ADR-168 a ADR-172).
|
| Cobre estado derivado, cota conjunta sob lock, ciclo de vida com slug,
| credito congelado da origem e duplicacao com linhagem.
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function layoutSystemActionData(array $overrides = []): LayoutSystemData
{
    return LayoutSystemData::fromArray(array_merge([
        'name' => 'Sistema do Site',
        'description' => null,
        'visibility' => 'private',
        'tags' => [],
        'state' => LayoutSystemFactory::stateArray(),
        'source_palette_id' => null,
    ], $overrides));
}

it('persiste o estado e deriva versoes e contagem do snapshot', function () {
    $user = User::factory()->create();
    $state = LayoutSystemFactory::stateArray();
    $state['snapshot']['algorithmVersion'] = 4;

    $system = app(CreateLayoutSystem::class)($user, layoutSystemActionData(['state' => $state]));

    expect($system->state)->toBe($state)
        ->and($system->tokens_count)->toBe(66)
        ->and($system->schema_version)->toBe(1)
        ->and($system->snapshot_version)->toBe(1)
        ->and($system->algorithm_version)->toBe(4)
        ->and($system->visibility)->toBe(PaletteVisibility::Private)
        ->and($system->slug)->toBeNull();
});

it('gera slug apenas quando nasce publico', function () {
    $user = User::factory()->create();

    $system = app(CreateLayoutSystem::class)($user, layoutSystemActionData([
        'name' => 'Sistema do Site',
        'visibility' => 'public',
    ]));

    expect($system->slug)->toBe('sistema-do-site');
});

it('congela o credito de uma paleta publica de outra conta', function () {
    $author = User::factory()->create(['name' => 'Autora']);
    $palette = ColorPalette::factory()->public()->for($author)->create([
        'name' => 'Oceano',
        'slug' => 'oceano',
    ]);

    $user = User::factory()->create();
    $system = app(CreateLayoutSystem::class)($user, layoutSystemActionData([
        'source_palette_id' => $palette->id,
    ]));

    expect($system->source_palette_id)->toBe($palette->id)
        ->and($system->source)->toMatchArray([
            'palette_name' => 'Oceano',
            'palette_slug' => 'oceano',
            'palette_public' => true,
            'author_name' => 'Autora',
        ]);
});

it('congela credito privado sem expor o link publico', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->for($user)->create([
        'name' => 'Rascunho',
        'slug' => 'rascunho',
    ]);

    $system = app(CreateLayoutSystem::class)($user, layoutSystemActionData([
        'source_palette_id' => $palette->id,
    ]));

    expect($system->source['palette_public'])->toBeFalse()
        ->and($system->source['palette_slug'])->toBeNull();
});

it('recusa origem inacessivel sem gravar credito', function () {
    $palette = ColorPalette::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(CreateLayoutSystem::class)($user, layoutSystemActionData([
        'source_palette_id' => $palette->id,
    ])))->toThrow(ValidationException::class);

    expect(LayoutSystem::query()->count())->toBe(0);
});

it('aplica a cota conjunta contando paletas e arquivados', function () {
    config(['colors.palettes.free_limit' => 2]);

    $user = User::factory()->create();
    ColorPalette::factory()->for($user)->create();
    LayoutSystem::factory()->for($user)->archived()->create();

    expect(fn () => app(CreateLayoutSystem::class)($user, layoutSystemActionData()))
        ->toThrow(ValidationException::class);
});

it('mantem o slug ao renomear e cria na primeira publicacao', function () {
    $user = User::factory()->create();
    $create = app(CreateLayoutSystem::class);
    $update = app(UpdateLayoutSystem::class);

    $system = $create($user, layoutSystemActionData(['visibility' => 'public']));

    $update($system, layoutSystemActionData([
        'name' => 'Outro Nome',
        'visibility' => 'public',
    ]));

    expect($system->refresh()->slug)->toBe('sistema-do-site');

    $private = $create($user, layoutSystemActionData(['name' => 'Sem Slug']));

    $update($private, layoutSystemActionData([
        'name' => 'Sem Slug',
        'visibility' => 'public',
    ]));

    expect($private->refresh()->slug)->toBe('sem-slug');
});

it('revoga a url ao tornar privado mantendo o slug reservado', function () {
    $user = User::factory()->create();
    $create = app(CreateLayoutSystem::class);
    $update = app(UpdateLayoutSystem::class);

    $system = $create($user, layoutSystemActionData(['visibility' => 'public']));

    $update($system, layoutSystemActionData(['visibility' => 'private']));

    expect($system->refresh()->slug)->toBe('sistema-do-site')
        ->and(LayoutSystem::query()->publiclyVisible()->count())->toBe(0);

    $update($system, layoutSystemActionData(['visibility' => 'public']));

    expect(LayoutSystem::query()->publiclyVisible()->count())->toBe(1);
});

it('preserva o credito ao salvar a mesma origem de novo', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->public()->for($user)->create(['name' => 'Marca']);

    $system = app(CreateLayoutSystem::class)($user, layoutSystemActionData([
        'source_palette_id' => $palette->id,
    ]));

    $captured = $system->source['captured_at'];

    $this->travel(5)->minutes();

    app(UpdateLayoutSystem::class)($system, layoutSystemActionData([
        'name' => 'Novo Nome',
        'source_palette_id' => $palette->id,
    ]));

    expect($system->refresh()->source['captured_at'])->toBe($captured);
});

it('arquiva reservando o slug e restaura devolvendo a url', function () {
    $user = User::factory()->create();
    $system = app(CreateLayoutSystem::class)($user, layoutSystemActionData(['visibility' => 'public']));

    app(ArchiveLayoutSystem::class)($system);

    expect($system->refresh()->slug)->toBeNull()
        ->and($system->archived_slug)->toBe('sistema-do-site')
        ->and($system->isArchived())->toBeTrue();

    app(RestoreLayoutSystem::class)($system);

    expect($system->refresh()->slug)->toBe('sistema-do-site')
        ->and($system->archived_slug)->toBeNull()
        ->and($system->isArchived())->toBeFalse();
});

it('restaurado privado mantem o slug reservado ate a publicacao', function () {
    $user = User::factory()->create();
    $system = app(CreateLayoutSystem::class)($user, layoutSystemActionData(['visibility' => 'public']));

    app(UpdateLayoutSystem::class)($system, layoutSystemActionData(['visibility' => 'private']));
    app(ArchiveLayoutSystem::class)($system);
    app(RestoreLayoutSystem::class)($system);

    expect($system->refresh()->slug)->toBeNull()
        ->and($system->archived_slug)->toBe('sistema-do-site')
        ->and($system->isArchived())->toBeFalse();

    app(UpdateLayoutSystem::class)($system, layoutSystemActionData(['visibility' => 'public']));

    expect($system->refresh()->slug)->toBe('sistema-do-site')
        ->and($system->archived_slug)->toBeNull();
});

it('aposenta o slug na exclusao e nunca o reemite', function () {
    config(['colors.palettes.free_limit' => 1]);

    $user = User::factory()->create();
    $create = app(CreateLayoutSystem::class);

    $system = $create($user, layoutSystemActionData([
        'name' => 'Sistema do Site',
        'visibility' => 'public',
    ]));

    app(DeleteLayoutSystem::class)($system);

    expect(LayoutSystem::query()->count())->toBe(0)
        ->and(DB::table('layout_system_retired_slugs')->where('slug', 'sistema-do-site')->exists())
        ->toBeTrue();

    // A exclusao libera a vaga; o novo sistema com o mesmo nome nasce com
    // slug diferente.
    $recreated = $create($user, layoutSystemActionData([
        'name' => 'Sistema do Site',
        'visibility' => 'public',
    ]));

    expect($recreated->slug)->toBe('sistema-do-site-2');
});

it('duplica entre contas com linhagem e sem a fk da origem', function () {
    $author = User::factory()->create();
    $palette = ColorPalette::factory()->public()->for($author)->create();
    $source = LayoutSystem::factory()->public()->for($author)->create([
        'source_palette_id' => $palette->id,
        'source' => ['palette_name' => 'P', 'palette_public' => true],
        'tokens_count' => 66,
    ]);
    $source->tags()->attach($author->colorTags()->create([
        'name' => 'Web',
        'key' => 'web',
        'position' => 0,
    ]));

    $user = User::factory()->create();
    $copy = app(DuplicateLayoutSystem::class)($user, $source);

    expect($copy->user_id)->toBe($user->id)
        ->and($copy->visibility)->toBe(PaletteVisibility::Private)
        ->and($copy->slug)->toBeNull()
        ->and($copy->duplicated_from_id)->toBe($source->id)
        ->and($copy->source_palette_id)->toBeNull()
        ->and($copy->source)->toBe($source->source)
        ->and($copy->state)->toBe($source->state)
        ->and($copy->tokens_count)->toBe(66)
        ->and($copy->tags->pluck('name')->all())->toBe(['Web'])
        ->and($source->refresh()->duplications_count)->toBe(1);
});

it('mantem a fk da origem ao duplicar na mesma conta', function () {
    $user = User::factory()->create();
    $palette = ColorPalette::factory()->public()->for($user)->create();
    $source = LayoutSystem::factory()->public()->for($user)->create([
        'source_palette_id' => $palette->id,
    ]);

    $copy = app(DuplicateLayoutSystem::class)($user, $source);

    expect($copy->source_palette_id)->toBe($palette->id)
        ->and($source->refresh()->duplications_count)->toBe(0);
});

it('bloqueia duplicacao no teto da conta', function () {
    config(['colors.palettes.free_limit' => 1]);

    $author = User::factory()->create();
    $source = LayoutSystem::factory()->public()->for($author)->create();

    $user = User::factory()->create();
    ColorPalette::factory()->for($user)->create();

    expect(fn () => app(DuplicateLayoutSystem::class)($user, $source))
        ->toThrow(ValidationException::class);
});
