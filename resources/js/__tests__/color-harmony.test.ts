import { describe, expect, it } from 'vitest';
import {
    buildHarmony,
    canonicalKey,
    hslToRgb,
    parseColor,
    rgbToHsl,
    rgbToOklch,
    type HarmonySet,
    type Rgba,
} from '@/lib/color';

/**
 * Harmonias (ADR-121): angulos, metodos, base, alpha, gamut e dedupe.
 *
 * A matematica e conferida nos dois espacos: em HSL a rotacao tem valor exato
 * conhecido; em OKLCH o matiz e preservado pelo mapeamento de gamut, mesmo
 * quando o croma precisa ceder.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const RED = colorOf('#ff0000');
const GRAY = colorOf('#808080');
const BLACK = colorOf('#000000');

function hues(set: HarmonySet): number[] {
    return set.colors.map((color) => rgbToHsl(color.rgba).h);
}

function lightnesses(set: HarmonySet): number[] {
    return set.colors.map((color) => rgbToHsl(color.rgba).l);
}

describe('harmonias em HSL', () => {
    it('complementar gira 180 graus e mantem a base', () => {
        const set = buildHarmony(RED, 'complementary', 'hsl');

        expect(set.expected).toBe(2);
        expect(set.colors.map((color) => color.hex)).toEqual([
            '#ff0000',
            '#00ffff',
        ]);
        expect(set.colors[0]?.isBase).toBe(true);
        expect(set.colors[1]?.isBase).toBe(false);
    });

    it('analoga usa -30, 0 e +30 graus', () => {
        const set = buildHarmony(RED, 'analogous', 'hsl');

        expect(hues(set).map((hue) => Math.round(hue))).toEqual([330, 0, 30]);
        expect(set.colors[1]?.isBase).toBe(true);
    });

    it('triadica usa 0, 120 e 240 graus', () => {
        const set = buildHarmony(RED, 'triadic', 'hsl');

        expect(set.colors.map((color) => color.hex)).toEqual([
            '#ff0000',
            '#00ff00',
            '#0000ff',
        ]);
    });

    it('tetradica retangular usa 0, 60, 180 e 240 graus', () => {
        const set = buildHarmony(RED, 'tetradic', 'hsl');

        expect(set.expected).toBe(4);
        expect(hues(set).map((hue) => Math.round(hue))).toEqual([
            0, 60, 180, 240,
        ]);
    });

    it('monocromatica varia L em passos de 14 e 28, do escuro ao claro', () => {
        const set = buildHarmony(RED, 'monochromatic', 'hsl');

        expect(set.expected).toBe(5);
        expect(set.colors).toHaveLength(5);
        expect(lightnesses(set).map((value) => Math.round(value))).toEqual([
            22, 36, 50, 64, 78,
        ]);
        expect(set.colors[2]?.isBase).toBe(true);
    });

    it('prende o L da monocromatica nos extremos e deduplica', () => {
        const set = buildHarmony(BLACK, 'monochromatic', 'hsl');

        // -28, -14 e 0 caem todos em L=0; sobram preto, 14 e 28.
        expect(set.colors).toHaveLength(3);
        expect(set.colors[0]?.isBase).toBe(true);
        expect(set.colors[0]?.hex).toBe('#000000');
    });

    it('cinza colapsa as rotacoes em uma cor', () => {
        const set = buildHarmony(GRAY, 'complementary', 'hsl');

        expect(set.expected).toBe(2);
        expect(set.colors).toHaveLength(1);
        expect(set.colors[0]?.isBase).toBe(true);
    });

    it('normaliza a matiz quando a rotacao passa de 360', () => {
        const base = { ...hslToRgb({ h: 10, s: 80, l: 50 }), a: 1 };
        const set = buildHarmony(base, 'analogous', 'hsl');

        expect(hues(set).map((hue) => Math.round(hue))).toEqual([340, 10, 40]);
    });
});

describe('harmonias em OKLCH', () => {
    it('preserva o matiz girado e marca a base', () => {
        const set = buildHarmony(RED, 'complementary', 'oklch');
        // O vermelho puro nao fica em 0 graus em OKLCH: o giro parte do matiz
        // real da base.
        const baseHue = rgbToOklch(RED).h;

        expect(set.colors).toHaveLength(2);
        expect(Math.round(set.colors[0]?.oklch.h ?? -1)).toBe(
            Math.round(baseHue),
        );
        expect(Math.round(set.colors[1]?.oklch.h ?? -1)).toBe(
            Math.round((baseHue + 180) % 360),
        );
        expect(set.colors[0]?.isBase).toBe(true);
    });

    it('aplica os mesmos angulos das outras harmonias', () => {
        const baseHue = rgbToOklch(RED).h;
        const triadic = buildHarmony(RED, 'triadic', 'oklch');
        const tetradic = buildHarmony(RED, 'tetradic', 'oklch');

        expect(triadic.colors.map((color) => color.oklch.h)).toEqual(
            [0, 120, 240].map((offset) => expect.closeTo((baseHue + offset) % 360, 3)),
        );
        expect(tetradic.colors.map((color) => color.oklch.h)).toEqual(
            [0, 60, 180, 240].map((offset) =>
                expect.closeTo((baseHue + offset) % 360, 3),
            ),
        );
    });

    it('reduz o croma quando a cor nao cabe no sRGB e marca clipped', () => {
        const set = buildHarmony(RED, 'tetradic', 'oklch');

        expect(set.colors.some((color) => color.clipped)).toBe(true);
        expect(set.colors.find((color) => color.isBase)?.clipped).toBe(false);
    });

    it('monocromatica varia L em 0.14 e 0.28 mantendo C e H', () => {
        const baseOklch = rgbToOklch(RED);
        const set = buildHarmony(RED, 'monochromatic', 'oklch');

        expect(set.colors).toHaveLength(5);
        expect(set.colors[2]?.oklch.l).toBeCloseTo(baseOklch.l, 2);
        expect(set.colors[0]?.oklch.l).toBeCloseTo(baseOklch.l - 0.28, 2);
        expect(set.colors[4]?.oklch.l).toBeCloseTo(baseOklch.l + 0.28, 2);
        expect(set.colors[2]?.oklch.h).toBeCloseTo(baseOklch.h, 2);
    });

    it('cinza colapsa as rotacoes em uma cor', () => {
        const set = buildHarmony(GRAY, 'triadic', 'oklch');

        expect(set.expected).toBe(3);
        expect(set.colors).toHaveLength(1);
    });
});

describe('base, alpha e determinismo', () => {
    it('inclui e marca a base em todos os tipos e metodos', () => {
        for (const kind of [
            'complementary',
            'analogous',
            'triadic',
            'tetradic',
            'monochromatic',
        ] as const) {
            for (const method of ['oklch', 'hsl'] as const) {
                const set = buildHarmony(RED, kind, method);

                expect(
                    set.colors.filter((color) => color.isBase),
                ).toHaveLength(1);
                expect(canonicalKey(set.colors.find((c) => c.isBase)?.rgba ?? RED)).toBe(
                    canonicalKey(RED),
                );
            }
        }
    });

    it('herda o alpha da base e usa HEX8', () => {
        const base = colorOf('#ff000080');
        const set = buildHarmony(base, 'analogous', 'hsl');

        for (const color of set.colors) {
            expect(color.rgba.a).toBeCloseTo(base.a, 6);
            expect(color.hex).toHaveLength(9);
        }
    });

    it('e deterministico', () => {
        expect(buildHarmony(RED, 'tetradic', 'oklch')).toEqual(
            buildHarmony(RED, 'tetradic', 'oklch'),
        );
        expect(buildHarmony(RED, 'monochromatic', 'hsl')).toEqual(
            buildHarmony(RED, 'monochromatic', 'hsl'),
        );
    });

    it('HSL e OKLCH produzem cores diferentes', () => {
        const hsl = buildHarmony(RED, 'triadic', 'hsl');
        const oklch = buildHarmony(RED, 'triadic', 'oklch');

        expect(hsl.colors[1]?.hex).not.toBe(oklch.colors[1]?.hex);
    });
});
