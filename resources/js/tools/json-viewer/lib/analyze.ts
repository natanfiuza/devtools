import { analyzeJson, type ParseFailure } from './parse';
import { collapseFrom, flatten, type JsonNode } from './tree';
import { minifyJson, prettyJson, sortJson } from './transform';

/**
 * A analise completa do documento: validar, achatar a arvore e - quando pedido
 * - reescrever o texto.
 *
 * Esta funcao e o **unico** caminho de analise: o worker chama ela, e a queda
 * para a thread principal chama a mesma. Dois caminhos com o mesmo resultado
 * por construcao, e nao por disciplina.
 *
 * Ela devolve o **modelo da tela** (a arvore achatada), e nao o valor
 * parseado: mandar a estrutura inteira de um JSON de 5 MB pela thread custaria
 * quase o que custa parsear, e o que a tela desenha e a lista de linhas.
 */

/** Forma do texto reescrito, quando alguem pede o texto. */
export type OutputMode = 'pretty' | 'minify';

/**
 * Profundidade em que os containers nascem fechados.
 *
 * Um: a raiz aparece aberta e o resto fechado. A chegada mostra a **forma** do
 * documento - o que ele tem no primeiro nivel - e o detalhe abre sob demanda.
 * Com tudo aberto, um JSON de API de alguns milhares de linhas chega como um
 * paredao; com a raiz fechada, chega como uma caixa.
 */
export const INITIAL_COLLAPSE_DEPTH = 1;

export interface AnalyzeOptions {
    /**
     * Reescrever o documento como texto? `null` quando ninguem vai usar o texto
     * agora - e o caso comum, que e a cada tecla digitada: serializar de novo um
     * documento grande so para jogar o resultado fora custa caro.
     */
    output: OutputMode | null;
    /** Ordenar as chaves de todos os objetos, recursivamente. */
    sortKeys: boolean;
    /** Profundidade a partir da qual os containers nascem fechados. */
    collapseDepth: number;
}

export const DEFAULT_ANALYZE_OPTIONS: AnalyzeOptions = Object.freeze({
    output: null,
    sortKeys: false,
    collapseDepth: INITIAL_COLLAPSE_DEPTH,
});

export interface AnalyzeStats {
    /** Tamanho do texto de entrada, em bytes. */
    bytes: number;
    /** Nos da arvore: containers e folhas. */
    nodes: number;
    /** Propriedades de objeto. */
    keys: number;
    /** Itens de array. */
    items: number;
}

export interface AnalyzeSuccess {
    ok: true;
    stats: AnalyzeStats;
    /** A arvore achatada em pre-ordem - o que a tela desenha. */
    nodes: JsonNode[];
    /** Containers que nascem fechados. */
    collapsed: number[];
    /** O documento reescrito na forma pedida, ou `null` se ninguem pediu. */
    output: string | null;
}

/** O erro do parse vem inteiro: quem mostra linha e coluna le daqui. */
export type AnalyzeOutcome = AnalyzeSuccess | ParseFailure;

function statsOf(nodes: JsonNode[], bytes: number): AnalyzeStats {
    let keys = 0;
    let items = 0;

    for (const node of nodes) {
        if (node.kind === 'object') {
            keys += node.childCount;
        } else if (node.kind === 'array') {
            items += node.childCount;
        }
    }

    return { bytes, nodes: nodes.length, keys, items };
}

export function analyzeDocument(
    text: string,
    options: AnalyzeOptions = DEFAULT_ANALYZE_OPTIONS,
): AnalyzeOutcome {
    const parsed = analyzeJson(text);

    if (!parsed.ok) {
        return parsed;
    }

    // A arvore e o texto saem do **mesmo** valor: com a ordenacao ligada, o
    // caminho copiado de uma linha tem que corresponder ao texto reescrito.
    const value = options.sortKeys ? sortJson(parsed.value) : parsed.value;
    const nodes = flatten(value);

    return {
        ok: true,
        stats: statsOf(nodes, parsed.bytes),
        nodes,
        collapsed: [...collapseFrom(nodes, options.collapseDepth)],
        output:
            options.output === null
                ? null
                : options.output === 'minify'
                  ? minifyJson(value)
                  : prettyJson(value),
    };
}
