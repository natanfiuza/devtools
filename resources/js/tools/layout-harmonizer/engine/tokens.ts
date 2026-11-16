import type { LayoutTokenFamily, LayoutTokenId } from './types';

export interface LayoutTokenSpec {
    id: LayoutTokenId;
    family: LayoutTokenFamily;
}

/**
 * Catalogo na ordem aprovada em TOKENS.md: marca, superficies, feedback,
 * conteudo, interacao e, por fim, o Accent triadico.
 */
export const LAYOUT_TOKEN_SPECS: readonly LayoutTokenSpec[] = [
    { id: 'primary', family: 'brand' },
    { id: 'primary-variant', family: 'brand' },
    { id: 'secondary', family: 'brand' },
    { id: 'secondary-variant', family: 'brand' },
    { id: 'surface', family: 'surface' },
    { id: 'surface-variant', family: 'surface' },
    { id: 'background', family: 'surface' },
    { id: 'hairline', family: 'surface' },
    { id: 'error', family: 'feedback' },
    { id: 'success', family: 'feedback' },
    { id: 'warning', family: 'feedback' },
    { id: 'info', family: 'feedback' },
    { id: 'on-primary', family: 'content' },
    { id: 'on-secondary', family: 'content' },
    { id: 'on-background', family: 'content' },
    { id: 'on-surface', family: 'content' },
    { id: 'on-surface-variant', family: 'content' },
    { id: 'on-error', family: 'content' },
    { id: 'on-success', family: 'content' },
    { id: 'on-warning', family: 'content' },
    { id: 'on-info', family: 'content' },
    { id: 'primary-hover', family: 'brand' },
    { id: 'primary-active', family: 'brand' },
    { id: 'secondary-hover', family: 'brand' },
    { id: 'secondary-active', family: 'brand' },
    { id: 'link', family: 'interaction' },
    { id: 'link-hover', family: 'interaction' },
    { id: 'link-active', family: 'interaction' },
    { id: 'focus-ring', family: 'interaction' },
    { id: 'accent', family: 'brand' },
    { id: 'on-accent', family: 'content' },
    { id: 'accent-hover', family: 'brand' },
    { id: 'accent-active', family: 'brand' },
];

export const LAYOUT_TOKEN_IDS: readonly LayoutTokenId[] =
    LAYOUT_TOKEN_SPECS.map((spec) => spec.id);

export const LAYOUT_TOKEN_FAMILY: Record<LayoutTokenId, LayoutTokenFamily> =
    LAYOUT_TOKEN_SPECS.reduce(
        (families, spec) => {
            families[spec.id] = spec.family;

            return families;
        },
        {} as Record<LayoutTokenId, LayoutTokenFamily>,
    );

export interface LayoutContrastPair {
    foreground: LayoutTokenId;
    background: LayoutTokenId;
}

/**
 * Pares medidos pelo motor: cada on-* contra o fundo correspondente e, na
 * marca, tambem contra hover/active (o conteudo e reutilizado nos estados).
 */
export const LAYOUT_CONTRAST_PAIRS: readonly LayoutContrastPair[] = [
    { foreground: 'on-background', background: 'background' },
    { foreground: 'on-surface', background: 'surface' },
    { foreground: 'on-surface-variant', background: 'surface-variant' },
    { foreground: 'on-primary', background: 'primary' },
    { foreground: 'on-primary', background: 'primary-hover' },
    { foreground: 'on-primary', background: 'primary-active' },
    { foreground: 'on-secondary', background: 'secondary' },
    { foreground: 'on-secondary', background: 'secondary-hover' },
    { foreground: 'on-secondary', background: 'secondary-active' },
    { foreground: 'on-accent', background: 'accent' },
    { foreground: 'on-accent', background: 'accent-hover' },
    { foreground: 'on-accent', background: 'accent-active' },
    { foreground: 'on-error', background: 'error' },
    { foreground: 'on-success', background: 'success' },
    { foreground: 'on-warning', background: 'warning' },
    { foreground: 'on-info', background: 'info' },
];
