<?php

use App\Models\ColorPalette;
use App\Models\ColorTag;
use App\Support\Colors\PalettePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Shapes de paleta (F4, ADR-123/ADR-124).
|
| A lista leva previa e metadados; o editor leva o retrato completo; a
| pagina publica nao expoe papeis nem origem.
*/

function presentedPalette(): ColorPalette
{
    $palette = ColorPalette::factory()->public()->create([
        'duplications_count' => 4,
    ]);

    $tag = ColorTag::factory()->for($palette->user)->create(['name' => 'marca', 'key' => 'marca']);
    $palette->tags()->attach($tag);

    return $palette->fresh(['tags']);
}

it('monta a linha da lista com previa e tags', function () {
    $row = (new PalettePresenter)->row(presentedPalette(), 3);

    expect($row['preview'])->toHaveCount(3)
        ->and($row['preview'][0])->toBe('#eef2ff')
        ->and($row['colors_count'])->toBe(11)
        ->and($row['duplications_count'])->toBe(4)
        ->and($row['tags'][0]['name'])->toBe('marca');
});

it('monta o editor com papeis e origem', function () {
    $palette = presentedPalette();
    $editor = (new PalettePresenter)->editor($palette);

    expect($editor)->toHaveKeys(['colors', 'roles', 'source', 'algorithm_version'])
        ->and($editor['roles'])->toHaveKeys(['light', 'dark'])
        ->and($editor['colors']['base'])->toBe('#6366f1');
});

it('nao expoe papeis nem origem na pagina publica', function () {
    $public = (new PalettePresenter)->public(presentedPalette());

    // `algorithm_version` fica: ele so rotula o arquivo exportado pelo visitante
    // (comentario do CSS/JSON), sem dizer nada da conta.
    expect($public)->not->toHaveKeys(['roles', 'source', 'visibility'])
        ->and($public['algorithm_version'])->toBe(1)
        ->and($public['colors'])->toHaveKey('items');
});
