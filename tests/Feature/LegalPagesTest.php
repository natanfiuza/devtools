<?php

use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Politica de Privacidade e Termos de Uso.
|
| Sao conteudo proprio do site: ficam sob o prefixo de idioma, indexaveis e com
| hreflang normal (ao contrario das telas de acesso, ver App\Support\Seo). O
| contato e a data de revisao vem de config/legal.php, nunca do codigo.
*/

/**
 * Mesma conta de App\Support\Legal::updatedLabel(), no idioma pedido.
 */
function dataRevisao(string $locale): string
{
    $label = trans('legal.updated', [
        'date' => Carbon::parse(config('legal.updated_at'))->locale($locale)->isoFormat('LL'),
    ], $locale);

    return is_string($label) ? $label : '';
}

it('atende as duas paginas nos cinco idiomas', function () {
    foreach (Locales::all() as $code => $locale) {
        $this->get('/'.$locale['slug'].'/privacy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Privacy')
                ->where('locale', $code)
                ->where('seo.noindex', false)
            );

        $this->get('/'.$locale['slug'].'/terms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Terms'));
    }
});

it('exibe o contato e a data de revisao configurados', function () {
    config()->set('legal.contact_email', 'privacidade@exemplo.com');
    config()->set('legal.updated_at', '2026-03-01');

    $this->get('/pt-br/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactEmail', 'privacidade@exemplo.com')
            ->where('updatedLabel', dataRevisao('pt_BR'))
        );
});

it('traduz a data de revisao no idioma da pagina', function () {
    config()->set('legal.updated_at', '2026-03-01');

    $this->get('/fr/terms')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('updatedLabel', dataRevisao('fr'))
        );

    expect(dataRevisao('fr'))->not->toBe(dataRevisao('en'));
});

it('cai para hoje quando a data de revisao e invalida', function () {
    config()->set('legal.updated_at', 'nao-e-data');

    // Config errada nao pode derrubar uma pagina publica.
    $this->get('/pt-br/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('updatedLabel', trans('legal.updated', [
                'date' => Carbon::now()->locale('pt_BR')->isoFormat('LL'),
            ], 'pt_BR'))
        );
});

it('omite o contato quando nao ha e-mail configurado', function () {
    config()->set('legal.contact_email', '');

    $this->get('/pt-br/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('contactEmail', null));
});

it('declara hreflang das paginas legais', function () {
    $html = $this->get('/pt-br/privacy')->assertOk()->getContent();

    foreach (Locales::all() as $locale) {
        expect($html)->toContain('hreflang="'.$locale['html'].'"');
    }

    expect($html)->toContain('<link rel="canonical" href="'.url('/pt-br/privacy').'">');
});
