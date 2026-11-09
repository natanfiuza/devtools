import { clamp, rgbToHsl, rgbToOklab, rgbToOklch } from './convert';
import { nearestNamedColor } from './named';
import type { ColorFormat, Rgb, Rgba } from './types';

/**
 * Formatacao para exibir/copiar (ADR-117).
 *
 * Arredondar aqui **nunca** contamina o estado nem a classificacao WCAG: a
 * cor autoritativa segue com os canais que vieram do parser.
 */

const FORMATS: readonly ColorFormat[] = [
    'hex',
    'hex8',
    'rgb',
    'rgba',
    'hsl',
    'hsla',
    'oklch',
    'oklab',
    'name',
];

function round(value: number, decimals: number): number {
    const factor = 10 ** decimals;

    return Math.round(value * factor) / factor;
}

/** Numero com no maximo `decimals` casas, sem zeros a direita. */
function trim(value: number, decimals: number): string {
    const text = round(value, decimals).toFixed(decimals);

    if (!text.includes('.')) {
        return text;
    }

    return text.replace(/0+$/, '').replace(/\.$/, '');
}

function roundedRgb(color: Rgb): Rgb {
    return {
        r: Math.round(clamp(color.r, 0, 255)),
        g: Math.round(clamp(color.g, 0, 255)),
        b: Math.round(clamp(color.b, 0, 255)),
    };
}

function hexChannel(value: number): string {
    return Math.round(clamp(value, 0, 255))
        .toString(16)
        .padStart(2, '0');
}

export function formatHex(color: Rgba): string {
    const rgb = roundedRgb(color);

    return `#${hexChannel(rgb.r)}${hexChannel(rgb.g)}${hexChannel(rgb.b)}`;
}

export function formatHex8(color: Rgba): string {
    return `${formatHex(color)}${hexChannel(clamp(color.a, 0, 1) * 255)}`;
}

function alphaText(alpha: number): string {
    return trim(clamp(alpha, 0, 1), 3);
}

export function formatColor(color: Rgba, format: ColorFormat): string {
    const rgb = roundedRgb(color);

    switch (format) {
        case 'hex':
            return formatHex(color);
        case 'hex8':
            return formatHex8(color);
        case 'rgb':
            return `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
        case 'rgba':
            return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alphaText(color.a)})`;
        case 'hsl': {
            const hsl = rgbToHsl(rgb);

            return `hsl(${Math.round(hsl.h)}, ${Math.round(hsl.s)}%, ${Math.round(hsl.l)}%)`;
        }
        case 'hsla': {
            const hsl = rgbToHsl(rgb);

            return `hsla(${Math.round(hsl.h)}, ${Math.round(hsl.s)}%, ${Math.round(hsl.l)}%, ${alphaText(color.a)})`;
        }
        case 'oklch': {
            const oklch = rgbToOklch(rgb);

            return `oklch(${trim(oklch.l * 100, 2)}% ${trim(oklch.c, 4)} ${trim(oklch.h, 2)})`;
        }
        case 'oklab': {
            const oklab = rgbToOklab(rgb);

            return `oklab(${trim(oklab.l, 4)} ${trim(oklab.a, 4)} ${trim(oklab.b, 4)})`;
        }
        case 'name':
            return nearestNamedColor(color);
    }
}

export function formatAll(
    color: Rgba,
): { format: ColorFormat; value: string }[] {
    return FORMATS.map((format) => ({
        format,
        value: formatColor(color, format),
    }));
}

/**
 * Chave de deduplicacao de historico/favoritos.
 *
 * Sempre 8 digitos para que `#ff0000` e `#ff0000ff` sejam a mesma cor, e
 * alpha 0 vira `transparent` porque a cor de base nao e visivel.
 */
export function canonicalKey(color: Rgba): string {
    if (Math.round(clamp(color.a, 0, 1) * 255) === 0) {
        return 'transparent';
    }

    return formatHex8(color);
}
