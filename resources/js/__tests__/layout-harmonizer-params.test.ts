import { describe, expect, it } from 'vitest';
import { parseColor, type Rgba } from '@/lib/color';
import {
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_PARAMS_VERSION,
    LAYOUT_SCHEMA_VERSION,
    LAYOUT_STRATEGIES,
    LAYOUT_THEMES,
    SURFACE_PARAMS,
    SURFACE_TEXT_PARAMS,
    generateLayoutSystem,
    type LayoutStrategy,
    type LayoutTheme,
} from '@/tools/layout-harmonizer/engine';

/**
 * Teto de croma aprovado no F0 e aplicado na emenda da F6: superficies e
 * textos ficam em C <= 0.012 no tema claro e C <= 0.020 no escuro. A tabela e
 * a geracao (em todas as seeds/estrategias) precisam respeitar o mesmo teto.
 */

const CAP: Record<LayoutTheme, number> = { light: 0.012, dark: 0.02 };

/** Mesmas seeds douradas das fixtures: cobre acromaticas, saturadas e gamut. */
const SEEDS: readonly string[] = [
    '#3b82f6',
    '#10b981',
    '#ef4444',
    '#f59e0b',
    '#7c3aed',
    '#000000',
    '#ffffff',
    '#808080',
    'oklch(0.70 0.35 20)',
];

const CAPPED_FAMILIES = new Set<string>(['surface', 'content']);

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

describe('teto de croma', () => {
    it('mantem as versoes aprovadas depois da emenda', () => {
        expect(LAYOUT_SCHEMA_VERSION).toBe(1);
        expect(LAYOUT_ALGORITHM_VERSION).toBe(2);
        expect(LAYOUT_PARAMS_VERSION).toBe(2);
    });

    it('nao publica croma acima do teto na tabela de parametros', () => {
        for (const theme of LAYOUT_THEMES) {
            for (const [id, params] of Object.entries(SURFACE_PARAMS)) {
                expect(
                    params[theme].c,
                    `SURFACE_PARAMS.${id}.${theme}`,
                ).toBeLessThanOrEqual(CAP[theme]);
            }

            for (const [id, params] of Object.entries(SURFACE_TEXT_PARAMS)) {
                expect(
                    params[theme].c,
                    `SURFACE_TEXT_PARAMS.${id}.${theme}`,
                ).toBeLessThanOrEqual(CAP[theme]);
            }
        }
    });

    it('respeita o teto em superficies e textos de toda seed e estrategia', () => {
        for (const seedText of SEEDS) {
            for (const strategy of LAYOUT_STRATEGIES as readonly LayoutStrategy[]) {
                const result = generateLayoutSystem({
                    seed: colorOf(seedText),
                    strategy,
                });

                for (const theme of LAYOUT_THEMES) {
                    const board = result.themes[theme];

                    for (const token of Object.values(board.tokens)) {
                        if (!CAPPED_FAMILIES.has(token.family)) {
                            continue;
                        }

                        expect(
                            token.oklch.c,
                            `${seedText} ${strategy} ${theme} ${token.id}`,
                        ).toBeLessThanOrEqual(CAP[theme]);
                    }
                }
            }
        }
    });
});
