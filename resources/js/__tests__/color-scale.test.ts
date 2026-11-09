import { describe, expect, it } from 'vitest';
import {
    BLACK,
    PALETTE_ALGORITHM_VERSION,
    PALETTE_TONES,
    WHITE,
    buildPaletteScale,
    canonicalKey,
    contrastReport,
    nearestToneFor,
    parseColor,
    rgbToOklch,
    type Rgba,
} from '@/lib/color';

/**
 * Escala de 11 tons (ADR-120).
 *
 * Os valores da tabela sao literais de proposito: se alguem ajustar a escada
 * "no feeling", o teste estrutural acusa e a versao do algoritmo precisa subir.
 */

const LADDER = [
    0.97, 0.94, 0.88, 0.81, 0.74, 0.66, 0.58, 0.5, 0.42, 0.34, 0.22,
];

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

function toneAt(scale: ReturnType<typeof buildPaletteScale>, index: number) {
    const tone = scale.tones[index];

    if (tone === undefined) {
        throw new Error(`tom ausente no indice ${index}`);
    }

    return tone;
}

const INDIGO = colorOf('#6366f1');

describe('nearestToneFor', () => {
    it('cobre os extremos da escada', () => {
        expect(nearestToneFor(1)).toBe(50);
        expect(nearestToneFor(0)).toBe(950);
        expect(nearestToneFor(0.66)).toBe(500);
    });

    it('empate fica com o tom mais claro', () => {
        expect(nearestToneFor(0.62)).toBe(500);
    });
});

describe('buildPaletteScale', () => {
    it('e deterministico', () => {
        expect(buildPaletteScale(INDIGO)).toEqual(buildPaletteScale(INDIGO));
    });

    it('devolve os 11 tons na ordem da tabela', () => {
        const scale = buildPaletteScale(INDIGO);

        expect(scale.tones.map((tone) => tone.tone)).toEqual([
            ...PALETTE_TONES,
        ]);
    });

    it('deixa a luminosidade estritamente decrescente na escada fixa', () => {
        const scale = buildPaletteScale(INDIGO);

        scale.tones.forEach((tone, index) => {
            expect(tone.oklch.l).toBeCloseTo(LADDER[index] ?? 0, 9);
        });

        for (let index = 1; index < scale.tones.length; index += 1) {
            const previous = toneAt(scale, index - 1).oklch.l;
            const current = toneAt(scale, index).oklch.l;

            expect(current).toBeLessThan(previous);
        }
    });

    it('nao altera a cor base, inclusive com alpha fracionario', () => {
        const base: Rgba = { ...INDIGO, a: 0.37 };
        const copy: Rgba = { ...base };

        const scale = buildPaletteScale(base);

        expect(scale.base).toEqual(copy);
        expect(base).toEqual(copy);

        for (const tone of scale.tones) {
            expect(tone.rgba.a).toBeCloseTo(0.37, 10);
            expect(tone.hex).toMatch(/^#[0-9a-f]{8}$/);
        }
    });

    it('normaliza o croma pelo tom mais proximo', () => {
        const scale = buildPaletteScale(INDIGO);

        expect(scale.nearestTone).toBe(600);
        expect(scale.baseIsTone500).toBe(false);

        // O tom mais proximo carrega exatamente o croma da base...
        expect(toneAt(scale, 6).oklch.c).toBeCloseTo(
            scale.baseOklch.c,
            6,
        );

        // ...e o 500, ampliado por 1/0.92, passa do gamut e e cortado.
        expect(toneAt(scale, 5).clipped).toBe(true);
        expect(toneAt(scale, 5).oklch.c).toBeLessThan(
            scale.baseOklch.c,
        );
    });

    it('fecha o ponto fixo no tom 500', () => {
        const first = buildPaletteScale(INDIGO);
        const second = buildPaletteScale(toneAt(first, 5).rgba);

        expect(second.nearestTone).toBe(500);
        expect(second.baseIsTone500).toBe(true);
    });

    it('trata branco e preto pelos extremos', () => {
        expect(buildPaletteScale({ ...WHITE, a: 1 }).nearestTone).toBe(50);
        expect(buildPaletteScale({ ...BLACK, a: 1 }).nearestTone).toBe(950);
    });

    it('zera o croma de uma cor acromatica', () => {
        const scale = buildPaletteScale(colorOf('#808080'));

        expect(scale.baseOklch.c).toBeLessThan(1e-4);
        expect(scale.clippedTones).toEqual([]);

        for (const tone of scale.tones) {
            expect(tone.oklch.c).toBe(0);

            const red = tone.hex.slice(1, 3);
            const green = tone.hex.slice(3, 5);
            const blue = tone.hex.slice(5, 7);

            expect(green).toBe(red);
            expect(blue).toBe(red);
        }
    });

    it('marca o clipping de uma cor saturada sem estourar os canais', () => {
        const scale = buildPaletteScale(colorOf('#ff0000'));

        expect(scale.clippedTones.length).toBeGreaterThan(0);

        for (const tone of scale.tones) {
            for (const channel of [tone.rgba.r, tone.rgba.g, tone.rgba.b]) {
                expect(channel).toBeGreaterThanOrEqual(0);
                expect(channel).toBeLessThanOrEqual(255);
            }
        }
    });

    it('preserva L no tom clipado e reduz o croma ao gamut', () => {
        const scale = buildPaletteScale(colorOf('#00ff00'));
        const tone50 = toneAt(scale, 0);

        expect(tone50.clipped).toBe(true);
        expect(tone50.oklch.l).toBeCloseTo(LADDER[0] ?? 0, 9);
        expect(tone50.oklch.c).toBeLessThan(scale.baseOklch.c * 0.6);
    });

    it('carrega a versao do algoritmo', () => {
        expect(buildPaletteScale(INDIGO).algorithmVersion).toBe(
            PALETTE_ALGORITHM_VERSION,
        );
        expect(PALETTE_ALGORITHM_VERSION).toBe(1);
    });

    it('calcula o contraste como o nucleo, inclusive com alpha', () => {
        const scale = buildPaletteScale({ ...INDIGO, a: 0.5 });
        const tone = toneAt(scale, 2);

        expect(tone.contrast.black).toEqual(
            contrastReport(tone.rgba, WHITE).black,
        );
        expect(tone.contrast.white).toEqual(
            contrastReport(tone.rgba, BLACK).white,
        );
        expect(tone.contrast.black.ratio).toBeGreaterThan(0);
        expect(tone.contrast.white.ratio).toBeGreaterThan(0);
    });

    it('usa HEX8 quando a base tem alpha', () => {
        const scale = buildPaletteScale({ ...INDIGO, a: 0.5 });

        for (const tone of scale.tones) {
            expect(tone.hex).toMatch(/^#[0-9a-f]{8}$/);
        }
    });

    it('mantem a chave canonica do tom 500 estavel', () => {
        const scale = buildPaletteScale(INDIGO);
        const tone500 = toneAt(scale, 5);

        expect(canonicalKey(tone500.rgba)).toMatch(/^#[0-9a-f]{8}$/);

        // O tom 500 do indigo esta na borda do gamut: o clamp de um canal muda
        // L em ~7e-5, muito abaixo do que a tela distingue.
        expect(rgbToOklch(tone500.rgba).l).toBeCloseTo(0.66, 3);
    });
});
