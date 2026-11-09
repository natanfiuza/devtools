import { describe, expect, it } from 'vitest';
import {
    clamp,
    hslToRgb,
    hsvToRgb,
    linearChannelToSrgb,
    normalizeHue,
    oklabToOklch,
    oklchToOklab,
    rgbToHsl,
    rgbToHsv,
    rgbToOklab,
    rgbToOklch,
    srgbChannelToLinear,
} from '@/lib/color';

/**
 * Conversoes do nucleo (ADR-117): curvas sRGB, HSL/HSV e OKLab/OKLCH com as
 * matrizes de Ottosson. Nada aqui arredonda.
 */

describe('curva sRGB', () => {
    it('mapeia os extremos da curva', () => {
        expect(srgbChannelToLinear(0)).toBe(0);
        expect(srgbChannelToLinear(255)).toBeCloseTo(1, 10);
        expect(linearChannelToSrgb(0)).toBe(0);
        expect(linearChannelToSrgb(1)).toBeCloseTo(255, 10);
    });

    it('usa o trecho linear para canais baixos', () => {
        expect(srgbChannelToLinear(10)).toBeCloseTo(10 / 255 / 12.92, 12);
    });

    it('ida e volta sem deriva relevante', () => {
        for (const channel of [0, 1, 10, 64, 128, 200, 254, 255]) {
            expect(
                linearChannelToSrgb(srgbChannelToLinear(channel)),
            ).toBeCloseTo(channel, 9);
        }
    });
});

describe('HSL e HSV', () => {
    it('converte primarias', () => {
        expect(rgbToHsl({ r: 255, g: 0, b: 0 })).toEqual({
            h: 0,
            s: 100,
            l: 50,
        });
        expect(rgbToHsl({ r: 0, g: 255, b: 0 })).toEqual({
            h: 120,
            s: 100,
            l: 50,
        });
        expect(rgbToHsl({ r: 0, g: 0, b: 255 })).toEqual({
            h: 240,
            s: 100,
            l: 50,
        });
    });

    it('nao produz NaN no acromatico', () => {
        const hsl = rgbToHsl({ r: 128, g: 128, b: 128 });

        expect(hsl.h).toBe(0);
        expect(hsl.s).toBe(0);
        expect(Number.isNaN(hsl.h)).toBe(false);
        expect(hslToRgb(hsl)).toEqual({ r: 128, g: 128, b: 128 });
    });

    it('ida e volta HSL em pontos variados', () => {
        const samples = [
            { r: 255, g: 0, b: 0 },
            { r: 12, g: 200, b: 90 },
            { r: 30, g: 30, b: 220 },
            { r: 250, g: 250, b: 10 },
            { r: 0, g: 0, b: 0 },
            { r: 255, g: 255, b: 255 },
        ];

        for (const rgb of samples) {
            const roundTrip = hslToRgb(rgbToHsl(rgb));

            expect(roundTrip.r).toBe(rgb.r);
            expect(roundTrip.g).toBe(rgb.g);
            expect(roundTrip.b).toBe(rgb.b);
        }
    });

    it('ida e volta HSV em pontos variados', () => {
        const samples = [
            { r: 255, g: 0, b: 0 },
            { r: 12, g: 200, b: 90 },
            { r: 30, g: 30, b: 220 },
        ];

        for (const rgb of samples) {
            const roundTrip = hsvToRgb(rgbToHsv(rgb));

            expect(roundTrip.r).toBe(rgb.r);
            expect(roundTrip.g).toBe(rgb.g);
            expect(roundTrip.b).toBe(rgb.b);
        }
    });
});

describe('OKLab e OKLCH', () => {
    it('converte o vermelho para os valores de referencia', () => {
        const oklab = rgbToOklab({ r: 255, g: 0, b: 0 });
        const oklch = rgbToOklch({ r: 255, g: 0, b: 0 });

        expect(oklab.l).toBeCloseTo(0.627955, 4);
        expect(oklab.a).toBeCloseTo(0.224863, 4);
        expect(oklab.b).toBeCloseTo(0.125846, 4);
        expect(oklch.l).toBeCloseTo(0.627955, 4);
        expect(oklch.c).toBeCloseTo(0.257683, 4);
        expect(oklch.h).toBeCloseTo(29.234, 1);
    });

    it('OKLCH e OKLab sao inversos', () => {
        const oklab = rgbToOklab({ r: 90, g: 140, b: 200 });
        const back = oklchToOklab(oklabToOklch(oklab));

        expect(back.l).toBeCloseTo(oklab.l, 10);
        expect(back.a).toBeCloseTo(oklab.a, 10);
        expect(back.b).toBeCloseTo(oklab.b, 10);
    });

    it('matiz do cinza e zero e nao NaN', () => {
        const oklch = rgbToOklch({ r: 128, g: 128, b: 128 });

        expect(oklch.c).toBeCloseTo(0, 8);
        expect(oklch.h).toBe(0);
    });
});

describe('utilidades', () => {
    it('normaliza matiz negativo e com voltas', () => {
        expect(normalizeHue(-30)).toBe(330);
        expect(normalizeHue(390)).toBe(30);
        expect(normalizeHue(720)).toBe(0);
        expect(normalizeHue(-720)).toBe(0);
    });

    it('prende valores ao intervalo', () => {
        expect(clamp(-1, 0, 1)).toBe(0);
        expect(clamp(2, 0, 1)).toBe(1);
        expect(clamp(0.5, 0, 1)).toBe(0.5);
    });
});
