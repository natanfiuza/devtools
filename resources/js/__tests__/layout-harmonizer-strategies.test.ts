import { describe, expect, it } from 'vitest';
import { parseColor, rgbToOklch, type Rgba } from '@/lib/color';
import {
    STRATEGY_HUES,
    generateLayoutSystem,
    secondaryHue,
    accentHue,
    type LayoutStrategy,
    type LayoutTheme,
} from '@/tools/layout-harmonizer/engine';

/**
 * Estrategias (ADR-156): analoga +30, complementar +180, triadica +240/+120
 * e monocromatica sem giro. O Accent fica em +120 em todas (ADR-163).
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const RED = colorOf('#ef4444');
const LOW_CHROMA = colorOf('#ddd6fe');
const SEED_HUE = rgbToOklch(RED).h;

const THEMES: readonly LayoutTheme[] = ['light', 'dark'];

/** Giro em 0..360 a partir da matiz da seed, na direcao do deslocamento. */
function hueOffset(hue: number, base: number): number {
    return (((hue - base) % 360) + 360) % 360;
}

describe('deslocamentos de matiz', () => {
    it('analoga soma 30 graus a seed', () => {
        const result = generateLayoutSystem({
            seed: RED,
            strategy: 'analogous',
        });

        for (const theme of THEMES) {
            const hue = result.themes[theme].tokens.secondary.oklch.h;

            expect(
                hueOffset(hue, secondaryHue(SEED_HUE, 'analogous')),
            ).toBeLessThan(1e-9);
            expect(hueOffset(hue, SEED_HUE)).toBeCloseTo(30, 6);
        }
    });

    it('complementar soma 180 graus', () => {
        const result = generateLayoutSystem({
            seed: RED,
            strategy: 'complementary',
        });

        for (const theme of THEMES) {
            const hue = result.themes[theme].tokens.secondary.oklch.h;

            expect(hueOffset(hue, SEED_HUE)).toBeCloseTo(180, 6);
        }
    });

    it('triadica usa +240 na secundaria e +120 no Accent', () => {
        const result = generateLayoutSystem({
            seed: RED,
            strategy: 'triadic',
        });

        for (const theme of THEMES) {
            const board = result.themes[theme];

            expect(
                hueOffset(board.tokens.secondary.oklch.h, SEED_HUE),
            ).toBeCloseTo(240, 6);
            expect(
                hueOffset(board.tokens.accent.oklch.h, SEED_HUE),
            ).toBeCloseTo(120, 6);
            expect(board.tokens['accent-hover'].oklch.h).toBeCloseTo(
                board.tokens.accent.oklch.h,
                6,
            );
        }
    });

    it('monocromatica mantem a matiz da seed na secundaria', () => {
        const result = generateLayoutSystem({
            seed: RED,
            strategy: 'monochromatic',
        });

        for (const theme of THEMES) {
            const secondary = result.themes[theme].tokens.secondary;

            expect(hueOffset(secondary.oklch.h, SEED_HUE)).toBeLessThan(1e-9);
            expect(secondary.oklch.l).toBeCloseTo(rgbToOklch(RED).l, 6);
        }
    });

    it('mantem o Accent em +120 em todas as estrategias', () => {
        const strategies: LayoutStrategy[] = [
            'analogous',
            'complementary',
            'triadic',
            'monochromatic',
        ];

        for (const strategy of strategies) {
            expect(STRATEGY_HUES[strategy].accent).toBe(120);

            const result = generateLayoutSystem({ seed: RED, strategy });

            for (const theme of THEMES) {
                const accent = result.themes[theme].tokens.accent;

                expect(hueOffset(accent.oklch.h, SEED_HUE)).toBeCloseTo(120, 6);
                expect(accent.oklch.h).toBeCloseTo(
                    accentHue(SEED_HUE, strategy),
                    6,
                );
            }
        }
    });

    it('reduz o croma da secundaria a 85% do croma da seed', () => {
        const seed = rgbToOklch(LOW_CHROMA);
        const result = generateLayoutSystem({
            seed: LOW_CHROMA,
            strategy: 'analogous',
        });

        for (const theme of THEMES) {
            expect(result.themes[theme].tokens.secondary.oklch.c).toBeCloseTo(
                seed.c * 0.85,
                6,
            );
        }
    });
});
