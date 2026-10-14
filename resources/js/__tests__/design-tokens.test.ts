import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Confere o contraste WCAG dos tokens do DESIGN.md nos dois temas.
 * O CSS e a fonte da verdade: os valores sao lidos de resources/css/app.css.
 */

const css = readFileSync(
    resolve(process.cwd(), 'resources/css/app.css'),
    'utf8',
);

type Palette = Record<string, string>;

function block(selector: string): Palette {
    // Casa o seletor no inicio da linha: ":root" e ".dark" tambem aparecem
    // dentro de outras regras (ex.: o custom-variant dark).
    const found = new RegExp(`^${selector}\\s*\\{`, 'm').exec(css);

    if (found === null) {
        throw new Error(`Bloco ${selector} nao encontrado no app.css`);
    }

    const start = found.index;

    const open = css.indexOf('{', start);
    const close = css.indexOf('}', open);
    const body = css.slice(open + 1, close);

    const palette: Palette = {};

    for (const line of body.split('\n')) {
        const match = /^\s*(--[\w-]+):\s*([^;]+);/.exec(line);

        if (match?.[1] !== undefined && match[2] !== undefined) {
            palette[match[1]] = match[2].trim();
        }
    }

    return palette;
}

type Rgb = [number, number, number];

function parseColor(value: string, palette: Palette, seen = 0): Rgb {
    const trimmed = value.trim();

    if (seen > 10) {
        throw new Error(`Referencia circular em ${value}`);
    }

    if (trimmed.startsWith('var(')) {
        const name = trimmed.slice(4, trimmed.indexOf(')'));

        return parseColor(palette[name] ?? '', palette, seen + 1);
    }

    const hex = /^#([0-9a-f]{6})$/i.exec(trimmed);

    if (hex?.[1] !== undefined) {
        const int = parseInt(hex[1], 16);

        return [(int >> 16) & 255, (int >> 8) & 255, int & 255];
    }

    const rgba =
        /^rgb\(\s*(\d+)\s+(\d+)\s+(\d+)\s*\/\s*([\d.]+)%?\s*\)$/.exec(trimmed);

    if (rgba !== null) {
        return [
            Number(rgba[1]),
            Number(rgba[2]),
            Number(rgba[3]),
        ];
    }

    throw new Error(`Cor nao reconhecida: ${value}`);
}

function alpha(value: string, palette: Palette, seen = 0): number {
    const trimmed = value.trim();

    if (trimmed.startsWith('var(')) {
        const name = trimmed.slice(4, trimmed.indexOf(')'));

        return alpha(palette[name] ?? '', palette, seen + 1);
    }

    const match = /\/\s*([\d.]+)%\s*\)$/.exec(trimmed);

    if (match?.[1] === undefined) {
        return 1;
    }

    return Number(match[1]) / 100;
}

function relativeLuminance([r, g, b]: Rgb): number {
    const channel = (value: number): number => {
        const v = value / 255;

        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

function contrast(foreground: Rgb, background: Rgb): number {
    const a = relativeLuminance(foreground);
    const b = relativeLuminance(background);

    const [light, dark] = a > b ? [a, b] : [b, a];

    return (light + 0.05) / (dark + 0.05);
}

/** Compõe uma cor com alpha sobre outra (o CSS usa rgb(... / 12%) nas bordas). */
function over(foreground: string, background: string, palette: Palette): Rgb {
    const fg = parseColor(foreground, palette);
    const bg = parseColor(background, palette);
    const a = alpha(foreground, palette);

    return [
        fg[0] * a + bg[0] * (1 - a),
        fg[1] * a + bg[1] * (1 - a),
        fg[2] * a + bg[2] * (1 - a),
    ];
}

/** Texto sobre superficie: o pior caso entre background e surface. */
const TEXT_PAIRS: Array<[string, string]> = [
    ['--foreground', '--background'],
    ['--foreground', '--surface'],
    ['--muted-foreground', '--background'],
    ['--muted-foreground', '--surface'],
    ['--subtle', '--surface'],
    ['--accent-2', '--surface'],
    ['--brand', '--background'],
    ['--brand', '--surface'],
    ['--primary-foreground', '--primary'],
    ['--destructive', '--surface'],
    ['--destructive-foreground', '--destructive'],
    ['--json-key', '--surface'],
    ['--json-string', '--surface'],
    ['--json-number', '--surface'],
    ['--json-literal', '--surface'],
    ['--json-punct', '--surface'],
];

const THEMES: Array<[string, string]> = [
    ['claro', ':root'],
    ['escuro', '.dark'],
];

describe.each(THEMES)('contraste AA no tema %s', (_name, selector) => {
    const palette = block(selector);

    it.each(TEXT_PAIRS)('%s sobre %s', (fg, bg) => {
        const ratio = contrast(
            over(palette[fg] ?? '', palette[bg] ?? '', palette),
            parseColor(palette[bg] ?? '', palette),
        );

        expect(ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('anel de foco tem 3:1 contra o fundo (WCAG 1.4.11)', () => {
        const ratio = contrast(
            over(palette['--ring'] ?? '', palette['--background'] ?? '', palette),
            parseColor(palette['--background'] ?? '', palette),
        );

        expect(ratio).toBeGreaterThanOrEqual(3);
    });
});
