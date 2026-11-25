import type { DiffResult, JsonChange } from '../types';

/**
 * O relatorio em texto do diff - o que o botao "copiar" e o "baixar" entregam.
 *
 * O relatorio e o diff **inteiro**, e nao o recorte dos dois paineis: o formato
 * e o do `diff` de todo dia (`@@`, `-`, `+`), para colar num e-mail, numa issue
 * ou num PR e a leitura continuar obvia. No modo JSON, as linhas viram
 * caminhos, no dialeto do JSON Path.
 *
 * O relatorio nao tem texto proprio: os rotulos chegam traduzidos de fora
 * (i18n) e aqui so entram dados e simbolos. E o mesmo contrato do motor, que
 * devolve codigos e nao frases.
 */

/** Os rotulos traduzidos que o relatorio usa. */
export interface ReportLabels {
    title: string;
    text: string;
    json: string;
    original: string;
    modified: string;
    added: string;
    removed: string;
    changed: string;
    noChanges: string;
    truncated: string;
    invalid: string;
    line: string;
    column: string;
}

function header(mode: string, labels: ReportLabels): string[] {
    return [labels.title, `${mode}: ${labels.original} → ${labels.modified}`];
}

function textReport(
    result: Extract<DiffResult, { mode: 'text' }>,
    labels: ReportLabels,
): string[] {
    const lines = header(labels.text, labels);

    lines.push(
        `${labels.added}: ${result.text.added} · ${labels.removed}: ${result.text.removed}`,
    );

    if (result.text.hunks.length === 0) {
        lines.push(labels.noChanges);
    }

    for (const hunk of result.text.hunks) {
        lines.push(`@@ -${hunk.leftStart + 1} +${hunk.rightStart + 1} @@`);

        for (const line of hunk.lines) {
            lines.push(`${line.type === 'removed' ? '-' : '+'}${line.text}`);
        }
    }

    if (result.text.truncated) {
        lines.push(labels.truncated);
    }

    return lines;
}

function changeLine(change: JsonChange): string {
    if (change.kind === 'added') {
        return `+ ${change.path} ${change.right ?? ''}`;
    }

    if (change.kind === 'removed') {
        return `- ${change.path} ${change.left ?? ''}`;
    }

    return `~ ${change.path} ${change.left ?? ''} → ${change.right ?? ''}`;
}

function jsonReport(
    result: Extract<DiffResult, { mode: 'json' }>,
    labels: ReportLabels,
): string[] {
    const lines = header(labels.json, labels);
    const json = result.json;

    if (!json.ok) {
        const side = json.side === 'left' ? labels.original : labels.modified;
        const position: string[] = [];

        if (json.line !== null) {
            position.push(`${labels.line} ${json.line}`);
        }

        if (json.column !== null) {
            position.push(`${labels.column} ${json.column}`);
        }

        // A posicao sai so com os numeros que o motor deu a conhecer: linha e
        // coluna sao uma dupla, mas nenhuma das duas e garantida.
        lines.push(
            `${labels.invalid}: ${side}${position.length === 0 ? '' : ` (${position.join(', ')})`}`,
        );

        return lines;
    }

    lines.push(
        `${labels.added}: ${json.added} · ${labels.removed}: ${json.removed} · ${labels.changed}: ${json.changed}`,
    );

    if (json.changes.length === 0) {
        lines.push(labels.noChanges);
    }

    for (const change of json.changes) {
        lines.push(changeLine(change));
    }

    return lines;
}

export function buildReport(result: DiffResult, labels: ReportLabels): string {
    return (
        result.mode === 'text'
            ? textReport(result, labels)
            : jsonReport(result, labels)
    ).join('\n');
}
