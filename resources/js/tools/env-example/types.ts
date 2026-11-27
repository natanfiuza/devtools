/**
 * As linhas de um arquivo `.env`.
 *
 * O documento guarda a linha crua **e** a leitura dela: a mascara troca so o
 * valor das chaves sensiveis e devolve o resto exatamente como estava - ordem,
 * comentarios, linhas em branco, agrupamentos, `export `, aspas e comentario em
 * linha. Por isso `raw` esta em toda linha e o par guarda as partes separadas.
 */

export type EnvLineKind = 'blank' | 'comment' | 'pair' | 'invalid';

export interface EnvBlankLine {
    kind: 'blank';
    /** Numero da linha, base 1. */
    line: number;
    raw: string;
}

export interface EnvCommentLine {
    kind: 'comment';
    line: number;
    raw: string;
}

/** Linha que nao virou par: sem `=`, ou com formato que o parser recusa. */
export interface EnvInvalidLine {
    kind: 'invalid';
    line: number;
    raw: string;
}

export interface EnvPairLine {
    kind: 'pair';
    line: number;
    /** Tudo antes do valor, ja com `export `, a chave, `=` e espacos. */
    lead: string;
    key: string;
    /** A aspa de abertura, ou string vazia quando o valor nao tem aspas. */
    quote: '' | '"' | "'";
    /** O conteudo do valor, sem aspas e sem o comentario em linha. */
    value: string;
    /** O comentario em linha, com o espaco antes do `#`; vazio quando nao ha. */
    comment: string;
    /** Quantas linhas o par ocupa (valor multi-linha entre aspas). */
    lines: number;
    /** A linha (ou linhas) como veio no arquivo. */
    raw: string;
}

export type EnvLine =
    EnvBlankLine | EnvCommentLine | EnvInvalidLine | EnvPairLine;

export interface EnvDocument {
    lines: EnvLine[];
}

export interface MaskedKey {
    key: string;
    /** Numero da linha onde a chave comeca, base 1. */
    line: number;
}

export interface InvalidEnvLine {
    line: number;
    raw: string;
}

export interface MaskResult {
    /** O `.env.example` inteiro, na ordem original. */
    output: string;
    /** As chaves cujo valor foi esvaziado, com a linha. */
    masked: MaskedKey[];
    /** As linhas mantidas sem `=` legivel, com a linha. */
    invalid: InvalidEnvLine[];
}
