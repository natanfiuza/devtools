import { describe, expect, it } from 'vitest';
import { formatHex, parseColor, rgbToOklch, type Rgba } from '@/lib/color';
import {
    ACHROMATIC_CHROMA,
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_PARAMS_VERSION,
    LAYOUT_SCHEMA_VERSION,
    LAYOUT_TOKEN_IDS,
    LAYOUT_TOKEN_SPECS,
    LayoutEngineError,
    SURFACE_PARAMS,
    generateLayoutSystem,
    isLayoutStrategy,
    type LayoutStrategy,
} from '@/tools/layout-harmonizer/engine';

/**
 * Motor do Layout Harmonizer (F1): catalogo, determinismo, seed exata,
 * acromatica, secundaria manual e erros de entrada.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const BLUE = colorOf('#3b82f6');

describe('catalogo de tokens', () => {
    it('tem 33 tokens unicos, nas cinco familias', () => {
        expect(LAYOUT_TOKEN_IDS).toHaveLength(33);
        expect(new Set(LAYOUT_TOKEN_IDS).size).toBe(33);

        const counts = LAYOUT_TOKEN_SPECS.reduce<Record<string, number>>(
            (families, spec) => {
                families[spec.family] = (families[spec.family] ?? 0) + 1;

                return families;
            },
            {},
        );

        expect(counts).toEqual({
            brand: 11,
            surface: 4,
            feedback: 4,
            content: 10,
            interaction: 4,
        });
    });

    it('expoe as versoes do contrato e da tabela', () => {
        expect(LAYOUT_SCHEMA_VERSION).toBe(1);
        expect(LAYOUT_ALGORITHM_VERSION).toBe(1);
        expect(LAYOUT_PARAMS_VERSION).toBe(1);
    });

    it('conhece apenas as quatro estrategias', () => {
        expect(isLayoutStrategy('analogous')).toBe(true);
        expect(isLayoutStrategy('triadic')).toBe(true);
        expect(isLayoutStrategy('monochromatic')).toBe(true);
        expect(isLayoutStrategy('complementary')).toBe(true);
        expect(isLayoutStrategy('tetradic')).toBe(false);
    });
});

describe('geracao', () => {
    it('e deterministica e gera os dois temas com todos os tokens', () => {
        const first = generateLayoutSystem({ seed: BLUE, strategy: 'triadic' });
        const second = generateLayoutSystem({
            seed: BLUE,
            strategy: 'triadic',
        });

        expect(first).toEqual(second);

        for (const theme of ['light', 'dark'] as const) {
            const board = first.themes[theme];

            expect(board.theme).toBe(theme);
            expect(Object.keys(board.tokens).sort()).toEqual(
                [...LAYOUT_TOKEN_IDS].sort(),
            );

            for (const token of Object.values(board.tokens)) {
                expect(token.rgba.a).toBe(1);
                expect(token.hex).toMatch(/^#[0-9a-f]{6}$/);
            }
        }
    });

    it('preserva a seed exata no Primary, nos dois temas', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'analogous',
        });

        expect(result.seed.original).toEqual(BLUE);
        expect(result.seed.effective).toEqual(BLUE);
        expect(result.seed.background).toBeNull();
        expect(result.seed.achromatic).toBe(false);

        for (const theme of ['light', 'dark'] as const) {
            const primary = result.themes[theme].tokens.primary;

            expect(primary.hex).toBe(formatHex(BLUE));
            expect(primary.rgba).toEqual(BLUE);
            expect(primary.oklch).toEqual(rgbToOklch(BLUE));
            expect(primary.clipped).toBe(false);
            expect(primary.source).toBe('seed');
        }
    });

    it('nao inventa matiz para seed acromatica', () => {
        const gray = colorOf('#808080');
        const result = generateLayoutSystem({
            seed: gray,
            strategy: 'triadic',
        });

        expect(rgbToOklch(gray).c).toBeLessThan(ACHROMATIC_CHROMA);
        expect(result.seed.achromatic).toBe(true);

        for (const theme of ['light', 'dark'] as const) {
            const board = result.themes[theme];

            for (const id of [
                'background',
                'surface',
                'surface-variant',
                'hairline',
                'on-background',
                'on-surface',
                'on-surface-variant',
                'focus-ring',
            ] as const) {
                expect(board.tokens[id].oklch.c).toBe(0);
                expect(board.tokens[id].oklch.h).toBe(0);
            }

            expect(board.tokens.secondary.oklch.c).toBe(0);
            expect(board.tokens.accent.oklch.c).toBe(0);
        }
    });

    it('superficies usam o croma literal da tabela quando a seed tem cor', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'analogous',
        });

        for (const theme of ['light', 'dark'] as const) {
            const board = result.themes[theme];

            for (const id of [
                'background',
                'surface',
                'surface-variant',
                'hairline',
            ] as const) {
                const token = board.tokens[id];
                const params = SURFACE_PARAMS[id][theme];

                expect(token.oklch.l).toBeCloseTo(params.l, 6);
                // `surface` claro pede L 1.0 com croma; o gamut reduz o croma
                // ao branco. O valor nunca passa do que a tabela pediu.
                expect(token.oklch.c).toBeLessThanOrEqual(params.c + 1e-9);

                if (!token.clipped) {
                    expect(token.oklch.c).toBeCloseTo(params.c, 6);
                }
            }
        }
    });

    it('aceita secundaria manual e a mantem exata', () => {
        const green = colorOf('#10b981');
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'triadic',
            secondary: { r: green.r, g: green.g, b: green.b },
        });

        expect(result.secondarySource).toBe('manual');

        for (const theme of ['light', 'dark'] as const) {
            const board = result.themes[theme];

            expect(board.tokens.secondary.hex).toBe('#10b981');
            expect(board.tokens.secondary.source).toBe('manual');
            expect(board.tokens.secondary.rgba).toEqual(green);
        }
    });

    it('recusa estrategia desconhecida em dado persistido', () => {
        expect(() =>
            generateLayoutSystem({
                seed: BLUE,
                strategy: 'tetradic' as LayoutStrategy,
            }),
        ).toThrow(LayoutEngineError);
    });

    it('recusa seed com alpha sem fundo explicito', () => {
        const translucent = colorOf('rgba(59, 130, 246, 0.6)');

        try {
            generateLayoutSystem({ seed: translucent, strategy: 'analogous' });
            throw new Error('deveria falhar');
        } catch (error) {
            expect(error).toBeInstanceOf(LayoutEngineError);
            expect((error as LayoutEngineError).code).toBe(
                'seed-alpha-without-background',
            );
        }
    });
});
