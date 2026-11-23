<?php

use App\Support\BadgeForge\LogoCatalog;
use Tests\TestCase;

uses(TestCase::class);

it('carrega os 25 logos embutidos com viewBox valido e ao menos um path', function () {
    $catalog = app(LogoCatalog::class);
    $logos = $catalog->metadata();

    expect($logos)->toHaveCount(25);

    foreach ($logos as $logo) {
        $resolved = $catalog->find($logo['slug']);

        expect($resolved)->not->toBeNull()
            ->and($resolved->viewBox)->toMatch('/^-?\d+(?:\.\d+)?(?:\s+-?\d+(?:\.\d+)?){3}$/')
            ->and(count($resolved->paths))->toBeGreaterThanOrEqual(1)
            ->and($resolved->paths[0]->d)->toMatch('/^[Mm]/')
            ->and($resolved->custom)->toBeFalse();
    }
});

it('preserva multiplos paths e fill-rule/clip-rule nos logos embutidos', function () {
    $catalog = app(LogoCatalog::class);

    $php = $catalog->find('php');
    $laravel = $catalog->find('laravel');
    $tailwind = $catalog->find('tailwind');

    expect($php)->not->toBeNull()
        ->and($php->paths)->toHaveCount(3)
        ->and($laravel)->not->toBeNull()
        ->and($laravel->paths[0]->fillRule)->toBe('evenodd')
        ->and($tailwind)->not->toBeNull()
        ->and($tailwind->paths[0]->fillRule)->toBe('evenodd')
        ->and($tailwind->paths[0]->clipRule)->toBe('evenodd');
});

it('expoe apenas slug e nome nos metadados', function () {
    $first = app(LogoCatalog::class)->metadata()[0];

    expect(array_keys($first))->toBe(['slug', 'name']);
});

it('ignora slug desconhecido, traversal e tipos invalidos', function () {
    $catalog = app(LogoCatalog::class);

    expect($catalog->find('nope'))->toBeNull()
        ->and($catalog->find('../github'))->toBeNull()
        ->and($catalog->find(null))->toBeNull()
        ->and($catalog->find(['github']))->toBeNull();
});
