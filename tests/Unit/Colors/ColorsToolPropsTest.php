<?php

use App\Models\ColorTag;
use App\Models\User;
use App\Support\Colors\ColorsToolProps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Ponte entre servidor e cliente (ADR-043, ADR-122, ADR-123).
|
| A importacao de imagem do gerador roda so no navegador; o que o servidor
| decide sao os tetos de `config/tools.php` e os limites do modal de salvar.
| Se um deles passar a morar no cliente, a pagina deixa de receber o numero e
| cai aqui.
*/

it('entrega os limites de imagem e de paleta da configuracao', function () {
    expect(ColorsToolProps::for())->toBe([
        'image' => [
            'maxBytes' => 10 * 1024 * 1024,
            'maxSide' => 4096,
            'maxPixels' => 16_000_000,
            'analysisSide' => 512,
            'fetchTimeoutMs' => 8000,
        ],
        'palette' => [
            'nameMaxLength' => 80,
            'descriptionMaxLength' => 500,
            'tagsPerPalette' => 10,
            'tagNameMaxLength' => 30,
            'tags' => [],
        ],
    ]);
});

it('respeita os limites configurados', function () {
    config([
        'tools.limits.colors_image.max_bytes' => 1234,
        'tools.limits.colors_image.max_side' => 77,
        'tools.limits.colors_image.max_pixels' => 999,
        'tools.limits.colors_image.analysis_side' => 64,
        'tools.limits.colors_image.fetch_timeout_ms' => 321,
        'colors.palettes.name_max_length' => 40,
        'colors.palettes.description_max_length' => 250,
        'colors.tags.per_palette' => 4,
        'colors.tags.name_max_length' => 12,
    ]);

    $props = ColorsToolProps::for();

    expect($props['image'])->toBe([
        'maxBytes' => 1234,
        'maxSide' => 77,
        'maxPixels' => 999,
        'analysisSide' => 64,
        'fetchTimeoutMs' => 321,
    ]);
    expect($props['palette'])->toBe([
        'nameMaxLength' => 40,
        'descriptionMaxLength' => 250,
        'tagsPerPalette' => 4,
        'tagNameMaxLength' => 12,
        'tags' => [],
    ]);
});

it('separa os limites do formulario dos de imagem para a lista e o editor', function () {
    expect(ColorsToolProps::formLimits())->toBe([
        'nameMaxLength' => 80,
        'descriptionMaxLength' => 500,
        'tagsPerPalette' => 10,
        'tagNameMaxLength' => 30,
    ]);

    expect(ColorsToolProps::imageLimits())->toBe([
        'maxBytes' => 10 * 1024 * 1024,
        'maxSide' => 4096,
        'maxPixels' => 16_000_000,
        'analysisSide' => 512,
        'fetchTimeoutMs' => 8000,
    ]);

    // O bloco do gerador continua sendo a soma dos dois, sem tags do usuario.
    expect(ColorsToolProps::for()['palette'])
        ->toMatchArray(ColorsToolProps::formLimits())
        ->and(ColorsToolProps::for()['image'])
        ->toBe(ColorsToolProps::imageLimits());
});

it('entrega as tags da conta na ordem global do usuario', function () {
    $user = User::factory()->create();
    $second = ColorTag::factory()->for($user)->create(['name' => 'brand', 'key' => 'brand', 'position' => 2]);
    $first = ColorTag::factory()->for($user)->create(['name' => 'web', 'key' => 'web', 'position' => 1]);
    // Tag de outra conta nunca entra nas sugestoes.
    ColorTag::factory()->create(['name' => 'outra', 'key' => 'outra']);

    expect(ColorsToolProps::for($user)['palette']['tags'])->toBe([
        ['id' => $first->id, 'name' => 'web'],
        ['id' => $second->id, 'name' => 'brand'],
    ]);
});
