import { describe, expect, it } from 'vitest';
import {
    mixOklab,
    relativeLuminance,
    rgbToOklch,
    shadesOf,
    tintsOf,
} from '@/lib/color';
import type { Rgba } from '@/lib/color';

/**
 * Mistura perceptual (ADR-117): OKLab para shades/tints, alpha preservado.
 */

const RED: Rgba = { r: 255, g: 0, b: 0, a: 1 };
const BLUE: Rgba = { r: 0, g: 0, b: 255, a: 1 };

describe('mixOklab', () => {
    it('devolve os extremos nos limites', () => {
        const start = mixOklab(RED, BLUE, 0);
        const end = mixOklab(RED, BLUE, 1);

        expect(start.r).toBeCloseTo(255, 4);
        expect(start.g).toBeCloseTo(0, 4);
        expect(start.b).toBeCloseTo(0, 4);
        expect(end.r).toBeCloseTo(0, 4);
        expect(end.g).toBeCloseTo(0, 4);
        expect(end.b).toBeCloseTo(255, 4);
    });

    it('no meio fica entre as duas cores', () => {
        const middle = mixOklab(RED, BLUE, 0.5);
        const oklch = rgbToOklch(middle);

        expect(middle.r).toBeGreaterThan(0);
        expect(middle.b).toBeGreaterThan(0);
        expect(oklch.h).toBeGreaterThan(0);
        expect(oklch.h).toBeLessThan(360);
    });

    it('interpola o alpha', () => {
        const mixed = mixOklab({ ...RED, a: 1 }, { ...BLUE, a: 0 }, 0.25);

        expect(mixed.a).toBeCloseTo(0.75, 10);
    });

    it('prende a fracao ao intervalo', () => {
        expect(mixOklab(RED, BLUE, -1).r).toBeCloseTo(255, 4);
        expect(mixOklab(RED, BLUE, 2).b).toBeCloseTo(255, 4);
    });
});

describe('shadesOf e tintsOf', () => {
    it('devolve dez tons de cada lado', () => {
        expect(shadesOf(RED)).toHaveLength(10);
        expect(tintsOf(RED)).toHaveLength(10);
    });

    it('escurece e clareia monotonicamente', () => {
        const base = relativeLuminance(RED);
        const shades = shadesOf(RED).map(relativeLuminance);
        const tints = tintsOf(RED).map(relativeLuminance);

        expect(shades[0] ?? 0).toBeLessThan(base);
        expect(shades[9] ?? 0).toBeLessThan(shades[0] ?? 0);

        expect(tints[0] ?? 0).toBeGreaterThan(base);
        expect(tints[9] ?? 0).toBeGreaterThan(tints[0] ?? 0);
    });

    it('preserva o alpha da cor base', () => {
        const translucent: Rgba = { ...RED, a: 0.4 };

        for (const shade of shadesOf(translucent)) {
            expect(shade.a).toBeCloseTo(0.4, 10);
        }

        for (const tint of tintsOf(translucent)) {
            expect(tint.a).toBeCloseTo(0.4, 10);
        }
    });

    it('respeita uma contagem diferente', () => {
        expect(shadesOf(RED, 3)).toHaveLength(3);
        expect(tintsOf(RED, 0)).toHaveLength(0);
    });
});
