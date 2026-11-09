import { describe, expect, it } from 'vitest';
import {
    canonicalKey,
    formatAll,
    formatColor,
    formatHex,
    formatHex8,
} from '@/lib/color';
import type { Rgba } from '@/lib/color';

/**
 * Formatacao (ADR-117): o arredondamento e da exibicao, e o HEX de 8 digitos
 * e a chave canonica de deduplicacao.
 */

const RED: Rgba = { r: 255, g: 0, b: 0, a: 1 };
const RED_HALF: Rgba = { r: 255, g: 0, b: 0, a: 0.5 };

describe('formatColor', () => {
    it('formata HEX e HEX8', () => {
        expect(formatHex(RED)).toBe('#ff0000');
        expect(formatHex8(RED)).toBe('#ff0000ff');
        expect(formatHex8(RED_HALF)).toBe('#ff000080');
    });

    it('formata rgb, rgba, hsl e hsla', () => {
        expect(formatColor(RED, 'rgb')).toBe('rgb(255, 0, 0)');
        expect(formatColor(RED_HALF, 'rgba')).toBe('rgba(255, 0, 0, 0.5)');
        expect(formatColor(RED, 'hsl')).toBe('hsl(0, 100%, 50%)');
        expect(formatColor(RED_HALF, 'hsla')).toBe('hsla(0, 100%, 50%, 0.5)');
    });

    it('formata OKLCH e OKLab com os valores de referencia do vermelho', () => {
        expect(formatColor(RED, 'oklch')).toBe('oklch(62.8% 0.2577 29.23)');
        expect(formatColor(RED, 'oklab')).toBe('oklab(0.628 0.2249 0.1258)');
    });

    it('da o nome CSS mais proximo', () => {
        expect(formatColor(RED, 'name')).toBe('red');
        expect(formatColor({ r: 0, g: 0, b: 0, a: 0 }, 'name')).toBe(
            'transparent',
        );
    });

    it('nunca exibe NaN em cor acromatica', () => {
        const gray: Rgba = { r: 128, g: 128, b: 128, a: 1 };

        for (const { value } of formatAll(gray)) {
            expect(value).not.toContain('NaN');
        }
    });

    it('lista os nove formatos', () => {
        const formats = formatAll(RED).map((item) => item.format);

        expect(formats).toEqual([
            'hex',
            'hex8',
            'rgb',
            'rgba',
            'hsl',
            'hsla',
            'oklch',
            'oklab',
            'name',
        ]);
    });
});

describe('canonicalKey', () => {
    it('trata HEX de 6 e de 8 digitos como a mesma cor', () => {
        expect(canonicalKey(RED)).toBe('#ff0000ff');
        expect(canonicalKey({ r: 255, g: 0, b: 0, a: 1 })).toBe('#ff0000ff');
    });

    it('normaliza alpha zero para transparent', () => {
        expect(canonicalKey({ r: 0, g: 0, b: 0, a: 0 })).toBe('transparent');
        expect(canonicalKey({ r: 255, g: 0, b: 0, a: 0.001 })).toBe(
            'transparent',
        );
    });

    it('distingue o mesmo RGB com alphas diferentes', () => {
        expect(canonicalKey(RED_HALF)).toBe('#ff000080');
        expect(canonicalKey(RED_HALF)).not.toBe(canonicalKey(RED));
    });
});
