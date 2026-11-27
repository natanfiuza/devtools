import { describe, expect, it } from 'vitest';
import { nextRuns } from '@/tools/cron-explainer/lib/next';
import { parseCron } from '@/tools/cron-explainer/lib/parse';

/**
 * As proximas execucoes.
 *
 * O fuso entra explicito em todo caso, entao nenhum teste depende do fuso da
 * maquina: `UTC` para o calendario puro e dois fusos com horario de verao para
 * as bordas. Os instantes sao comparados por ISO, que e absoluto.
 */

function runsOf(expression: string, from: string, count = 10, zone = 'UTC') {
    const parsed = parseCron(expression);

    if (!parsed.ok) {
        throw new Error(`expressao invalida: ${parsed.error.code}`);
    }

    return nextRuns(parsed.spec, new Date(from), count, zone);
}

function isosOf(expression: string, from: string, count = 10, zone = 'UTC') {
    return runsOf(expression, from, count, zone).runs.map((run) =>
        run.toISOString(),
    );
}

describe('nextRuns', () => {
    it('anda pelos dias uteis e para na quantidade pedida', () => {
        expect(isosOf('0 9 * * 1-5', '2026-01-01T08:00:00.000Z', 5)).toEqual([
            '2026-01-01T09:00:00.000Z',
            '2026-01-02T09:00:00.000Z',
            '2026-01-05T09:00:00.000Z',
            '2026-01-06T09:00:00.000Z',
            '2026-01-07T09:00:00.000Z',
        ]);
    });

    it('respeita o passo dos minutos', () => {
        expect(isosOf('*/15 * * * *', '2026-05-10T10:07:00.000Z', 4)).toEqual([
            '2026-05-10T10:15:00.000Z',
            '2026-05-10T10:30:00.000Z',
            '2026-05-10T10:45:00.000Z',
            '2026-05-10T11:00:00.000Z',
        ]);
    });

    it('conta o minuto seguinte, nunca o minuto corrente', () => {
        expect(isosOf('* * * * *', '2026-01-01T00:00:00.000Z', 1)).toEqual([
            '2026-01-01T00:01:00.000Z',
        ]);
        expect(isosOf('* * * * *', '2026-01-01T00:00:30.000Z', 1)).toEqual([
            '2026-01-01T00:01:00.000Z',
        ]);
    });

    it('encontra o 29 de fevereiro', () => {
        expect(isosOf('0 0 29 2 *', '2026-01-01T00:00:00.000Z', 2)).toEqual([
            '2028-02-29T00:00:00.000Z',
            '2032-02-29T00:00:00.000Z',
        ]);
    });

    it('usa OR quando dia do mes e dia da semana estao restritos', () => {
        expect(isosOf('0 0 13 * 5', '2026-01-01T00:00:00.000Z', 3)).toEqual([
            '2026-01-02T00:00:00.000Z',
            '2026-01-09T00:00:00.000Z',
            '2026-01-13T00:00:00.000Z',
        ]);
    });

    it('para no horizonte quando a expressao nunca casa', () => {
        const result = runsOf('0 0 30 2 *', '2026-01-01T00:00:00.000Z');

        expect(result.runs).toEqual([]);
        expect(result.exhausted).toBe(true);
    });

    it('converte a parede para o fuso pedido', () => {
        // Sao Paulo nao tem mais horario de verao: 09:00 locais sao 12:00 UTC.
        expect(
            isosOf(
                '0 9 * * *',
                '2026-01-01T03:00:00.000Z',
                1,
                'America/Sao_Paulo',
            ),
        ).toEqual(['2026-01-01T12:00:00.000Z']);
    });

    it('pula a hora que nao existe no salto do horario de verao', () => {
        // 08/03/2026: as 02:00 EST viram 03:00 EDT. 02:30 nao acontece.
        expect(
            isosOf(
                '30 2 * * *',
                '2026-03-07T05:00:00.000Z',
                2,
                'America/New_York',
            ),
        ).toEqual(['2026-03-07T07:30:00.000Z', '2026-03-09T06:30:00.000Z']);
    });

    it('usa o primeiro instante quando a hora se repete', () => {
        // 01/11/2026: 01:30 EDT (05:30Z) e de novo 01:30 EST (06:30Z).
        // A lista guarda a primeira parede; a seguinte e a do dia 2.
        expect(
            isosOf(
                '30 1 * * *',
                '2026-11-01T04:00:00.000Z',
                2,
                'America/New_York',
            ),
        ).toEqual(['2026-11-01T05:30:00.000Z', '2026-11-02T06:30:00.000Z']);
    });
});
