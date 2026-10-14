<?php

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Metas de compartilhamento (Open Graph) e a imagem padrao.
|
| Elas saem so nas paginas indexaveis: as telas de acesso e as paginas de token
| nao circulam, entao nao ganham card. Do site inteiro a view escreve tipo,
| nome, idioma, url e imagem; o titulo e a descricao de cada pagina saem do
| <Head> do Inertia - sem SSR, eles nao estao no HTML do servidor e por isso
| nao sao cobrados aqui.
*/

it('escreve as metas de compartilhamento na home', function () {
    $html = $this->get('/pt-br')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:type" content="website">')
        ->toContain('<meta property="og:site_name" content="'.config('app.name').'">')
        ->toContain('<meta property="og:locale" content="pt_BR">')
        ->toContain('<meta property="og:url" content="'.url('/pt-br').'">')
        ->toContain('<meta property="og:image" content="'.url('og-default.png').'">')
        ->toContain('<meta property="og:image:width" content="1200">')
        ->toContain('<meta property="og:image:height" content="630">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">');
});

it('declara o idioma da pagina e os outros como alternates do og', function () {
    $outros = [
        'en' => ['pt_BR', 'pt_PT', 'fr', 'es'],
        'pt_BR' => ['en', 'pt_PT', 'fr', 'es'],
        'pt_PT' => ['en', 'pt_BR', 'fr', 'es'],
        'fr' => ['en', 'pt_BR', 'pt_PT', 'es'],
        'es' => ['en', 'pt_BR', 'pt_PT', 'fr'],
    ];

    foreach (Locales::all() as $code => $locale) {
        $html = $this->get('/'.$locale['slug'])->assertOk()->getContent();

        // O og:locale quer `idioma_TERRITORIO`: pt-BR vira pt_BR.
        $proprio = str_replace('-', '_', $locale['html']);

        expect($html)
            ->toContain('<meta property="og:locale" content="'.$proprio.'">')
            ->toContain('<meta property="og:url" content="'.url('/'.$locale['slug']).'">')
            // O og:url e o canonical da mesma pagina: mesma fonte, um so valor.
            ->toContain('<link rel="canonical" href="'.url('/'.$locale['slug']).'">')
            ->not->toContain('<meta property="og:locale:alternate" content="'.$proprio.'">');

        foreach ($outros[$code] as $outro) {
            expect($html)->toContain('<meta property="og:locale:alternate" content="'.$outro.'">');
        }
    }
});

it('descreve a imagem no idioma da pagina', function () {
    // A imagem e uma so, mas quem le o card esta em algum dos cinco idiomas: a
    // descricao e a marca mais a frase do site, traduzidas.
    foreach (Locales::all() as $code => $locale) {
        $alt = trans('app.brand', [], $code).' - '.trans('app.tagline', [], $code);

        expect($this->get('/'.$locale['slug'])->assertOk()->getContent())
            ->toContain('<meta property="og:image:alt" content="'.e($alt).'">');
    }
});

it('nao poe metas de compartilhamento nas telas de acesso', function () {
    // Elas nao entram no indice: sem hreflang, sem card, com canonical propria.
    $html = $this->get('/pt-br/login')->assertOk()->getContent();

    expect($html)
        ->not->toContain('property="og:')
        ->not->toContain('name="twitter:card"')
        ->toContain('<meta name="robots" content="noindex, follow">')
        ->toContain('<link rel="canonical" href="'.url('/pt-br/login').'">');
});

it('tem a imagem padrao com as dimensoes que a meta promete', function () {
    // Se a imagem for redesenhada, as metas nao podem continuar dizendo o
    // tamanho antigo: e o que este teste prende.
    $path = public_path((string) config('seo.image.file'));

    expect(is_file($path))->toBeTrue();

    $size = getimagesize($path);

    expect($size)
        ->not->toBeFalse()
        ->and($size[0])->toBe((int) config('seo.image.width'))
        ->and($size[1])->toBe((int) config('seo.image.height'))
        ->and($size['mime'])->toBe('image/png');
});

/*
| Imagem por tool (F2): a pagina de uma ferramenta que funciona mostra o card
| dela, e nao o padrao. As duas imagens saem do mesmo desenho, entao as metas de
| tamanho continuam valendo para as duas.
*/

it('usa a imagem da propria tool na pagina dela', function () {
    $relative = trim((string) config('seo.image.tools_dir'), '/').'/json-viewer.png';

    $html = $this->get('/pt-br/tools/json-viewer')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:image" content="'.url($relative).'">')
        // A padrao nao aparece mais: sao metas de uma imagem so.
        ->not->toContain('content="'.url((string) config('seo.image.file')).'"')
        ->toContain('<meta property="og:image:width" content="'.config('seo.image.width').'">')
        ->toContain('<meta property="og:image:height" content="'.config('seo.image.height').'">');
});

it('tem o PNG da tool com as dimensoes que a meta promete', function () {
    $path = public_path(
        trim((string) config('seo.image.tools_dir'), '/').'/json-viewer.png',
    );

    expect(is_file($path))->toBeTrue("Falta o PNG da tool: {$path}");

    $size = getimagesize($path);

    expect($size)
        ->not->toBeFalse()
        ->and($size[0])->toBe((int) config('seo.image.width'))
        ->and($size[1])->toBe((int) config('seo.image.height'))
        ->and($size['mime'])->toBe('image/png');
});

it('descreve a imagem da tool no idioma da pagina', function () {
    // A imagem tem o nome da ferramenta; a descricao repete esse nome no idioma
    // de quem vai ler o card, e nao o nome do idioma padrao.
    foreach (Locales::all() as $code => $locale) {
        $alt = trans('tools.json-viewer.name', [], $code).' - '.trans('app.tagline', [], $code);

        expect($this->get('/'.$locale['slug'].'/tools/json-viewer')->assertOk()->getContent())
            ->toContain('<meta property="og:image:alt" content="'.e($alt).'">');
    }
});

it('cai na imagem padrao quando a tool nao tem PNG proprio', function () {
    // A fixture tem uma tool `available` e nenhuma imagem desenhada para ela: o
    // card sai com a padrao, em vez de apontar para um arquivo que nao existe.
    config(['tools.path' => base_path('tests/Fixtures/tools')]);

    expect($this->get('/pt-br/tools/zeta-tool')->assertOk()->getContent())
        ->toContain('<meta property="og:image" content="'.url((string) config('seo.image.file')).'">')
        ->not->toContain('zeta-tool.png');
});
