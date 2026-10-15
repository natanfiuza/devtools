/**
 * Um instante entra, seis leituras saem.
 *
 * O que se cola aqui tem quatro formas, e a tool aceita as quatro sem
 * perguntar: o Unix em segundos (`1790856000`), o Unix em milissegundos
 * (`1790856000000`), a data escrita (`2026-10-01T12:00:00Z`) e um id que
 * carrega o proprio nascimento (UUID v7 e ULID).
 *
 * A unidade do numero e decidida pela **quantidade de digitos**, e a fronteira
 * e `1e11`: abaixo dela o numero e curto demais para ser um milissegundo que
 * faca sentido (1e10 milissegundos e a primeira semana de 1970, enquanto 1e10
 * segundos ainda e uma data, 2286), e acima dela o segundo nao cabe mais numa
 * data que alguem reconheca. Nao ha como acertar sempre - um `1e10` solto e
 * ambiguo de verdade -, e a tela mostra as duas unidades logo abaixo, entao
 * quem colou erra o palpite e ve o certo na linha de baixo.
 *
 * `now` entra por parametro em tudo que depende do relogio, para o teste poder
 * fixar o instante: o "tempo relativo" e uma comparacao com o agora, e um teste
 * que dependesse do relogio de verdade envelheceria sozinho.
 */

import { instantOfId } from './ids';

/** O que o texto colado era. */
export type InstantSource = 'seconds' | 'milliseconds' | 'date' | 'uuid_v7' | 'ulid';

export interface Instant {
    source: InstantSource;
    date: Date;
}

/** As seis linhas da saida, na ordem em que aparecem. */
export type ReadingLabel =
    | 'unix_seconds'
    | 'unix_ms'
    | 'iso_utc'
    | 'iso_local'
    | 'long_date'
    | 'relative';

export const READING_LABELS: readonly ReadingLabel[] = [
    'unix_seconds',
    'unix_ms',
    'iso_utc',
    'iso_local',
    'long_date',
    'relative',
];

export interface Reading {
    label: ReadingLabel;
    text: string;
}

/** A fronteira entre segundos e milissegundos, em digitos (ver o cabecalho). */
const MILLISECONDS_FROM = 1e11;

/** Um numero redondo de digitos e um numero; o resto e texto. */
const DIGITS = /^-?\d+$/;

function instantFrom(ms: number, source: InstantSource): Instant | null {
    const date = new Date(ms);

    // Fora da faixa que um `Date` aceita (ou um numero que nao e numero) nao ha
    // instante: `new Date(NaN)` existe, e so parece uma data.
    return Number.isFinite(date.getTime()) ? { source, date } : null;
}

/** Le um instante do texto colado, ou devolve `null` quando nao ha o que ler. */
export function readInstant(input: string): Instant | null {
    const text = input.trim();

    if (text === '') {
        return null;
    }

    // Os ids vem primeiro: 26 caracteres de ULID tambem passariam por texto de
    // data em alguns navegadores, e o instante deles e o que interessa.
    const id = instantOfId(text);

    if (id !== null) {
        return instantFrom(id.ms, id.kind);
    }

    if (DIGITS.test(text)) {
        const value = Number(text);
        const seconds = Math.abs(value) < MILLISECONDS_FROM;

        return instantFrom(seconds ? value * 1000 : value, seconds ? 'seconds' : 'milliseconds');
    }

    const parsed = Date.parse(text);

    return Number.isFinite(parsed) ? instantFrom(parsed, 'date') : null;
}

function pad(value: number): string {
    return String(Math.abs(value)).padStart(2, '0');
}

/**
 * O ISO 8601 no fuso de quem esta vendo, com o deslocamento escrito.
 *
 * Montado a mao porque o `Intl` nao tem essa forma: `dateStyle`/`timeStyle`
 * dão um texto localizado ("14/10/2026, 09:00:00 BRT"), e o que se quer aqui e
 * a forma que se cola de volta em qualquer lugar - e que volta ao mesmo
 * instante.
 */
export function isoLocal(date: Date): string {
    const offset = -date.getTimezoneOffset();
    const sign = offset < 0 ? '-' : '+';

    return (
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
        `T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}` +
        `${sign}${pad(Math.floor(Math.abs(offset) / 60))}:${pad(Math.abs(offset) % 60)}`
    );
}

/**
 * As unidades do "tempo relativo", da maior para a menor.
 *
 * O ano e o mes sao medias (365,25 dias e a doze avos): o `Intl` nao sabe em que
 * mes do calendario o instante caiu, e fingir precisao de calendario daria
 * "ha 1 mes" para 31 dias e 28.
 */
const RELATIVE_UNITS: readonly [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 31_557_600_000],
    ['month', 2_629_800_000],
    ['week', 604_800_000],
    ['day', 86_400_000],
    ['hour', 3_600_000],
    ['minute', 60_000],
    ['second', 1_000],
];

/** O "ha quanto tempo" no idioma, na maior unidade que ainda diz algo. */
export function relativeText(date: Date, now: number, locale: string): string {
    const delta = date.getTime() - now;
    const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const [unit, size] of RELATIVE_UNITS) {
        if (Math.abs(delta) >= size) {
            return format.format(Math.round(delta / size), unit);
        }
    }

    // Abaixo de um segundo nao ha unidade menor: o `numeric: 'auto'` diz
    // "agora" para o zero.
    return format.format(0, 'second');
}

/** As seis leituras do instante, na ordem de `READING_LABELS`. */
export function readingsFor(date: Date, now: number, locale: string): Reading[] {
    const ms = date.getTime();
    const long = new Intl.DateTimeFormat(locale, { dateStyle: 'full', timeStyle: 'long' });

    const texts: Record<ReadingLabel, string> = {
        unix_seconds: String(Math.floor(ms / 1000)),
        unix_ms: String(ms),
        iso_utc: date.toISOString(),
        iso_local: isoLocal(date),
        long_date: long.format(date),
        relative: relativeText(date, now, locale),
    };

    return READING_LABELS.map((label) => ({ label, text: texts[label] }));
}
