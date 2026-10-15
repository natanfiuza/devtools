import type { JsonValue } from '@/lib/json/types';

/**
 * As transformacoes de texto do viewer: formatar, minificar e ordenar chaves.
 *
 * Todas recebem e devolvem **valor** JSON, e nao texto: o painel de saida e a
 * arvore, e a arvore sai do mesmo valor que o texto formatado. Transformar
 * texto exigiria reparsear - e reparsear um texto que veio de um parse que deu
 * certo e trabalho jogado fora.
 */

/** Espacos da indentacao do "formatar" - o mesmo que o editor usa. */
export const JSON_INDENT = 2;

export function prettyJson(value: JsonValue): string {
    return JSON.stringify(value, null, JSON_INDENT);
}

export function minifyJson(value: JsonValue): string {
    return JSON.stringify(value);
}

/**
 * Comparacao de chaves por **code unit**.
 *
 * Nada de `localeCompare`: o resultado dele depende do idioma e do ICU da
 * maquina, entao a mesma chave ordenaria diferente em dois navegadores - e o
 * teste passaria aqui e falharia la. Code unit e a mesma regra em todo lugar.
 *
 * Chave que parece numero (`"10"`, `"9"`) ordena como texto, e nao como numero:
 * e JSON, onde chave e sempre string.
 */
export function compareKeys(left: string, right: string): number {
    if (left < right) {
        return -1;
    }

    return left > right ? 1 : 0;
}

/**
 * Ordena as chaves de todos os objetos, recursivamente.
 *
 * Arrays **preservam a ordem** dos itens - a posicao em um array e dado, nao
 * arrumacao. O que acontece dentro dos itens e outra historia: um objeto dentro
 * de um array e ordenado como qualquer objeto.
 *
 * Em objeto com chave numerica o `JSON.stringify` reordena por indice numerico
 * crescente (regra da linguagem, nao escolha daqui) - arvore e texto passam
 * pela mesma reordenacao, entao os dois continuam contando a mesma historia.
 */
export function sortJson(value: JsonValue): JsonValue {
    if (Array.isArray(value)) {
        return value.map((item) => sortJson(item));
    }

    if (value !== null && typeof value === 'object') {
        const source = value as Record<string, JsonValue>;
        const sorted: Record<string, JsonValue> = {};

        for (const key of Object.keys(source).sort(compareKeys)) {
            sorted[key] = sortJson(source[key] as JsonValue);
        }

        return sorted;
    }

    return value;
}
