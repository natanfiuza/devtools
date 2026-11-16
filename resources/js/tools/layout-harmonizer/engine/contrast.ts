import { classifyContrast, contrastRatio } from '@/lib/color';
import { LAYOUT_CONTRAST_PAIRS } from './tokens';
import type { LayoutPairCheck, LayoutToken, LayoutTokenId } from './types';

/**
 * Mede os pares de conteudo sem arredondar (ADR-158). O selo AA/AAA usa a
 * razao crua; a interface decide o que mostrar (AA padrao, AAA opcional).
 */
export function evaluateContrast(
    tokens: Record<LayoutTokenId, LayoutToken>,
): LayoutPairCheck[] {
    return LAYOUT_CONTRAST_PAIRS.map(({ foreground, background }) => {
        const ratio = contrastRatio(
            tokens[foreground].rgba,
            tokens[background].rgba,
        );

        return {
            foreground,
            background,
            verdict: classifyContrast(ratio),
        };
    });
}
