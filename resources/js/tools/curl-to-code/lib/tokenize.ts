/**
 * O pedaco de shell que um comando curl usa.
 *
 * Nao e um interpretador de shell: e o subconjunto que aparece numa linha de
 * curl colada de um README ou do "Copy as cURL" do navegador. Aspas simples sao
 * literais, aspas duplas aceitam `\"`, `\\`, `\$` e `` \` ``, a barra invertida
 * fora de aspas escapa o proximo caractere, e `\` + quebra de linha continua o
 * comando na linha de baixo.
 *
 * O que nao fecha vira um problema listado, nao um token silenciosamente
 * diferente: aspas sem par e barra no fim da entrada tem codigo proprio, e a
 * tela recusa o comando em vez de gerar codigo com outro texto.
 */

export type TokenIssueCode =
    'unterminated_single' | 'unterminated_double' | 'dangling_escape';

export interface TokenIssue {
    code: TokenIssueCode;
    position: number;
}

export interface TokenizeResult {
    tokens: string[];
    issues: TokenIssue[];
}

const DOUBLE_QUOTE_ESCAPES = new Set(['"', '\\', '$', '`']);

function isSpace(char: string): boolean {
    return char === ' ' || char === '\t' || char === '\r' || char === '\n';
}

/** Le a entrada e devolve os tokens, na ordem, com os problemas do shell. */
export function tokenizeShell(source: string): TokenizeResult {
    const tokens: string[] = [];
    const issues: TokenIssue[] = [];
    let current = '';
    let hasToken = false;
    let i = 0;

    const push = (): void => {
        if (hasToken) {
            tokens.push(current);
            current = '';
            hasToken = false;
        }
    };

    while (i < source.length) {
        const char = source[i] ?? '';

        if (char === '\\') {
            const next = source[i + 1];

            if (next === '\n') {
                i += 2;
                continue;
            }

            if (next === '\r' && source[i + 2] === '\n') {
                i += 3;
                continue;
            }

            if (next === undefined) {
                issues.push({ code: 'dangling_escape', position: i });
                hasToken = true;
                i += 1;
                continue;
            }

            current += next;
            hasToken = true;
            i += 2;
            continue;
        }

        if (char === "'") {
            const end = source.indexOf("'", i + 1);

            if (end === -1) {
                issues.push({ code: 'unterminated_single', position: i });
                current += source.slice(i + 1);
                hasToken = true;
                i = source.length;
                continue;
            }

            current += source.slice(i + 1, end);
            hasToken = true;
            i = end + 1;
            continue;
        }

        if (char === '"') {
            let j = i + 1;
            let value = '';
            let closed = false;

            while (j < source.length) {
                const inner = source[j] ?? '';

                if (inner === '"') {
                    closed = true;
                    j += 1;
                    break;
                }

                if (inner === '\\') {
                    const next = source[j + 1];

                    if (next === '\n') {
                        j += 2;
                        continue;
                    }

                    if (next === '\r' && source[j + 2] === '\n') {
                        j += 3;
                        continue;
                    }

                    if (next !== undefined && DOUBLE_QUOTE_ESCAPES.has(next)) {
                        value += next;
                        j += 2;
                        continue;
                    }

                    // Fora desses, a barra e literal dentro de aspas duplas.
                    value += '\\';
                    j += 1;
                    continue;
                }

                value += inner;
                j += 1;
            }

            if (!closed) {
                issues.push({ code: 'unterminated_double', position: i });
            }

            current += value;
            hasToken = true;
            i = j;
            continue;
        }

        if (isSpace(char)) {
            push();
            i += 1;
            continue;
        }

        current += char;
        hasToken = true;
        i += 1;
    }

    push();

    return { tokens, issues };
}
