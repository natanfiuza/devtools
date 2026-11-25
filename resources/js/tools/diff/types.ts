/**
 * Os tipos que atravessam a fronteira do motor do diff.
 *
 * O pedido e cloneavel, porque atravessa para o worker; o resultado carrega so
 * dados e codigos, nunca texto de tela - a traducao acontece na UI. Os dois
 * modos tem resultado proprio porque comparam coisas diferentes: o texto
 * compara linhas, o JSON compara caminhos.
 */

/** O que esta sendo comparado. */
export type DiffMode = 'text' | 'json';

/** O que o motor recebe. */
export interface DiffRequest {
    left: string;
    right: string;
    mode: DiffMode;
}

/** Uma linha que mudou, no lado dela (indice zero-based). */
export interface HunkLine {
    type: 'removed' | 'added';
    /** Linha no documento do lado: o original, no removido; o modificado, no adicionado. */
    line: number;
    text: string;
    /**
     * A linha tem par do outro lado: onde havia remocoes e adicoes na mesma
     * regiao, cada par e uma linha **alterada**, e e isso que pinta o realce
     * de "mudou" em vez de "entrou/saiu".
     */
    paired: boolean;
}

/** Uma regiao de mudanca: o que navegar e o que pintar. */
export interface DiffHunk {
    /** Primeira linha do hunk no original (zero-based); vale como ancora. */
    leftStart: number;
    /** Primeira linha do hunk no modificado (zero-based). */
    rightStart: number;
    lines: HunkLine[];
}

/** O resultado da comparacao por linhas. */
export interface TextDiff {
    hunks: DiffHunk[];
    removed: number;
    added: number;
    /**
     * O orcamento de edicoes estourou e o diff e grosseiro (por posicao).
     * A tela avisa: o resultado continua valido, mas perde precisao.
     */
    truncated: boolean;
}

/** Uma diferenca semantica do JSON, ja achatada por caminho. */
export interface JsonChange {
    /** Caminho com `$` na raiz (ex.: `$.user.name`, `$.items[0].id`). */
    path: string;
    kind: 'added' | 'removed' | 'changed';
    /** Valor no original, como texto JSON; `null` quando so existe no modificado. */
    left: string | null;
    /** Valor no modificado, como texto JSON; `null` quando so existe no original. */
    right: string | null;
}

export interface JsonDiffOk {
    ok: true;
    changes: JsonChange[];
    added: number;
    removed: number;
    changed: number;
}

/** O JSON de um dos lados nao e valido: nao ha comparacao semantica. */
export interface JsonDiffFailure {
    ok: false;
    side: 'left' | 'right';
    line: number | null;
    column: number | null;
}

export type JsonDiffResult = JsonDiffOk | JsonDiffFailure;

/** O resultado do motor, no modo pedido. */
export type DiffResult =
    { mode: 'text'; text: TextDiff } | { mode: 'json'; json: JsonDiffResult };
