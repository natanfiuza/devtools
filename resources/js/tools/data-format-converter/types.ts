import type { Loss } from './lib/lossy';

/**
 * Os tipos que atravessam a fronta do motor: pedido, resultado e falha.
 *
 * Ficam num modulo proprio (e nao em `convert.ts`) porque o worker, a tela e
 * os serializadores precisam deles, e nenhum deles precisa de todos os
 * parsers: um tipo compartilhado aqui nao puxa codigo de biblioteca nenhuma
 * para dentro do bundle de quem so quer a lista de formatos.
 */

/** Os quatro formatos da F1. O slug e o mesmo do manifesto e do i18n. */
export const FORMATS = ['json', 'yaml', 'toml', 'xml'] as const;

export type DataFormat = (typeof FORMATS)[number];

/** O que o motor recebe. Cloneavel: atravessa para o worker. */
export interface ConvertRequest {
    text: string;
    from: DataFormat;
    to: DataFormat;
}

/**
 * Por que a conversao nao aconteceu.
 *
 * `parse` e o unico com linha e coluna: e o erro do texto de origem, que o
 * editor marca. Os outros sao conflitos entre o valor e o formato de destino -
 * nao ha posicao no texto de origem para eles.
 */
export type ConvertFailureCode =
    | 'parse'
    /** TOML so tem tabela na raiz: um array ou um escalar nao tem onde caber. */
    | 'toml-root'
    /** TOML nao aceita array com tipos misturados. */
    | 'toml-mixed-array'
    /** A biblioteca recusou a tabela por outro motivo. */
    | 'toml-invalid'
    /** Uma chave da arvore nao e um nome valido de elemento XML. */
    | 'xml-name'
    /** Valor de atributo (`@chave`) que nao e escalar. */
    | 'xml-attribute'
    /** A escrita do XML falhou por um motivo nao previsto. */
    | 'xml-invalid';

export interface ConvertFailure {
    ok: false;
    code: ConvertFailureCode;
    /** Detalhe para a mensagem (a chave, o caminho); nunca texto de tela. */
    detail: string | null;
    /** Linha 1-based, quando o erro e do parser. */
    line: number | null;
    /** Coluna 1-based, quando o erro e do parser. */
    column: number | null;
}

export interface ConvertSuccess {
    ok: true;
    /** O documento no formato de destino. */
    text: string;
    format: DataFormat;
    /** O que o destino nao representa do valor convertido. */
    losses: Loss[];
}

export type ConvertResult = ConvertSuccess | ConvertFailure;

/** O que a serializacao devolve: o texto, ou o conflito. */
export type SerializeResult =
    | { ok: true; text: string }
    | {
          ok: false;
          code: Exclude<ConvertFailureCode, 'parse'>;
          detail: string | null;
      };
