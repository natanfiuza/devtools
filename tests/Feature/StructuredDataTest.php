<?php

use App\Support\Locales;
use App\Support\StructuredData;
use App\Support\Tools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

/*
| Dados estruturados (JSON-LD, ADR-053).
|
| A home declara a organizacao e o site; a pagina de cada ferramenta declara o
| SoftwareApplication e o BreadcrumbList. Tudo sai no HTML do servidor, para
| robo de busca que nao executa JavaScript. Pagina noindex nao leva JSON-LD.
*/

/** Blocos JSON-LD do HTML, ja decodificados. @return list<array<string, mixed>> */
function structuredDataBlocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $blocks);

    return array_map(static function (string $json): array {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }, $blocks[1]);
}

it('declara a organizacao e o site na home', function () {
    $blocks = structuredDataBlocks($this->get('/pt-br')->assertOk()->getContent());

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['@context'] ?? null)->toBe('https://schema.org')
        ->and($blocks[0]['@type'] ?? null)->toBe('Organization')
        ->and($blocks[0]['name'] ?? null)->toBe(trans('app.brand'))
        ->and($blocks[0]['url'] ?? null)->toBe(url('/pt-br'))
        ->and($blocks[0]['logo'] ?? null)->toBe([
            '@type' => 'ImageObject',
            'url' => url((string) config('seo.image.file')),
        ])
        ->and($blocks[1]['@type'] ?? null)->toBe('WebSite')
        ->and($blocks[1]['name'] ?? null)->toBe(trans('app.brand'))
        ->and($blocks[1]['url'] ?? null)->toBe(url('/pt-br'))
        ->and($blocks[1]['inLanguage'] ?? null)->toBe('pt-BR');
});

it('declara o site no idioma da pagina', function () {
    foreach (Locales::all() as $locale) {
        $blocks = structuredDataBlocks($this->get('/'.$locale['slug'])->assertOk()->getContent());

        expect($blocks[1]['inLanguage'] ?? null)->toBe($locale['html'])
            ->and($blocks[0]['url'] ?? null)->toBe(url('/'.$locale['slug']))
            ->and($blocks[1]['url'] ?? null)->toBe(url('/'.$locale['slug']));
    }
});

it('declara a ferramenta e o caminho de navegacao na pagina da tool', function () {
    $blocks = structuredDataBlocks($this->get('/pt-br/tools/json-viewer')->assertOk()->getContent());

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['@type'] ?? null)->toBe('SoftwareApplication')
        ->and($blocks[0]['name'] ?? null)->toBe(trans('tools.json-viewer.name'))
        ->and($blocks[0]['description'] ?? null)->toBe(trans('tools.json-viewer.description'))
        ->and($blocks[0]['applicationCategory'] ?? null)->toBe('DeveloperApplication')
        ->and($blocks[0]['operatingSystem'] ?? null)->toBe('Any')
        ->and($blocks[0]['isAccessibleForFree'] ?? null)->toBeTrue()
        ->and($blocks[0]['url'] ?? null)->toBe(url('/pt-br/tools/json-viewer'))
        ->and($blocks[0]['inLanguage'] ?? null)->toBe('pt-BR')
        ->and($blocks[1]['@type'] ?? null)->toBe('BreadcrumbList');

    expect($blocks[1]['itemListElement'] ?? null)->toBe([
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => (string) trans('app.nav.tools'),
            'item' => url('/pt-br'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => (string) trans('tools.json-viewer.name'),
            'item' => url('/pt-br/tools/json-viewer'),
        ],
    ]);
});

it('cobre cada ferramenta que funciona', function () {
    foreach (Tools::available() as $tool) {
        $blocks = structuredDataBlocks(
            $this->get('/pt-br/tools/'.(string) $tool['slug'])->assertOk()->getContent(),
        );

        expect($blocks[0]['@type'] ?? null)->toBe('SoftwareApplication')
            ->and($blocks[0]['url'] ?? null)->toBe(url('/pt-br/tools/'.(string) $tool['slug']));
    }
});

it('nao emite dados estruturados em pagina noindex', function () {
    foreach (['/pt-br/login', '/pt-br/register', '/pt-br/styleguide'] as $url) {
        expect($this->get($url)->assertOk()->getContent())
            ->not->toContain('application/ld+json');
    }
});

it('nao emite dados estruturados em pagina indexavel sem dados proprios', function () {
    expect($this->get('/pt-br/privacy')->assertOk()->getContent())
        ->not->toContain('application/ld+json');
});

it('nao devolve dados para uma requisicao sem rota', function () {
    expect(StructuredData::forRequest(Request::create('/')))->toBe([]);
});
