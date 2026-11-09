import { clamp, rgbToLinear } from './convert';
import type { ContrastReport, ContrastVerdict, Rgb, Rgba } from './types';

/**
 * Contraste WCAG 2.x (ADR-117).
 *
 * A razao e calculada sobre a luminancia relativa (canais linearizados) da
 * cor **ja composta** sobre um fundo explicito. A classificacao usa o valor
 * cru da razao: arredondar para exibir 4.50 nao transforma uma cor de 4.4999
 * em AA.
 */

/** Composicao source-over em sRGB, como o navegador desenha. */
export function compositeOver(foreground: Rgba, background: Rgb): Rgb {
    const alpha = clamp(foreground.a, 0, 1);
    const inverse = 1 - alpha;

    return {
        r: foreground.r * alpha + background.r * inverse,
        g: foreground.g * alpha + background.g * inverse,
        b: foreground.b * alpha + background.b * inverse,
    };
}

export function relativeLuminance(rgb: Rgb): number {
    const linear = rgbToLinear({
        r: clamp(rgb.r, 0, 255),
        g: clamp(rgb.g, 0, 255),
        b: clamp(rgb.b, 0, 255),
    });

    return 0.2126 * linear.r + 0.7152 * linear.g + 0.0722 * linear.b;
}

export function contrastRatio(first: Rgb, second: Rgb): number {
    const a = relativeLuminance(first);
    const b = relativeLuminance(second);
    const lighter = Math.max(a, b);
    const darker = Math.min(a, b);

    return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Selos AA/AAA. Os limites sao 4.5 e 7 para texto normal, 3 e 4.5 para texto
 * grande. `>=` no valor cru, sem epsilon.
 */
export function classifyContrast(ratio: number): ContrastVerdict {
    return {
        ratio,
        aaNormal: ratio >= 4.5,
        aaaNormal: ratio >= 7,
        aaLarge: ratio >= 3,
        aaaLarge: ratio >= 4.5,
    };
}

export const BLACK: Rgb = { r: 0, g: 0, b: 0 };
export const WHITE: Rgb = { r: 255, g: 255, b: 255 };

/**
 * Relatorio das duas opcoes de texto (preto e branco) sobre a cor composta
 * no fundo informado.
 */
export function contrastReport(color: Rgba, background: Rgb): ContrastReport {
    const composed = compositeOver(color, background);

    return {
        background: composed,
        black: classifyContrast(contrastRatio(composed, BLACK)),
        white: classifyContrast(contrastRatio(composed, WHITE)),
    };
}
