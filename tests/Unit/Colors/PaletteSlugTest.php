<?php

use App\Models\ColorPalette;
use App\Support\Colors\PaletteSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Slug publico da paleta (F4, ADR-124).
|
| Nasce do nome, e unico global, nao muda ao renomear e trata reservados e
| colisoes com sufixo.
*/

it('gera o slug a partir do nome', function () {
    $slug = new PaletteSlug;

    expect($slug->base('Minha Paleta!'))->toBe('minha-paleta')
        ->and($slug->base('!!!'))->toBe('palette')
        ->and($slug->base('Ação & Cores'))->toBe('acao-cores');
});

it('reserva segmentos de rota', function () {
    $slug = new PaletteSlug;

    expect($slug->base('Create'))->toBe('create-palette')
        ->and($slug->base('og'))->toBe('og-palette');
});

it('limita o tamanho configurado', function () {
    config(['colors.slug.max_length' => 12]);

    $slug = new PaletteSlug;

    expect(strlen($slug->base('uma paleta com nome bem comprido')))->toBeLessThanOrEqual(12);
});

it('resolve colisoes com sufixo numerico', function () {
    ColorPalette::factory()->create(['slug' => 'minha-paleta']);
    ColorPalette::factory()->create(['slug' => 'minha-paleta-2']);

    $slug = new PaletteSlug;

    expect($slug->unique('minha-paleta'))->toBe('minha-paleta-3');
});

it('reivindica o slug anterior quando estiver livre', function () {
    $slug = new PaletteSlug;

    expect($slug->claimOrGenerate('minha-paleta', 'Outro Nome'))->toBe('minha-paleta');
});

it('gera outro slug quando o anterior foi tomado', function () {
    ColorPalette::factory()->create(['slug' => 'minha-paleta']);

    $slug = new PaletteSlug;

    expect($slug->claimOrGenerate('minha-paleta', 'Nome Novo'))->toBe('nome-novo');
});
