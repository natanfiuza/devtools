import type { BadgeFont, BadgeForgeToolProps } from '../types';

/**
 * Fontes empacotadas (ADR-077).
 *
 * O catalogo vem do servidor em `toolProps.fonts` (config
 * `badgeforge.fonts`); o espelho so resolve o slug escolhido. Valor
 * desconhecido devolve `null` e o badge cai na pilha do estilo - mesma regra
 * do `BadgeFontCatalog::find` no PHP.
 */

/** Valor do select quando a fonte e a do estilo (reka-ui recusa valor vazio). */
export const DEFAULT_FONT = 'default';

export function fontAsset(
    slug: string | null,
    props: BadgeForgeToolProps,
): BadgeFont | null {
    if (slug === null) {
        return null;
    }

    const wanted = slug.toLowerCase().trim();

    if (wanted === '' || wanted === DEFAULT_FONT) {
        return null;
    }

    return props.fonts.find((font) => font.slug === wanted) ?? null;
}
