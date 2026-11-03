<?php

use App\Support\BadgeForge\BadgeFontCatalog;
use App\Support\BadgeForge\BadgeSpec;
use App\Support\BadgeForge\ColorValue;
use App\Support\BadgeForge\Logo;
use App\Support\BadgeForge\LogoCatalog;
use App\Support\BadgeForge\LogoPath;
use App\Support\BadgeForge\StaticBadgeRenderer;
use App\Support\BadgeForge\StyleCatalog;
use Tests\TestCase;

uses(TestCase::class);

/*
| Fixtures douradas: o mesmo par (cases.json, expected/*.svg) e consumido
| pelo Vitest. Qualquer divergencia entre PHP e TypeScript quebra aqui.
*/

function badgeSpecFromArray(array $data): BadgeSpec
{
    return new BadgeSpec(
        label: $data['label'],
        message: $data['message'],
        style: app(StyleCatalog::class)->get($data['style']),
        font: isset($data['font']) ? app(BadgeFontCatalog::class)->find($data['font']) : null,
        labelColor: new ColorValue($data['labelColor']['hex'], (float) $data['labelColor']['alpha']),
        messageColor: new ColorValue($data['messageColor']['hex'], (float) $data['messageColor']['alpha']),
        logo: badgeLogoFromFixture($data['logo']),
        logoColor: new ColorValue($data['logoColor']['hex'], (float) $data['logoColor']['alpha']),
        logoSize: $data['logoSize'],
        logoPosition: $data['logoPosition'],
        link: $data['link'],
        cacheSeconds: $data['cacheSeconds'],
    );
}

/**
 * `logo` na fixture e um slug embutido ou um logo custom inline
 * (`{viewBox, paths}`), permitindo cobrir multi-path sem banco.
 *
 * @param  string|array<string, mixed>|null  $logo
 */
function badgeLogoFromFixture(string|array|null $logo): ?Logo
{
    if ($logo === null) {
        return null;
    }

    if (is_string($logo)) {
        return app(LogoCatalog::class)->find($logo);
    }

    $paths = array_map(
        static fn (array $path): LogoPath => new LogoPath(
            d: (string) $path['d'],
            fillRule: isset($path['fillRule']) ? (string) $path['fillRule'] : null,
            clipRule: isset($path['clipRule']) ? (string) $path['clipRule'] : null,
        ),
        (array) $logo['paths'],
    );

    return new Logo(
        slug: 'fixture',
        name: 'fixture',
        viewBox: (string) $logo['viewBox'],
        paths: $paths,
        custom: true,
    );
}

function badgeCases(): array
{
    $path = base_path('tests/Fixtures/badgeforge/cases.json');

    return json_decode((string) file_get_contents($path), true)['cases'];
}

it('reproduz as fixtures douradas byte a byte', function () {
    $renderer = app(StaticBadgeRenderer::class);

    foreach (badgeCases() as $case) {
        $expected = (string) file_get_contents(base_path('tests/Fixtures/badgeforge/expected/'.$case['name'].'.svg'));

        expect($renderer->render(badgeSpecFromArray($case['spec'])))->toBe($expected);
    }
});

it('produz o mesmo SVG em renders repetidos', function () {
    $renderer = app(StaticBadgeRenderer::class);
    $spec = badgeSpecFromArray(badgeCases()[0]['spec']);

    expect($renderer->render($spec))->toBe($renderer->render($spec));
});

it('inclui role, title e aria-label', function () {
    $svg = app(StaticBadgeRenderer::class)->render(badgeSpecFromArray(badgeCases()[0]['spec']));

    expect($svg)
        ->toContain('role="img"')
        ->toContain('<title>build: passing</title>')
        ->toContain('aria-label="build: passing"');
});

it('escapa texto e nao emite script', function () {
    $case = collect(badgeCases())->firstWhere('name', 'xss_text');
    $svg = app(StaticBadgeRenderer::class)->render(badgeSpecFromArray($case['spec']));

    expect($svg)
        ->not->toContain('<script')
        ->not->toContain('javascript:')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('envolve o conteudo em ancora somente com link valido', function () {
    $withLink = collect(badgeCases())->firstWhere('name', 'flat_link');
    $withoutLink = collect(badgeCases())->firstWhere('name', 'flat_basic');

    expect(app(StaticBadgeRenderer::class)->render(badgeSpecFromArray($withLink['spec'])))
        ->toContain('<a href="https://example.com/docs?x=1&amp;y=2" target="_blank" rel="noopener noreferrer">')
        ->and(app(StaticBadgeRenderer::class)->render(badgeSpecFromArray($withoutLink['spec'])))
        ->not->toContain('<a ');
});
