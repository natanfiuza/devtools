<?php

namespace App\Support\Colors;

use App\Enums\LayoutStrategy;

/**
 * Catalogo do Layout Harmonizer no servidor (F4, ADR-168).
 *
 * Espelha `engine/tokens.ts` e `engine/types.ts`: os 33 tokens com a familia,
 * os 16 pares medidos e as estrategias. O servidor valida o snapshot contra
 * esta tabela; um teste de paridade le os arquivos TS e falha se divergirem.
 *
 * @phpstan-type TokenSpec array{id: string, family: string}
 * @phpstan-type PairSpec array{foreground: string, background: string}
 */
final class LayoutSystemCatalog
{
    /** @var list<array{id: string, family: string}> */
    public const TOKENS = [
        ['id' => 'primary', 'family' => 'brand'],
        ['id' => 'primary-variant', 'family' => 'brand'],
        ['id' => 'secondary', 'family' => 'brand'],
        ['id' => 'secondary-variant', 'family' => 'brand'],
        ['id' => 'surface', 'family' => 'surface'],
        ['id' => 'surface-variant', 'family' => 'surface'],
        ['id' => 'background', 'family' => 'surface'],
        ['id' => 'hairline', 'family' => 'surface'],
        ['id' => 'error', 'family' => 'feedback'],
        ['id' => 'success', 'family' => 'feedback'],
        ['id' => 'warning', 'family' => 'feedback'],
        ['id' => 'info', 'family' => 'feedback'],
        ['id' => 'on-primary', 'family' => 'content'],
        ['id' => 'on-secondary', 'family' => 'content'],
        ['id' => 'on-background', 'family' => 'content'],
        ['id' => 'on-surface', 'family' => 'content'],
        ['id' => 'on-surface-variant', 'family' => 'content'],
        ['id' => 'on-error', 'family' => 'content'],
        ['id' => 'on-success', 'family' => 'content'],
        ['id' => 'on-warning', 'family' => 'content'],
        ['id' => 'on-info', 'family' => 'content'],
        ['id' => 'primary-hover', 'family' => 'brand'],
        ['id' => 'primary-active', 'family' => 'brand'],
        ['id' => 'secondary-hover', 'family' => 'brand'],
        ['id' => 'secondary-active', 'family' => 'brand'],
        ['id' => 'link', 'family' => 'interaction'],
        ['id' => 'link-hover', 'family' => 'interaction'],
        ['id' => 'link-active', 'family' => 'interaction'],
        ['id' => 'focus-ring', 'family' => 'interaction'],
        ['id' => 'accent', 'family' => 'brand'],
        ['id' => 'on-accent', 'family' => 'content'],
        ['id' => 'accent-hover', 'family' => 'brand'],
        ['id' => 'accent-active', 'family' => 'brand'],
    ];

    /** @var list<array{foreground: string, background: string}> */
    public const PAIRS = [
        ['foreground' => 'on-background', 'background' => 'background'],
        ['foreground' => 'on-surface', 'background' => 'surface'],
        ['foreground' => 'on-surface-variant', 'background' => 'surface-variant'],
        ['foreground' => 'on-primary', 'background' => 'primary'],
        ['foreground' => 'on-primary', 'background' => 'primary-hover'],
        ['foreground' => 'on-primary', 'background' => 'primary-active'],
        ['foreground' => 'on-secondary', 'background' => 'secondary'],
        ['foreground' => 'on-secondary', 'background' => 'secondary-hover'],
        ['foreground' => 'on-secondary', 'background' => 'secondary-active'],
        ['foreground' => 'on-accent', 'background' => 'accent'],
        ['foreground' => 'on-accent', 'background' => 'accent-hover'],
        ['foreground' => 'on-accent', 'background' => 'accent-active'],
        ['foreground' => 'on-error', 'background' => 'error'],
        ['foreground' => 'on-success', 'background' => 'success'],
        ['foreground' => 'on-warning', 'background' => 'warning'],
        ['foreground' => 'on-info', 'background' => 'info'],
    ];

    public const THEMES = ['light', 'dark'];

    public const TOKEN_SOURCES = ['seed', 'manual', 'rule', 'feedback'];

    public const OVERRIDE_ORIGINS = ['edit', 'accept', 'lock'];

    public const TARGETS = ['aa', 'aaa'];

    public const SECONDARY_SOURCES = ['auto', 'manual'];

    /** @var list<string> */
    public const STRATEGIES = [
        LayoutStrategy::Analogous->value,
        LayoutStrategy::Complementary->value,
        LayoutStrategy::Triadic->value,
        LayoutStrategy::Monochromatic->value,
    ];

    /**
     * @return array<string, string> token id => familia
     */
    public static function families(): array
    {
        $families = [];

        foreach (self::TOKENS as $token) {
            $families[$token['id']] = $token['family'];
        }

        return $families;
    }

    /**
     * @return list<string>
     */
    public static function tokenIds(): array
    {
        return array_map(static fn (array $token): string => $token['id'], self::TOKENS);
    }

    /**
     * Assinatura "foreground|background" de cada par medido.
     *
     * @return array<string, true>
     */
    public static function pairKeys(): array
    {
        $keys = [];

        foreach (self::PAIRS as $pair) {
            $keys[$pair['foreground'].'|'.$pair['background']] = true;
        }

        return $keys;
    }
}
