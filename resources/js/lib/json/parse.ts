import {
    lineColumnAt,
    lineColumnFromMessage,
    positionFromMessage,
} from '@/lib/position';
import { byteLength } from '@/lib/bytes';
import type { JsonValue } from './types';

// O contador nasceu aqui e hoje mora em `lib/bytes` (o AI SQL Builder tambem o
// usa). O reexport mantem os caminhos antigos funcionando.
export { byteLength } from '@/lib/bytes';

/**
 * Validacao do JSON com **linha e coluna**.
 *
 * O `JSON.parse` do motor e a unica implementacao disponivel - e a que o
 * navegador de quem usa tem. O problema e que a mensagem dele nao e
 * padronizada: cada motor escreve a posicao de um jeito, e nenhum deles
 * promete traducao. Entao a mensagem crua fica como detalhe tecnico e o que a
 * tela mostra e a linha e a coluna, calculadas aqui - as contas de posicao
 * moram em `lib/position`, que o SQL Formatter tambem usa.
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
                return {
                    ok: false,
                    message,
                    position: null,
                    line: null,
                    column: null,
                };
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
