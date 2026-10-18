<?php

use App\Support\Format;
use Carbon\Carbon;

test('formata data e data/hora com fuso UTC e offset +00:00 sem disparar IntlException', function () {
    app()->setLocale('pt_BR');

    $dateUtc = Carbon::parse('2026-10-18T10:30:00Z');
    expect(Format::date($dateUtc))->not->toBeNull();
    expect(Format::dateTime($dateUtc))->not->toBeNull();

    $dateOffset = Carbon::parse('2026-10-18T10:30:00+00:00');
    expect($dateOffset->getTimezone()->getName())->toBe('+00:00');
    expect(Format::date($dateOffset))->not->toBeNull();
    expect(Format::dateTime($dateOffset))->not->toBeNull();

    $dateNegativeOffset = Carbon::parse('2026-10-18T10:30:00-03:00');
    expect(Format::date($dateNegativeOffset))->not->toBeNull();
    expect(Format::dateTime($dateNegativeOffset))->not->toBeNull();

    $dateNamed = Carbon::parse('2026-10-18 10:30:00', 'America/Recife');
    expect(Format::date($dateNamed))->not->toBeNull();
    expect(Format::dateTime($dateNamed))->not->toBeNull();

    expect(Format::date(null))->toBeNull();
    expect(Format::dateTime(null))->toBeNull();
});

test('formata numero, dinheiro e bytes de acordo com o idioma', function () {
    app()->setLocale('pt_BR');

    expect(Format::number(12500))->toBe('12.500');
    expect(Format::money(2990, 'BRL'))->toContain('29,90');
    expect(Format::bytes(1024))->toBe('1 KB');
    expect(Format::bytes(1048576))->toBe('1 MB');
});
