<?php

use App\Support\Locales;
use App\Support\Seo;
use App\Support\Tools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Metas de compartilhamento (Open Graph) e a imagem padrao.
|
| Elas saem so nas paginas indexaveis: as telas de acesso e as paginas de token
| nao circulam, entao nao ganham card. O site inteiro (tipo, nome, idioma, url e
| imagem) e o titulo e a descricao de cada pagina saem do servidor
| (App\Support\Seo::pageMeta): robo de preview nao executa JS e o SSR pode
| estar fora do ar, entao as metas nao podem depender dele.
*/

/** URL do card com a versao de cache que config/seo.php manda. */
function seoOgUrl(string $relative): string
{
    return url($relative).'?v='.(int) config('seo.image.version');
}

it('escreve as metas de compartilhamento na home', function () {
    $html = $this->get('/pt-br')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:type" content="website">')
        ->toContain('<meta property="og:site_name" content="'.config('app.name').'">')
        ->toContain('<meta property="og:locale" content="pt_BR">')
        ->toContain('<meta property="og:url" content="'.url('/pt-br').'">')
        ->toContain('<meta property="og:image" content="'.seoOgUrl((string) config('seo.image.file')).'">')
        ->toContain('<meta property="og:image:width" content="'.config('seo.image.width').'">')
        ->toContain('<meta property="og:image:height" content="'.config('seo.image.height').'">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">');
});

it('completa o card do Twitter com titulo, descricao e imagem', function () {
    $html = $this->get('/pt-br')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta name="twitter:title" content="'.e((string) trans('app.nav.tools')).'">')
        ->toContain('<meta name="twitter:description" content="'.e((string) trans('home.subtitle')).'">')
        ->toContain('<meta name="twitter:image" content="'.seoOgUrl((string) config('seo.image.file')).'">')
        ->toContain('<meta name="twitter:image:alt" content="'.e(trans('app.brand').' - '.trans('app.tagline')).'">');
});

it('aponta o card do Twitter para a imagem da propria tool', function () {
    $relative = trim((string) config('seo.image.tools_dir'), '/').'/json-viewer.png';
    $html = $this->get('/pt-br/tools/json-viewer')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta name="twitter:title" content="'.e((string) trans('tools.json-viewer.name')).'">')
        ->toContain('<meta name="twitter:image" content="'.seoOgUrl($relative).'">');
});

it('escreve titulo e descricao de cada pagina indexavel no HTML do servidor', function () {
    // Sem SSR, o <Head> do Inertia nao chega ao robo do WhatsApp: o titulo e a
    // descricao precisam sair do Blade. Este teste varre as cinco paginas
    // publicas nos cinco idiomas.
    foreach (Locales::all() as $code => $locale) {
        $paginas = [
            '/'.$locale['slug'] => [
                (string) trans('app.nav.tools', [], $code),
                (string) trans('home.subtitle', [], $code),
            ],
            '/'.$locale['slug'].'/tools/json-viewer' => [
                (string) trans('tools.json-viewer.name', [], $code),
                (string) trans('tools.json-viewer.description', [], $code),
            ],
            '/'.$locale['slug'].'/privacy' => [
                (string) trans('legal.privacy.title', [], $code),
                str_replace('{app}', (string) trans('app.brand', [], $code), (string) trans('legal.privacy.intro', [], $code)),
            ],
            '/'.$locale['slug'].'/terms' => [
                (string) trans('legal.terms.title', [], $code),
                str_replace('{app}', (string) trans('app.brand', [], $code), (string) trans('legal.terms.intro', [], $code)),
            ],
            '/'.$locale['slug'].'/donate' => [
                (string) trans('donations.meta.title', [], $code),
                (string) trans('donations.meta.description', [], $code),
            ],
        ];

        foreach ($paginas as $url => [$titulo, $descricao]) {
            expect($this->get($url)->assertOk()->getContent())
                ->toContain('<title>'.e(Seo::documentTitle($titulo)).'</title>')
                ->toContain('<meta name="description" content="'.e($descricao).'">')
                ->toContain('<meta property="og:title" content="'.e($titulo).'">')
                ->toContain('<meta property="og:description" content="'.e($descricao).'">');
        }
    }
});

it('nao deixa o marcador {app} cru nas metas das paginas legais', function () {
    // O `{app}` e interpolado pelo vue-i18n no cliente (e por isso continua no
    // payload das traducoes); no servidor quem faz o mesmo e
    // Seo::legalIntro(). O que nao pode vazar e a meta com o marcador cru.
    foreach (Locales::all() as $locale) {
        foreach (['privacy', 'terms'] as $pagina) {
            expect($this->get('/'.$locale['slug'].'/'.$pagina)->assertOk()->getContent())
                ->not->toMatch('/content="[^"]*\{app\}[^"]*"/');
        }
    }
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
    // Elas nao entram no indice: sem hreflang, sem card, sem descricao, com
    // canonical propria e o titulo do site.
    $html = $this->get('/pt-br/login')->assertOk()->getContent();

    expect($html)
        ->not->toContain('property="og:')
        ->not->toContain('name="twitter:card"')
        ->not->toContain('name="description"')
        ->toContain('<title>'.e((string) config('app.name')).'</title>')
        ->toContain('<meta name="robots" content="noindex, follow">')
        ->toContain('<link rel="canonical" href="'.url('/pt-br/login').'">');
});

it('compartilha titulo e descricao da pagina nas props do Inertia', function () {
    // O <title> da navegacao do cliente sai daqui: as shared props chegam em
    // toda resposta Inertia, com ou sem SSR.
    $this->get('/pt-br/tools/json-viewer')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->where('seo.meta.title', (string) trans('tools.json-viewer.name', [], 'pt_BR'))
            ->where('seo.meta.description', (string) trans('tools.json-viewer.description', [], 'pt_BR')),
    );

    // Noindex nao tem meta de pagina: as duas props voltam vazias.
    $this->get('/pt-br/login')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->where('seo.meta.title', '')
            ->where('seo.meta.description', ''),
    );
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

it('desenha um card quadrado', function () {
    // O WhatsApp exibe o preview numa area quadrada e corta o que e horizontal:
    // a proporcao do arquivo e a mesma das metas.
    expect((int) config('seo.image.width'))->toBe((int) config('seo.image.height'));
});

it('usa a versao de cache da config na URL do og:image', function () {
    // O arquivo em disco e o mesmo; a query string e o que faz a rede buscar o
    // PNG redesenhado. O teste troca a config para provar que a URL a segue.
    config(['seo.image.version' => 7]);

    expect($this->get('/pt-br')->assertOk()->getContent())
        ->toContain('<meta property="og:image" content="'.url((string) config('seo.image.file')).'?v=7">');
});

/*
| Imagem por tool (F2, ampliada na F3 e fechada na F5): a pagina de uma
| ferramenta que funciona mostra o card dela, e nao o padrao - e agora sao as
| nove do MVP. Todas as imagens saem do mesmo desenho, entao as metas de tamanho
| valem para todas.
*/

it('usa a imagem da propria tool na pagina dela', function () {
    $relative = trim((string) config('seo.image.tools_dir'), '/').'/json-viewer.png';

    $html = $this->get('/pt-br/tools/json-viewer')->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:image" content="'.seoOgUrl($relative).'">')
        // A padrao nao aparece mais: sao metas de uma imagem so.
        ->not->toContain('content="'.seoOgUrl((string) config('seo.image.file')).'"')
        ->toContain('<meta property="og:image:width" content="'.config('seo.image.width').'">')
        ->toContain('<meta property="og:image:height" content="'.config('seo.image.height').'">');
});

it('tem o PNG de cada tool que funciona, com as dimensoes que a meta promete', function () {
    // O `seo:og-image` desenha uma imagem por tool `available`: uma tool que
    // virasse `available` sem a imagem cairia na padrao em silencio, e e contra
    // esse silencio que o teste guarda.
    foreach (Tools::available() as $tool) {
        $path = public_path(
            trim((string) config('seo.image.tools_dir'), '/').'/'.$tool['slug'].'.png',
        );

        expect(is_file($path))->toBeTrue("Falta o PNG da tool: {$path}");

        $size = getimagesize($path);

        expect($size)
            ->not->toBeFalse()
            ->and($size[0])->toBe((int) config('seo.image.width'))
            ->and($size[1])->toBe((int) config('seo.image.height'))
            ->and($size['mime'])->toBe('image/png');
    }
});

it('aponta o card de cada tool que funciona para o PNG dela', function () {
    foreach (Tools::available() as $tool) {
        $slug = (string) $tool['slug'];
        $relative = trim((string) config('seo.image.tools_dir'), '/')."/{$slug}.png";

        expect($this->get("/pt-br/tools/{$slug}")->assertOk()->getContent())
            ->toContain('<meta property="og:image" content="'.seoOgUrl($relative).'">');
    }
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
        ->toContain('<meta property="og:image" content="'.seoOgUrl((string) config('seo.image.file')).'">')
        ->not->toContain('zeta-tool.png');
});

/*
| Telas de conta (F7): os nomes de rota do starter kit nao usam o prefixo
| `settings.`; por isso o NOINDEX lista cada um. A regra e conferida pelo nome
| da rota, sem depender de sessao, 2FA ou dados de conta.
*/

it('marca como noindex as telas de conta e o desafio de dois fatores', function () {
    $rotas = [
        'profile.edit',
        'security.edit',
        'appearance.edit',
        'two-factor.login',
        'api-tokens.index',
    ];

    foreach ($rotas as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("Rota ausente: {$name}");

        $request = Request::create('/'.$route->uri(), 'GET');
        $request->setRouteResolver(fn () => $route);

        expect(Seo::noindex($request))->toBeTrue("Rota indexavel: {$name}");
    }
});

it('mantem as paginas publicas fora da lista de noindex', function () {
    foreach (['home', 'pricing', 'privacy', 'terms', 'donate', 'tools.show'] as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("Rota ausente: {$name}");

        $request = Request::create('/'.$route->uri(), 'GET');
        $request->setRouteResolver(fn () => $route);

        expect(Seo::noindex($request))->toBeFalse("Rota noindex: {$name}");
    }
});
