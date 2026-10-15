import { describe, expect, it } from 'vitest';
import { ulid, uuidV7 } from '@/tools/timestamp-uuid/lib/ids';
import {
    isoLocal,
    readInstant,
    readingsFor,
    relativeText,
    READING_LABELS,
    type Instant,
} from '@/tools/timestamp-uuid/lib/time';

/**
 * A leitura de um instante, sem tela no meio.
 *
 * O instante do teste entra sempre pela mao - `readingsFor` e `relativeText`
 * recebem o "agora" por parametro -, para nenhum caso depender do relogio de
 * quem roda a suite.
 *
 * Os ids usados como entrada vem do `ids.ts`: ali eles sao o objeto do teste, e
 * aqui sao so uma forma de escrever um instante. As contas do ULID e do v7 nao
 * sao conferidas de novo neste arquivo (`ids.test.ts` e o lugar delas) - o que
 * se cobra aqui e a leitura.
 */

/** Um instante fixo: 2026-10-14 12:00 UTC. */
const AGORA = Date.UTC(2026, 9, 14, 12, 0, 0);

/** O mesmo instante em segundos, que e a unidade do Unix curto. */
const AGORA_S = AGORA / 1000;

function ler(input: string): Instant {
    const instant = readInstant(input);

    if (instant === null) {
        throw new Error(`esperava um instante, veio a recusa: ${input}`);
    }

    return instant;
}

/** As leituras de um instante, no idioma dos testes. */
function leituras(input: string, now: number = AGORA): Record<string, string> {
    const rows = readingsFor(ler(input).date, now, 'pt-BR');

    return Object.fromEntries(rows.map((row) => [row.label, row.text]));
}

describe('readInstant', () => {
    it('le os digitos como segundos', () => {
        const instant = ler(String(AGORA_S));

        expect(instant.source).toBe('seconds');
        expect(instant.date.getTime()).toBe(AGORA);
    });

    it('le os digitos longos como milissegundos', () => {
        const instant = ler(String(AGORA));

        expect(instant.source).toBe('milliseconds');
        expect(instant.date.getTime()).toBe(AGORA);
    });

    it('decide pelo tamanho do numero, na fronteira de 1e11', () => {
        // 11 digitos ainda e um segundo (o ano 5138 e estranho, mas e uma
        // data); 12 ja e um milissegundo (1973, que e uma data de verdade).
        expect(ler('99999999999').source).toBe('seconds');
        expect(ler('100000000000').source).toBe('milliseconds');
    });

    it('le o numero negativo como antes de 1970', () => {
        const instant = ler('-1');

        expect(instant.source).toBe('seconds');
        expect(instant.date.getTime()).toBe(-1000);
    });

    it('le a data escrita', () => {
        const instant = ler('2026-10-01T12:00:00Z');

        expect(instant.source).toBe('date');
        expect(instant.date.toISOString()).toBe('2026-10-01T12:00:00.000Z');
    });

    it('le a data sem a hora', () => {
        expect(ler('2026-10-01').date.toISOString()).toBe('2026-10-01T00:00:00.000Z');
    });

    it('le o instante de um UUID v7 e de um ULID', () => {
        expect(ler(uuidV7(AGORA))).toEqual({ source: 'uuid_v7', date: new Date(AGORA) });
        expect(ler(ulid(AGORA))).toEqual({ source: 'ulid', date: new Date(AGORA) });
    });

    it('recusa o que nao e um instante', () => {
        expect(readInstant('')).toBeNull();
        expect(readInstant('   ')).toBeNull();
        expect(readInstant('nada')).toBeNull();
        expect(readInstant('2026-13-45')).toBeNull();

        // Vinte digitos: um numero, mas um instante que nao cabe em `Date`.
        expect(readInstant('99999999999999999999')).toBeNull();
    });
});

describe('as seis leituras', () => {
    it('saem sempre na mesma ordem', () => {
        expect(READING_LABELS).toEqual([
            'unix_seconds',
            'unix_ms',
            'iso_utc',
            'iso_local',
            'long_date',
            'relative',
        ]);
        expect(readingsFor(new Date(AGORA), AGORA, 'pt-BR').map((row) => row.label)).toEqual(
            READING_LABELS,
        );
    });

    it('os dois Unix sao o mesmo instante em unidades diferentes', () => {
        const rows = leituras(String(AGORA_S));

        expect(rows['unix_seconds']).toBe(String(AGORA_S));
        expect(rows['unix_ms']).toBe(String(AGORA));
    });

    it('o ISO de cima e o UTC', () => {
        expect(leituras(String(AGORA_S))['iso_utc']).toBe('2026-10-14T12:00:00.000Z');
    });

    it('o ISO local volta ao mesmo instante', () => {
        const date = new Date(AGORA);
        const local = isoLocal(date);

        // A prova de que o deslocamento esta escrito certo: relido, ele da o
        // mesmo instante - e nao um na hora do fuso.
        expect(Date.parse(local)).toBe(AGORA);
        expect(local).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/);
    });

    it('a data por extenso sai no idioma', () => {
        expect(leituras(String(AGORA_S))['long_date']).toContain('2026');
        expect(leituras(String(AGORA_S))['long_date']).toContain('outubro');
    });

    it('o tempo relativo acompanha o agora', () => {
        expect(leituras(String(AGORA_S))['relative']).not.toMatch(/\d/);
    });
});

describe('relativeText', () => {
    const format = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });
    const em = (ms: number): string => relativeText(new Date(AGORA + ms), AGORA, 'pt-BR');

    it('escolhe a maior unidade que ainda diz algo', () => {
        expect(em(-2 * 31_557_600_000)).toBe(format.format(-2, 'year'));
        expect(em(-90 * 86_400_000)).toBe(format.format(-3, 'month'));
        expect(em(-3 * 86_400_000)).toBe(format.format(-3, 'day'));
        expect(em(-5 * 3_600_000)).toBe(format.format(-5, 'hour'));
        expect(em(-5 * 60_000)).toBe(format.format(-5, 'minute'));
        expect(em(-5_000)).toBe(format.format(-5, 'second'));
    });

    it('anda para a frente tambem', () => {
        expect(em(3 * 86_400_000)).toBe(format.format(3, 'day'));
    });

    it('abaixo de um segundo nao inventa unidade', () => {
        expect(em(500)).toBe(format.format(0, 'second'));
        expect(em(-500)).toBe(format.format(0, 'second'));
    });
});
