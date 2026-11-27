import { describe, expect, it } from 'vitest';
import { parseCron } from '@/tools/cron-explainer/lib/parse';

/**
 * O parser do cron de cinco campos.
 *
 * Os testes cobram o contrato de fora: a expansao de cada item, a normalizacao
 * do domingo e a semantica Vixie que o `starred` carrega. Os erros sao
 * conferidos por codigo (nao por texto), porque a frase e do i18n.
 */

function specOf(expression: string) {
    const result = parseCron(expression);

    if (!result.ok) {
        throw new Error(`esperava sucesso: ${result.error.code}`);
    }

    return result.spec;
}

function errorOf(expression: string) {
    const result = parseCron(expression);

    if (result.ok) {
        throw new Error(`esperava erro: ${expression}`);
    }

    return result.error;
}

describe('parseCron', () => {
    it('le os cinco campos e expande listas, intervalos e passos', () => {
        const spec = specOf('*/15 0 1,15 * *');

        expect(spec.macro).toBeNull();
        expect(spec.normalized).toBe('*/15 0 1,15 * *');
        expect(spec.fields.minute.values).toEqual([0, 15, 30, 45]);
        expect(spec.fields.minute.starred).toBe(true);
        expect(spec.fields.hour.values).toEqual([0]);
        expect(spec.fields.day_of_month.values).toEqual([1, 15]);
    });

    it('expande um passo com inicio no fim do campo', () => {
        expect(specOf('5/15 * * * *').fields.minute.values).toEqual([
            5, 20, 35, 50,
        ]);
    });

    it('le nomes de mes e de dia sem diferenciar maiusculas', () => {
        const months = specOf('0 0 * JAN-Mar *');
        const days = specOf('0 0 * * Mon-FRI');

        expect(months.fields.month.values).toEqual([1, 2, 3]);
        expect(days.fields.day_of_week.values).toEqual([1, 2, 3, 4, 5]);
    });

    it('trata 0 e 7 como domingo', () => {
        expect(specOf('0 0 * * 0').fields.day_of_week.values).toEqual([0]);
        expect(specOf('0 0 * * 7').fields.day_of_week.values).toEqual([0]);
        expect(specOf('0 0 * * 5-7').fields.day_of_week.values).toEqual([
            0, 5, 6,
        ]);
    });

    it('expande as macros para os cinco campos', () => {
        const spec = specOf('@weekly');

        expect(spec.macro).toBe('@weekly');
        expect(spec.input).toBe('@weekly');
        expect(spec.normalized).toBe('0 0 * * 0');
        expect(spec.fields.minute.values).toEqual([0]);
        expect(spec.fields.day_of_week.values).toEqual([0]);
    });

    it('so marca starred quando o campo comeca com asterisco', () => {
        expect(specOf('* * * * *').fields.minute.starred).toBe(true);
        expect(specOf('*/5 * * * *').fields.minute.starred).toBe(true);
        expect(specOf('1,2 * * * *').fields.minute.starred).toBe(false);
    });

    it('aplica a regra Vixie do dia', () => {
        expect(specOf('* * * * *').dayRule).toBe('any');
        // Asterisco com passo continua sem restringir (decisao do grill).
        expect(specOf('* * */2 * *').dayRule).toBe('any');
        expect(specOf('* * */2 * 1').dayRule).toBe('day_of_week');
        expect(specOf('* * 1 * *').dayRule).toBe('day_of_month');
        expect(specOf('* * 1 * 1').dayRule).toBe('either');
        expect(specOf('* * 1 * */2').dayRule).toBe('day_of_month');
    });

    it('normaliza espacos, tabulacoes e quebras de linha', () => {
        expect(specOf('0\t9  * *\n1-5').normalized).toBe('0 9 * * 1-5');
    });

    it('recusa expressao vazia', () => {
        expect(errorOf('   ').code).toBe('empty');
    });

    it('recusa numero de campos diferente de cinco', () => {
        expect(errorOf('* * * *').code).toBe('field_count');
        expect(errorOf('* * * * * *').code).toBe('field_count');
        expect(errorOf('* * * *').token).toBe('4');
    });

    it('recusa macro desconhecida e macro com campos', () => {
        expect(errorOf('@sometimes').code).toBe('unknown_macro');
        expect(errorOf('@daily 5').code).toBe('macro_with_fields');
    });

    it('recusa nome fora dos campos de mes e dia', () => {
        expect(errorOf('jan * * * *').code).toBe('unknown_name');
        expect(errorOf('* * * * jan').code).toBe('unknown_name');
    });

    it('recusa valor fora da faixa', () => {
        expect(errorOf('60 * * * *').code).toBe('invalid_value');
        expect(errorOf('* 24 * * *').code).toBe('invalid_value');
        expect(errorOf('* * 0 * *').code).toBe('invalid_value');
        expect(errorOf('* * * 13 *').code).toBe('invalid_value');
        expect(errorOf('* * * * 8').code).toBe('invalid_value');
    });

    it('recusa intervalo invertido e passo invalido', () => {
        expect(errorOf('10-5 * * * *').code).toBe('invalid_range');
        expect(errorOf('*/0 * * * *').code).toBe('invalid_step');
        expect(errorOf('*/a * * * *').code).toBe('invalid_step');
        expect(errorOf('1/2/3 * * * *').code).toBe('invalid_step');
    });

    it('recusa lista com item vazio', () => {
        expect(errorOf('1,,2 * * * *').code).toBe('invalid_syntax');
    });

    it('aponta o campo do erro', () => {
        const error = errorOf('0 0 * * 8');

        expect(error.field).toBe('day_of_week');
        expect(error.token).toBe('8');
    });
});
