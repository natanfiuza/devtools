<?php

use App\Support\Colors\ColorsToolProps;
use Tests\TestCase;

uses(TestCase::class);

/*
| Ponte entre servidor e cliente (ADR-043, ADR-122).
|
| A importacao de imagem do gerador roda so no navegador; o que o servidor
| decide sao os tetos de `config/tools.php`. Se um deles passar a morar no
| cliente, a pagina deixa de receber o numero e cai aqui.
*/

it('entrega os limites de imagem da configuracao', function () {
    expect(ColorsToolProps::for())->toBe([
        'image' => [
            'maxBytes' => 10 * 1024 * 1024,
            'maxSide' => 4096,
            'maxPixels' => 16_000_000,
            'analysisSide' => 512,
            'fetchTimeoutMs' => 8000,
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
    ]);

    expect(ColorsToolProps::for()['image'])->toBe([
        'maxBytes' => 1234,
        'maxSide' => 77,
        'maxPixels' => 999,
        'analysisSide' => 64,
        'fetchTimeoutMs' => 321,
    ]);
});
