<?php

use App\Support\BadgeForge\Providers\ProviderMetricFormatter;
use Tests\TestCase;

uses(TestCase::class);

function metricFormatter(): ProviderMetricFormatter
{
    return new ProviderMetricFormatter;
}

it('formata numeros com k, M e B', function () {
    $formatter = metricFormatter();

    expect($formatter->number(0))->toBe('0')
        ->and($formatter->number(999))->toBe('999')
        ->and($formatter->number(1000))->toBe('1k')
        ->and($formatter->number(1234))->toBe('1.2k')
        ->and($formatter->number(12_345))->toBe('12.3k')
        ->and($formatter->number(123_456))->toBe('123k')
        ->and($formatter->number(1_000_000))->toBe('1M')
        ->and($formatter->number(1_234_567))->toBe('1.2M')
        ->and($formatter->number(1_000_000_000))->toBe('1B');
});

it('recusa numero invalido ou negativo', function () {
    $formatter = metricFormatter();

    expect($formatter->number(null))->toBeNull()
        ->and($formatter->number('nope'))->toBeNull()
        ->and($formatter->number(-1))->toBeNull()
        ->and($formatter->number(INF))->toBeNull();
});

it('formata licenca pelo spdx_id e usa unknown quando ausente', function () {
    $formatter = metricFormatter();

    expect($formatter->license(['spdx_id' => 'MIT']))->toBe('MIT')
        ->and($formatter->license(['spdx_id' => 'NOASSERTION']))->toBe('unknown')
        ->and($formatter->license(null))->toBe('unknown')
        ->and($formatter->license('Apache-2.0'))->toBe('Apache-2.0');
});

it('limpa e trunca versao', function () {
    $formatter = metricFormatter();
    $version = "1.0.0\x01  beta";

    expect($formatter->format($version, 'version'))->toBe('1.0.0 beta');

    $long = $formatter->format(str_repeat('v', 150), 'version');

    expect(mb_strlen((string) $long))->toBe(100)
        ->and($long)->toEndWith('…');
});

it('despacha por extractor_key', function () {
    $formatter = metricFormatter();

    expect($formatter->format(42, 'stars'))->toBe('42')
        ->and($formatter->format(42, 'downloads'))->toBe('42')
        ->and($formatter->format(42, 'pulls'))->toBe('42')
        ->and($formatter->format(['spdx_id' => 'MIT'], 'license'))->toBe('MIT')
        ->and($formatter->format('2.1.0', 'version'))->toBe('2.1.0');
});
