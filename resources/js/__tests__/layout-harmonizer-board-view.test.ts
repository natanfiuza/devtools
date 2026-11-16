import { describe, expect, it } from 'vitest';
import { parseColor } from '@/lib/color';
import {
    generateLayoutSystem,
    LAYOUT_TOKEN_SPECS,
    type LayoutTokenId,
    type LayoutSystemResult,
} from '@/tools/layout-harmonizer/engine';
import {
    checksByToken,
    formatRatio,
    groupTokens,
    LAYOUT_FAMILY_ORDER,
} from '@/tools/layout-harmonizer/lib/boardView';

/**
 * Camada pura do quadro (F2): agrupamento por familia, indice dos pares de
 * contraste e formatacao da razao. Nada aqui pode alterar valores do motor.
 */

function system(): LayoutSystemResult {
    const parsed = parseColor('#3b82f6');

    if (!parsed.ok) {
        throw new Error('cor de teste invalida');
    }

    return generateLayoutSystem({
        seed: parsed.color.rgba,
        strategy: 'analogous',
    });
}

describe('agrupamento do quadro', () => {
    it('entrega as cinco familias na ordem fixa', () => {
        const groups = groupTokens(system().themes.light.tokens);

        expect(groups.map((group) => group.family)).toEqual(
            LAYOUT_FAMILY_ORDER,
        );
    });

    it('tem 11 de marca, 4 de superficie, 4 de feedback, 10 de conteudo e 4 de interacao', () => {
        const groups = groupTokens(system().themes.light.tokens);

        expect(
            groups.map((group) => [group.family, group.tokens.length]),
        ).toEqual([
            ['brand', 11],
            ['surface', 4],
            ['feedback', 4],
            ['content', 10],
            ['interaction', 4],
        ]);
    });

    it('preserva a ordem do catalogo dentro de cada familia, sem repetir token', () => {
        const groups = groupTokens(system().themes.light.tokens);
        const seen: LayoutTokenId[] = [];

        for (const group of groups) {
            const expected = LAYOUT_TOKEN_SPECS.filter(
                (spec) => spec.family === group.family,
            ).map((spec) => spec.id);

            expect(group.tokens.map((token) => token.id)).toEqual(expected);
            seen.push(...group.tokens.map((token) => token.id));
        }

        expect(seen).toHaveLength(33);
        expect(new Set(seen).size).toBe(33);
    });
});

describe('indice dos pares de contraste', () => {
    it('da uma entrada, mesmo vazia, para cada um dos 33 tokens', () => {
        const index = checksByToken(system().themes.light);

        expect(Object.keys(index)).toHaveLength(33);
        expect(index.primary).toEqual([]);
        expect(index.hairline).toEqual([]);
    });

    it('mede 16 pares e atribui todos ao primeiro plano', () => {
        const board = system().themes.light;
        const index = checksByToken(board);
        const checks = Object.values(index).flat();
        const signature = (list: typeof checks): string[] =>
            list
                .map((check) => `${check.foreground}-${check.background}`)
                .sort();

        expect(checks).toHaveLength(16);
        expect(signature(checks)).toEqual(signature(board.checks));
    });

    it('concentra os tres pares da marca no on-primary', () => {
        const index = checksByToken(system().themes.light);

        expect(index['on-primary'].map((check) => check.background)).toEqual([
            'primary',
            'primary-hover',
            'primary-active',
        ]);
        expect(index['on-secondary']).toHaveLength(3);
        expect(index['on-accent']).toHaveLength(3);
        expect(index['on-surface']).toHaveLength(1);
        expect(index['on-background']).toHaveLength(1);
    });
});

describe('formatacao da razao', () => {
    it('usa de 1 a 2 casas no idioma da tela', () => {
        expect(formatRatio(4.826, 'pt_BR')).toBe('4,83');
        expect(formatRatio(2, 'pt_BR')).toBe('2,0');
    });

    it('converte o separador de locale do PHP para o do Intl', () => {
        expect(formatRatio(3.1, 'en')).toBe('3.1');
        expect(formatRatio(3.1, 'fr')).toBe('3,1');
    });
});
