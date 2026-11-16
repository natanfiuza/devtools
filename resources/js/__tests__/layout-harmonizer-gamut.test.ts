import { describe, expect, it } from 'vitest';
import {
    formatHex,
    isOklchInSrgbGamut,
    mapOklchToSrgb,
    parseColor,
    rgbToOklch,
    type Rgba,
} from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutTheme,
} from '@/tools/layout-harmonizer/engine';

/**
 * Gamut (ADR-117/ADR-147): a seed e preservada como veio; toda cor derivada e
 * mapeada para sRGB e todo token efetivo esta dentro do gamut.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const THEMES: readonly LayoutTheme[] = ['light', 'dark'];

describe('mapeamento de gamut', () => {
    it('preserva a seed fora do gamut como cor efetiva valida', () => {
        const parsed = parseColor('oklch(0.70 0.35 20)');

        expect(parsed.ok).toBe(true);

        if (!parsed.ok) {
            return;
        }

        expect(parsed.color.outOfGamut).toBe(true);

        const seed = parsed.color.rgba;
        const result = generateLayoutSystem({
            seed,
            strategy: 'triadic',
        });

        for (const theme of THEMES) {
            const primary = result.themes[theme].tokens.primary;

            expect(primary.hex).toBe(formatHex(seed));
            expect(primary.clipped).toBe(false);
            expect(primary.oklch).toEqual(rgbToOklch(seed));
        }
    });

    it('mapeia todas as cores derivadas para sRGB', () => {
        const seed = colorOf('oklch(0.70 0.35 20)');
        const result = generateLayoutSystem({
            seed,
            strategy: 'complementary',
        });

        for (const theme of THEMES) {
            const board = result.themes[theme];

            for (const token of Object.values(board.tokens)) {
                expect(isOklchInSrgbGamut(token.oklch)).toBe(true);
                expect(token.hex).toMatch(/^#[0-9a-f]{6}$/);

                for (const channel of [
                    token.rgba.r,
                    token.rgba.g,
                    token.rgba.b,
                ]) {
                    expect(Number.isFinite(channel)).toBe(true);
                    expect(channel).toBeGreaterThanOrEqual(0);
                    expect(channel).toBeLessThanOrEqual(255);
                }
            }
        }
    });

    it('deriva o Accent do croma efetivo, nao do croma pedido', () => {
        const seed = colorOf('oklch(0.70 0.35 20)');
        const seedOklch = rgbToOklch(seed);
        const result = generateLayoutSystem({
            seed,
            strategy: 'triadic',
        });

        for (const theme of THEMES) {
            const accent = result.themes[theme].tokens.accent;
            const requested = {
                l: seedOklch.l,
                c: seedOklch.c,
                h: (seedOklch.h + 120) % 360,
            };
            const mapped = mapOklchToSrgb(requested);

            expect(accent.oklch.c).toBeLessThanOrEqual(seedOklch.c + 1e-9);
            expect(accent.oklch.l).toBeCloseTo(requested.l, 6);
            expect(accent.oklch.h).toBeCloseTo(requested.h, 6);
            expect(accent.clipped).toBe(mapped.clipped);
            expect(accent.hex).toBe(formatHex({ ...mapped.rgb, a: 1 }));
        }
    });

    it('gera quadro neutro para preto, branco e cinza', () => {
        for (const input of ['#000000', '#ffffff', '#808080']) {
            const seed = colorOf(input);
            const result = generateLayoutSystem({
                seed,
                strategy: 'monochromatic',
            });

            expect(result.seed.achromatic).toBe(true);

            for (const theme of THEMES) {
                expect(result.themes[theme].tokens.primary.hex).toBe(
                    formatHex(seed),
                );
                expect(result.themes[theme].tokens.primary.clipped).toBe(false);
            }
        }
    });
});
