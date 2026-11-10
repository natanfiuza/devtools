import {
    clamp,
    hslToRgb,
    normalizeHue,
    rgbToHsl,
    rgbToOklch,
} from './convert';
import { canonicalKey, formatHex, formatHex8 } from './format';
import { mapOklchToSrgb } from './gamut';
import type { Hsl, Oklch, Rgba } from './types';

/**
 * Harmonias de cor (ADR-121).
 *
 * A base entra no conjunto e e a unica cor que nao muda: os outros membros
 * giram a matiz em OKLCH (padrao) ou em HSL. O resultado e so matematico -
 * quem decide o que a tela mostra e o componente, e o rotulo vem do i18n.
 */

export type HarmonyKind =
    | 'complementary'
    | 'analogous'
    | 'triadic'
    | 'tetradic'
    | 'monochromatic';

export type HarmonyMethod = 'oklch' | 'hsl';

export const HARMONY_KINDS: readonly HarmonyKind[] = [
    'complementary',
    'analogous',
    'triadic',
    'tetradic',
    'monochromatic',
];

export const HARMONY_METHODS: readonly HarmonyMethod[] = ['oklch', 'hsl'];

/**
 * Angulos de matiz de cada harmonia, em graus, com a base em 0.
 *
 * Tetradica e a **retangular** (0/60/180/240), nao a quadrada (0/90/180/270):
 * a base entra no conjunto e os quatro pontos formam dois pares complementares.
 */
export const HARMONY_HUE_OFFSETS: Record<
    Exclude<HarmonyKind, 'monochromatic'>,
    readonly number[]
> = {
    complementary: [0, 180],
    analogous: [-30, 0, 30],
    triadic: [0, 120, 240],
    tetradic: [0, 60, 180, 240],
};

/** Passos relativos de L da monocromatica, do mais escuro ao mais claro. */
const MONOCHROMATIC_OKLCH_STEPS = [-0.28, -0.14, 0, 0.14, 0.28];
const MONOCHROMATIC_HSL_STEPS = [-28, -14, 0, 14, 28];

/** Faixa de L em que a monocromatica OKLCH fica: nem preto, nem branco. */
const OKLCH_LIGHTNESS = { min: 0.08, max: 0.99 };

export interface HarmonyColor {
    rgba: Rgba;
    /** HEX, ou HEX8 quando a base tem alpha (herdado). */
    hex: string;
    oklch: Oklch;
    /** O croma foi reduzido para caber no sRGB (metodo OKLCH). */
    clipped: boolean;
    isBase: boolean;
}

export interface HarmonySet {
    kind: HarmonyKind;
    method: HarmonyMethod;
    base: Rgba;
    /** Quantas cores a definicao pede antes do dedupe. */
    expected: number;
    /**
     * Cores unicas na ordem da definicao (a monocromatica vai do escuro ao
     * claro). Cinzas e clamp de L podem reduzir o conjunto: a tela compara com
     * `expected` e avisa.
     */
    colors: HarmonyColor[];
}

function toHarmonyColor(
    rgba: Rgba,
    oklch: Oklch,
    clipped: boolean,
    baseKey: string,
): HarmonyColor {
    return {
        rgba,
        hex: rgba.a < 1 ? formatHex8(rgba) : formatHex(rgba),
        oklch,
        clipped,
        isBase: canonicalKey(rgba) === baseKey,
    };
}

/** Uma cor de OKLCH: matiz girado, L e C da base, gamut por reducao de croma. */
function oklchColor(
    base: Rgba,
    baseOklch: Oklch,
    lightness: number,
    hueOffset: number,
    baseKey: string,
): HarmonyColor {
    const mapped = mapOklchToSrgb({
        l: lightness,
        c: baseOklch.c,
        h: normalizeHue(baseOklch.h + hueOffset),
    });

    return toHarmonyColor(
        { ...mapped.rgb, a: base.a },
        mapped.oklch,
        mapped.clipped,
        baseKey,
    );
}

/** Uma cor de HSL: matiz girado (ou L trocado), H/S/L da base. */
function hslColor(
    base: Rgba,
    baseHsl: Hsl,
    lightness: number,
    hueOffset: number,
    baseKey: string,
): HarmonyColor {
    const rgb = hslToRgb({
        h: normalizeHue(baseHsl.h + hueOffset),
        s: baseHsl.s,
        l: lightness,
    });

    return toHarmonyColor({ ...rgb, a: base.a }, rgbToOklch(rgb), false, baseKey);
}

/** Remove repetidas pela chave canonica, preservando a primeira ocorrencia. */
function uniqueColors(colors: HarmonyColor[]): HarmonyColor[] {
    const seen = new Set<string>();

    return colors.filter((color) => {
        const key = canonicalKey(color.rgba);

        if (seen.has(key)) {
            return false;
        }

        seen.add(key);

        return true;
    });
}

export function buildHarmony(
    base: Rgba,
    kind: HarmonyKind,
    method: HarmonyMethod,
): HarmonySet {
    const baseKey = canonicalKey(base);

    if (kind === 'monochromatic') {
        const colors =
            method === 'oklch'
                ? buildMonochromaticOklch(base, baseKey)
                : buildMonochromaticHsl(base, baseKey);

        return {
            kind,
            method,
            base,
            expected: MONOCHROMATIC_OKLCH_STEPS.length,
            colors: uniqueColors(colors),
        };
    }

    const offsets = HARMONY_HUE_OFFSETS[kind];
    let colors: HarmonyColor[];

    if (method === 'oklch') {
        const baseOklch = rgbToOklch(base);

        colors = offsets.map((offset) =>
            oklchColor(base, baseOklch, baseOklch.l, offset, baseKey),
        );
    } else {
        const baseHsl = rgbToHsl(base);

        colors = offsets.map((offset) =>
            hslColor(base, baseHsl, baseHsl.l, offset, baseKey),
        );
    }

    return {
        kind,
        method,
        base,
        expected: offsets.length,
        colors: uniqueColors(colors),
    };
}

function buildMonochromaticOklch(base: Rgba, baseKey: string): HarmonyColor[] {
    const baseOklch = rgbToOklch(base);

    return MONOCHROMATIC_OKLCH_STEPS.map((step) =>
        oklchColor(
            base,
            baseOklch,
            clamp(
                baseOklch.l + step,
                OKLCH_LIGHTNESS.min,
                OKLCH_LIGHTNESS.max,
            ),
            0,
            baseKey,
        ),
    );
}

function buildMonochromaticHsl(base: Rgba, baseKey: string): HarmonyColor[] {
    const baseHsl = rgbToHsl(base);

    return MONOCHROMATIC_HSL_STEPS.map((step) =>
        hslColor(
            base,
            baseHsl,
            clamp(baseHsl.l + step, 0, 100),
            0,
            baseKey,
        ),
    );
}
