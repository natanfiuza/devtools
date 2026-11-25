import { parse as parseToml } from 'smol-toml';
import { parseDocument, visit } from 'yaml';
import { analyzeJson } from '@/lib/json/parse';
import { addLoss, type Loss } from './lossy';
import {
    fromPlain,
    objectValue,
    stringValue,
    type DataValue,
    type ObjectEntry,
} from './tree';
import type { DataFormat } from '../types';

/**
 * Texto de origem -> arvore intermediaria.
 *
 * Cada formato tem o seu parser, e tres deles sao de biblioteca: JSON pelo
 * motor, YAML pelo `yaml` e TOML pelo `smol-toml`. O XML sai do `DOMParser`,
 * que todo navegador tem.
 *
 * O contrato e o mesmo para os quatro: ou a arvore, ou a mensagem **com linha
 * e coluna** quando o parser souber onde parou. A mensagem crua fica como
 * detalhe; a tela mostra a linha e a coluna traduzidas, como o JSON Viewer
 * faz.
 *
 * A convencao do XML (ADR-180):
 *
 * - o elemento raiz vira a chave de topo;
 * - atributo vira `@nome`;
 * - o texto de um elemento que tambem tem atributo ou filhos vira `#text`;
 * - irmaos com o mesmo nome viram array, na ordem do documento;
 * - declaracao, comentarios e instrucoes de processamento sao descartados.
 *
 * O comentario e o unico item que a arvore nunca guarda: ele e do texto, e nao
 * do dado. Como os quatro serializadores escrevem so a arvore, todo comentario
 * da origem some na conversao - mesmo indo de YAML para YAML. Cada parser conta
 * os comentarios que **o texto tinha** e registra a perda com a contagem real;
 * um arquivo sem comentario nao ganha aviso nenhum.
 */

export type ParseOutcome =
    | { ok: true; value: DataValue; losses: Loss[] }
    | {
          ok: false;
          message: string;
          line: number | null;
          column: number | null;
      };

/** O erro que a biblioteca lancou, lido sem depender do tipo dela. */
interface LocatedError {
    message?: unknown;
    line?: unknown;
    column?: unknown;
    linePos?: unknown;
}

function messageOf(error: unknown): string {
    return error instanceof Error ? error.message : String(error);
}

function numberOrNull(value: unknown): number | null {
    return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

function parseJson(text: string): ParseOutcome {
    const result = analyzeJson(text);

    if (!result.ok) {
        return {
            ok: false,
            message: result.message,
            line: result.line,
            column: result.column,
        };
    }

    const losses: Loss[] = [];

    return { ok: true, value: fromPlain(result.value, losses), losses };
}

/**
 * Conta os comentarios do YAML pelo documento tipado.
 *
 * O parser nao guarda comentario no valor, mas guarda em cada no do documento
 * (o `#` no fim da linha fica no no e o de cima, no seguinte). Escanear o texto
 * na mao nao serve: `#` dentro de string e dentro de bloco literal e conteudo,
 * e so a biblioteca sabe disso.
 */
function countYamlComments(document: ReturnType<typeof parseDocument>): number {
    let count = 0;

    for (const comment of [document.comment, document.commentBefore]) {
        if (comment !== null && comment !== undefined) {
            count += 1;
        }
    }

    visit(document, (_key, node) => {
        if (node === null || typeof node !== 'object') {
            return;
        }

        const commented = node as {
            comment?: string | null;
            commentBefore?: string | null;
        };

        if (commented.comment !== null && commented.comment !== undefined) {
            count += 1;
        }

        if (
            commented.commentBefore !== null &&
            commented.commentBefore !== undefined
        ) {
            count += 1;
        }
    });

    return count;
}

function parseYamlText(text: string): ParseOutcome {
    try {
        const document = parseDocument(text);

        // O `parse` da biblioteca e exatamente isto: o primeiro erro do
        // documento vira excecao, e a posicao e a que a tela mostra.
        const firstError = document.errors[0];

        if (firstError !== undefined) {
            throw firstError;
        }

        const losses: Loss[] = [];
        const comments = countYamlComments(document);

        if (comments > 0) {
            addLoss(losses, 'comments-dropped', comments);
        }

        return { ok: true, value: fromPlain(document.toJS(), losses), losses };
    } catch (error) {
        const located = error as LocatedError;
        const position = Array.isArray(located.linePos)
            ? (located.linePos[0] as { line?: unknown; col?: unknown })
            : null;

        return {
            ok: false,
            message: messageOf(error),
            line: numberOrNull(position?.line),
            column: numberOrNull(position?.col),
        };
    }
}

/**
 * Conta os comentarios do TOML: um `#` que nao esta dentro de string.
 *
 * O `smol-toml` nao devolve a regiao comentada, entao a leitura e do texto. O
 * TOML tem quatro tipos de string - basica, literal, basica multi-linha e
 * literal multi-linha -, e so a basica tem escape; fora delas, o `#` abre
 * comentario ate o fim da linha.
 */
function countTomlComments(text: string): number {
    type State =
        'code' | 'basic' | 'literal' | 'multiline-basic' | 'multiline-literal';

    let count = 0;
    let index = 0;
    let state: State = 'code';

    while (index < text.length) {
        const char = text[index];

        if (state === 'code') {
            if (char === '#') {
                count += 1;

                while (index < text.length && text[index] !== '\n') {
                    index += 1;
                }

                continue;
            }

            if (char === '"' || char === "'") {
                const triple = char.repeat(3);

                if (text.startsWith(triple, index)) {
                    state =
                        char === '"' ? 'multiline-basic' : 'multiline-literal';
                    index += 3;
                } else {
                    state = char === '"' ? 'basic' : 'literal';
                    index += 1;
                }

                continue;
            }

            index += 1;
            continue;
        }

        if (state === 'basic' || state === 'multiline-basic') {
            if (char === '\\') {
                // O escape consome o proximo caractere (`\"` nao fecha).
                index += 2;
                continue;
            }

            if (char === '"') {
                if (state === 'basic') {
                    state = 'code';
                    index += 1;
                    continue;
                }

                // No multi-linha o fecho e um trio; ate duas aspas de conteudo
                // podem vir antes dele (`""""`), e o trio final e o que fecha.
                let run = 0;

                while (text[index + run] === '"') {
                    run += 1;
                }

                index += run;

                if (run >= 3) {
                    state = 'code';
                }

                continue;
            }

            index += 1;
            continue;
        }

        // Literal (sem escape): o fecho e a aspa sozinha ou o trio dela.
        if (char === "'") {
            if (state === 'literal') {
                state = 'code';
                index += 1;
                continue;
            }

            let run = 0;

            while (text[index + run] === "'") {
                run += 1;
            }

            index += run;

            if (run >= 3) {
                state = 'code';
            }

            continue;
        }

        index += 1;
    }

    return count;
}

function parseTomlText(text: string): ParseOutcome {
    try {
        const losses: Loss[] = [];
        const comments = countTomlComments(text);

        if (comments > 0) {
            addLoss(losses, 'comments-dropped', comments);
        }

        return { ok: true, value: fromPlain(parseToml(text), losses), losses };
    } catch (error) {
        const located = error as LocatedError;

        // O `smol-toml` ja entrega linha e coluna 1-based no proprio erro.
        return {
            ok: false,
            message: messageOf(error),
            line: numberOrNull(located.line),
            column: numberOrNull(located.column),
        };
    }
}

/** O texto que e filho direto do elemento (CDATA incluido), sem os netos. */
function directText(element: Element): string {
    let text = '';

    for (const node of element.childNodes) {
        if (node.nodeType === 3 || node.nodeType === 4) {
            text += node.nodeValue ?? '';
        }
    }

    return text.trim();
}

/**
 * Um elemento -> valor da arvore.
 *
 * O elemento sem nada vira string vazia (e nao `null`): `<a/>` e um elemento
 * vazio de verdade, e o TOML e o unico formato da tela sem `null` - inventar
 * um nulo aqui viraria um aviso de perda numa ida e volta que nao perdeu nada.
 */
function elementValue(element: Element, losses: Loss[]): DataValue {
    const attributes = [...element.attributes];
    const children = [...element.children];

    if (attributes.length === 0 && children.length === 0) {
        return stringValue(directText(element));
    }

    const entries: ObjectEntry[] = [];

    for (const attribute of attributes) {
        entries.push({
            key: `@${attribute.name}`,
            value: stringValue(attribute.value),
        });
    }

    const text = directText(element);

    if (text !== '' && children.length > 0) {
        // O texto de antes, do meio e de depois dos filhos nao tem ordem na
        // arvore: ele vai inteiro para o comeco.
        addLoss(losses, 'mixed-content-flattened');
    }

    if (text !== '') {
        entries.push({ key: '#text', value: stringValue(text) });
    }

    const grouped = new Map<string, DataValue[]>();

    for (const child of children) {
        const values = grouped.get(child.tagName) ?? [];
        values.push(elementValue(child, losses));
        grouped.set(child.tagName, values);
    }

    for (const [name, values] of grouped) {
        entries.push({
            key: name,
            value:
                values.length === 1
                    ? (values[0] as DataValue)
                    : { type: 'array', items: values },
        });
    }

    return objectValue(entries);
}

/** Linha e coluna que alguns navegadores escrevem no texto do `parsererror`. */
function xmlPosition(message: string): {
    line: number | null;
    column: number | null;
} {
    const line = /line\s+(\d+)/i.exec(message)?.[1];
    const column = /(?:column|col)\s+(\d+)/i.exec(message)?.[1];

    return {
        line: line === undefined ? null : Number(line),
        column: column === undefined ? null : Number(column),
    };
}

/** Conta nos de comentario (`nodeType` 8) em qualquer nivel do documento. */
function countXmlComments(node: Node): number {
    let count = node.nodeType === 8 ? 1 : 0;

    for (const child of Array.from(node.childNodes)) {
        count += countXmlComments(child);
    }

    return count;
}

function parseXmlText(text: string): ParseOutcome {
    // O `DOMParser` nao lanca: um XML invalido vira um documento com um
    // elemento `parsererror` dentro. O texto desse elemento varia de motor
    // para motor (e de navegador para jsdom), entao nada de teste depende
    // dele - so a leitura de linha e coluna, quando existir.
    const document = new DOMParser().parseFromString(text, 'application/xml');
    const failure = document.querySelector('parsererror');

    if (failure !== null) {
        const message = (failure.textContent ?? '').trim();

        return {
            ok: false,
            message: message === '' ? 'invalid XML' : message,
            ...xmlPosition(message),
        };
    }

    const root = document.documentElement;

    if (root === null) {
        return {
            ok: false,
            message: 'missing root element',
            line: null,
            column: null,
        };
    }

    const losses: Loss[] = [];
    const comments = countXmlComments(document);

    if (comments > 0) {
        addLoss(losses, 'comments-dropped', comments);
    }

    return {
        ok: true,
        value: objectValue([
            { key: root.tagName, value: elementValue(root, losses) },
        ]),
        losses,
    };
}

export function parseSource(text: string, format: DataFormat): ParseOutcome {
    switch (format) {
        case 'json':
            return parseJson(text);
        case 'yaml':
            return parseYamlText(text);
        case 'toml':
            return parseTomlText(text);
        case 'xml':
            return parseXmlText(text);
    }
}
