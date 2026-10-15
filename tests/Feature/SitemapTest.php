<?php

use App\Support\Locales;
use App\Support\Tools;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Sitemap (docs/SPEC.md secao 7).
|
| O arquivo sai das rotas publicas de conteudo, nos cinco idiomas, com as
| irmas de cada pagina - a pagina da tool inclusa, quando a tool funciona. O
| que se cobra aqui: as URLs certas, as alternates completas, nada de
| `noindex` dentro - e que nenhuma URL do arquivo minta sobre a pagina que ela
| aponta.
*/

const SITEMAP_NS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

const SITEMAP_XHTML_NS = 'http://www.w3.org/1999/xhtml';

/**
 * As paginas de conteudo, na ordem, com as URLs de cada idioma.
 *
 * A pagina de cada tool que funciona entra aqui como uma pagina de conteudo a
 * mais: e a mesma promessa (cinco idiomas, com as irmas), entao a lista do
 * teste sai do registro, e nao escrita a mao.
 */
function sitemapGroups(): array
{
    /** @var list<array{string, array<string, string>}> $pages */
    $pages = [['home', []], ['privacy', []], ['terms', []]];

    foreach (Tools::available() as $tool) {
        $pages[] = ['tools.show', ['slug' => (string) $tool['slug']]];
    }

    $groups = [];

    foreach ($pages as [$name, $params]) {
        $urls = [];

        foreach (Locales::all() as $locale) {
            $urls[$locale['html']] = route($name, ['locale' => $locale['slug'], ...$params]);
        }

        $groups[] = $urls;
    }

    return $groups;
}

/**
 * O sitemap lido como XML - nao como texto.
 *
 * @return list<SimpleXMLElement>
 */
function sitemapBlocks(string $xml): array
{
    $document = simplexml_load_string($xml);

    expect($document)->not->toBeFalse();

    $document?->registerXPathNamespace('s', SITEMAP_NS);

    $blocks = $document?->xpath('//s:url');

    expect($blocks)->not->toBeFalse();

    return $blocks === false ? [] : $blocks;
}

/** @return list<string> */
function sitemapLocations(string $xml): array
{
    $locations = [];

    foreach (sitemapBlocks($xml) as $block) {
        $locations[] = (string) ($block->children(SITEMAP_NS)->loc ?? '');
    }

    return $locations;
}

it('lista as paginas de conteudo nos cinco idiomas', function () {
    $locs = sitemapLocations($this->get('/sitemap.xml')->assertOk()->getContent());

    $esperado = array_merge(...array_map('array_values', sitemapGroups()));

    sort($locs);
    sort($esperado);

    expect($locs)->toBe($esperado);
});

it('declara as irmas de cada pagina, ela mesma inclusa, e o x-default', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    $groups = sitemapGroups();
    $fallback = Locales::html(Locales::fallback());

    foreach (sitemapBlocks($xml) as $block) {
        $loc = (string) $block->children(SITEMAP_NS)->loc;

        $grupo = null;

        foreach ($groups as $urls) {
            if (in_array($loc, $urls, true)) {
                $grupo = $urls;
            }
        }

        expect($grupo)->not->toBeNull("URL que nao e de pagina de conteudo: {$loc}");

        $links = [];

        foreach ($block->children(SITEMAP_XHTML_NS) as $link) {
            $attributes = $link->attributes();

            $links[(string) $attributes['hreflang']] = (string) $attributes['href'];
        }

        // As cinco irmas - a propria inclusa, como o Google pede - e o
        // x-default, que e o idioma de fallback da mesma pagina.
        expect($links)->toBe([...$grupo, 'x-default' => $grupo[$fallback]]);
    }
});

it('deixa de fora o que nao entra no indice', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)
        ->not->toContain('/login')
        ->not->toContain('/register')
        ->not->toContain('/styleguide')
        ->not->toContain('/dashboard')
        // As tools que funcionam entram no indice (F2 levou a primeira, a F3
        // levou as seis seguintes); a que depende de servidor fica de fora,
        // mesmo com o manifest no registro.
        ->not->toContain('/tools/ai-sql-builder');
});

it('lista a tool que funciona nos cinco idiomas', function () {
    // A fixture tem uma tool `available` (zeta) e uma `soon` (alpha): e a
    // separacao entre as duas que este teste cobra, com a rota de verdade.
    config(['tools.path' => base_path('tests/Fixtures/tools')]);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)
        ->toContain(route('tools.show', ['locale' => 'pt-br', 'slug' => 'zeta-tool']))
        ->not->toContain('alpha-tool');
});

it('lista a pagina de cada tool que funciona', function () {
    // A lista e escrita a mao de proposito: o teste dos grupos sai do registro,
    // e este aqui nao - se um manifest voltar para `soon` sem querer, o outro
    // continua verde e este cai.
    $slugs = [
        'json-viewer',
        'json-to-code',
        'sql-formatter',
        'base64-url-hash',
        'jwt-decoder',
        'timestamp-uuid',
        'regex-tester',
    ];

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    foreach (Locales::all() as $locale) {
        foreach ($slugs as $slug) {
            expect($xml)->toContain(
                route('tools.show', ['locale' => $locale['slug'], 'slug' => $slug]),
            );
        }
    }
});

it('nao aponta para nenhuma pagina que pede noindex', function () {
    // A lista de paginas do sitemap e escrita a mao; se alguem puser uma nela
    // que tambem esteja no NOINDEX, o sitemap passa a mentir - e este teste cai.
    foreach (sitemapLocations($this->get('/sitemap.xml')->assertOk()->getContent()) as $loc) {
        $response = $this->get($loc);

        expect($response->status())->toBe(200, "URL do sitemap sem pagina: {$loc}");

        $html = $response->getContent();

        expect($html)
            ->not->toContain('content="noindex')
            ->toContain('<link rel="canonical" href="'.$loc.'">');
    }
});
