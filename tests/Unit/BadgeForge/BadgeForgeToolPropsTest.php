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
