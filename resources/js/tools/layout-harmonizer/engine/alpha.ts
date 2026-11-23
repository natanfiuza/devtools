import { compositeOver, type Rgb, type Rgba } from '@/lib/color';
import { LayoutEngineError, type LayoutSecondaryInfo } from './types';

export interface ResolvedSeed {
    /** Cor informada, preservada como veio (inclusive alpha). */
    original: Rgba;
    /** Cor usada em toda a geracao; sempre opaca. */
    effective: Rgba;
    /** Fundo da composicao; `null` quando a seed ja era opaca. */
    background: Rgb | null;
}

/**
 * Resolve a seed antes de gerar (ADR-149).
 *
 * Seed opaca e usada como esta. Seed com alpha exige fundo explicito: nao ha
 * fundo padrao, porque a cor efetiva depende da escolha do usuario. O original
 * nunca e descartado; o motor so trabalha com a efetiva.
 */
export function resolveSeed(seed: Rgba, background?: Rgb | null): ResolvedSeed {
    if (seed.a >= 1) {
        return {
            original: { ...seed },
            effective: { r: seed.r, g: seed.g, b: seed.b, a: 1 },
            background: null,
        };
    }

    if (!background) {
        throw new LayoutEngineError(
            'seed-alpha-without-background',
            'seed com alpha exige um fundo explicito para a composicao',
        );
    }

    const composed = compositeOver(seed, background);

    return {
        original: { ...seed },
        effective: { ...composed, a: 1 },
        background,
    };
}

/**
 * Resolve a secundaria manual com alpha (emenda da F6).
 *
 * Mesma regra da seed: alpha sem fundo explicito e recusado, nunca descartado.
 * Sem alpha o fundo informado e ignorado e a cor entra como veio.
 */
export function resolveSecondary(
    secondary: Rgba,
    background?: Rgb | null,
): LayoutSecondaryInfo {
    if (secondary.a >= 1) {
        return {
            original: { ...secondary },
            effective: { ...secondary, a: 1 },
            background: null,
        };
    }

    if (!background) {
        throw new LayoutEngineError(
            'secondary-alpha-without-background',
            'secundaria com alpha exige um fundo explicito para a composicao',
        );
    }

    const composed = compositeOver(secondary, background);

    return {
        original: { ...secondary },
        effective: { ...composed, a: 1 },
        background,
    };
}
