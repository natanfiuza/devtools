<?php

use App\Models\LayoutSystem;
use App\Support\Locales;
use App\Support\Seo;
use App\Support\Sitemap;
use Database\Factories\LayoutSystemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/*
| SEO da pagina publica do sistema de layout (F4, ADR-174).
|
| Metas do servidor com nome/descricao, OG estatica do Layout Harmonizer e
| sitemap so com sistema publico ativo, nos cinco idiomas.
*/

it('usa nome e descricao do dono nas metas', function () {
    LayoutSystem::factory()->public()->create([
        'name' => 'Sistema Oceano',
        'description' => 'Tokens azuis para interfaces longas.',
        'slug' => 'sistema-oceano',
    ]);

    $html = $this->get('/pt-br/colors/s/sistema-oceano')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>'.e(Seo::documentTitle('Sistema Oceano')).'</title>')
        ->toContain('<meta name="description" content="Tokens azuis para interfaces longas.">')
        ->toContain('<meta property="og:title" content="Sistema Oceano">')
        ->not->toContain('content="noindex');
});

it('monta a descricao padrao com estrategia e tokens', function () {
    LayoutSystem::factory()->public()->create([
        'name' => 'Sem Descricao',
        'description' => null,
        'slug' => 'sem-descricao',
    ]);

    foreach (Locales::all() as $code => $locale) {
        $expected = (string) trans('colors.meta.system_description', [
            'strategy' => (string) trans('tools.layout-harmonizer.strategy.analogous.name', [], $code),
            'tokens' => 66,
        ], $code);

        expect($this->get('/'.$locale['slug'].'/colors/s/sem-descricao')->assertOk()->getContent())
            ->toContain('<meta name="description" content="'.e($expected).'">');
    }
});

it('usa a og estatica do layout harmonizer', function () {
    LayoutSystem::factory()->public()->create(['slug' => 'com-og']);

    $html = $this->get('/en/colors/s/com-og')->assertOk()->getContent();

    expect($html)
        ->toContain('og/layout-harmonizer.png')
        ->toContain('<meta property="og:image:width" content="'.config('seo.image.width').'">');
});

it('mantem canonical e hreflang sem a query string', function () {
    LayoutSystem::factory()->public()->create(['slug' => 'sem-query']);

    $html = $this->get('/en/colors/s/sem-query?duplicate=1')->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="'.url('/en/colors/s/sem-query').'">')
        ->toContain('<link rel="alternate" hreflang="pt-BR" href="'.url('/pt-br/colors/s/sem-query').'">')
        ->toContain('<meta property="og:url" content="'.url('/en/colors/s/sem-query').'">');
});

it('lista sistemas publicos ativos no sitemap e ignora arquivado e privado', function () {
    LayoutSystem::factory()->public()->create(['slug' => 'publico']);
    LayoutSystem::factory()->create(['slug' => 'privado']);
    LayoutSystem::factory()->public()->archived()->create(['slug' => 'arquivado']);

    $urls = collect(Sitemap::urls())->pluck('loc');

    expect($urls)->toContain(url('/en/colors/s/publico'))
        ->and($urls)->toContain(url('/pt-br/colors/s/publico'))
        ->not->toContain(url('/en/colors/s/privado'))
        ->not->toContain(url('/en/colors/s/arquivado'));
});

it('nao calcula meta de sistema quando o slug nao resolve', function () {
    LayoutSystem::factory()->create(['slug' => 'privado']);

    $this->get('/en/colors/s/privado')->assertNotFound();
});

it('limita o sitemap pela configuracao', function () {
    config(['colors.layout_systems.sitemap_limit' => 0]);

    LayoutSystem::factory()->public()->create(['slug' => 'publico']);

    $urls = collect(Sitemap::urls())->pluck('loc');

    expect($urls)->not->toContain(url('/en/colors/s/publico'));
});

it('mantem as versoes do estado no presenter publico', function () {
    $system = LayoutSystem::factory()->public()->create([
        'state' => LayoutSystemFactory::stateArray(),
        'algorithm_version' => 2,
    ]);

    $this->get(route('colors.systems.show', ['locale' => 'en', 'slug' => $system->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('system.algorithm_version', 2)
            ->where('system.schema_version', 1)
            ->where('system.state.snapshot.algorithmVersion', 1));
});
