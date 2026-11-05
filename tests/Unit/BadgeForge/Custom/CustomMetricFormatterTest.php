<?php

use App\Enums\CustomMetricFormatter as Formatter;
use App\Support\BadgeForge\Custom\CustomMetricFormatter;
use App\Support\BadgeForge\Providers\ProviderMetricFormatter;
use Tests\TestCase;

uses(TestCase::class);

function customFormatter(): CustomMetricFormatter
{
    return new CustomMetricFormatter(new ProviderMetricFormatter);
}

it('formata number, percent e text com as regras do catalogo', function () {
    expect(customFormatter()->format(1234, Formatter::Number))->toBe('1.2k')
        ->and(customFormatter()->format(2_000_000, Formatter::Number))->toBe('2M')
        ->and(customFormatter()->format(66.89, Formatter::Percent))->toBe('67%')
        ->and(customFormatter()->format(101, Formatter::Percent))->toBeNull()
        ->and(customFormatter()->format("  build\n ok  ", Formatter::Text))->toBe('build ok');
});

it('devolve null para tipo incompativel com o formatador', function () {
    expect(customFormatter()->format(['x' => 1], Formatter::Text))->toBeNull()
        ->and(customFormatter()->format('abc', Formatter::Number))->toBeNull()
        ->and(customFormatter()->format(null, Formatter::Text))->toBeNull();
});

it('trunca pelo limite de texto do badge', function () {
    config()->set('badgeforge.limits.max_text_length', 5);

    expect(customFormatter()->format(str_repeat('a', 10), Formatter::Text))->toBe('aaaa…');
});
