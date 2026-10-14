import type { JsonValue } from './types';

/**
 * Validacao do JSON com **linha e coluna**.
 *
 * O `JSON.parse` do motor e a unica implementacao disponivel - e a que o
 * navegador de quem usa tem. O problema e que a mensagem dele nao e
 * padronizada: cada motor escreve a posicao de um jeito, e nenhum deles
 * promete traducao. Entao a mensagem crua fica como detalhe tecnico e o que a
 * tela mostra e a linha e a coluna, calculadas aqui.
 *
 * A convencao de linha e coluna **1-based** e a mesma dos editores: e o numero
 * que o usuario le no canto da tela.
 *
 * O que **nao** da para detectar aqui: chave duplicada. `{"a":1,"a":2}` e
 * aceito por V8, Firefox e Safari (fica a ultima) e nao ha erro nenhum para
 * ler. Detectar exigiria um parser proprio - custo alto e risco de divergir do
 * motor em todo o resto.
 */

export interface ParseOk {
    ok: true;
    value: JsonValue;
    /** Tamanho do texto em bytes (UTF-8), medido uma vez so. */
    bytes: number;
}

export interface ParseFailure {
    ok: false;
    /** Mensagem do motor, sem tratamento: e detalhe tecnico, nao texto de tela. */
    message: string;
    /** Posicao em code units, quando o motor informou. */
    position: number | null;
    /** Linha 1-based, ou `null` quando o motor nao informou a posicao. */
    line: number | null;
    /** Coluna 1-based (em caracteres, nao em code units). */
    column: number | null;
}

export type ParseResult = ParseOk | ParseFailure;

/**
 * Marca de ordem de bytes no inicio do arquivo - comum em arquivo do Windows.
 *
 * Comparada por codigo numerico, e nao por um caractere literal no fonte: o BOM
 * e invisivel, e um caractere invisivel no meio do codigo e exatamente o tipo
 * de coisa que passa na revisao e quebra na primeira ferramenta que reescrever
 * o arquivo.
 */
const BOM_CODE_POINT = 0xfeff;

/** `true` quando o texto comeca com a marca de ordem de bytes. */
export function hasBom(text: string): boolean {
    return text.length > 0 && text.charCodeAt(0) === BOM_CODE_POINT;
}

export function byteLength(text: string): number {
    return new TextEncoder().encode(text).length;
}

/**
 * Posicao que a mensagem do motor aponta, em code units.
 *
 * V8 escreve `at position 12`; o `position N` sem o `at` cobre as variacoes
 * dele mesmo e de outros motores.
 */
export function positionFromMessage(message: string): number | null {
    const match = /(?:at\s+)?position\s+(\d+)/i.exec(message);

    if (match === null || match[1] === undefined) {
        return null;
    }

    return Number(match[1]);
}

/**
 * Linha e coluna que a mensagem do motor ja traz prontas (Firefox escreve
 * `line 3 column 5`).
 */
export function lineColumnFromMessage(
    message: string,
): { line: number | null; column: number | null } {
    const match = /line\s+(\d+)(?:\s*(?:column|col)\s+(\d+))?/i.exec(message);

    if (match === null || match[1] === undefined) {
        return { line: null, column: null };
    }

    return {
        line: Number(match[1]),
        column: match[2] === undefined ? null : Number(match[2]),
    };
}

/**
 * Linha e coluna de uma posicao em code units.
 *
 * A coluna conta **caracteres**, e nao code units: com um emoji antes do erro,
 * a coluna em code units apontaria dois lugares depois do que o olho ve.
 */
export function lineColumnAt(text: string, position: number): { line: number; column: number } {
    const limit = Math.min(Math.max(position, 0), text.length);
    let line = 1;
    let lineStart = 0;

    for (let index = 0; index < limit; index += 1) {
        if (text[index] === '\n') {
            line += 1;
            lineStart = index + 1;
        }
    }

    return { line, column: [...text.slice(lineStart, limit)].length + 1 };
}

/**
 * Analisa o texto e devolve o valor **ou** o erro com a posicao.
 *
 * O BOM e descartado antes do `JSON.parse` (que nao o aceita) e as posicoes do
 * erro sao corrigidas em um - senao um arquivo salvo no Windows acusaria a
 * coluna errada em toda a primeira linha.
 */
export function analyzeJson(text: string): ParseResult {
    const bytes = byteLength(text);
    const offset = hasBom(text) ? 1 : 0;
    const source = offset === 1 ? text.slice(1) : text;

    try {
        return { ok: true, value: JSON.parse(source) as JsonValue, bytes };
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        const position = positionFromMessage(message);

        if (position !== null) {
            // Posicao fora do texto e motor fora do combinado: melhor nao
            // mostrar numero nenhum do que mostrar um numero errado.
            if (position < 0 || position > source.length) {
                return { ok: false, message, position: null, line: null, column: null };
            }

            const { line, column } = lineColumnAt(text, position + offset);

            return { ok: false, message, position, line, column };
        }

        return {
            ok: false,
            message,
            position: null,
            ...lineColumnFromMessage(message),
        };
    }
}

/** Versao do parse para quem so quer saber se o texto e valido. */
export function isValidJson(text: string): boolean {
    return analyzeJson(text).ok;
}
