/**
 * O que a conversao perde no caminho.
 *
 * Nenhum formato representa tudo o que os outros representam - o TOML nao tem
 * `null`, o JSON nao tem comentario nem `NaN`, o XML nao tem numero. A decisao
 * da F1 e converter **sempre** e dizer o que ficou de fora, em vez de recusar
 * a conversao: quem converteu viu o aviso e sabe o que conferir no destino.
 *
 * O codigo viaja ate a tela e a traducao acontece la; o motor nao conhece
 * idioma. A contagem agrupa o mesmo aviso - "3 valores nulos omitidos" e
 * melhor que tres linhas iguais.
 */

export const LOSS_CODES = [
    /** Data/hora do TOML virou texto: o destino nao tem tipo de data. */
    'date-as-text',
    /** `!!binary` do YAML virou texto em base64. */
    'binary-as-base64',
    /** Inteiro maior que o `number` do JavaScript virou texto. */
    'bigint-as-text',
    /**
     * Comentarios da origem nao existem na arvore (e nao chegam ao destino).
     * A contagem e a do que a origem **tinha**, e nao uma do formato: sem
     * comentario no texto, nao ha aviso.
     */
    'comments-dropped',
    /** `null` nao existe no TOML: a chave ou o item saiu. */
    'null-dropped',
    /** `null` virou elemento vazio no XML. */
    'null-as-empty-element',
    /** `NaN`/`Infinity` nao existem no JSON: virou `null`. */
    'non-finite-as-null',
    /** `NaN`/`Infinity` virou texto no XML. */
    'non-finite-as-text',
    /** O XML mistura texto e filhos; a arvore guarda o texto antes dos filhos. */
    'mixed-content-flattened',
    /** O XML ganhou um `<root>`: o documento precisa de um elemento so. */
    'root-wrapped',
    /** Valor de tipo que a arvore nao representa (ex.: `undefined`) saiu. */
    'value-dropped',
] as const;

export type LossCode = (typeof LOSS_CODES)[number];

/** Um aviso e quantas vezes ele aconteceu. */
export interface Loss {
    code: LossCode;
    count: number;
}

/** Soma um aviso na lista, sem repetir o codigo. */
export function addLoss(losses: Loss[], code: LossCode, count = 1): void {
    const found = losses.find((loss) => loss.code === code);

    if (found === undefined) {
        losses.push({ code, count });

        return;
    }

    found.count += count;
}
