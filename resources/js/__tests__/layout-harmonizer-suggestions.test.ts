import { describe, expect, it } from 'vitest';
import {
    clamp,
    contrastRatio,
    mapOklchToSrgb,
    normalizeHue,
    parseColor,
    rgbToOklch,
    type Rgba,
} from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutSystemResult,
    type LayoutTheme,
    type LayoutTokenId,
} from '@/tools/layout-harmonizer/engine';
import {
    LAYOUT_SUGGESTIONS_VERSION,
    SUGGESTION_EPSILON,
    SUGGESTION_MAX_STEPS,
    SUGGESTION_STEP,
    isInviable,
    suggestionsFor,
    type LayoutSuggestion,
} from '@/tools/layout-harmonizer/lib/suggestions';

/**
 * Sugestoes de contraste: uma por token de frente e tema, menor delta de L,
 * todos os fundos atendidos e aviso explicito quando a meta nao e alcancavel.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

function boardOf(
    result: LayoutSystemResult,
    theme: LayoutTheme,
    token: LayoutTokenId,
) {
    const board = result.themes[theme];

    return {
        board,
        checks: board.checks.filter((check) => check.foreground === token),
    };
}

function failingTokens(
    result: LayoutSystemResult,
    target: 'aa' | 'aaa',
): Set<string> {
    const failing = new Set<string>();

    for (const theme of ['light', 'dark'] as const) {
        for (const check of result.themes[theme].checks) {
            const passes =
                target === 'aaa'
                    ? check.verdict.aaaNormal
                    : check.verdict.aaNormal;

            if (!passes) {
                failing.add(`${theme}:${check.foreground}`);
            }
        }
    }

    return failing;
}

const BLUE = colorOf('#3b82f6');
const GRAY = colorOf('#777777');

describe('constantes', () => {
    it('expoe a versao e os parametros aprovados', () => {
        expect(LAYOUT_SUGGESTIONS_VERSION).toBe(1);
        expect(SUGGESTION_STEP).toBe(0.005);
        expect(SUGGESTION_MAX_STEPS).toBe(200);
        expect(SUGGESTION_EPSILON).toBe(0.001);
    });
});

describe('cobertura', () => {
    it('cobre exatamente os tokens que falham, por tema', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'analogous',
        });
        const suggestions = suggestionsFor(result, 'aa');

        expect(
            new Set(suggestions.map((item) => `${item.theme}:${item.token}`)),
        ).toEqual(failingTokens(result, 'aa'));
        expect(suggestions.every((item) => item.target === 'aa')).toBe(true);
    });

    it('nao sugere nada para um token que ja passa', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'analogous',
        });
        const passing = result.themes.light.checks.find(
            (check) => check.verdict.aaNormal,
        );

        expect(passing).toBeDefined();

        const suggestion = suggestionsFor(result, 'aa').find(
            (item) =>
                item.theme === 'light' && item.token === passing!.foreground,
        );

        expect(suggestion).toBeUndefined();
    });
});

describe('proposta', () => {
    // No azul, o tema claro deriva hover/active mais escuros: um on-* escuro
    // perde razao neles e um on-* claro nao alcanca 4,5 na base. Nenhuma
    // luminosidade unica atende os tres fundos.
    const blue = generateLayoutSystem({ seed: BLUE, strategy: 'analogous' });
    // No cinza, o tema escuro clareia hover/active e existe proposta viavel.
    const gray = generateLayoutSystem({ seed: GRAY, strategy: 'analogous' });
    const feasible = suggestionsFor(gray, 'aa').filter(
        (item) => item.proposal !== null,
    );

    it('atende a meta em todos os fundos do token', () => {
        expect(feasible.length).toBeGreaterThan(0);

        for (const suggestion of feasible) {
            for (const entry of suggestion.ratios) {
                expect(entry.projected).toBeGreaterThanOrEqual(4.5);
            }
        }
    });

    it('preserva matiz e croma do token', () => {
        for (const suggestion of feasible) {
            if (!suggestion.proposal) {
                continue;
            }

            const before = rgbToOklch(suggestion.current);
            const after = rgbToOklch(suggestion.proposal);

            if (after.c > 0.01) {
                const diff = Math.abs(
                    normalizeHue(after.h) - normalizeHue(before.h),
                );

                expect(Math.min(diff, 360 - diff)).toBeLessThan(1);
            }

            expect(after.c).toBeLessThanOrEqual(before.c + 0.005);
        }
    });

    it('usa o menor passo de luminosidade', () => {
        for (const suggestion of feasible) {
            if (!suggestion.proposal) {
                continue;
            }

            const base = rgbToOklch(suggestion.current);
            const sign = suggestion.deltaLightness > 0 ? 1 : -1;
            const smaller = clamp(
                base.l + (suggestion.deltaLightness - sign * SUGGESTION_STEP),
                0,
                1,
            );
            const mapped = mapOklchToSrgb({
                l: smaller,
                c: base.c,
                h: base.h,
            });
            const { checks } = boardOf(
                gray,
                suggestion.theme,
                suggestion.token,
            );
            const ratios = checks.map((check) =>
                contrastRatio(
                    mapped.rgb,
                    gray.themes[suggestion.theme].tokens[check.background].rgba,
                ),
            );

            expect(ratios.some((ratio) => ratio < 4.5)).toBe(true);
        }
    });

    it('marca como inviavel quando nenhum fundo aceita a mesma proposta', () => {
        const suggestions = suggestionsFor(blue, 'aa');

        expect(suggestions.length).toBeGreaterThan(0);
        expect(suggestions.every(isInviable)).toBe(true);
    });

    it('mede a razao projetada no sRGB mapeado', () => {
        const suggestion = feasible[0] as LayoutSuggestion;
        const { checks } = boardOf(gray, suggestion.theme, suggestion.token);

        expect(suggestion.proposal).not.toBeNull();

        for (const [index, entry] of suggestion.ratios.entries()) {
            expect(entry.background).toBe(checks[index]!.background);
            expect(entry.projected).toBeCloseTo(
                contrastRatio(
                    suggestion.proposal!,
                    gray.themes[suggestion.theme].tokens[entry.background].rgba,
                ),
                10,
            );
        }
    });
});

describe('metas', () => {
    it('AA pode ser inviavel quando os fundos do token cruzam a faixa media', () => {
        // O mesmo token e medido contra base, hover e active; quando as tres
        // luminancias ficam em faixas diferentes, um unico foreground nao
        // atende 4,5 em todas. A sugestao existe, marcada como inviavel.
        const blue = suggestionsFor(
            generateLayoutSystem({ seed: BLUE, strategy: 'analogous' }),
            'aa',
        );

        expect(blue.some(isInviable)).toBe(true);

        // E o aviso nao impede a meta de achar propostas onde ha solucao.
        const gray = suggestionsFor(
            generateLayoutSystem({ seed: GRAY, strategy: 'analogous' }),
            'aa',
        );

        expect(gray.some((item) => item.proposal !== null)).toBe(true);
    });

    it('AAA pode ser inviavel em fundo de luminancia media', () => {
        const result = generateLayoutSystem({
            seed: GRAY,
            strategy: 'analogous',
        });
        const suggestions = suggestionsFor(result, 'aaa');
        const inviable = suggestions.filter(isInviable);

        expect(inviable.length).toBeGreaterThan(0);
        expect(inviable[0]!.deltaLightness).toBe(0);
        expect(inviable[0]!.proposal).toBeNull();
        expect(
            inviable[0]!.ratios.every(
                (entry) => entry.projected === entry.current,
            ),
        ).toBe(true);
    });

    it('trocar a meta nao altera as cores do quadro', () => {
        const result = generateLayoutSystem({
            seed: GRAY,
            strategy: 'analogous',
        });

        suggestionsFor(result, 'aa');
        suggestionsFor(result, 'aaa');

        // `suggestionsFor` e puro: o resultado entregue continua o mesmo objeto.
        expect(result.themes.light.tokens.primary.hex).toBe(
            generateLayoutSystem({ seed: GRAY, strategy: 'analogous' }).themes
                .light.tokens.primary.hex,
        );
    });
});
