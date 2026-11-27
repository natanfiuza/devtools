/**
 * As proximas execucoes de uma expressao cron, no fuso do navegador.
 *
 * O calendario do cron e de parede (hora local), mas um `Date` e um instante.
 * A ponte entre os dois e o `Intl`, com o fuso **explicito**: o motor anda em
 * dias de parede (ano, mes, dia) e so converte para instante quando a hora e o
 * minuto casam. Duas armadilhas do horario de verao ficam resolvidas pela
 * propria conversao:
 *
 * - hora que nao existe (o salto da primavera) nao tem instante que volte para
 *   aquela parede, entao e pulada;
 * - hora que se repete (o retorno do outono) tem dois instantes; o motor usa o
 *   primeiro, para a lista nao repetir a mesma parede duas vezes.
 *
 * O fuso nao e global: entra por parametro. O teste passa `UTC` ou um fuso com
 * DST e pina instantes absolutos, sem depender do fuso da maquina.
 *
 * O horizonte existe pelas expressoes impossiveis (`0 0 30 2 *`): depois de
 * oito anos sem achar nada a busca para e devolve `exhausted`, em vez de girar
 * para sempre.
 */

import type { CronSpec, DayRule } from '../types';

const MINUTE = 60_000;
const DAY = 86_400_000;

/** Oito anos cobrem um ciclo de bissexto e os 29 de fevereiro. */
export const MAX_SEARCH_DAYS = 366 * 8 + 31;

const WEEKDAY_NAMES = 7;

export interface WallClock {
    year: number;
    month: number;
    day: number;
    hour: number;
    minute: number;
    second: number;
}

export interface NextRuns {
    runs: Date[];
    exhausted: boolean;
}

/** O fuso do navegador; `UTC` quando o ambiente nao informa. */
export function browserTimeZone(): string {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

const formatters = new Map<string, Intl.DateTimeFormat>();

function zoneFormatter(zone: string): Intl.DateTimeFormat {
    let formatter = formatters.get(zone);

    if (formatter === undefined) {
        formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: zone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        formatters.set(zone, formatter);
    }

    return formatter;
}

/** A parede (hora local) de um instante, no fuso dado. */
export function wallOf(ms: number, zone: string): WallClock {
    const parts = zoneFormatter(zone).formatToParts(new Date(ms));
    const read = (type: Intl.DateTimeFormatPartTypes): number =>
        Number(parts.find((part) => part.type === type)?.value ?? '0');

    return {
        year: read('year'),
        month: read('month'),
        day: read('day'),
        // `h23` ja entrega 00-23; o `% 24` so cobre um motor antigo que devolva 24.
        hour: read('hour') % 24,
        minute: read('minute'),
        second: read('second'),
    };
}

/** Quanto a parede do fuso esta adiantada em relacao ao UTC, em ms. */
function offsetAt(ms: number, zone: string): number {
    const wall = wallOf(ms, zone);
    const asUtc = Date.UTC(
        wall.year,
        wall.month - 1,
        wall.day,
        wall.hour,
        wall.minute,
        wall.second,
    );

    return asUtc - Math.floor(ms / 1000) * 1000;
}

/**
 * O instante de uma parede, ou `null` quando ela nao existe (salto do DST).
 *
 * Os deslocamentos sao amostrados perto da parede (um dia para cada lado) em
 * vez de presumidos: um palpite fixo de fuso erraria justamente na virada. Dos
 * candidatos que voltam para a mesma parede, o menor (o mais cedo) vence.
 */
export function instantOfWallClock(
    year: number,
    month: number,
    day: number,
    hour: number,
    minute: number,
    zone: string,
): number | null {
    const target = Date.UTC(year, month - 1, day, hour, minute);
    const offsets = new Set<number>();

    for (const delta of [-DAY, -3_600_000, 0, 3_600_000, DAY]) {
        offsets.add(offsetAt(target + delta, zone));
    }

    const candidates = [...offsets]
        .map((offset) => target - offset)
        .filter((ms) => {
            const wall = wallOf(ms, zone);

            return (
                wall.year === year &&
                wall.month === month &&
                wall.day === day &&
                wall.hour === hour &&
                wall.minute === minute
            );
        })
        .sort((a, b) => a - b);

    return candidates[0] ?? null;
}

function dayMatches(
    rule: DayRule,
    dayOfMonth: number[],
    dayOfWeek: number[],
    day: number,
    weekday: number,
): boolean {
    switch (rule) {
        case 'any':
            return true;
        case 'day_of_month':
            return dayOfMonth.includes(day);
        case 'day_of_week':
            return dayOfWeek.includes(weekday);
        case 'either':
            return dayOfMonth.includes(day) || dayOfWeek.includes(weekday);
    }
}

/**
 * As `count` proximas execucoes estritamente depois de `from`.
 *
 * `from` e arredondado para o minuto seguinte: a execucao do proprio minuto ja
 * passou quando a pessoa consulta a expressao.
 */
export function nextRuns(
    spec: CronSpec,
    from: Date,
    count = 10,
    zone = browserTimeZone(),
): NextRuns {
    const runs: Date[] = [];
    const after = Math.floor(from.getTime() / MINUTE) * MINUTE + MINUTE;
    const start = wallOf(after, zone);
    const hours = spec.fields.hour.values;
    const minutes = spec.fields.minute.values;
    const months = spec.fields.month.values;
    const daysOfMonth = spec.fields.day_of_month.values;
    const daysOfWeek = spec.fields.day_of_week.values;

    // O cursor e um dia de parede guardado num `Date` UTC: somar um dia nele
    // acerta mes e ano sem calendario proprio.
    const cursor = new Date(Date.UTC(start.year, start.month - 1, start.day));

    for (let scanned = 0; scanned <= MAX_SEARCH_DAYS; scanned += 1) {
        const year = cursor.getUTCFullYear();
        const month = cursor.getUTCMonth() + 1;
        const day = cursor.getUTCDate();
        const weekday = cursor.getUTCDay() % WEEKDAY_NAMES;

        if (
            months.includes(month) &&
            dayMatches(spec.dayRule, daysOfMonth, daysOfWeek, day, weekday)
        ) {
            for (const hour of hours) {
                for (const minute of minutes) {
                    const instant = instantOfWallClock(
                        year,
                        month,
                        day,
                        hour,
                        minute,
                        zone,
                    );

                    if (instant === null || instant < after) {
                        continue;
                    }

                    runs.push(new Date(instant));

                    if (runs.length === count) {
                        return { runs, exhausted: false };
                    }
                }
            }
        }

        cursor.setUTCDate(cursor.getUTCDate() + 1);
    }

    return { runs, exhausted: true };
}
