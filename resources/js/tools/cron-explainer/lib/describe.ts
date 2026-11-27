/**
 * A forma de cada campo, sem frase pronta.
 *
 * O motor nao escreve texto: devolve um descritor (`any`, `at`, `range`,
 * `step_star`, `step_range`) com os numeros que a tela precisa, e a tela os
 * traduz. E o que permite a mesma explicacao aparecer nos cinco idiomas sem
 * uma string por combinacao.
 *
 * A expansao (`values`) vai junto porque a tela a usa para os nomes de mes e
 * de dia: `mon-fri` mostra o intervalo, mas a lista `1,3,5` mostra os nomes.
 */

import {
    CRON_FIELD_IDS,
    type CronField,
    type CronFieldId,
    type CronSpec,
    type FieldDescription,
} from '../types';

/** Descreve um campo: o tipo do descritor e os numeros que ele carrega. */
export function describeField(field: CronField): FieldDescription {
    const base = {
        id: field.id,
        values: field.values,
        from: null as number | null,
        to: null as number | null,
        step: null as number | null,
    };

    if (field.raw === '*') {
        return { ...base, kind: 'any' };
    }

    const atom = field.atoms[0];

    if (field.atoms.length === 1 && atom !== undefined) {
        if (atom.kind === 'step' && atom.starred) {
            return { ...base, kind: 'step_star', step: atom.step };
        }

        if (atom.kind === 'step') {
            return {
                ...base,
                kind: 'step_range',
                from: atom.from,
                to: atom.to,
                step: atom.step,
            };
        }

        if (atom.kind === 'range') {
            return {
                ...base,
                kind: 'range',
                from: atom.from,
                to: atom.to,
            };
        }

        if (atom.kind === 'value') {
            return { ...base, kind: 'at', values: [atom.from] };
        }
    }

    // Lista, lista de intervalos, ou qualquer combinacao: a expansao e o que
    // descreve, e a tela a mostra como valores (com nomes quando for mes/dia).
    return { ...base, kind: 'at' };
}

/** Os cinco campos, na ordem, com o descritor de cada um. */
export function describeCron(spec: CronSpec): FieldDescription[] {
    return CRON_FIELD_IDS.map((id: CronFieldId) =>
        describeField(spec.fields[id]),
    );
}
