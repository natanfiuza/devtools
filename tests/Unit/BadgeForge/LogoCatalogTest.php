<?php

use App\Support\BadgeForge\LogoCatalog;
use Tests\TestCase;

uses(TestCase::class);

it('carrega os 25 logos embutidos com viewBox e um unico path', function () {
    $catalog = app(LogoCatalog::class);
    $logos = $catalog->metadata();

    expect($logos)->toHaveCount(25);

    foreach ($logos as $logo) {
        $resolved = $catalog->find($logo['slug']);

        expect($resolved)->not->toBeNull()
            ->and($resolved->viewBox)->toBe('0 0 24 24')
            ->and($resolved->pathData)->toStartWith('M');
    }
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
