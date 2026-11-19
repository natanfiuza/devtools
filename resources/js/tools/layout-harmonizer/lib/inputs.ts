import { parseColor, type Rgb } from '@/lib/color';

/**
 * Leitura das entradas de texto da ferramenta (F2/F4).
 *
 * O picker guarda o valor autoritativo; estes helpers existem para a
 * hidratacao e para os dois controllers (live e congelado) concordarem sobre
 * o que significa "fundo valido" e "secundaria invalida".
 */

/**
 * Cor interna do picker antes da primeira escolha. Nunca vira seed: o campo
 * abre vazio e so uma interacao real (`text` nao vazio) libera a geracao.
 */
export const LAYOUT_PLACEHOLDER = { r: 255, g: 255, b: 255, a: 1 } as const;

/** Fundo de composicao opaco; `null` quando vazio ou invalido. */
export function parseBackgroundText(text: string): Rgb | null {
    const trimmed = text.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = parseColor(trimmed);

    if (!parsed.ok) {
        return null;
    }

    return {
        r: parsed.color.rgba.r,
        g: parsed.color.rgba.g,
        b: parsed.color.rgba.b,
    };
}

/** `undefined` = texto invalido; `null` = automatico; `Rgb` = manual. */
export function parseSecondaryText(text: string): Rgb | null | undefined {
    const trimmed = text.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = parseColor(trimmed);

    return parsed.ok
        ? {
              r: parsed.color.rgba.r,
              g: parsed.color.rgba.g,
              b: parsed.color.rgba.b,
          }
        : undefined;
}
