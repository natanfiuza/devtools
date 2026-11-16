import { normalizeHue } from '@/lib/color';
import { STRATEGY_HUES } from './params';
import { LAYOUT_STRATEGIES, type LayoutStrategy } from './types';

/**
 * Deslocamentos de matiz aprovados (ADR-156): analoga +30, complementar
 * +180, triadica +240 (secundaria) e +120 (Accent) e monocromatica sem giro.
 * A matiz e normalizada em 0..360 depois da soma.
 */

export function isLayoutStrategy(value: string): value is LayoutStrategy {
    return (LAYOUT_STRATEGIES as readonly string[]).includes(value);
}

export function secondaryHue(
    seedHue: number,
    strategy: LayoutStrategy,
): number {
    return normalizeHue(seedHue + STRATEGY_HUES[strategy].secondary);
}

/**
 * O Accent usa +120 em qualquer estrategia: na triadica ele e uma cor da
 * harmonia; nas outras, o mesmo papel de destaque, para o catalogo nao mudar
 * (ADR-163).
 */
export function accentHue(seedHue: number, strategy: LayoutStrategy): number {
    return normalizeHue(seedHue + STRATEGY_HUES[strategy].accent);
}
