import { describe, expect, it } from 'vitest';
import { parseColor, type Rgb, type Rgba } from '@/lib/color';
import {
    LAYOUT_TOKEN_IDS,
    generateLayoutSystem,
    type LayoutStrategy,
    type LayoutSystemResult,
    type LayoutTheme,
} from '@/tools/layout-harmonizer/engine';

/**
 * Fixtures aprovadas na F1: cada seed passa pelas quatro estrategias e pelos
 * dois temas, e o resultado (HEX, corte de gamut e razoes de contraste) fica
 * congelado. Mudar a tabela de parametros deve quebrar estas fixtures.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

interface Fixture {
    label: string;
    seed: Rgba;
    background?: Rgb;
}

const WHITE: Rgb = { r: 255, g: 255, b: 255 };

const FIXTURES: readonly Fixture[] = [
    { label: 'blue', seed: colorOf('#3b82f6') },
    { label: 'green', seed: colorOf('#10b981') },
    { label: 'red', seed: colorOf('#ef4444') },
    { label: 'amber', seed: colorOf('#f59e0b') },
    { label: 'violet', seed: colorOf('#7c3aed') },
    { label: 'black', seed: colorOf('#000000') },
    { label: 'white', seed: colorOf('#ffffff') },
    { label: 'gray', seed: colorOf('#808080') },
    { label: 'out-of-gamut', seed: colorOf('oklch(0.70 0.35 20)') },
    {
        label: 'alpha-over-white',
        seed: colorOf('rgba(59, 130, 246, 0.6)'),
        background: WHITE,
    },
];

const STRATEGIES: readonly LayoutStrategy[] = [
    'analogous',
    'complementary',
    'triadic',
    'monochromatic',
];

const THEMES: readonly LayoutTheme[] = ['light', 'dark'];

/** Linha compacta e diffavel: hex por token e razoes dos checks. */
function fixtureLine(result: LayoutSystemResult, theme: LayoutTheme): string {
    const board = result.themes[theme];
    const tokens = LAYOUT_TOKEN_IDS.map((id) => {
        const token = board.tokens[id];

        return `${id}:${token.hex}${token.clipped ? '*' : ''}`;
    }).join(' ');
    const checks = board.checks
        .map((check) => {
            const ratio = check.verdict.ratio.toFixed(2);
            const flag = check.verdict.aaNormal ? '' : '!';

            return `${check.foreground}/${check.background}=${ratio}${flag}`;
        })
        .join(' ');

    return `tokens ${tokens}\nchecks ${checks}`;
}

describe('fixtures do motor', () => {
    for (const fixture of FIXTURES) {
        for (const strategy of STRATEGIES) {
            const result = generateLayoutSystem({
                seed: fixture.seed,
                strategy,
                background: fixture.background,
            });

            for (const theme of THEMES) {
                it(`${fixture.label} ${strategy} ${theme}`, () => {
                    expect(fixtureLine(result, theme)).toMatchSnapshot();
                });
            }
        }
    }
});
