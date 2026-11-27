/**
 * A lista das execucoes e o texto que se copia.
 *
 * A formatacao e do `Intl` no locale da interface, no fuso escolhido: a mesma
 * execucao aparece como `qui., 1 de jan. de 2026, 09:00` em `pt_BR` e
 * `Thu, Jan 1, 2026, 09:00` em `en`. Os rotulos do relatorio chegam
 * traduzidos da tela; o motor nao conhece i18n.
 */

export interface ReportLabels {
    /** "Fuso horario" / "Time zone". */
    zone: string;
    /** "Proximas execucoes" / "Next runs". */
    runs: string;
    /** "Nenhuma execucao no horizonte." */
    empty: string;
}

export interface ReportInput {
    expression: string;
    zone: string;
    locale: string;
    runs: number[];
    exhausted: boolean;
    labels: ReportLabels;
}

/** Uma execucao no formato longo do locale, no fuso dado. */
export function formatRun(ms: number, zone: string, locale: string): string {
    return new Intl.DateTimeFormat(locale.replace('_', '-'), {
        timeZone: zone,
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(new Date(ms));
}

/** O relatorio copiavel: expressao, fuso e uma execucao por linha. */
export function buildReport(input: ReportInput): string {
    const lines = [
        input.expression,
        `${input.labels.zone}: ${input.zone}`,
        '',
        `${input.labels.runs}:`,
    ];

    if (input.runs.length === 0) {
        lines.push(input.labels.empty);
    } else {
        input.runs.forEach((ms, index) => {
            lines.push(
                `${index + 1}. ${formatRun(ms, input.zone, input.locale)}`,
            );
        });

        if (input.exhausted) {
            lines.push(input.labels.empty);
        }
    }

    return lines.join('\n');
}
