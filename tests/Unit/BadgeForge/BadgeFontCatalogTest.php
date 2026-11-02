<?php

use App\Support\BadgeForge\BadgeFont;
use App\Support\BadgeForge\BadgeFontCatalog;
use Tests\TestCase;

uses(TestCase::class);

/*
| Fontes empacotadas (ADR-077): o catalogo le `config/badgeforge.php` e o
| espelho TypeScript recebe exatamente `{slug, name, stack}` via toolProps.
*/

it('resolve a fonte sem diferenciar maiusculas', function () {
    $catalog = app(BadgeFontCatalog::class);

    expect($catalog->find('fira-code')?->slug)->toBe('fira-code')
        ->and($catalog->find(' Fira-Code ')?->name)->toBe('Fira Code')
        ->and($catalog->find('fira-code')?->stack)->toBe('Fira Code,DejaVu Sans,monospace');
});

it('devolve nulo para fonte ausente ou desconhecida', function () {
    $catalog = app(BadgeFontCatalog::class);

    expect($catalog->find(null))->toBeNull()
        ->and($catalog->find(''))->toBeNull()
        ->and($catalog->find('nope'))->toBeNull();
});

it('lista as fontes na ordem da config', function () {
    $catalog = app(BadgeFontCatalog::class);

    expect(array_map(
        static fn (BadgeFont $font): string => $font->slug,
        $catalog->all(),
    ))->toBe(array_keys((array) config('badgeforge.fonts')));
});

it('expoe somente slug, nome e pilha no metadata', function () {
    foreach (app(BadgeFontCatalog::class)->metadata() as $font) {
        expect(array_keys($font))->toBe(['slug', 'name', 'stack']);
    }
});
