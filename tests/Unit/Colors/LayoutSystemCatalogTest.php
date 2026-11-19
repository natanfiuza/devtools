<?php

use App\Enums\LayoutStrategy;
use App\Support\Colors\LayoutSystemCatalog;
use Tests\TestCase;

uses(TestCase::class);

/*
| Paridade do catalogo PHP com o motor TS (F4, ADR-168).
|
| O servidor nao reimplementa o motor: ele valida contra a mesma tabela de
| tokens, pares e estrategias. Se o TS mudar sem o PHP, este teste quebra.
*/

function layoutHarmonizerEnginePath(string $file): string
{
    return resource_path('js/tools/layout-harmonizer/engine/'.$file);
}

/**
 * @return list<array{id: string, family: string}>
 */
function layoutHarmonizerTsTokens(): array
{
    $source = (string) file_get_contents(layoutHarmonizerEnginePath('tokens.ts'));

    preg_match_all("/\{\s*id:\s*'([^']+)',\s*family:\s*'([^']+)'\s*\}/", $source, $matches, PREG_SET_ORDER);

    return array_map(
        static fn (array $match): array => ['id' => $match[1], 'family' => $match[2]],
        $matches,
    );
}

/**
 * @return list<array{foreground: string, background: string}>
 */
function layoutHarmonizerTsPairs(): array
{
    $source = (string) file_get_contents(layoutHarmonizerEnginePath('tokens.ts'));

    preg_match_all(
        "/\{\s*foreground:\s*'([^']+)',\s*background:\s*'([^']+)'\s*\}/",
        $source,
        $matches,
        PREG_SET_ORDER,
    );

    return array_map(
        static fn (array $match): array => ['foreground' => $match[1], 'background' => $match[2]],
        $matches,
    );
}

it('mantem os 33 tokens e familias iguais ao motor', function () {
    expect(LayoutSystemCatalog::TOKENS)->toBe(layoutHarmonizerTsTokens())
        ->and(LayoutSystemCatalog::TOKENS)->toHaveCount(33);
});

it('mantem os 16 pares de contraste iguais ao motor', function () {
    expect(LayoutSystemCatalog::PAIRS)->toBe(layoutHarmonizerTsPairs())
        ->and(LayoutSystemCatalog::PAIRS)->toHaveCount(16);
});

it('mantem as estrategias iguais ao motor', function () {
    $source = (string) file_get_contents(layoutHarmonizerEnginePath('types.ts'));

    preg_match('/LAYOUT_STRATEGIES = \[(.*?)\] as const;/s', $source, $match);

    preg_match_all("/'([^']+)'/", $match[1] ?? '', $values);

    expect(LayoutSystemCatalog::STRATEGIES)->toBe($values[1])
        ->and(LayoutSystemCatalog::STRATEGIES)->toBe(array_map(
            static fn (LayoutStrategy $strategy): string => $strategy->value,
            LayoutStrategy::cases(),
        ));
});

it('expoe familias e ids derivados sem repeticao', function () {
    $families = LayoutSystemCatalog::families();
    $ids = LayoutSystemCatalog::tokenIds();

    expect($families)->toHaveCount(33)
        ->and($ids)->toHaveCount(33)
        ->and(array_values(array_unique($ids)))->toBe($ids)
        ->and(LayoutSystemCatalog::pairKeys())->toHaveCount(16);
});
