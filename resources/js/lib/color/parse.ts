import { clamp, hslToRgb, normalizeHue } from './convert';
import { isOklchInSrgbGamut, mapOklabToSrgb, mapOklchToSrgb } from './gamut';
import { namedColors } from './named';
import type {
    ColorSyntax,
    Oklab,
    Oklch,
    ParseErrorCode,
    ParseResult,
    Rgba,
} from './types';

/**
 * Parser de cor no subconjunto pratico do CSS Color 4 (ADR-117).
 *
 * Aceita HEX de 3/4/6/8 digitos (com ou sem `#`), `rgb()`/`rgba()`,
 * `hsl()`/`hsla()` nas sintaxes com virgula e moderna, `oklch()`, `oklab()`,
 * os nomes do CSS e `transparent`. Erros sao tipados - nunca `NaN` silencioso.
 */

const FUNCTION_PATTERN = /^([a-z]+)\((.*)\)$/i;

function fail(code: ParseErrorCode): ParseResult {
    return { ok: false, code };
}

function color(
    rgba: Rgba,
    syntax: ColorSyntax,
    sourceValue: string | null = null,
    outOfGamut = false,
): ParseResult {
    return {
        ok: true,
        color: {
            rgba: { ...rgba, a: clamp(rgba.a, 0, 1) },
            syntax,
            sourceValue,
            outOfGamut,
        },
    };
}

interface Token {
    value: number;
    /** `%`, `deg` ou vazio, ja com angulos normalizados para graus. */
    unit: '' | '%' | 'deg';
}

function readToken(raw: string): Token | null {
    const match =
        /^([+-]?(?:\d+\.?\d*|\.\d+)(?:e[+-]?\d+)?)(%|deg|rad|turn|grad)?$/i.exec(
            raw,
        );

    if (match === null) {
        return null;
    }

    const rawUnit = (match[2] ?? '').toLowerCase();
    let value = Number(match[1]);

    if (rawUnit === 'rad') {
        value = (value * 180) / Math.PI;
    } else if (rawUnit === 'turn') {
        value = value * 360;
    } else if (rawUnit === 'grad') {
        value = value * 0.9;
    }

    const unit = rawUnit === '' ? '' : rawUnit === '%' ? '%' : ('deg' as const);

    return { value, unit };
}

/** Um canal sRGB: numero 0..255 ou porcentagem 0..100%. */
function readChannel(raw: string): number | null {
    const token = readToken(raw);

    if (token === null || token.unit === 'deg') {
        return null;
    }

    if (token.unit === '%') {
        return token.value >= 0 && token.value <= 100
            ? (token.value / 100) * 255
            : null;
    }

    return token.value >= 0 && token.value <= 255 ? token.value : null;
}

/** Alpha em 0..1, aceitando porcentagem. */
function readAlpha(raw: string): number | null {
    const token = readToken(raw);

    if (token === null) {
        return null;
    }

    if (token.unit === '%') {
        return token.value >= 0 && token.value <= 100
            ? token.value / 100
            : null;
    }

    if (token.unit === 'deg') {
        return null;
    }

    return token.value >= 0 && token.value <= 1 ? token.value : null;
}

interface Arguments {
    values: string[];
    alpha: string | null;
}

/** Distingue "nao e numero" de "numero fora do intervalo" para o erro. */
function invalidCodeFor(raw: string): ParseErrorCode {
    return readToken(raw) === null ? 'syntax' : 'range';
}

/**
 * Separa os argumentos de uma funcao de cor: alpha depois de `/` (moderno) ou
 * como quarto argumento (legado com virgula). Devolve `null` se a estrutura
 * estiver quebrada.
 */
function splitArguments(body: string): Arguments | null {
    const slashParts = body.split('/');

    if (slashParts.length > 2) {
        return null;
    }

    const alpha = slashParts.length === 2 ? (slashParts[1] ?? '').trim() : null;
    const head = (slashParts[0] ?? '').trim();

    if (head === '' || (alpha !== null && alpha === '')) {
        return null;
    }

    const comma = head.includes(',');

    if (alpha !== null && comma) {
        return null;
    }

    const values = comma
        ? head.split(',').map((part) => part.trim())
        : head.split(/\s+/);

    if (values.some((value) => value === '')) {
        return null;
    }

    return { values, alpha };
}

function hexToRgba(source: string): Rgba | null {
    const digits = source.replace(/^#/, '').toLowerCase();

    if (!/^[0-9a-f]+$/.test(digits)) {
        return null;
    }

    const expand = (part: string): number => Number.parseInt(part, 16);

    let r: number;
    let g: number;
    let b: number;
    let a = 1;

    if (digits.length === 3 || digits.length === 4) {
        const first = digits.charAt(0);
        const second = digits.charAt(1);
        const third = digits.charAt(2);

        r = expand(first + first);
        g = expand(second + second);
        b = expand(third + third);

        if (digits.length === 4) {
            const fourth = digits.charAt(3);
            a = expand(fourth + fourth) / 255;
        }
    } else if (digits.length === 6 || digits.length === 8) {
        r = expand(digits.slice(0, 2));
        g = expand(digits.slice(2, 4));
        b = expand(digits.slice(4, 6));

        if (digits.length === 8) {
            a = expand(digits.slice(6, 8)) / 255;
        }
    } else {
        return null;
    }

    return { r, g, b, a };
}

function parseHex(source: string): ParseResult {
    const rgba = hexToRgba(source);

    return rgba === null ? fail('syntax') : color(rgba, 'hex');
}

function parseRgb(body: string): ParseResult {
    const args = splitArguments(body);

    if (args === null) {
        return fail('syntax');
    }

    const values = [...args.values];
    let alpha = args.alpha;

    if (values.length === 4 && alpha === null) {
        alpha = values.pop() ?? null;
    }

    if (values.length !== 3) {
        return fail('syntax');
    }

    const channels = values.map(readChannel);

    if (channels.some((channel) => channel === null)) {
        return fail(
            values.some((value) => readToken(value) === null)
                ? 'syntax'
                : 'range',
        );
    }

    const a = alpha === null ? 1 : readAlpha(alpha);

    if (a === null) {
        return fail(alpha === null ? 'range' : invalidCodeFor(alpha));
    }

    return color(
        {
            r: channels[0] as number,
            g: channels[1] as number,
            b: channels[2] as number,
            a,
        },
        'rgb',
    );
}

function parseHsl(body: string): ParseResult {
    const args = splitArguments(body);

    if (args === null) {
        return fail('syntax');
    }

    const values = [...args.values];
    let alpha = args.alpha;

    if (values.length === 4 && alpha === null) {
        alpha = values.pop() ?? null;
    }

    if (values.length !== 3) {
        return fail('syntax');
    }

    const [rawHue = '', rawSaturation = '', rawLightness = ''] = values;
    const hueToken = readToken(rawHue);
    const saturation = readPercentage(rawSaturation);
    const lightness = readPercentage(rawLightness);

    if (hueToken === null || hueToken.unit === '%') {
        return fail('syntax');
    }

    if (saturation === null) {
        return fail(invalidCodeFor(rawSaturation));
    }

    if (lightness === null) {
        return fail(invalidCodeFor(rawLightness));
    }

    const a = alpha === null ? 1 : readAlpha(alpha);

    if (a === null) {
        return fail(alpha === null ? 'range' : invalidCodeFor(alpha));
    }

    const rgb = hslToRgb({
        h: normalizeHue(hueToken.value),
        s: clamp(saturation, 0, 100),
        l: clamp(lightness, 0, 100),
    });

    return color({ ...rgb, a }, 'hsl');
}

/** Porcentagem 0..100%, ou numero nu lido como porcentagem (compatibilidade). */
function readPercentage(raw: string): number | null {
    const token = readToken(raw);

    if (token === null) {
        return null;
    }

    if (token.unit === 'deg') {
        return null;
    }

    return token.value >= 0 && token.value <= 100 ? token.value : null;
}

/** Luminosidade OKLab/OKLCH: numero 0..1 ou porcentagem de 0..100%. */
function readLightness(raw: string): number | null {
    const token = readToken(raw);

    if (token === null || token.unit === 'deg') {
        return null;
    }

    const value = token.unit === '%' ? token.value / 100 : token.value;

    return value >= 0 && value <= 1 ? value : null;
}

function readChroma(raw: string): number | null {
    const token = readToken(raw);

    if (token === null || token.unit === 'deg') {
        return null;
    }

    const value = token.unit === '%' ? token.value * 0.004 : token.value;

    return value >= 0 ? value : null;
}

function readAxis(raw: string): number | null {
    const token = readToken(raw);

    if (token === null || token.unit === 'deg') {
        return null;
    }

    const value = token.unit === '%' ? (token.value / 100) * 0.4 : token.value;

    return Math.abs(value) <= 1 ? value : null;
}

function parseOklch(body: string, source: string): ParseResult {
    const args = splitArguments(body);

    if (args === null) {
        return fail('syntax');
    }

    if (args.values.length !== 3) {
        return fail('syntax');
    }

    const [rawLightness = '', rawChroma = '', rawHue = ''] = args.values;
    const lightness = readLightness(rawLightness);
    const chroma = readChroma(rawChroma);
    const hueToken = readToken(rawHue);

    if (lightness === null) {
        return fail(invalidCodeFor(rawLightness));
    }

    if (chroma === null) {
        return fail(invalidCodeFor(rawChroma));
    }

    if (hueToken === null || hueToken.unit === '%') {
        return fail('syntax');
    }

    const a = args.alpha === null ? 1 : readAlpha(args.alpha);

    if (a === null) {
        return fail(args.alpha === null ? 'range' : invalidCodeFor(args.alpha));
    }

    const oklch: Oklch = {
        l: lightness,
        c: chroma,
        h: normalizeHue(hueToken.value),
    };
    const mapped = mapOklchToSrgb(oklch);
    const clipped = !isOklchInSrgbGamut(oklch) || mapped.clipped;

    return color(
        { ...mapped.rgb, a },
        'oklch',
        clipped ? source : null,
        clipped,
    );
}

function parseOklab(body: string, source: string): ParseResult {
    const args = splitArguments(body);

    if (args === null) {
        return fail('syntax');
    }

    if (args.values.length !== 3) {
        return fail('syntax');
    }

    const [rawLightness = '', rawAxisA = '', rawAxisB = ''] = args.values;
    const lightness = readLightness(rawLightness);
    const axisA = readAxis(rawAxisA);
    const axisB = readAxis(rawAxisB);

    if (lightness === null) {
        return fail(invalidCodeFor(rawLightness));
    }

    if (axisA === null) {
        return fail(invalidCodeFor(rawAxisA));
    }

    if (axisB === null) {
        return fail(invalidCodeFor(rawAxisB));
    }

    const a = args.alpha === null ? 1 : readAlpha(args.alpha);

    if (a === null) {
        return fail(args.alpha === null ? 'range' : invalidCodeFor(args.alpha));
    }

    const oklab: Oklab = { l: lightness, a: axisA, b: axisB };
    const mapped = mapOklabToSrgb(oklab);
    const clipped = mapped.clipped;

    return color(
        { ...mapped.rgb, a },
        'oklab',
        clipped ? source : null,
        clipped,
    );
}

export function parseColor(input: string): ParseResult {
    const source = input.trim();

    if (source === '') {
        return fail('empty');
    }

    const lower = source.toLowerCase();

    if (lower === 'transparent') {
        return color({ r: 0, g: 0, b: 0, a: 0 }, 'name');
    }

    const named = namedColors[lower];

    if (named !== undefined) {
        const rgba = hexToRgba(named);

        if (rgba !== null) {
            return color(rgba, 'name');
        }
    }

    if (source.startsWith('#')) {
        return parseHex(source);
    }

    const call = FUNCTION_PATTERN.exec(source);

    if (call !== null) {
        const name = (call[1] ?? '').toLowerCase();
        const body = call[2] ?? '';

        switch (name) {
            case 'rgb':
            case 'rgba':
                return parseRgb(body);
            case 'hsl':
            case 'hsla':
                return parseHsl(body);
            case 'oklch':
                return parseOklch(body, source);
            case 'oklab':
                return parseOklab(body, source);
            default:
                return fail('syntax');
        }
    }

    if (/^[0-9a-f]{3,4}$|^[0-9a-f]{6}$|^[0-9a-f]{8}$/i.test(source)) {
        return parseHex(source);
    }

    if (/^[a-z]+$/i.test(source)) {
        return fail('unknown_name');
    }

    return fail('syntax');
}
