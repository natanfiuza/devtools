import { compositeOver, type Rgb, type Rgba } from '@/lib/color';
import { LayoutEngineError } from './types';

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
