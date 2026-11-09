import { describe, expect, it } from 'vitest';
import {
    isOklchInSrgbGamut,
    mapOklabToSrgb,
    mapOklchToSrgb,
    rgbToOklch,
} from '@/lib/color';

/**
 * Gamut sRGB (ADR-117): reduzir croma preserva L e H; o aviso de corte e
 * idempotente depois do mapeamento.
 */

describe('isOklchInSrgbGamut', () => {
    it('reconhece cores que o sRGB tem', () => {
        expect(isOklchInSrgbGamut({ l: 1, c: 0, h: 0 })).toBe(true);
        expect(isOklchInSrgbGamut({ l: 0, c: 0, h: 0 })).toBe(true);
        expect(isOklchInSrgbGamut(rgbToOklch({ r: 255, g: 0, b: 0 }))).toBe(
            true,
        );
    });

    it('reconhece verde saturado fora do gamut', () => {
        expect(isOklchInSrgbGamut({ l: 0.7, c: 0.4, h: 150 })).toBe(false);
    });
});

describe('mapOklchToSrgb', () => {
    it('deixa a cor intacta quando ja cabe no sRGB', () => {
        const oklch = { l: 0.6, c: 0.1, h: 30 };
        const result = mapOklchToSrgb(oklch);

        expect(result.clipped).toBe(false);
        expect(result.oklch).toEqual(oklch);
        expect(result.rgb.r).toBeGreaterThanOrEqual(0);
        expect(result.rgb.r).toBeLessThanOrEqual(255);
    });

    it('reduz o croma preservando L e H', () => {
        const source = { l: 0.7, c: 0.4, h: 150 };
        const result = mapOklchToSrgb(source);

        expect(result.clipped).toBe(true);
        expect(result.oklch.l).toBeCloseTo(0.7, 10);
        expect(result.oklch.h).toBeCloseTo(150, 8);
        expect(result.oklch.c).toBeLessThan(0.4);

        const effective = rgbToOklch(result.rgb);

        expect(effective.l).toBeCloseTo(0.7, 2);
        expect(effective.h).toBeCloseTo(150, 0);
    });

    it('mapeia OKLab por meio do OKLCH equivalente', () => {
        const result = mapOklabToSrgb({ l: 0.7, a: -0.3, b: 0.1 });

        expect(result.clipped).toBe(true);
        expect(result.rgb.g).toBeLessThanOrEqual(255);
    });

    it('e estavel: mapear de novo nao corta outra vez', () => {
        const first = mapOklchToSrgb({ l: 0.7, c: 0.4, h: 150 });
        const second = mapOklchToSrgb(rgbToOklch(first.rgb));

        expect(second.clipped).toBe(false);
        expect(second.oklch.c).toBeCloseTo(first.oklch.c, 1);
    });
});
