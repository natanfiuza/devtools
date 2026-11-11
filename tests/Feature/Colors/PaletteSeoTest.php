<?php

use App\Models\ColorPalette;
use App\Models\ColorTag;
use App\Support\Colors\PaletteOgStorage;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

/*
| SEO da pagina publica (F4, ADR-124/ADR-125).
|
| As metas saem do servidor: nome, descricao (ou texto montado com contagem e
| tags), canonical/hreflang sem query e a OG do dono quando existe. O sitemap
| lista so paleta publica ativa, nos cinco idiomas.
*/

beforeEach(function () {
    config(['colors.og.path' => 'framework/testing/colors-og']);
});

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing/colors-og'));
});

it('usa nome e descricao da paleta nas metas do servidor', function () {
    $palette = ColorPalette::factory()->public()->create([
        'name' => 'Oceano Profundo',
        'description' => 'Azuis calmos para interfaces longas.',
        'slug' => 'oceano-profundo',
    ]);

    $html = $this->get('/pt-br/colors/p/oceano-profundo')->assertOk()->getContent();

    expect($html)
        ->toContain('<title>'.e(Seo::documentTitle('Oceano Profundo')).'</title>')
        ->toContain('<meta name="description" content="Azuis calmos para interfaces longas.">')
        ->toContain('<meta property="og:title" content="Oceano Profundo">')
        ->toContain('<meta property="og:description" content="Azuis calmos para interfaces longas.">')
        ->not->toContain('content="noindex');

    expect($palette->slug)->toBe('oceano-profundo');
});

it('monta a descricao com contagem e tags quando o dono nao escreveu uma', function () {
    $palette = ColorPalette::factory()->public()->create([
        'name' => 'Sem Descricao',
        'description' => null,
        'slug' => 'sem-descricao',
    ]);

    $palette->tags()->attach([
        ColorTag::factory()->for($palette->user)->create(['name' => 'Web', 'key' => 'web', 'position' => 0])->id,
        ColorTag::factory()->for($palette->user)->create(['name' => 'Brand', 'key' => 'brand', 'position' => 1])->id,
    ]);

    foreach (Locales::all() as $code => $locale) {
        $expected = (string) trans('colors.meta.description', [
            'count' => 11,
            'tags' => 'Web, Brand',
        ], $code);

        expect($this->get('/'.$locale['slug'].'/colors/p/sem-descricao')->assertOk()->getContent())
            ->toContain('<meta name="description" content="'.e($expected).'">');
    }
});

it('mantem canonical e hreflang sem a query string', function () {
    ColorPalette::factory()->public()->create(['slug' => 'sem-query']);

    $html = $this->get('/en/colors/p/sem-query?duplicate=1')->assertOk()->getContent();

    // A query continua na URL atual (e no data-page do Inertia); o que nao
    // pode carrega-la e o canonical, o hreflang e o og:url.
    expect($html)
        ->toContain('<link rel="canonical" href="'.url('/en/colors/p/sem-query').'">')
        ->toContain('<link rel="alternate" hreflang="pt-BR" href="'.url('/pt-br/colors/p/sem-query').'">')
        ->toContain('<meta property="og:url" content="'.url('/en/colors/p/sem-query').'">');
});

it('usa a OG gerada pelo dono, com versao e dimensoes da configuracao', function () {
    $palette = ColorPalette::factory()->public()->create([
        'name' => 'Com Imagem',
        'slug' => 'com-imagem',
        'og_version' => 2,
    ]);

    (new PaletteOgStorage)->put($palette->id, 'png');

    $html = $this->get('/pt-br/colors/p/com-imagem')->assertOk()->getContent();

    $image = route('colors.palettes.og', [
        'locale' => 'pt-br',
        'slug' => 'com-imagem',
        'v' => 2,
    ]);

    expect($html)
        ->toContain('<meta property="og:image" content="'.e($image).'">')
        ->toContain('<meta property="og:image:width" content="'.config('colors.og.width').'">')
        ->toContain('<meta property="og:image:height" content="'.config('colors.og.height').'">')
        ->toContain('<meta property="og:image:alt" content="'.e((string) trans('colors.meta.image_alt', ['name' => 'Com Imagem'], 'pt_BR')).'">');
});

it('cai na imagem padrao quando a OG nao existe', function () {
    ColorPalette::factory()->public()->create(['slug' => 'sem-imagem']);

    $html = $this->get('/pt-br/colors/p/sem-imagem')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:image" content="'.url((string) config('seo.image.file')).'?v='.(int) config('seo.image.version').'">');
});

it('lista paletas publicas ativas no sitemap, em todos os idiomas', function () {
    ColorPalette::factory()->public()->create(['slug' => 'publica']);
    ColorPalette::factory()->create(['slug' => 'privada']);
    ColorPalette::factory()->public()->archived()->create(['slug' => 'arquivada']);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    foreach (Locales::all() as $locale) {
        expect($xml)->toContain(
            route('colors.palettes.show', ['locale' => $locale['slug'], 'slug' => 'publica']),
        );
    }

    expect($xml)
        ->not->toContain('/colors/p/privada')
        ->not->toContain('/colors/p/arquivada');
});

it('respeita o limite de paletas do sitemap', function () {
    config(['colors.palettes.sitemap_limit' => 0]);

    ColorPalette::factory()->public()->create(['slug' => 'publica']);

    expect($this->get('/sitemap.xml')->assertOk()->getContent())
        ->not->toContain('/colors/p/publica');
});
