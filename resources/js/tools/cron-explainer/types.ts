/**
 * O cron de cinco campos, do jeito que a tool enxerga.
 *
 * Cada campo guarda tres leituras do mesmo texto: o `raw` que a pessoa
 * escreveu (para a tabela mostrar o que esta na tela), os `atoms` (o que cada
 * item da lista significa, para explicar `10-20/5` sem despejar a expansao) e
 * os `values` (a expansao ordenada, que o calculo das execucoes usa).
 *
 * `starred` nao e "o campo tem passo": e "o campo comeca com `*`". E o que a
 * semantica Vixie consulta para decidir se o dia-do-mes e o dia-da-semana
 * restringem a data, e `*` com passo continua sendo um `*` para essa pergunta.
 */

export const CRON_FIELD_IDS = [
    'minute',
    'hour',
    'day_of_month',
    'month',
    'day_of_week',
] as const;

export type CronFieldId = (typeof CRON_FIELD_IDS)[number];

export const CRON_MACROS = [
    '@yearly',
    '@annually',
    '@monthly',
    '@weekly',
    '@daily',
    '@midnight',
    '@hourly',
] as const;

export type CronMacro = (typeof CRON_MACROS)[number];

/** Quem decide o dia, quando o campo do mes e o da semana discordam. */
export type DayRule = 'any' | 'day_of_month' | 'day_of_week' | 'either';

export type CronAtomKind = 'any' | 'value' | 'range' | 'step';

export interface CronAtom {
    kind: CronAtomKind;
    from: number;
    to: number;
    step: number;
    /** `true` quando o passo cobre o campo inteiro (asterisco com passo). */
    starred: boolean;
}

export interface CronField {
    id: CronFieldId;
    raw: string;
    atoms: CronAtom[];
    values: number[];
    starred: boolean;
}

export interface CronSpec {
    /** O texto como chegou (com a macro, se houver). */
    input: string;
    /** A expressao de cinco campos que a macro expandiu. */
    normalized: string;
    macro: CronMacro | null;
    fields: Record<CronFieldId, CronField>;
    dayRule: DayRule;
}

export type CronErrorCode =
    | 'empty'
    | 'field_count'
    | 'unknown_macro'
    | 'macro_with_fields'
    | 'unknown_name'
    | 'invalid_value'
    | 'invalid_range'
    | 'invalid_step'
    | 'invalid_syntax';

export interface CronError {
    code: CronErrorCode;
    field: CronFieldId | null;
    /** O pedaco que falhou, para a tela mostrar junto do erro. */
    token: string;
}

export type CronParseResult =
    { ok: true; spec: CronSpec } | { ok: false; error: CronError };

/** Como a tela conta o que o campo significa. */
export type FieldDescriptorKind =
    'any' | 'step_star' | 'step_range' | 'range' | 'at';

export interface FieldDescription {
    id: CronFieldId;
    kind: FieldDescriptorKind;
    values: number[];
    from: number | null;
    to: number | null;
    step: number | null;
}
