import { describe, expect, it } from 'vitest';
import {
    classifyContrast,
    contrastRatio,
    mapOklchToSrgb,
    parseColor,
    type Rgba,
} from '@/lib/color';
import {
    LAYOUT_CONTRAST_PAIRS,
    generateLayoutSystem,
    type LayoutTheme,
} from '@/tools/layout-harmonizer/engine';

/**
 * Contraste (ADR-158): pares de conteudo, razao crua e mesma classificacao
 * do nucleo. O motor mede; a interface decide o selo exibido.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const BLUE = colorOf('#3b82f6');
const THEMES: readonly LayoutTheme[] = ['light', 'dark'];

const WHITE = mapOklchToSrgb({ l: 1, c: 0, h: 0 });
const DARK = mapOklchToSrgb({ l: 0.18, c: 0, h: 0 });

describe('pares de contraste', () => {
    it('mede cada par da tabela contra os tokens gerados', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'triadic',
        });

        for (const theme of THEMES) {
            const board = result.themes[theme];

            expect(board.checks).toHaveLength(LAYOUT_CONTRAST_PAIRS.length);

            for (const check of board.checks) {
                const expected = contrastRatio(
                    board.tokens[check.foreground].rgba,
                    board.tokens[check.background].rgba,
                );

                expect(check.verdict.ratio).toBe(expected);
                expect(check.verdict).toEqual(classifyContrast(expected));
            }
        }
    });

    it('inclui os estados de marca, nao so a cor base', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'analogous',
        });

        for (const theme of THEMES) {
            const backgrounds = result.themes[theme].checks.map(
                (check) => `${check.foreground}/${check.background}`,
            );

            expect(backgrounds).toContain('on-primary/primary-hover');
            expect(backgrounds).toContain('on-primary/primary-active');
            expect(backgrounds).toContain('on-secondary/secondary-hover');
            expect(backgrounds).toContain('on-secondary/secondary-active');
            expect(backgrounds).toContain('on-accent/accent-hover');
            expect(backgrounds).toContain('on-accent/accent-active');
        }
    });

    it('escolhe o conteudo de maior razao sobre a marca', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'analogous',
        });

        for (const theme of THEMES) {
            const board = result.themes[theme];

            for (const [on, base] of [
                ['on-primary', 'primary'],
                ['on-secondary', 'secondary'],
                ['on-accent', 'accent'],
                ['on-error', 'error'],
                ['on-success', 'success'],
                ['on-warning', 'warning'],
                ['on-info', 'info'],
            ] as const) {
                const background = board.tokens[base].rgba;
                const best = Math.max(
                    contrastRatio(WHITE.rgb, background),
                    contrastRatio(DARK.rgb, background),
                );

                expect(board.tokens[on].rgba).toEqual(
                    contrastRatio(WHITE.rgb, background) >=
                        contrastRatio(DARK.rgb, background)
                        ? { ...WHITE.rgb, a: 1 }
                        : { ...DARK.rgb, a: 1 },
                );
                expect(
                    contrastRatio(board.tokens[on].rgba, background),
                ).toBeCloseTo(best, 12);
            }
        }
    });

    it('classifica sem arredondar: 4.4999 nao vira AA', () => {
        const verdict = classifyContrast(4.4999);

        expect(verdict.aaNormal).toBe(false);
        expect(verdict.aaLarge).toBe(true);
        expect(verdict.ratio).toBe(4.4999);
    });
});
