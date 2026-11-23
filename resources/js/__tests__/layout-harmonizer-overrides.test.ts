import { describe, expect, it } from 'vitest';
import { contrastRatio, parseColor, type Rgba } from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutOverrides,
} from '@/tools/layout-harmonizer/engine';

/**
 * Overrides por tema (F3): o valor manual entra depois da derivacao e antes
 * do contraste, nao recalcula dependentes e nunca toca o outro tema. Sem
 * overrides o resultado e identico ao da F2.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const BLUE = colorOf('#3b82f6');
const RED = { r: 255, g: 0, b: 0 };

function generate(overrides?: LayoutOverrides) {
    return generateLayoutSystem({
        seed: BLUE,
        strategy: 'analogous',
        overrides,
    });
}

describe('override por token', () => {
    it('aplica o valor exato, marca manual e recolhe o croma', () => {
        const result = generate({ light: { primary: RED } });
        const primary = result.themes.light.tokens.primary;

        expect(primary.hex).toBe('#ff0000');
        expect(primary.rgba).toEqual({ r: 255, g: 0, b: 0, a: 1 });
        expect(primary.source).toBe('manual');
        expect(primary.clipped).toBe(false);
        expect(primary.theme).toBe('light');
        expect(primary.family).toBe('brand');
    });

    it('nao toca o outro tema', () => {
        const baseline = generate();
        const result = generate({ light: { primary: RED } });

        expect(result.themes.dark.tokens.primary.hex).toBe(
            baseline.themes.dark.tokens.primary.hex,
        );
    });

    it('nao recalcula dependentes', () => {
        const baseline = generate();
        const result = generate({ light: { primary: RED } });

        expect(result.themes.light.tokens['primary-hover'].hex).toBe(
            baseline.themes.light.tokens['primary-hover'].hex,
        );
        expect(result.themes.light.tokens['on-primary'].hex).toBe(
            baseline.themes.light.tokens['on-primary'].hex,
        );
    });

    it('reme os checks depois do override', () => {
        const result = generate({ light: { 'on-primary': RED } });
        const board = result.themes.light;
        const check = board.checks.find(
            (item) => item.foreground === 'on-primary',
        );

        expect(check).toBeDefined();

        const expected = contrastRatio(
            board.tokens['on-primary'].rgba,
            board.tokens[check!.background].rgba,
        );

        expect(check!.verdict.ratio).toBeCloseTo(expected, 10);
    });

    it('vale para os dois temas ao mesmo tempo', () => {
        const result = generate({
            light: { background: RED },
            dark: { background: RED },
        });

        expect(result.themes.light.tokens.background.hex).toBe('#ff0000');
        expect(result.themes.dark.tokens.background.hex).toBe('#ff0000');
    });
});

describe('compatibilidade', () => {
    it('ausente ou vazio devolve o quadro automatico identico', () => {
        expect(generate({})).toEqual(generate());
        expect(generate({ light: {} })).toEqual(generate());
    });

    it('ignora ids e temas desconhecidos', () => {
        const result = generate({
            light: { 'nao-existe': RED },
            sepia: { primary: RED },
        } as never);

        expect(result).toEqual(generate());
    });

    it('override vence a secundaria manual no tema informado', () => {
        const result = generateLayoutSystem({
            seed: BLUE,
            strategy: 'analogous',
            secondary: colorOf('#00ff00'),
            overrides: { light: { secondary: RED } },
        });

        expect(result.themes.light.tokens.secondary.hex).toBe('#ff0000');
        expect(result.themes.dark.tokens.secondary.hex).toBe('#00ff00');
        expect(result.secondarySource).toBe('manual');
    });

    it('mantem a versao do algoritmo', () => {
        const result = generate({ light: { primary: RED } });

        expect(result.algorithmVersion).toBe(2);
    });
});
