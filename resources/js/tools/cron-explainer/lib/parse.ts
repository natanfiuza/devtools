/**
 * O dialeto cron de cinco campos, lido sem biblioteca.
 *
 * A gramatica e a do Vixie cron: `*`, listas, intervalos, passos, nomes de mes
 * e de dia da semana e as macros `@...`. O que nao esta aqui - segundos,
 * `L`, `W`, `#`, `?` - e recusado com um codigo de erro proprio, em vez de
 * virar um campo que nunca casa.
 *
 * O dia da semana aceita `0` e `7` como domingo; a expansao normaliza o `7`
 * para `0` para o calculo e para a lista de valores terem uma forma so.
 *
 * `starred` responde pela semantica Vixie: so um campo que **comeca** com `*`
 * deixa de restringir o dia. Asterisco com passo continua sem restringir.
 */

import {
    CRON_FIELD_IDS,
    CRON_MACROS,
    type CronAtom,
    type CronError,
    type CronErrorCode,
    type CronField,
    type CronFieldId,
    type CronMacro,
    type CronParseResult,
    type CronSpec,
    type DayRule,
} from '../types';

/** O que cada macro vale em cinco campos. */
export const MACRO_EXPANSIONS: Record<CronMacro, string> = {
    '@yearly': '0 0 1 1 *',
    '@annually': '0 0 1 1 *',
    '@monthly': '0 0 1 * *',
    '@weekly': '0 0 * * 0',
    '@daily': '0 0 * * *',
    '@midnight': '0 0 * * *',
    '@hourly': '0 * * * *',
};

interface Bounds {
    min: number;
    max: number;
}

/** A faixa aceita em cada campo; o dia da semana inclui o `7` do domingo. */
const FIELD_BOUNDS: Record<CronFieldId, Bounds> = {
    minute: { min: 0, max: 59 },
    hour: { min: 0, max: 23 },
    day_of_month: { min: 1, max: 31 },
    month: { min: 1, max: 12 },
    day_of_week: { min: 0, max: 7 },
};

const MONTH_NAMES: Record<string, number> = {
    jan: 1,
    feb: 2,
    mar: 3,
    apr: 4,
    may: 5,
    jun: 6,
    jul: 7,
    aug: 8,
    sep: 9,
    oct: 10,
    nov: 11,
    dec: 12,
};

const DAY_NAMES: Record<string, number> = {
    sun: 0,
    mon: 1,
    tue: 2,
    wed: 3,
    thu: 4,
    fri: 5,
    sat: 6,
};

/** Onde os nomes valem; nos outros campos uma letra e um nome invalido. */
const NAME_TABLES: Partial<Record<CronFieldId, Record<string, number>>> = {
    month: MONTH_NAMES,
    day_of_week: DAY_NAMES,
};

const DIGITS = /^\d+$/;
const LETTERS = /^[a-z]+$/i;

function fail(
    code: CronErrorCode,
    field: CronFieldId | null,
    token: string,
): CronError {
    return { code, field, token };
}

function isMacro(value: string): value is CronMacro {
    return (CRON_MACROS as readonly string[]).includes(value);
}

/** Le um numero ou um nome do campo; devolve o erro quando nao ha valor. */
function readToken(
    id: CronFieldId,
    token: string,
    raw: string,
): number | CronError {
    const lower = token.toLowerCase();

    if (LETTERS.test(lower)) {
        const table = NAME_TABLES[id];
        const named = table?.[lower];

        return named === undefined ? fail('unknown_name', id, raw) : named;
    }

    if (!DIGITS.test(token)) {
        return fail('invalid_value', id, raw);
    }

    const value = Number(token);
    const bounds = FIELD_BOUNDS[id];

    return value < bounds.min || value > bounds.max
        ? fail('invalid_value', id, raw)
        : value;
}

/**
 * Le um campo inteiro (uma lista separada por virgula).
 *
 * Devolve o proprio erro quando qualquer item falha: a tela aponta o campo e
 * o pedaco, e nao tenta adivinhar o resto.
 */
function parseField(id: CronFieldId, raw: string): CronField | CronError {
    const bounds = FIELD_BOUNDS[id];
    const items = raw.split(',');

    if (items.some((item) => item === '')) {
        return fail('invalid_syntax', id, raw);
    }

    const atoms: CronAtom[] = [];
    const values = new Set<number>();

    for (const item of items) {
        const parts = item.split('/');

        if (parts.length > 2) {
            return fail('invalid_step', id, item);
        }

        const base = parts[0] ?? '';
        const stepText = parts[1];
        let step = 1;

        if (stepText !== undefined) {
            if (!DIGITS.test(stepText) || Number(stepText) < 1) {
                return fail('invalid_step', id, item);
            }

            step = Number(stepText);
        }

        const hasStep = stepText !== undefined;
        let from: number;
        let to: number;
        let kind: CronAtom['kind'];

        if (base === '*') {
            from = bounds.min;
            to = bounds.max;
            kind = hasStep ? 'step' : 'any';
        } else if (base.includes('-')) {
            const [left, right, ...rest] = base.split('-');
            const start = readToken(id, left ?? '', item);

            if (typeof start !== 'number') {
                return start;
            }

            const end = readToken(id, right ?? '', item);

            if (typeof end !== 'number') {
                return end;
            }

            if (rest.length > 0 || start > end) {
                return fail('invalid_range', id, item);
            }

            from = start;
            to = end;
            kind = hasStep ? 'step' : 'range';
        } else {
            const start = readToken(id, base, item);

            if (typeof start !== 'number') {
                return start;
            }

            from = start;
            // `5/15` comeca em 5 e vai ate o fim do campo, como no Vixie.
            to = hasStep ? bounds.max : start;
            kind = hasStep ? 'step' : 'value';
        }

        atoms.push({ kind, from, to, step, starred: base === '*' });

        for (let value = from; value <= to; value += step) {
            values.add(id === 'day_of_week' && value === 7 ? 0 : value);
        }
    }

    return {
        id,
        raw,
        atoms,
        values: [...values].sort((a, b) => a - b),
        starred: raw.startsWith('*'),
    };
}

function dayRuleFor(dayOfMonth: CronField, dayOfWeek: CronField): DayRule {
    if (dayOfMonth.starred && dayOfWeek.starred) {
        return 'any';
    }

    if (dayOfMonth.starred) {
        return 'day_of_week';
    }

    if (dayOfWeek.starred) {
        return 'day_of_month';
    }

    return 'either';
}

function withFields(
    input: string,
    normalized: string,
    macro: CronMacro | null,
    tokens: string[],
): CronParseResult {
    const fields = {} as Record<CronFieldId, CronField>;

    for (const [index, id] of CRON_FIELD_IDS.entries()) {
        const parsed = parseField(id, tokens[index] ?? '');

        if ('code' in parsed) {
            return { ok: false, error: parsed };
        }

        fields[id] = parsed;
    }

    const spec: CronSpec = {
        input,
        normalized,
        macro,
        fields,
        dayRule: dayRuleFor(fields.day_of_month, fields.day_of_week),
    };

    return { ok: true, spec };
}

/** Le a expressao (ou a macro) e devolve a especificacao ou o erro. */
export function parseCron(source: string): CronParseResult {
    const input = source.trim();

    if (input === '') {
        return { ok: false, error: fail('empty', null, '') };
    }

    const tokens = input.split(/\s+/);

    if (input.startsWith('@')) {
        const macro = (tokens[0] ?? '').toLowerCase();

        if (!isMacro(macro)) {
            return {
                ok: false,
                error: fail('unknown_macro', null, tokens[0] ?? ''),
            };
        }

        if (tokens.length > 1) {
            return { ok: false, error: fail('macro_with_fields', null, macro) };
        }

        const expression = MACRO_EXPANSIONS[macro];

        return withFields(input, expression, macro, expression.split(' '));
    }

    if (tokens.length !== CRON_FIELD_IDS.length) {
        return {
            ok: false,
            error: fail('field_count', null, String(tokens.length)),
        };
    }

    return withFields(input, tokens.join(' '), null, tokens);
}
