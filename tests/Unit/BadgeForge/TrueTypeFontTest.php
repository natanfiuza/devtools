<?php

use App\Support\BadgeForge\TrueTypeFont;
use Tests\TestCase;

uses(TestCase::class);

/*
| Leitor de TTF que alimenta `badgeforge:font-metrics`.
|
| Os valores fixados sao os avancos publicados dos tres arquivos empacotados,
| normalizados para 1000 unidades por em (Fira Code declara 2000).
*/

function bundledFont(string $relative): TrueTypeFont
{
    return new TrueTypeFont(
        (string) config('badgeforge.fonts_path').DIRECTORY_SEPARATOR.$relative,
    );
}

it('le o unitsPerEm de cada fonte', function () {
    expect(bundledFont('space-grotesk/SpaceGrotesk-Regular.ttf')->unitsPerEm())->toBe(1000)
        ->and(bundledFont('libre-franklin/LibreFranklin-Regular.ttf')->unitsPerEm())->toBe(1000)
        ->and(bundledFont('fira-code/FiraCode-Regular.ttf')->unitsPerEm())->toBe(2000);
});

it('normaliza o avanco para 1000 unidades por em', function () {
    $spaceGrotesk = bundledFont('space-grotesk/SpaceGrotesk-Regular.ttf');
    $libreFranklin = bundledFont('libre-franklin/LibreFranklin-Regular.ttf');
    $firaCode = bundledFont('fira-code/FiraCode-Regular.ttf');

    expect($spaceGrotesk->advanceWidth(65))->toBe(630)
        ->and($spaceGrotesk->advanceWidth(32))->toBe(257)
        ->and($spaceGrotesk->advanceWidth(109))->toBe(860)
        ->and($libreFranklin->advanceWidth(65))->toBe(727)
        ->and($libreFranklin->advanceWidth(32))->toBe(214)
        // 1200 unidades a 2000/em = 600 a 1000/em.
        ->and($firaCode->advanceWidth(65))->toBe(600)
        ->and($firaCode->advanceWidth(32))->toBe(600);
});

it('devolve nulo para code point sem glifo', function () {
    $font = bundledFont('space-grotesk/SpaceGrotesk-Regular.ttf');

    expect($font->advanceWidth(0))->toBeNull()
        ->and($font->advanceWidth(127))->toBeNull();
});

it('mantem o JSON versionado em dia com os TTF', function () {
    $this->artisan('badgeforge:font-metrics', ['--check' => true])
        ->assertSuccessful();
});
