import type { JsonNode } from './tree';

/**
 * O caminho de um no, no formato JSONPath.
 *
 * O formato tem uma razao pratica: e o que cola direto numa ferramenta de
 * consulta (jq, teste de API, script) e o que descreve o no sem ambiguidade.
 * As regras sao as dos navegadores de JSON:
 *
 * - chave que e identificador vira `.chave` (leitura boa);
 * - o resto vira `['chave']`, com `\` e `'` escapados - senao `a.b` viraria
 *   dois niveis e `it's` quebraria a citacao;
 * - item de array vira `[0]`.
 *
 * A raiz e `$`.
 */

const IDENTIFIER = /^[A-Za-z_$][A-Za-z0-9_$]*$/;

export function isIdentifier(key: string): boolean {
    return IDENTIFIER.test(key);
}

/** Escapa para dentro de `['...']`: primeiro a barra, depois a aspa. */
export function escapeKey(key: string): string {
    return key.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

export function keySegment(key: string): string {
    return isIdentifier(key) ? `.${key}` : `['${escapeKey(key)}']`;
}

export function indexSegment(index: number): string {
    return `[${index}]`;
}

export function buildPath(segments: string[]): string {
    return `$${segments.join('')}`;
}

/** Caminho da raiz ate o no, subindo pelos pais. */
export function nodePath(nodes: JsonNode[], index: number): string {
    const segments: string[] = [];
    let current: number | null = index;

    while (current !== null) {
        // O tipo fica escrito: sem ele o compilador fecha um ciclo - o indice
        // vem de `current`, que vem do `parent` do proprio no.
        const node: JsonNode | undefined = nodes[current];

        if (node === undefined) {
            break;
        }

        if (node.index !== null) {
            segments.unshift(indexSegment(node.index));
        } else if (node.key !== null) {
            segments.unshift(keySegment(node.key));
        }

        current = node.parent;
    }

    return buildPath(segments);
}
