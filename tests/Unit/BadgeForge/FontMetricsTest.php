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

it('mede pelas metricas de cada fonte empacotada', function () {
    $metrics = app(FontMetrics::class);

    // Space Grotesk e mais estreita que a tabela aproximada da sans.
    expect($metrics->width('build', 'space-grotesk', 11, 600, 0))
        ->toBeLessThan($metrics->width('build', 'sans', 11, 600, 0))
        ->and($metrics->width('build', 'libre-franklin', 11, 600, 0))
        ->not->toBe($metrics->width('build', 'sans', 11, 600, 0));
});

it('aplica o percentual de negrito da propria familia', function () {
    $metrics = app(FontMetrics::class);

    expect($metrics->width('BUILD', 'libre-franklin', 10, 700, 0))
        ->toBeGreaterThan($metrics->width('BUILD', 'libre-franklin', 10, 400, 0))
        // Fira Code e monoespacada: Bold e Regular tem os mesmos avancos.
        ->and($metrics->width('build', 'fira-code', 11, 700, 0))
        ->toBe($metrics->width('build', 'fira-code', 11, 400, 0));
});
