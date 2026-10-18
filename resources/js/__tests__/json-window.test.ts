import { describe, expect, it } from 'vitest';
import {
    OVERSCAN,
    ROW_HEIGHT,
    windowRange,
} from '@/tools/json-viewer/lib/window';

/**
 * A conta da virtualizacao. Sem layout: e justamente por isso que ela mora numa
 * funcao pura - a alternativa seria medir altura de linha no navegador e
 * descobrir o erro de conta so na tela.
 *
 * O limiar a partir do qual a janela entra nao esta aqui: ele vem da
 * configuracao (`config/tools.php`) ate a tela, e quem o cobra e
 * json-viewer.test.ts.
 */

const VIEWPORT = 240;
const ROWS_IN_VIEWPORT = VIEWPORT / ROW_HEIGHT;

describe('geometria', () => {
    it('tem altura de linha e folga positivas', () => {
        expect(OVERSCAN).toBeGreaterThan(0);
        expect(ROW_HEIGHT).toBeGreaterThan(0);
    });
});

describe('windowRange', () => {
    it('comeca no topo com a folga de baixo', () => {
        expect(windowRange(0, VIEWPORT, 1000)).toEqual({
            start: 0,
            end: ROWS_IN_VIEWPORT + OVERSCAN,
            paddingTop: 0,
            paddingBottom: (1000 - ROWS_IN_VIEWPORT - OVERSCAN) * ROW_HEIGHT,
        });
    });

    it('acompanha o meio da lista', () => {
        const scrollTop = 2400;
        const first = scrollTop / ROW_HEIGHT;

        expect(windowRange(scrollTop, VIEWPORT, 1000)).toEqual({
            start: first - OVERSCAN,
            end: first + ROWS_IN_VIEWPORT + OVERSCAN,
            paddingTop: (first - OVERSCAN) * ROW_HEIGHT,
            paddingBottom:
                (1000 - first - ROWS_IN_VIEWPORT - OVERSCAN) * ROW_HEIGHT,
        });
    });

    it('para no fim da lista', () => {
        const total = 100;

        expect(windowRange(total * ROW_HEIGHT, VIEWPORT, total)).toEqual({
            start: total - OVERSCAN,
            end: total,
            paddingTop: (total - OVERSCAN) * ROW_HEIGHT,
            paddingBottom: 0,
        });
    });

    it('nao passa do comeco com rolagem negativa', () => {
        expect(windowRange(-50, VIEWPORT, 1000).start).toBe(0);
        expect(windowRange(-50, VIEWPORT, 1000).paddingTop).toBe(0);
    });

    it('renderiza a lista inteira quando ela e menor que a janela', () => {
        expect(windowRange(0, VIEWPORT, 5)).toEqual({
            start: 0,
            end: 5,
            paddingTop: 0,
            paddingBottom: 0,
        });
    });

    it('devolve janela vazia para lista vazia', () => {
        expect(windowRange(0, VIEWPORT, 0)).toEqual({
            start: 0,
            end: 0,
            paddingTop: 0,
            paddingBottom: 0,
        });
    });

    it('mantem a altura total, com qualquer rolagem', () => {
        const total = 5000;
        const esperado = total * ROW_HEIGHT;

        for (const scrollTop of [0, 1, 119, 2400, 120000, 999999]) {
            const range = windowRange(scrollTop, VIEWPORT, total);
            const renderizadas = range.end - range.start;

            expect(
                range.paddingTop +
                    renderizadas * ROW_HEIGHT +
                    range.paddingBottom,
            ).toBe(esperado);
            expect(range.start).toBeGreaterThanOrEqual(0);
            expect(range.end).toBeLessThanOrEqual(total);
            expect(range.start).toBeLessThanOrEqual(range.end);
        }
    });

    it('aceita altura de linha diferente', () => {
        expect(windowRange(0, 300, 100, 30, 1)).toEqual({
            start: 0,
            end: 11,
            paddingTop: 0,
            paddingBottom: 89 * 30,
        });
    });
});
