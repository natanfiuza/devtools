import {
    clamp,
    linearToRgbClamped,
    normalizeHue,
    oklabToLinearRgb,
    oklabToOklch,
    oklchToOklab,
} from './convert';
import type { GamutResult, LinearRgb, Oklab, Oklch, Rgb } from './types';

/**
 * Gamut sRGB (ADR-117).
 *
 * OKLCH/OKLab descrevem cores que o sRGB nao tem. Em vez de prender cada
 * canal (o que muda L e H), a cor e trazida para dentro reduzindo o croma por
 * busca binaria: a luminosidade e o matiz percebidos ficam, so a saturacao
 * cede. O resultado carrega `clipped` para a tela avisar.
 */

/**
 * Tolerancia em linear 0..1. Cobre o ruido de arredondamento de OKLCH digitado
 * (ex.: `oklch(62.8% 0.2577 29.23)` fica 2e-4 acima do vermelho exato) sem
 * aceitar cor visivelmente fora do gamut.
 */
const EPSILON = 1e-3;

function inGamut(linear: LinearRgb): boolean {
    return (
        linear.r >= -EPSILON &&
        linear.r <= 1 + EPSILON &&
        linear.g >= -EPSILON &&
        linear.g <= 1 + EPSILON &&
        linear.b >= -EPSILON &&
        linear.b <= 1 + EPSILON
    );
}

export function isOklabInSrgbGamut(oklab: Oklab): boolean {
    return inGamut(oklabToLinearRgb(oklab));
}

export function isOklchInSrgbGamut(oklch: Oklch): boolean {
    return isOklabInSrgbGamut(oklchToOklab(oklch));
}

/**
 * OKLCH dentro do gamut sRGB, preservando L e H.
 *
 * Busca binaria de 24 iteracoes (precisao muito acima do que a tela mostra);
 * se ate o croma 0 nao couber, devolve o cinza daquele L - o que so acontece
 * com L fora de 0..1.
 */
export function mapOklchToSrgb(oklch: Oklch): GamutResult {
    const lightness = clamp(oklch.l, 0, 1);
    const hue = normalizeHue(oklch.h);
    const chroma = Math.max(0, oklch.c);
    const lightnessClamped = lightness !== oklch.l;

    if (isOklchInSrgbGamut({ l: lightness, c: chroma, h: hue })) {
        return {
            rgb: oklchToRgbInGamut({ l: lightness, c: chroma, h: hue }),
            oklch: { l: lightness, c: chroma, h: hue },
            clipped: lightnessClamped || chroma !== oklch.c,
        };
    }

    let low = 0;
    let high = chroma;

    for (let index = 0; index < 24; index += 1) {
        const middle = (low + high) / 2;

        if (isOklchInSrgbGamut({ l: lightness, c: middle, h: hue })) {
            low = middle;
        } else {
            high = middle;
        }
    }

    const mapped: Oklch = { l: lightness, c: low, h: hue };

    return {
        rgb: oklchToRgbInGamut(mapped),
        oklch: mapped,
        clipped: true,
    };
}

/** OKLab fora do gamut -> OKLCH com croma reduzido. */
export function mapOklabToSrgb(oklab: Oklab): GamutResult {
    return mapOklchToSrgb(oklabToOklch(oklab));
}

/**
 * Converte um OKLCH **ja dentro** do gamut para sRGB, sem perder o canal por
 * arredondamento: o linear e cortado apenas no ruido de ponto flutuante.
 */
function oklchToRgbInGamut(oklch: Oklch): Rgb {
    const linear = oklabToLinearRgb(oklchToOklab(oklch));
    const safe: LinearRgb = {
        r: clamp(linear.r, 0, 1),
        g: clamp(linear.g, 0, 1),
        b: clamp(linear.b, 0, 1),
    };

    return linearToRgbClamped(safe);
}
