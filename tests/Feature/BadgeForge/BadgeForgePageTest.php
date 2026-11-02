<?php

use App\Support\BadgeForge\BadgeForgeToolProps;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Pagina do BadgeForge (`/{locale}/tools/badgeforge`).
|
| O servidor so entrega o slug e o catalogo (endpoint, estilos, logos,
| limites e defaults); o resto da pagina e do cliente. O payload precisa ser o
| mesmo que o Vitest consome em `tests/Fixtures/badgeforge/tool-props.json`.
*/

it('entrega a tool no catalogo com o pacote do BadgeForge', function (string $locale) {
    $expected = json_decode(
        (string) file_get_contents(base_path('tests/Fixtures/badgeforge/tool-props.json')),
        true,
    );

    $this->get('/'.$locale.'/tools/badgeforge')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tools/Show')
            ->where('slug', 'badgeforge')
            ->where('seo.noindex', false)
            ->where('toolProps', $expected)
        );
})->with(['en', 'es', 'fr', 'pt-br', 'pt-pt']);

it('acompanha os limites da configuracao', function () {
    config([
        'badgeforge.limits.max_text_length' => 42,
        'badgeforge.limits.logo_size' => ['min' => 4, 'max' => 30],
        'badgeforge.limits.cache_seconds' => ['default' => 600, 'min' => 60, 'max' => 900],
        'badgeforge.limits.link_max_length' => 512,
        'badgeforge.default_style' => 'plastic',
        'badgeforge.colors.default' => '#123456',
        'badgeforge.colors.label' => '#000000',
        'badgeforge.colors.logo' => '#ffffff',
        'badgeforge.logo_position' => 'right',
    ]);

    $props = BadgeForgeToolProps::for();

    expect($props['limits'])->toBe([
        'maxTextLength' => 42,
        'logoSize' => ['min' => 4, 'max' => 30],
        'cacheSeconds' => ['default' => 600, 'min' => 60, 'max' => 900],
        'linkMaxLength' => 512,
        'png' => ['maxWidth' => 600],
    ])->and($props['defaults'])->toBe([
        'style' => 'plastic',
        'color' => '#123456',
        'labelColor' => '#000000',
        'logoColor' => '#ffffff',
        'logoPosition' => 'right',
    ]);
});
