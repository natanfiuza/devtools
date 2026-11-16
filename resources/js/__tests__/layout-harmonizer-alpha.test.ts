import { describe, expect, it } from 'vitest';
import {
    compositeOver,
    formatHex,
    parseColor,
    type Rgb,
    type Rgba,
} from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutTheme,
} from '@/tools/layout-harmonizer/engine';

/**
 * Alpha (ADR-149): a seed com alpha so vira cor efetiva sobre um fundo
 * escolhido; o original fica guardado e todas as contas usam a efetiva.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const TRANSLUCENT = colorOf('rgba(59, 130, 246, 0.6)');
const WHITE_BG: Rgb = { r: 255, g: 255, b: 255 };
const BLACK_BG: Rgb = { r: 0, g: 0, b: 0 };
const THEMES: readonly LayoutTheme[] = ['light', 'dark'];

describe('composicao de alpha', () => {
    it('compõe sobre o fundo escolhido e guarda o original', () => {
        const result = generateLayoutSystem({
            seed: TRANSLUCENT,
            strategy: 'analogous',
            background: WHITE_BG,
        });
        const effective = compositeOver(TRANSLUCENT, WHITE_BG);

        expect(result.seed.original).toEqual(TRANSLUCENT);
        expect(result.seed.effective).toEqual({ ...effective, a: 1 });
        expect(result.seed.background).toEqual(WHITE_BG);

        for (const theme of THEMES) {
            const board = result.themes[theme];

            expect(board.tokens.primary.hex).toBe(
                formatHex({ ...effective, a: 1 }),
            );
            expect(board.tokens.primary.rgba).toEqual({ ...effective, a: 1 });

            for (const token of Object.values(board.tokens)) {
                expect(token.rgba.a).toBe(1);
            }
        }
    });

    it('a escolha do fundo muda a cor efetiva', () => {
        const onWhite = generateLayoutSystem({
            seed: TRANSLUCENT,
            strategy: 'analogous',
            background: WHITE_BG,
        });
        const onBlack = generateLayoutSystem({
            seed: TRANSLUCENT,
            strategy: 'analogous',
            background: BLACK_BG,
        });

        expect(onWhite.seed.effective).not.toEqual(onBlack.seed.effective);
        expect(onWhite.themes.light.tokens.primary.hex).not.toBe(
            onBlack.themes.light.tokens.primary.hex,
        );
    });

    it('seed opaca ignora o fundo informado e nao o registra', () => {
        const opaque = colorOf('#3b82f6');
        const result = generateLayoutSystem({
            seed: opaque,
            strategy: 'analogous',
            background: WHITE_BG,
        });

        expect(result.seed.original).toEqual(opaque);
        expect(result.seed.effective).toEqual(opaque);
        expect(result.seed.background).toBeNull();
    });
});
