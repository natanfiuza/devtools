import { normalize } from '@/tools/search';
import type { JsonNodeKind, JsonPrimitive, JsonValue } from './types';

/**
 * A arvore do JSON como **lista plana em pre-ordem**.
 *
 * A escolha nao e estetica: em pre-ordem a subarvore de um no e um intervalo
 * contiguo, entao esconder um no colapsado e **pular um intervalo** - uma
 * passada O(n), sem recursao e sem recalcular nada a cada quadro. Uma arvore de
 * objetos aninhados, com `Set` de colapsados, daria a mesma tela com uma
 * travessia por linha visivel.
 *
 * O modelo nao guarda o valor cru dos containers: a linha na tela mostra a
 * chave, o tipo e - quando e folha - o valor. Container colapsado mostra a
 * contagem de filhos, que e o que o `childCount` ja responde.
 */
export interface JsonNode {
    /** Indice do no pai; `null` na raiz. */
    parent: number | null;
    /** Profundidade na arvore (a raiz e 0). */
    depth: number;
    kind: JsonNodeKind;
    /** Nome da chave no pai; `null` na raiz e nos itens de array. */
    key: string | null;
    /** Posicao dentro do array do pai - e o `[i]` do caminho. */
    index: number | null;
    /** Valor da folha. `null` tambem e valor: quem distingue e o `kind`. */
    value: JsonPrimitive;
    /** Filhos diretos. */
    childCount: number;
    /**
     * Quantos nos a subarvore ocupa **contando este**. E o que permite pular um
     * no colapsado: `childCount` so conta os filhos diretos, e pular por ele
     * deixaria os netos na tela.
     */
    subtreeSize: number;
}

const LEAF_KINDS: JsonNodeKind[] = ['string', 'number', 'boolean', 'null'];

export function kindOf(value: JsonValue): JsonNodeKind {
    if (value === null) {
        return 'null';
    }

    if (Array.isArray(value)) {
        return 'array';
    }

    // Comparacoes, e nao o resultado do `typeof`: `JsonValue` e recursivo, e o
    // `typeof` de um tipo recursivo nao vira uniao de literais - vira `string`.
    if (typeof value === 'object') {
        return 'object';
    }

    if (typeof value === 'boolean') {
        return 'boolean';
    }

    return typeof value === 'number' ? 'number' : 'string';
}

export function isContainer(node: JsonNode): boolean {
    return !LEAF_KINDS.includes(node.kind);
}

/** O valor da folha como texto, para a linha e para a busca. */
export function leafText(node: JsonNode): string {
    if (node.kind === 'string') {
        return `"${node.value as string}"`;
    }

    if (node.kind === 'null') {
        return 'null';
    }

    return String(node.value);
}

/**
 * Achata o valor em pre-ordem.
 *
 * O valor que chega aqui ja passou por `sortJson`, quando a ordenacao esta
 * ligada: a arvore e o texto do painel de saida saem do **mesmo** valor, senao
 * o caminho copiado de uma linha nao corresponderia ao que esta na tela.
 */
export function flatten(value: JsonValue): JsonNode[] {
    const nodes: JsonNode[] = [];

    const visit = (
        current: JsonValue,
        parent: number | null,
        depth: number,
        key: string | null,
        index: number | null,
    ): void => {
        const kind = kindOf(current);
        const position = nodes.length;

        const node: JsonNode = {
            parent,
            depth,
            kind,
            key,
            index,
            value: kind === 'object' || kind === 'array' ? null : (current as JsonPrimitive),
            childCount: 0,
            subtreeSize: 1,
        };

        nodes.push(node);

        if (kind === 'array') {
            const items = current as JsonValue[];

            for (let item = 0; item < items.length; item += 1) {
                visit(items[item] as JsonValue, position, depth + 1, null, item);
            }

            node.childCount = items.length;
        } else if (kind === 'object') {
            const entries = Object.entries(current as Record<string, JsonValue>);

            for (const [childKey, childValue] of entries) {
                visit(childValue, position, depth + 1, childKey, null);
            }

            node.childCount = entries.length;
        }

        node.subtreeSize = nodes.length - position;
    };

    visit(value, null, 0, null, null);

    return nodes;
}

/**
 * Indices dos nos visiveis, na ordem da tela.
 *
 * Colapsar um no esconde a subarvore inteira dele - que, em pre-ordem, sao os
 * indices logo depois dele.
 */
export function visibleRows(nodes: JsonNode[], collapsed: Set<number>): number[] {
    const visible: number[] = [];

    for (let index = 0; index < nodes.length; index += 1) {
        const node = nodes[index];

        if (node === undefined) {
            continue;
        }

        // O no colapsado continua na tela - quem sai sao os descendentes.
        visible.push(index);

        if (collapsed.has(index)) {
            // Menos o proprio no: o `index += 1` do laco ja cobre ele.
            index += node.subtreeSize - 1;
        }
    }

    return visible;
}

/** Todos os containers com filhos a partir de uma profundidade. */
export function collapseFrom(nodes: JsonNode[], depth: number): Set<number> {
    const collapsed = new Set<number>();

    nodes.forEach((node, index) => {
        if (isContainer(node) && node.childCount > 0 && node.depth >= depth) {
            collapsed.add(index);
        }
    });

    return collapsed;
}

/** Caminho do no ate a raiz, do mais distante para o mais proximo. */
export function ancestorsOf(nodes: JsonNode[], index: number): number[] {
    const ancestors: number[] = [];
    let current = nodes[index]?.parent ?? null;

    while (current !== null) {
        ancestors.push(current);
        current = nodes[current]?.parent ?? null;
    }

    return ancestors;
}

export interface SearchResult {
    /** Indices dos nos que casam, na ordem da arvore. */
    matches: number[];
    /** Containers que precisam estar abertos para os resultados aparecerem. */
    ancestors: Set<number>;
    /** `true` quando o teto de resultados foi atingido. */
    truncated: boolean;
}

/** Teto de resultados: a busca e uma ajuda visual, nao um relatorio. */
export const SEARCH_LIMIT = 1000;

/**
 * Busca por **chave e valor**.
 *
 * O caminho nao entra na comparacao de proposito: o que a pessoa digita e o
 * nome de uma chave (`store`, `book`), nao o caminho inteiro - e o caminho
 * filtraria por "tem essa chave em algum lugar acima", que nao e a pergunta.
 *
 * O texto e normalizado como o da busca da home (`normalize`, de
 * `tools/search`): sem acento e sem caixa, para "joao" achar "João".
 */
export function searchNodes(
    nodes: JsonNode[],
    query: string,
    limit: number = SEARCH_LIMIT,
): SearchResult {
    const needle = normalize(query);
    const matches: number[] = [];
    const ancestors = new Set<number>();
    let truncated = false;

    if (needle === '') {
        return { matches, ancestors, truncated };
    }

    for (let index = 0; index < nodes.length; index += 1) {
        const node = nodes[index];

        if (node === undefined) {
            continue;
        }

        const inKey = node.key !== null && normalize(node.key).includes(needle);
        const inValue = !isContainer(node) && normalize(leafText(node)).includes(needle);

        if (!inKey && !inValue) {
            continue;
        }

        if (matches.length >= limit) {
            truncated = true;

            break;
        }

        matches.push(index);

        for (const ancestor of ancestorsOf(nodes, index)) {
            ancestors.add(ancestor);
        }
    }

    return { matches, ancestors, truncated };
}
