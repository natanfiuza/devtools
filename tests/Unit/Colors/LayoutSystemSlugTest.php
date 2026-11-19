<?php

use App\Models\LayoutSystem;
use App\Support\Colors\LayoutSystemSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Slug publico do sistema de layout (F4, ADR-171).
|
| Nasce do nome, e unico global, arquivado fica reservado e excluido vira
| aposentado para sempre.
*/

it('gera o slug a partir do nome', function () {
    $slug = new LayoutSystemSlug;

    expect($slug->base('Sistema do Site'))->toBe('sistema-do-site')
        ->and($slug->base('!!!'))->toBe('layout-system')
        ->and($slug->base('Ação & Cores'))->toBe('acao-cores');
});

it('sufixa segmentos reservados de rota', function () {
    $slug = new LayoutSystemSlug;

    expect($slug->base('Create'))->toBe('create-system')
        ->and($slug->base('og'))->toBe('og-system');
});

it('limita o tamanho configurado', function () {
    config(['colors.slug.max_length' => 12]);

    $slug = new LayoutSystemSlug;

    expect(strlen($slug->base('um sistema com nome bem comprido')))->toBeLessThanOrEqual(12);
});

it('resolve colisoes com sufixo numerico', function () {
    LayoutSystem::factory()->create(['slug' => 'sistema-do-site']);
    LayoutSystem::factory()->create(['slug' => 'sistema-do-site-2']);

    expect((new LayoutSystemSlug)->unique('sistema-do-site'))->toBe('sistema-do-site-3');
});

it('considera arquivado e aposentado como tomados', function () {
    LayoutSystem::factory()->create(['archived_slug' => 'arquivado']);
    DB::table('layout_system_retired_slugs')->insert([
        'slug' => 'aposentado',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $slug = new LayoutSystemSlug;

    expect($slug->taken('arquivado'))->toBeTrue()
        ->and($slug->taken('aposentado'))->toBeTrue()
        ->and($slug->taken('livre'))->toBeFalse();
});

it('reivindica o slug anterior quando estiver livre', function () {
    $slug = new LayoutSystemSlug;

    expect($slug->claimOrGenerate('sistema-do-site', 'Outro Nome'))->toBe('sistema-do-site');
});

it('gera outro slug quando o anterior foi tomado', function () {
    LayoutSystem::factory()->create(['slug' => 'sistema-do-site']);

    expect((new LayoutSystemSlug)->claimOrGenerate('sistema-do-site', 'Nome Novo'))->toBe('nome-novo');
});

it('aposenta um slug para sempre de forma idempotente', function () {
    $slug = new LayoutSystemSlug;

    $slug->retire('meu-sistema');
    $slug->retire('meu-sistema');
    $slug->retire(null);

    expect(DB::table('layout_system_retired_slugs')->where('slug', 'meu-sistema')->count())->toBe(1)
        ->and($slug->taken('meu-sistema'))->toBeTrue();
});
