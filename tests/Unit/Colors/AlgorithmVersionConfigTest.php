<?php

use App\Support\Colors\PaletteRules;
use Tests\TestCase;

uses(TestCase::class);

/*
| Versoes e limites da persistencia (F4, ADR-123).
|
| A versao gravada na paleta e a mesma que o navegador declarou; a extracao
| aceita apenas 5, 7, 9 ou 11 cores. Nenhum dos dois pode divergir do que a
| F3 entregou.
*/

it('mantem as versoes de algoritmo da F3', function () {
    expect(config('colors.algorithm_versions.scale'))->toBe(1)
        ->and(config('colors.algorithm_versions.extraction'))->toBe(1);
});

it('aceita apenas os tamanhos de extracao previstos', function () {
    $rules = new PaletteRules;

    expect(PaletteRules::EXTRACTED_COUNTS)->toBe([5, 7, 9, 11])
        ->and($rules->validExtractedCount(array_fill(0, 5, ['hex' => '#000000'])))->toBeTrue()
        ->and($rules->validExtractedCount(array_fill(0, 6, ['hex' => '#000000'])))->toBeFalse()
        ->and(PaletteRules::TONES)->toHaveCount(11);
});
