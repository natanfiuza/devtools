/**
 * Linha e coluna: da posicao de um texto ou da mensagem de um motor.
 *
 * Nasceu dentro do `json/parse` e virou `lib` quando o SQL Formatter precisou
 * do mesmo: a mensagem de erro **nao e do JSON**, e a de qualquer motor que
 * informe onde errou. O que muda de uma ferramenta para outra e so quem chama.
 *
 * A convencao de linha e coluna **1-based** e a mesma dos editores: e o numero
 * que a pessoa le no canto da tela.
 */

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
 * Linha e coluna que a mensagem do motor ja traz prontas.
 *
 * Dois formatos conhecidos, e os dois com a mesma escrita: o
 * `line 3 column 5` do SpiderMonkey (`JSON.parse: unexpected character at line
 * 3 column 5`) e o `at line 1 column 8` do `sql-formatter`.
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
 * A volta: a posicao em code units de uma linha e coluna.
 *
 * E o que permite marcar no editor o erro que veio com linha e coluna - o
 * `sql-formatter` informa assim, e nao por posicao. A conta e a mesma de
 * `lineColumnAt`, ao contrario: a coluna anda em **caracteres** dentro da
 * linha, e a posicao some com a quebra que ficou para tras.
 *
 * Linha e coluna fora do texto ficam presas ao que existe - a ultima linha, e
 * o fim dela -: a marca do editor cai perto do erro em vez de sumir da tela.
 */
export function positionAt(text: string, line: number, column: number): number {
    const lines = text.split('\n');
    const target = Math.min(Math.max(line, 1), lines.length) - 1;
    let position = 0;

    for (let index = 0; index < target; index += 1) {
        position += (lines[index]?.length ?? 0) + 1;
    }

    const chars = [...(lines[target] ?? '')].slice(0, Math.max(column - 1, 0));

    return position + chars.join('').length;
}
