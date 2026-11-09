import { BLACK, WHITE, contrastReport } from './contrast';
import { normalizeHue, rgbToOklch } from './convert';
import { formatHex, formatHex8, canonicalKey } from './format';
import { mapOklchToSrgb } from './gamut';
import type { ContrastVerdict, Oklch, Rgba } from './types';

/**
 * Escala de 11 tons a partir de uma cor base (ADR-120).
 *
 * A escada de luminosidade e fixa em OKLCH; o croma e relativo a base e
 * normalizado pelo tom mais proximo, entao o tom em que a base "cai" carrega
 * exatamente o croma dela. A base nunca e alterada: se nao for o tom 500, a
 * escala continua a mesma e so o selo do tom mais proximo aparece na tela.
 *
 * Puro: sem Vue, DOM, i18n, datas ou aleatoriedade. Mudar a tabela abaixo e
 * mudar o algoritmo - por isso `PALETTE_ALGORITHM_VERSION` acompanha.
 */

export const PALETTE_TONES = [
    50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950,
] as const;

export type PaletteTone = (typeof PALETTE_TONES)[number];

export const PALETTE_ALGORITHM_VERSION = 1;

/** Luminosidade OKLCH de cada tom, literal (ADR-120). */
const TONE_LIGHTNESS: Record<PaletteTone, number> = {
    50: 0.97,
    100: 0.94,
    200: 0.88,
    300: 0.81,
    400: 0.74,
    500: 0.66,
    600: 0.58,
    700: 0.5,
    800: 0.42,
    900: 0.34,
    950: 0.22,
};

/** Fator de croma de cada tom; 500 = 1.0 e serve de referencia. */
const TONE_CHROMA_FACTOR: Record<PaletteTone, number> = {
    50: 0.18,
    100: 0.3,
    200: 0.5,
    300: 0.72,
    400: 0.88,
    500: 1,
    600: 0.92,
    700: 0.78,
    800: 0.62,
    900: 0.46,
    950: 0.3,
};

/**
 * Abaixo disto a cor e tratada como acromatica: variar a matiz de um cinza
 * produz ruido de conversao, nao cor. O limite e o mesmo usado no resto do
 * nucleo para distinguir matiz real.
 */
const ACHROMATIC_CHROMA = 1e-4;

export interface PaletteToneResult {
    tone: PaletteTone;
    /** sRGB com o alpha da base, sem arredondar. */
    rgba: Rgba;
    /** OKLCH efetivo, depois do gamut mapping. */
    oklch: Oklch;
    /** HEX, ou HEX8 quando a cor tem alpha. */
    hex: string;
    /** O croma pedido nao coube no sRGB e foi reduzido. */
    clipped: boolean;
    /** Contraste de texto preto e branco sobre este tom. */
    contrast: {
        black: ContrastVerdict;
        white: ContrastVerdict;
    };
}

export interface PaletteScale {
    /** A entrada, sem alteracao. */
    base: Rgba;
    baseOklch: Oklch;
    /** Sempre 11, na ordem de `PALETTE_TONES`. */
    tones: PaletteToneResult[];
    nearestTone: PaletteTone;
    baseIsTone500: boolean;
    clippedTones: PaletteTone[];
    algorithmVersion: number;
}

/**
 * Tom cuja luminosidade fixa esta mais perto de `lightness`.
 *
 * Empate fica com o tom mais claro (o primeiro da tabela), porque a tabela ja
 * esta ordenada do claro para o escuro.
 */
export function nearestToneFor(lightness: number): PaletteTone {
    let nearest: PaletteTone = PALETTE_TONES[0];
    let best = Math.abs(TONE_LIGHTNESS[nearest] - lightness);

    for (const tone of PALETTE_TONES) {
        const distance = Math.abs(TONE_LIGHTNESS[tone] - lightness);

        if (distance < best) {
            best = distance;
            nearest = tone;
        }
    }

    return nearest;
}

/**
 * Escala completa a partir de uma cor base.
 *
 * O croma de cada tom e `base.c * fator[tom] / fator[maisProximo]`, entao o
 * tom mais proximo reproduz o croma da base e os extremos o reduzem ou
 * ampliam proporcionalmente. Onde o croma passa do gamut sRGB, o mapeamento
 * corta por croma (preservando L e H) e o tom e marcado como `clipped`.
 */
export function buildPaletteScale(base: Rgba): PaletteScale {
    const baseOklch = rgbToOklch(base);
    const achromatic = baseOklch.c < ACHROMATIC_CHROMA;
    const hue = achromatic ? 0 : normalizeHue(baseOklch.h);
    const nearestTone = nearestToneFor(baseOklch.l);
    const nearestFactor = TONE_CHROMA_FACTOR[nearestTone];

    const tones = PALETTE_TONES.map((tone): PaletteToneResult => {
        const chroma = achromatic
            ? 0
            : (baseOklch.c * TONE_CHROMA_FACTOR[tone]) / nearestFactor;
        const mapped = mapOklchToSrgb({
            l: TONE_LIGHTNESS[tone],
            c: chroma,
            h: hue,
        });
        const rgba: Rgba = { ...mapped.rgb, a: base.a };

        return {
            tone,
            rgba,
            oklch: mapped.oklch,
            hex: rgba.a < 1 ? formatHex8(rgba) : formatHex(rgba),
            clipped: mapped.clipped,
            contrast: {
                black: contrastReport(rgba, WHITE).black,
                white: contrastReport(rgba, BLACK).white,
            },
        };
    });

    const tone500 = tones.find((tone) => tone.tone === 500);

    return {
        base: { ...base },
        baseOklch,
        tones,
        nearestTone,
        baseIsTone500:
            tone500 !== undefined &&
            canonicalKey(base) === canonicalKey(tone500.rgba),
        clippedTones: tones
            .filter((tone) => tone.clipped)
            .map((tone) => tone.tone),
        algorithmVersion: PALETTE_ALGORITHM_VERSION,
    };
}
