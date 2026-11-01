<?php

use App\Support\BadgeForge\FontMetrics;
use Tests\TestCase;

uses(TestCase::class);

/*
| A tabela de avancos e a mesma lida pelo espelho TypeScript. Os valores
| abaixo fixam a formula: unidades somadas, negrito a 106%, arredondamento
| para pixel e tracking por caractere.
*/

it('mede texto vazio como zero', function () {
    expect(app(FontMetrics::class)->width('', 'sans', 11, 600, 0))->toBe(0);
});

it('soma os avancos e arredonda para pixel', function () {
    $metrics = app(FontMetrics::class);

    expect($metrics->width('build', 'sans', 11, 600, 0))->toBe(29)
        ->and($metrics->width('BUILD', 'sans', 11, 600, 0))->toBe(37);
});

it('aplica negrito e tracking', function () {
    $metrics = app(FontMetrics::class);

    expect($metrics->width('BUILD', 'sans', 10, 700, 1))->toBe(39)
        ->and($metrics->width('BUILD', 'sans', 10, 700, 0))->toBe(34);
});

it('usa a familia helvetica no estilo social', function () {
    $metrics = app(FontMetrics::class);

    expect($metrics->width('build', 'helvetica', 11, 400, 0))
        ->not->toBe($metrics->width('build', 'sans', 11, 400, 0));
});

it('trata acentos, marcas combinantes e glifos desconhecidos', function () {
    $metrics = app(FontMetrics::class);

    expect($metrics->width('é', 'sans', 11, 400, 0))->toBe(7)
        ->and($metrics->width("e\u{0301}", 'sans', 11, 400, 0))->toBe(7)
        // CJK nao esta na tabela: cai no fallback largo.
        ->and($metrics->width('状', 'sans', 11, 400, 0))->toBe(7);
});
