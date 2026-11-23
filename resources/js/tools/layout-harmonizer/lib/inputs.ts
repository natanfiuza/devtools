import { parseColor, type Rgb, type Rgba } from '@/lib/color';

/**
 * Leitura das entradas de texto da ferramenta (F2/F4, emenda da F6).
 *
 * O picker guarda o valor autoritativo; estes helpers existem para a
 * hidratacao e para os dois controllers (live e congelado) concordarem sobre
 * o que significa "fundo valido" e "secundaria invalida".
 *
 * Fundos de composicao sao sempre opacos: um fundo com alpha nao pode ser
 * descartado em silencio, entao o parser discrimina o motivo (vazio, invalido
 * ou alpha) em vez de devolver apenas a cor.
 */

/**
 * Cor interna do picker antes da primeira escolha. Nunca vira seed: o campo
 * abre vazio e so uma interacao real (`text` nao vazio) libera a geracao.
 */
export const LAYOUT_PLACEHOLDER = { r: 255, g: 255, b: 255, a: 1 } as const;

export type ParsedOpaqueInput =
    | { kind: 'empty' }
    | { kind: 'invalid' }
    | { kind: 'alpha' }
    | { kind: 'color'; rgb: Rgb };

/** Fundo de composicao opaco; discrimina vazio, invalido e alpha. */
export function parseOpaqueText(text: string): ParsedOpaqueInput {
    const trimmed = text.trim();

    if (trimmed === '') {
        return { kind: 'empty' };
    }

    const parsed = parseColor(trimmed);

    if (!parsed.ok) {
        return { kind: 'invalid' };
    }

    const rgba = parsed.color.rgba;

    if (rgba.a < 1) {
        return { kind: 'alpha' };
    }

    return { kind: 'color', rgb: { r: rgba.r, g: rgba.g, b: rgba.b } };
}

/** Secundaria: `undefined` = texto invalido; `null` = automatico; `Rgba` = manual. */
export function parseSecondaryText(text: string): Rgba | null | undefined {
    const trimmed = text.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = parseColor(trimmed);

    return parsed.ok ? { ...parsed.color.rgba } : undefined;
}
