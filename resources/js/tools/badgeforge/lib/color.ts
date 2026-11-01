import catalog from '../../../../badgeforge/colors.json';
import type { BadgeColor } from '../types';

/**
 * Espelho de `App\Support\BadgeForge\Color` (ADR-075).
 *
 * O catalogo e o mesmo JSON lido pelo PHP: nomes CSS, aliases de badge, hex
 * 3/4/6/8, rgb(a), hsl(a) e `transparent`. Entrada invalida devolve null e
 * quem chama decide o fallback.
 */

const ALIASES: Record<string, string> = catalog.aliases;
const NAMED: Record<string, string> = catalog.named;

/** Numero com ate 3 decimais e sem zeros a direita ("0.6", "0.125", "1"). */
export function formatNumber(value: number, precision = 3): string {
    return value.toFixed(precision).replace(/0+$/, '').replace(/\.$/, '');
}

export function parseColor(raw: unknown): BadgeColor | null {
    if (typeof raw !== 'string') {
        return null;
    }

    const value = raw.toLowerCase().trim();

    if (value === '') {
        return null;
    }

    if (value === 'transparent') {
        return { hex: '#000000', alpha: 0 };
    }

    if (ALIASES[value] !== undefined) {
        return parseColor(ALIASES[value]);
    }

    if (NAMED[value] !== undefined) {
        return parseColor(NAMED[value]);
    }

    if (value.startsWith('#')) {
        return parseHex(value.slice(1));
    }

    // Hex sem "#" (ex.: color=4c1), como nos badges tradicionais.
    if ([3, 4, 6, 8].includes(value.length) && /^[0-9a-f]+$/.test(value)) {
        return parseHex(value);
    }

    if (value.startsWith('rgb')) {
        return parseRgb(value);
    }

    if (value.startsWith('hsl')) {
        return parseHsl(value);
    }

    return null;
}

export function parseColorOr(raw: unknown, fallback: string): BadgeColor {
    return (
        parseColor(raw) ?? parseColor(fallback) ?? { hex: '#000000', alpha: 1 }
    );
}

/** `fill` e, quando translucido, `fill-opacity`. */
export function fillAttributes(color: BadgeColor): string {
    const attributes = `fill="${color.hex}"`;

    if (color.alpha < 1) {
        return `${attributes} fill-opacity="${formatNumber(color.alpha)}"`;
    }

    return attributes;
}

function parseHex(hex: string): BadgeColor | null {
    if (!/^[0-9a-f]+$/.test(hex) || ![3, 4, 6, 8].includes(hex.length)) {
        return null;
    }

    const expanded =
        hex.length <= 4
            ? hex
                  .split('')
                  .map((char) => char + char)
                  .join('')
            : hex;

    const r = parseInt(expanded.slice(0, 2), 16);
    const g = parseInt(expanded.slice(2, 4), 16);
    const b = parseInt(expanded.slice(4, 6), 16);
    const alpha =
        expanded.length === 8 ? parseInt(expanded.slice(6, 8), 16) / 255 : 1;

    return {
        hex: `#${pad(r)}${pad(g)}${pad(b)}`,
        alpha: roundAlpha(alpha),
    };
}

function parseRgb(value: string): BadgeColor | null {
    const match =
        /^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([0-9.]+%?)\s*)?\)$/.exec(
            value,
        );

    if (match === null) {
        return null;
    }

    const r = Number(match[1]);
    const g = Number(match[2]);
    const b = Number(match[3]);

    if (r > 255 || g > 255 || b > 255) {
        return null;
    }

    const alpha = match[4] !== undefined ? alphaValue(match[4]) : 1;

    if (alpha === null) {
        return null;
    }

    return { hex: `#${pad(r)}${pad(g)}${pad(b)}`, alpha: roundAlpha(alpha) };
}

function parseHsl(value: string): BadgeColor | null {
    const match =
        /^hsla?\(\s*(-?\d{1,4}(?:\.\d+)?)(?:deg)?\s*,\s*(\d{1,3}(?:\.\d+)?)%\s*,\s*(\d{1,3}(?:\.\d+)?)%\s*(?:,\s*([0-9.]+%?)\s*)?\)$/.exec(
            value,
        );

    if (match === null) {
        return null;
    }

    let hue = Number(match[1]) % 360;

    if (hue < 0) {
        hue += 360;
    }

    const saturation = Number(match[2]) / 100;
    const lightness = Number(match[3]) / 100;

    if (saturation > 1 || lightness > 1) {
        return null;
    }

    const alpha = match[4] !== undefined ? alphaValue(match[4]) : 1;

    if (alpha === null) {
        return null;
    }

    const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
    const sector = hue / 60;
    const x = chroma * (1 - Math.abs((sector % 2) - 1));

    let r1 = 0;
    let g1 = 0;
    let b1 = 0;

    if (sector < 1) {
        [r1, g1, b1] = [chroma, x, 0];
    } else if (sector < 2) {
        [r1, g1, b1] = [x, chroma, 0];
    } else if (sector < 3) {
        [r1, g1, b1] = [0, chroma, x];
    } else if (sector < 4) {
        [r1, g1, b1] = [0, x, chroma];
    } else if (sector < 5) {
        [r1, g1, b1] = [x, 0, chroma];
    } else {
        [r1, g1, b1] = [chroma, 0, x];
    }

    const m2 = lightness - chroma / 2;

    return {
        hex: `#${pad(Math.round((r1 + m2) * 255))}${pad(
            Math.round((g1 + m2) * 255),
        )}${pad(Math.round((b1 + m2) * 255))}`,
        alpha: roundAlpha(alpha),
    };
}

function alphaValue(value: string): number | null {
    const alpha = value.endsWith('%')
        ? Number(value.slice(0, -1)) / 100
        : Number(value);

    return alpha >= 0 && alpha <= 1 ? alpha : null;
}

function roundAlpha(value: number): number {
    return Math.round(Math.max(0, Math.min(1, value)) * 1000) / 1000;
}

function pad(value: number): string {
    return value.toString(16).padStart(2, '0');
}
