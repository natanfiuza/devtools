<?php

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Pagina de uma ferramenta (`/{locale}/tools/{slug}`, docs/SPEC.md secao 4).
|
| Quem decide se a pagina existe e o servidor: so tool `available` responde, e
| todo o resto - slug que nao existe, tool `soon` e tool `hidden` - da o mesmo
| 404 (ADR-007). Um 200 de "em breve" seria uma segunda representacao do status
| e um link morto no indice.
|
| O que se cobra aqui e esse contrato e as tres props que so o servidor conhece:
| o slug (que vem do registro, nunca da URL), o teto de entrada e o limiar da
| virtualizacao. O resto da pagina e do cliente - nome, descricao, editor e
| arvore saem do registro e do componente da tool, cobertos pelo vitest.
*/

it('entrega a pagina da tool nos cinco idiomas', function () {
    foreach (Locales::all() as $locale) {
        $this->get('/'.$locale['slug'].'/tools/json-viewer')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tools/Show')
                // O slug da prop e o do registro: e por ele que a pagina
                // encontra o componente da tool e as traducoes.
                ->where('slug', 'json-viewer')
                // Pagina de conteudo: entra no indice e no sitemap.
                ->where('seo.noindex', false)
                ->has('maxInputBytes')
                ->has('virtualizeFrom')
            );
    }
});

it('manda os limites que vem da configuracao', function () {
    // Nenhum limite mora no codigo (CLAUDE.md): os dois saem de
    // config/tools.php. Se alguem trocar a config por um numero escrito no
    // controller, cai aqui.
    config([
        'tools.limits.input_bytes' => 1234,
        'tools.limits.virtualize_from' => 77,
    ]);

    $this->get('/pt-br/tools/json-viewer')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->where('maxInputBytes', 1234)
            ->where('virtualizeFrom', 77),
    );
});

it('da 404 para slug desconhecido, tool `soon` e tool `hidden`', function () {
    // A fixture tem as tres situacoes, e a tool `available` dela serve de
    // controle: sem esse 200, um 404 geral passaria no teste.
    config(['tools.path' => base_path('tests/Fixtures/tools')]);

    $this->get('/pt-br/tools/zeta-tool')->assertOk();

    $this->get('/pt-br/tools/alpha-tool')->assertNotFound();
    $this->get('/pt-br/tools/hidden-tool')->assertNotFound();
    $this->get('/pt-br/tools/nao-existe')->assertNotFound();
});

it('nao monta caminho a partir do slug da URL', function () {
    // A pagina recebe o slug **do registro**, nunca o da URL: um caminho
    // disfarcado de slug nao encontra tool nenhuma e nao vira leitura de
    // arquivo. A barra vai codificada, que e como ela chegara de verdade.
    $this->get('/pt-br/tools/..%2F..%2Fcomposer.json')->assertNotFound();
});
