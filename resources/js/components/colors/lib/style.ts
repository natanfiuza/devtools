import { hslToRgb, normalizeHue, type Rgba } from '@/lib/color';

/**
 * Pedacos de estilo que nao sao cor de tema.
 *
 * O xadrez e desenhado em CSS para o alpha ser visivel sem imagem externa - o
 * mesmo fundo que os editores de imagem usam. As duas camadas de degrade
 * formam os quadrados de 12 px.
 */

export function cssColor(color: Rgba): string {
    return `rgba(${color.r}, ${color.g}, ${color.b}, ${color.a})`;
}

export const checkerboardStyle = {
    backgroundImage:
        'linear-gradient(45deg, rgba(127, 127, 127, 0.22) 25%, transparent 25%, transparent 75%, rgba(127, 127, 127, 0.22) 75%), linear-gradient(45deg, rgba(127, 127, 127, 0.22) 25%, transparent 25%, transparent 75%, rgba(127, 127, 127, 0.22) 75%)',
    backgroundSize: '12px 12px',
    backgroundPosition: '0 0, 6px 6px',
} as const;

/** HSL apenas para o fundo da area 2D: matiz puro, saturacao e luz maximas. */
export function hueBackground(hue: number): string {
    const rgb = hslToRgb({ h: normalizeHue(hue), s: 100, l: 50 });

    return `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
}
