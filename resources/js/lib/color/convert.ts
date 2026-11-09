import type { Hsl, Hsv, LinearRgb, Oklab, Oklch, Rgb, Rgba } from './types';

/**
 * Conversoes de cor, sem dependencia (ADR-117).
 *
 * sRGB <-> linear segue a especificacao CSS Color 4; OKLab/OKLCH usa as
 * matrizes de Bjorn Ottosson. Nada aqui arredonda: o arredondamento e so da
 * exibicao (`format.ts`).
 */

export function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

/** Normaliza o matiz para 0..360, aceitando negativos e voltas inteiras. */
export function normalizeHue(degrees: number): number {
    const hue = degrees % 360;

    if (hue < 0) {
        return hue + 360;
    }

    // `-0` e `0` sao iguais por `===`, mas nao por `Object.is`; normaliza.
    return hue === 0 ? 0 : hue;
}

/** Um canal sRGB 0..255 em linear 0..1 (a curva do WCAG). */
export function srgbChannelToLinear(channel: number): number {
    const value = channel / 255;

    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
}

/** Um canal linear 0..1 de volta para sRGB 0..255, sem arredondar. */
export function linearChannelToSrgb(channel: number): number {
    const value =
        channel <= 0.0031308
            ? channel * 12.92
            : 1.055 * channel ** (1 / 2.4) - 0.055;

    return value * 255;
}

export function rgbToLinear(rgb: Rgb): LinearRgb {
    return {
        r: srgbChannelToLinear(rgb.r),
        g: srgbChannelToLinear(rgb.g),
        b: srgbChannelToLinear(rgb.b),
    };
}

export function linearToRgb(linear: LinearRgb): Rgb {
    return {
        r: linearChannelToSrgb(linear.r),
        g: linearChannelToSrgb(linear.g),
        b: linearChannelToSrgb(linear.b),
    };
}

/** Linear 0..1 para 0..255 e de volta ao gamut, para exibir. */
export function linearToRgbClamped(linear: LinearRgb): Rgb {
    const rgb = linearToRgb(linear);

    return {
        r: clamp(rgb.r, 0, 255),
        g: clamp(rgb.g, 0, 255),
        b: clamp(rgb.b, 0, 255),
    };
}

/* ---------------------------------------------------------------- HSL/HSV */

export function rgbToHsl(rgb: Rgb): Hsl {
    const r = rgb.r / 255;
    const g = rgb.g / 255;
    const b = rgb.b / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;
    const lightness = (max + min) / 2;

    if (delta === 0) {
        return { h: 0, s: 0, l: lightness * 100 };
    }

    const saturation = delta / (1 - Math.abs(2 * lightness - 1));

    let hue: number;

    if (max === r) {
        hue = ((g - b) / delta) % 6;
    } else if (max === g) {
        hue = (b - r) / delta + 2;
    } else {
        hue = (r - g) / delta + 4;
    }

    return {
        h: normalizeHue(hue * 60),
        s: saturation * 100,
        l: lightness * 100,
    };
}

export function hslToRgb(hsl: Hsl): Rgb {
    const hue = normalizeHue(hsl.h);
    const saturation = clamp(hsl.s, 0, 100) / 100;
    const lightness = clamp(hsl.l, 0, 100) / 100;

    if (saturation === 0) {
        const channel = Math.round(lightness * 255);

        return { r: channel, g: channel, b: channel };
    }

    const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
    const x = chroma * (1 - Math.abs(((hue / 60) % 2) - 1));
    const m = lightness - chroma / 2;

    let channel: [number, number, number];

    if (hue < 60) {
        channel = [chroma, x, 0];
    } else if (hue < 120) {
        channel = [x, chroma, 0];
    } else if (hue < 180) {
        channel = [0, chroma, x];
    } else if (hue < 240) {
        channel = [0, x, chroma];
    } else if (hue < 300) {
        channel = [x, 0, chroma];
    } else {
        channel = [chroma, 0, x];
    }

    return {
        r: Math.round((channel[0] + m) * 255),
        g: Math.round((channel[1] + m) * 255),
        b: Math.round((channel[2] + m) * 255),
    };
}

export function rgbToHsv(rgb: Rgb): Hsv {
    const max = Math.max(rgb.r, rgb.g, rgb.b);
    const min = Math.min(rgb.r, rgb.g, rgb.b);
    const delta = max - min;
    const value = max / 255;

    if (delta === 0) {
        return { h: 0, s: 0, v: value * 100 };
    }

    const saturation = delta / max;

    let hue: number;

    if (max === rgb.r) {
        hue = ((rgb.g - rgb.b) / delta) % 6;
    } else if (max === rgb.g) {
        hue = (rgb.b - rgb.r) / delta + 2;
    } else {
        hue = (rgb.r - rgb.g) / delta + 4;
    }

    return {
        h: normalizeHue(hue * 60),
        s: saturation * 100,
        v: value * 100,
    };
}

export function hsvToRgb(hsv: Hsv): Rgb {
    const hue = normalizeHue(hsv.h);
    const saturation = clamp(hsv.s, 0, 100) / 100;
    const value = clamp(hsv.v, 0, 100) / 100;

    if (saturation === 0) {
        const channel = Math.round(value * 255);

        return { r: channel, g: channel, b: channel };
    }

    const chroma = value * saturation;
    const x = chroma * (1 - Math.abs(((hue / 60) % 2) - 1));
    const m = value - chroma;

    let channel: [number, number, number];

    if (hue < 60) {
        channel = [chroma, x, 0];
    } else if (hue < 120) {
        channel = [x, chroma, 0];
    } else if (hue < 180) {
        channel = [0, chroma, x];
    } else if (hue < 240) {
        channel = [0, x, chroma];
    } else if (hue < 300) {
        channel = [x, 0, chroma];
    } else {
        channel = [chroma, 0, x];
    }

    return {
        r: Math.round((channel[0] + m) * 255),
        g: Math.round((channel[1] + m) * 255),
        b: Math.round((channel[2] + m) * 255),
    };
}

/* ----------------------------------------------------------------- OKLab */

/** sRGB 0..255 -> OKLab (matrizes de Bjorn Ottosson). */
export function linearRgbToOklab(linear: LinearRgb): Oklab {
    const l = Math.cbrt(
        0.4122214708 * linear.r +
            0.5363325363 * linear.g +
            0.0514459929 * linear.b,
    );
    const m = Math.cbrt(
        0.2119034982 * linear.r +
            0.6806995451 * linear.g +
            0.1073969566 * linear.b,
    );
    const s = Math.cbrt(
        0.0883024619 * linear.r +
            0.2817188376 * linear.g +
            0.6299787005 * linear.b,
    );

    return {
        l: 0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s,
        a: 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s,
        b: 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s,
    };
}

/** OKLab -> linear sRGB, **sem** prender ao gamut: quem decide e `gamut.ts`. */
export function oklabToLinearRgb(oklab: Oklab): LinearRgb {
    const l = (oklab.l + 0.3963377774 * oklab.a + 0.2158037573 * oklab.b) ** 3;
    const m = (oklab.l - 0.1055613458 * oklab.a - 0.0638541728 * oklab.b) ** 3;
    const s = (oklab.l - 0.0894841775 * oklab.a - 1.291485548 * oklab.b) ** 3;

    return {
        r: 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        g: -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        b: -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s,
    };
}

export function rgbToOklab(rgb: Rgb): Oklab {
    return linearRgbToOklab(rgbToLinear(rgb));
}

/** OKLab -> sRGB preso ao gamut, para exibir/gravar. */
export function oklabToRgb(oklab: Oklab): Rgb {
    return linearToRgbClamped(oklabToLinearRgb(oklab));
}

/** OKLab -> OKLCH. Abaixo de 1e-7 o croma e ruido de ponto flutuante: cinza. */
export function oklabToOklch(oklab: Oklab): Oklch {
    const chroma = Math.sqrt(oklab.a ** 2 + oklab.b ** 2);

    if (chroma < 1e-7) {
        return { l: oklab.l, c: 0, h: 0 };
    }

    return {
        l: oklab.l,
        c: chroma,
        h: normalizeHue((Math.atan2(oklab.b, oklab.a) * 180) / Math.PI),
    };
}

/** OKLCH -> OKLab. */
export function oklchToOklab(oklch: Oklch): Oklab {
    const radians = (normalizeHue(oklch.h) * Math.PI) / 180;

    return {
        l: oklch.l,
        a: oklch.c * Math.cos(radians),
        b: oklch.c * Math.sin(radians),
    };
}

export function rgbToOklch(rgb: Rgb): Oklch {
    return oklabToOklch(rgbToOklab(rgb));
}

export function oklchToRgb(oklch: Oklch): Rgb {
    return oklabToRgb(oklchToOklab(oklch));
}

/** Copia sRGB com alpha preso a 0..1. */
export function withAlpha(rgb: Rgb, alpha: number): Rgba {
    return { r: rgb.r, g: rgb.g, b: rgb.b, a: clamp(alpha, 0, 1) };
}
