import { format } from 'sql-formatter';
import { lineColumnFromMessage } from '@/lib/position';
import { languageOf, type Dialect } from './dialects';

/**
 * A formatacao: o `sql-formatter` por dentro, e um resultado proprio por fora.
 *
 * A biblioteca lanca em vez de devolver erro, e o que ela lanca tem duas
 * formas:
 *
 * - `Parse error: Unexpected "'abc" at line 1 column 8.` - curta, com a
 *   posicao escrita no meio da frase;
 * - `Parse error at token: «EOF» at line 1 column 29` seguida da **arvore
 *   inteira** de tokens que o motor esperava - dezenas de linhas de gramatica.
 *
 * As duas tem a posicao, e a posicao sai por `lineColumnFromMessage`
 * (`lib/position`). Da mensagem fica so a **primeira linha**: a segunda forma
 * nao cabe em lugar nenhum, e quem le a tela quer a linha e a coluna, com o
 * texto do motor como detalhe.
 *
 * As opcoes vao explicitas na chamada: o que a tela escolhe (dialeto e caixa) e
 * a indentacao fixa. O resto do comportamento e o padrao da versao instalada da
 * biblioteca - a tool nao expoe nada disso, entao um upgrade pode mexer no
 * texto formatado.
 */

/**
 * A caixa das palavras-chave - a **unica** opcao da tela (SPEC secao 5).
 *
 * `preserve` existe porque e o que a biblioteca oferece, e porque ha consulta
 * em que a caixa e do autor; as outras duas sao a escolha de quem so quer o SQL
 * legivel. O padrao e maiusculas.
 */
export const KEYWORD_CASES = ['upper', 'lower', 'preserve'] as const;

export type KeywordCase = (typeof KEYWORD_CASES)[number];

export const DEFAULT_KEYWORD_CASE: KeywordCase = 'upper';

/** Espacos por nivel: fixo em 2, sem opcao na tela. */
const TAB_WIDTH = 2;

export interface FormatOptions {
    dialect: Dialect;
    keywordCase: KeywordCase;
}

/** O que atravessa para o worker: o texto e as duas escolhas. */
export interface FormatRequest {
    text: string;
    options: FormatOptions;
}

export interface FormatOk {
    ok: true;
    text: string;
}

export interface FormatFailure {
    ok: false;
    /** Primeira linha da mensagem da biblioteca: detalhe tecnico, nao texto de tela. */
    message: string;
    line: number | null;
    column: number | null;
}

export type FormatResult = FormatOk | FormatFailure;

export function formatSql(text: string, options: FormatOptions): FormatResult {
    try {
        return {
            ok: true,
            text: format(text, {
                language: languageOf(options.dialect),
                keywordCase: options.keywordCase,
                tabWidth: TAB_WIDTH,
                useTabs: false,
            }),
        };
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);

        return { ok: false, message: firstLine(message), ...lineColumnFromMessage(message) };
    }
}

/** A mensagem ate a primeira quebra: o resto e a gramatica do motor. */
function firstLine(message: string): string {
    const [line = ''] = message.split('\n');

    return line.trim();
}
