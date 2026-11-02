<?php

use App\Support\BadgeForge\BadgeForgeToolProps;
use App\Support\BadgeForge\LogoCatalog;
use Tests\TestCase;

uses(TestCase::class);

/*
| Ponte entre servidor e cliente (ADR-075).
|
| O mesmo `tests/Fixtures/badgeforge/tool-props.json` e consumido pelo Vitest:
| se um limite, um default ou o registro de logos divergir do que a pagina
| recebe, um dos dois lados quebra.
*/

it('produz exatamente o payload compartilhado', function () {
    $expected = json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/badgeforge/tool-props.json')),
        true,
    );

    expect(BadgeForgeToolProps::for())->toBe($expected);
});

it('ordena os logos pelo nome sem diferenciar maiusculas', function () {
    /** @var list<array{slug: string, name: string}> $logos */
    $logos = BadgeForgeToolProps::for()['logos'];
    $names = array_column($logos, 'name');
    $sorted = $names;

    usort($sorted, static fn (string $a, string $b): int => strcasecmp($a, $b));

    expect($names)->toBe($sorted)
        ->and($names[0])->toBe('Composer')
        ->and($names[array_key_last($names)])->toBe('Vue');
});

it('lista as fontes na ordem da config com as TTF empacotadas', function () {
    /** @var list<array{slug: string, name: string, stack: string}> $fonts */
    $fonts = BadgeForgeToolProps::for()['fonts'];
    $directory = (string) config('badgeforge.fonts_path');
    /** @var array<string, array{regular: string, bold: string}> $configured */
    $configured = (array) config('badgeforge.fonts');

    expect(array_column($fonts, 'slug'))->toBe(array_keys($configured));

    foreach ($configured as $data) {
        expect(is_file($directory.'/'.$data['regular']))->toBeTrue()
            ->and(is_file($directory.'/'.$data['bold']))->toBeTrue();
    }
});

it('registra todo logo com arquivo e viewBox', function () {
    $catalog = app(LogoCatalog::class);
    $directory = (string) config('badgeforge.logos_path');
    $metadata = $catalog->metadata();

    expect($metadata)->toHaveCount(count((array) config('badgeforge.logos')));

    foreach ($metadata as $logo) {
        expect(is_file($directory.'/'.$logo['slug'].'.svg'))->toBeTrue()
            ->and($catalog->find($logo['slug']))->not->toBeNull();
    }
});

it('mantem cada SVG de logo com um unico path', function () {
    $directory = (string) config('badgeforge.logos_path');

    foreach (glob($directory.'/*.svg') ?: [] as $file) {
        $svg = (string) file_get_contents($file);

        preg_match_all('/<\s*\/?\s*([a-zA-Z][a-zA-Z0-9:-]*)/', $svg, $tags);

        $unique = array_values(array_unique(array_map('strtolower', $tags[1])));

        expect($unique)->toBe(['svg', 'path'])
            ->and(substr_count($svg, '<path'))->toBe(1);
    }
});
