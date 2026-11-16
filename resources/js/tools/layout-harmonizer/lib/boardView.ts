import {
    LAYOUT_TOKEN_SPECS,
    type LayoutPairCheck,
    type LayoutThemeBoard,
    type LayoutToken,
    type LayoutTokenFamily,
    type LayoutTokenId,
} from '../engine';

/**
 * Camada de apresentacao pura do quadro (F2).
 *
 * O motor ja entrega os 33 tokens e os 16 pares medidos; aqui so agrupamos
 * pela familia do catalogo, indexamos os checks pelo token de frente e
 * formatamos a razao para leitura. Nada aqui altera valores ou vereditos.
 */

/** Modos de visualizacao do quadro; estado local da pagina. */
export type LayoutViewMode = 'light' | 'dark' | 'compare';

/** Ordem fixa das familias no quadro (TOKENS.md). */
export const LAYOUT_FAMILY_ORDER: readonly LayoutTokenFamily[] = [
    'brand',
    'surface',
    'feedback',
    'content',
    'interaction',
];

export interface LayoutTokenGroupView {
    family: LayoutTokenFamily;
    tokens: LayoutToken[];
}

/**
 * Agrupa preservando a ordem aprovada do catalogo: as familias saem na ordem
 * fixa e, dentro de cada uma, os tokens seguem `LAYOUT_TOKEN_SPECS` (o Accent
 * continua no fim da marca e o on-accent no fim do conteudo).
 */
export function groupTokens(
    tokens: Record<LayoutTokenId, LayoutToken>,
): LayoutTokenGroupView[] {
    const groups = new Map<LayoutTokenFamily, LayoutToken[]>(
        LAYOUT_FAMILY_ORDER.map((family) => [family, []]),
    );

    for (const spec of LAYOUT_TOKEN_SPECS) {
        groups.get(spec.family)?.push(tokens[spec.id]);
    }

    return LAYOUT_FAMILY_ORDER.map((family) => ({
        family,
        tokens: groups.get(family) ?? [],
    }));
}

/**
 * Indexa os checks pelo token de frente. Todo token ganha uma entrada (mesmo
 * vazia), para a celula sempre receber um array.
 *
 * Nao ha selo nos fundos: o par e medido do primeiro plano contra o fundo e e
 * atribuido ao primeiro plano, nunca aos dois lados.
 */
export function checksByToken(
    board: LayoutThemeBoard,
): Record<LayoutTokenId, LayoutPairCheck[]> {
    const index = {} as Record<LayoutTokenId, LayoutPairCheck[]>;

    for (const spec of LAYOUT_TOKEN_SPECS) {
        index[spec.id] = [];
    }

    for (const check of board.checks) {
        index[check.foreground]?.push(check);
    }

    return index;
}

/**
 * Razao em 1-2 casas, no idioma da tela. O veredito continua vindo do numero
 * sem arredondar; aqui o arredondado e so o que a pessoa le.
 */
export function formatRatio(ratio: number, locale: string): string {
    return new Intl.NumberFormat(locale.replace('_', '-'), {
        minimumFractionDigits: 1,
        maximumFractionDigits: 2,
    }).format(ratio);
}
