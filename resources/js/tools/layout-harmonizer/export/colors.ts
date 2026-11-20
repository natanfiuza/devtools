import type { Rgba } from '@/lib/color';

/**
 * Formatos de cor dos arquivos exportados (F5).
 *
 * Os valores vem do snapshot (HEX, rgba e OKLCH efetivos depois do mapeamento
 * para sRGB): nada aqui recalcula cor, so formata para o texto de saida.
 */

export interface LayoutExportOklch {
    l: number;
    c: number;
    h: number;
}

/** Numero com `decimals` casas e sem zeros a direita. */
export function trimNumber(value: number, decimals: number): string {
    const text = value.toFixed(decimals);

    return text.includes('.')
        ? text.replace(/0+$/, '').replace(/\.$/, '')
        : text;
}

/** Fallback sRGB: HEX opaco ou `rgba()` quando o token tiver alpha. */
export function cssFallback(rgba: Rgba, hex: string): string {
    if (rgba.a >= 1) {
        return hex;
    }

    const red = Math.round(rgba.r);
    const green = Math.round(rgba.g);
    const blue = Math.round(rgba.b);

    return `rgba(${red}, ${green}, ${blue}, ${trimNumber(rgba.a, 3)})`;
}

/** OKLCH efetivo do snapshot, com alpha quando existir. */
export function formatCssOklch(oklch: LayoutExportOklch, alpha = 1): string {
    const value = `oklch(${trimNumber(oklch.l * 100, 2)}% ${trimNumber(oklch.c, 4)} ${trimNumber(oklch.h, 2)}`;

    return alpha >= 1 ? `${value})` : `${value} / ${trimNumber(alpha, 3)})`;
}
