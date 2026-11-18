import {
    clamp,
    contrastRatio,
    mapOklchToSrgb,
    rgbToOklch,
    type Oklch,
    type Rgba,
} from '@/lib/color';
import {
    LAYOUT_THEMES,
    LAYOUT_TOKEN_SPECS,
    type LayoutTokenId,
    type LayoutSystemResult,
    type LayoutTheme,
} from '../engine';
import type { LayoutEditTarget } from './editState';

/**
 * Sugestoes de correcao de contraste (F3).
 *
 * Nada aqui aplica nada: a sugestao so descreve a menor mudanca de
 * luminosidade que faz o token de frente passar na meta escolhida contra
 * todos os fundos dele no tema. O aceite e uma acao explicita da interface.
 */

/** Sobe quando o algoritmo de sugestao mudar o resultado. */
export const LAYOUT_SUGGESTIONS_VERSION = 1;

/** Passo de luminosidade OKLCH entre candidatos. */
export const SUGGESTION_STEP = 0.005;

/** Teto de passos em cada direcao (1.0 de L no total). */
export const SUGGESTION_MAX_STEPS = 200;

/** Folga acima da meta: cobre o arredondamento do contraste medido. */
export const SUGGESTION_EPSILON = 0.001;

export const SUGGESTION_TARGETS: Record<LayoutEditTarget, number> = {
    aa: 4.5,
    aaa: 7,
};

export interface LayoutSuggestionRatio {
    background: LayoutTokenId;
    /** Razao do token como esta hoje. */
    current: number;
    /** Razao depois da proposta; igual a atual quando nao ha proposta. */
    projected: number;
}

export interface LayoutSuggestion {
    theme: LayoutTheme;
    token: LayoutTokenId;
    target: LayoutEditTarget;
    current: Rgba;
    /** `null` quando nenhuma luminosidade alcanca a meta. */
    proposal: Rgba | null;
    /** Delta de L OKLCH da proposta; 0 quando inviavel. */
    deltaLightness: number;
    ratios: LayoutSuggestionRatio[];
}

export function suggestionTarget(target: LayoutEditTarget): number {
    return SUGGESTION_TARGETS[target];
}

function targetPasses(
    target: LayoutEditTarget,
    verdict: { aaNormal: boolean; aaaNormal: boolean },
): boolean {
    return target === 'aaa' ? verdict.aaaNormal : verdict.aaNormal;
}

interface Proposal {
    rgba: Rgba;
    deltaLightness: number;
}

/**
 * Menor k em qualquer direcao cuja cor cumpra a meta em todos os fundos.
 * Empate no mesmo k: maior razao minima; persistindo, a direcao mais clara.
 */
function findProposal(
    base: Oklch,
    backgrounds: Rgba[],
    minimum: number,
): Proposal | null {
    for (let step = 1; step <= SUGGESTION_MAX_STEPS; step++) {
        const candidates: (Proposal & { minRatio: number })[] = [];

        for (const sign of [-1, 1]) {
            const l = clamp(base.l + sign * step * SUGGESTION_STEP, 0, 1);

            if (Math.abs(l - base.l) < Number.EPSILON) {
                continue;
            }

            const mapped = mapOklchToSrgb({ l, c: base.c, h: base.h });
            const rgba: Rgba = { ...mapped.rgb, a: 1 };
            const ratios = backgrounds.map((background) =>
                contrastRatio(rgba, background),
            );

            if (
                ratios.every((ratio) => ratio >= minimum + SUGGESTION_EPSILON)
            ) {
                candidates.push({
                    rgba,
                    deltaLightness: l - base.l,
                    minRatio: Math.min(...ratios),
                });
            }
        }

        if (candidates.length > 0) {
            candidates.sort(
                (a, b) =>
                    b.minRatio - a.minRatio ||
                    b.deltaLightness - a.deltaLightness,
            );

            const best = candidates[0];

            if (!best) {
                return null;
            }

            return {
                rgba: best.rgba,
                deltaLightness: best.deltaLightness,
            };
        }
    }

    return null;
}

/**
 * Uma sugestao por token de frente e tema, nunca por par: um mesmo foreground
 * pode aparecer contra base, hover e active, e a proposta precisa passar em
 * todos para nao trocar um problema por outro.
 */
export function suggestionsFor(
    result: LayoutSystemResult,
    target: LayoutEditTarget,
): LayoutSuggestion[] {
    const minimum = suggestionTarget(target);
    const suggestions: LayoutSuggestion[] = [];

    for (const theme of LAYOUT_THEMES) {
        const board = result.themes[theme];
        const checksByToken = new Map<LayoutTokenId, typeof board.checks>();

        for (const check of board.checks) {
            const list = checksByToken.get(check.foreground) ?? [];
            list.push(check);
            checksByToken.set(check.foreground, list);
        }

        for (const spec of LAYOUT_TOKEN_SPECS) {
            const checks = checksByToken.get(spec.id);

            if (
                !checks ||
                checks.every((check) => targetPasses(target, check.verdict))
            ) {
                continue;
            }

            const token = board.tokens[spec.id];
            const backgrounds = checks.map(
                (check) => board.tokens[check.background].rgba,
            );
            const proposal = findProposal(
                rgbToOklch(token.rgba),
                backgrounds,
                minimum,
            );

            suggestions.push({
                theme,
                token: spec.id,
                target,
                current: token.rgba,
                proposal: proposal?.rgba ?? null,
                deltaLightness: proposal?.deltaLightness ?? 0,
                ratios: checks.map((check) => {
                    const background = board.tokens[check.background].rgba;

                    return {
                        background: check.background,
                        current: check.verdict.ratio,
                        projected: proposal
                            ? contrastRatio(proposal.rgba, background)
                            : check.verdict.ratio,
                    };
                }),
            });
        }
    }

    return suggestions;
}

export function isInviable(suggestion: LayoutSuggestion): boolean {
    return suggestion.proposal === null;
}
