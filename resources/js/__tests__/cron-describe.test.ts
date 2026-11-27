import { describe, expect, it } from 'vitest';
import {
    describeCron,
    describeField,
} from '@/tools/cron-explainer/lib/describe';
import { parseCron } from '@/tools/cron-explainer/lib/parse';

/**
 * Os descritores dos campos.
 *
 * O motor nao devolve frase: devolve o tipo (`any`, `at`, `range`,
 * `step_star`, `step_range`) e os numeros que a tela usa para montar a frase
 * em qualquer um dos cinco idiomas.
 */

function fieldOf(expression: string, index: number) {
    const parsed = parseCron(expression);

    if (!parsed.ok) {
        throw new Error(`expressao invalida: ${parsed.error.code}`);
    }

    const fields = Object.values(parsed.spec.fields);

    return fields[index]!;
}

describe('describeField', () => {
    it('descreve o campo inteiro como any', () => {
        expect(describeField(fieldOf('0 9 * * 1-5', 2))).toMatchObject({
            id: 'day_of_month',
            kind: 'any',
            from: null,
            to: null,
            step: null,
        });
    });

    it('descreve um valor unico como at', () => {
        expect(describeField(fieldOf('0 9 * * 1-5', 1))).toMatchObject({
            id: 'hour',
            kind: 'at',
            values: [9],
        });
    });

    it('descreve intervalo, passo com asterisco e passo com intervalo', () => {
        expect(describeField(fieldOf('0 9 * * 1-5', 4))).toMatchObject({
            kind: 'range',
            from: 1,
            to: 5,
        });

        expect(describeField(fieldOf('*/15 * * * *', 0))).toMatchObject({
            kind: 'step_star',
            step: 15,
            from: null,
            to: null,
            values: [0, 15, 30, 45],
        });

        expect(describeField(fieldOf('10-20/5 * * * *', 0))).toMatchObject({
            kind: 'step_range',
            from: 10,
            to: 20,
            step: 5,
            values: [10, 15, 20],
        });
    });

    it('descreve lista pela expansao', () => {
        expect(describeField(fieldOf('1,3,5 * * * *', 0))).toMatchObject({
            kind: 'at',
            values: [1, 3, 5],
        });

        expect(describeField(fieldOf('1-2,5-6 * * * *', 0))).toMatchObject({
            kind: 'at',
            values: [1, 2, 5, 6],
        });
    });

    it('cobre os cinco campos na ordem', () => {
        const parsed = parseCron('0 9 * * 1-5');

        if (!parsed.ok) {
            throw new Error('expressao invalida');
        }

        expect(describeCron(parsed.spec).map((field) => field.id)).toEqual([
            'minute',
            'hour',
            'day_of_month',
            'month',
            'day_of_week',
        ]);
    });
});
