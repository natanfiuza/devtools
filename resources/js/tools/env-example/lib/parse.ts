/**
 * O `.env` linha a linha, sem interpretar o valor.
 *
 * A tool nao carrega o arquivo: ela precisa saber onde a chave termina e onde o
 * valor comeca para esvaziar so o valor sensivel. Entao o parser separa
 * `lead` (indentacao + `export ` + chave + `=` + espacos), aspas, valor e
 * comentario em linha, e guarda a linha crua para o resto do arquivo sair igual.
 *
 * Regras do dialeto, na parte que a mascara usa:
 * - `export ` opcional, antes da chave;
 * - valor entre aspas simples ou duplas pode atravessar linhas;
 * - aspas duplas aceitam `\"` e `\\`; aspas simples sao literais;
 * - `#` fora de aspas e comentario quando comeca o valor ou segue um espaco;
 * - CRLF e aceito na leitura (a saida volta em LF);
 * - linha sem `=` legivel fica inteira e entra na lista de invalidas.
 */

import type { EnvDocument, EnvLine, EnvPairLine } from '../types';

/** Chave no comeco da linha, com `export ` opcional e o `=` obrigatorio. */
const PAIR = /^(\s*(?:export\s+)?)([A-Za-z_][A-Za-z0-9_.-]*)(\s*)=/;

function findClosingQuote(
    text: string,
    quote: '"' | "'",
    start: number,
): number {
    for (let i = start; i < text.length; i += 1) {
        const char = text[i] ?? '';

        if (quote === '"' && char === '\\') {
            // O escape protege o caractere seguinte - inclusive uma aspa.
            i += 1;

            continue;
        }

        if (char === quote) {
            return i;
        }
    }

    return -1;
}

/** Separa o comentario em linha de um valor sem aspas. */
function splitUnquoted(text: string): { value: string; comment: string } {
    for (let i = 0; i < text.length; i += 1) {
        if (text[i] !== '#') {
            continue;
        }

        const before = text[i - 1];

        if (i === 0 || (before !== undefined && /\s/.test(before))) {
            let start = i;

            while (start > 0 && /\s/.test(text[start - 1] ?? '')) {
                start -= 1;
            }

            return {
                value: text.slice(0, start).trimEnd(),
                comment: text.slice(start),
            };
        }
    }

    return { value: text.trimEnd(), comment: '' };
}

/**
 * Le o comentario que segue a aspa de fechamento.
 *
 * Depois da aspa so cabem espacos e um `#`; qualquer outra coisa e um par que
 * o parser nao sabe explicar - e a linha e guardada como invalida em vez de
 * virar um valor com pedaco de comentario no meio.
 */
function readComment(after: string): string | null {
    const rest = after.trimEnd();

    if (rest === '') {
        return '';
    }

    if (!rest.trimStart().startsWith('#')) {
        return null;
    }

    return rest;
}

/** Le o valor do par, com aspas e possivel continuacao de linha. */
function readValue(
    lines: string[],
    index: number,
    lead: string,
    key: string,
): { pair: EnvPairLine | null; consumed: number } {
    const raw = lines[index] ?? '';
    const rest = raw.slice(lead.length);
    const padding = rest.length - rest.trimStart().length;
    const valuePart = rest.slice(padding);
    const fullLead = `${lead}${' '.repeat(padding)}`;

    if (valuePart === '') {
        return {
            pair: {
                kind: 'pair',
                line: index + 1,
                lead: fullLead,
                key,
                quote: '',
                value: '',
                comment: '',
                lines: 1,
                raw,
            },
            consumed: 1,
        };
    }

    const first = valuePart[0];

    if (first !== '"' && first !== "'") {
        const { value, comment } = splitUnquoted(valuePart);

        return {
            pair: {
                kind: 'pair',
                line: index + 1,
                lead: fullLead,
                key,
                quote: '',
                value,
                comment,
                lines: 1,
                raw,
            },
            consumed: 1,
        };
    }

    let ending = findClosingQuote(valuePart, first, 1);
    let endIndex = index;

    if (ending === -1) {
        // Valor multi-linha: a aspa fecha numa linha seguinte, ou nao fecha.
        for (endIndex = index + 1; endIndex < lines.length; endIndex += 1) {
            ending = findClosingQuote(lines[endIndex] ?? '', first, 0);

            if (ending !== -1) {
                break;
            }
        }

        if (ending === -1) {
            // Aspa sem par: so a primeira linha e recusada; a continuacao pode
            // ser um par de verdade, e cada linha e lida por si.
            return { pair: null, consumed: 1 };
        }
    }

    const lastLine = lines[endIndex] ?? '';
    const after =
        endIndex === index
            ? valuePart.slice(ending + 1)
            : lastLine.slice(ending + 1);
    const comment = readComment(after);

    if (comment === null) {
        return {
            pair: null,
            consumed: endIndex - index + 1,
        };
    }

    const parts: string[] = [
        endIndex === index ? valuePart.slice(1, ending) : valuePart.slice(1),
    ];

    for (let i = index + 1; i < endIndex; i += 1) {
        parts.push(lines[i] ?? '');
    }

    if (endIndex !== index) {
        parts.push(lastLine.slice(0, ending));
    }

    return {
        pair: {
            kind: 'pair',
            line: index + 1,
            lead: fullLead,
            key,
            quote: first,
            value: parts.join('\n'),
            comment,
            lines: endIndex - index + 1,
            raw: lines.slice(index, endIndex + 1).join('\n'),
        },
        consumed: endIndex - index + 1,
    };
}

/** Le o documento inteiro, preservando cada linha como ela veio. */
export function parseEnv(source: string): EnvDocument {
    const lines = source.replace(/^\uFEFF/, '').split(/\r?\n/);
    const parsed: EnvLine[] = [];

    let index = 0;

    while (index < lines.length) {
        const raw = lines[index] ?? '';
        const line = index + 1;

        if (raw.trim() === '') {
            parsed.push({ kind: 'blank', line, raw });
            index += 1;

            continue;
        }

        if (raw.trimStart().startsWith('#')) {
            parsed.push({ kind: 'comment', line, raw });
            index += 1;

            continue;
        }

        const match = PAIR.exec(raw);

        if (match === null) {
            parsed.push({ kind: 'invalid', line, raw });
            index += 1;

            continue;
        }

        const result = readValue(lines, index, match[0], match[2] ?? '');

        if (result.pair === null) {
            parsed.push({
                kind: 'invalid',
                line,
                raw: lines.slice(index, index + result.consumed).join('\n'),
            });
            index += result.consumed;

            continue;
        }

        parsed.push(result.pair);
        index += result.consumed;
    }

    return { lines: parsed };
}
