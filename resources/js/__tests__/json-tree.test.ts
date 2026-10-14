import { describe, expect, it } from 'vitest';
import {
    ancestorsOf,
    collapseFrom,
    flatten,
    isContainer,
    kindOf,
    leafText,
    searchNodes,
    visibleRows,
} from '@/tools/json-viewer/lib/tree';
import type { JsonValue } from '@/tools/json-viewer/lib/types';

/**
 * A arvore achatada: indices, profundidade, colapso e busca.
 *
 * O documento de teste tem de tudo o que a tela precisa distinguir: objeto,
 * array, array vazio, folhas de cada tipo e um valor com acento.
 */
const DOC: JsonValue = {
    store: {
        book: [
            { title: 'A', price: 5 },
            { title: 'Ávila', price: 10, tags: [] },
        ],
    },
    count: 2,
    active: true,
    note: null,
};

/*
| Achatamento de DOC (indice: no):
|  0 objeto raiz          5 price 5              10 count
|  1 store                6 [1]                  11 active
|  2 store.book           7 title "Ávila"        12 note
|  3 [0]                  8 price 10
|  4 title "A"            9 tags []
*/
const nodes = flatten(DOC);

describe('flatten', () => {
    it('achata em pre-ordem, com o objeto antes dos filhos', () => {
        expect(nodes.map((node) => node.key ?? (node.index === null ? '$' : `[${node.index}]`))).toEqual([
            '$',
            'store',
            'book',
            '[0]',
            'title',
            'price',
            '[1]',
            'title',
            'price',
            'tags',
            'count',
            'active',
            'note',
        ]);
    });

    it('guarda a profundidade de cada no', () => {
        expect(nodes.map((node) => node.depth)).toEqual([
            0, 1, 2, 3, 4, 4, 3, 4, 4, 4, 1, 1, 1,
        ]);
    });

    it('liga cada no ao pai', () => {
        expect(nodes[0]?.parent).toBeNull();
        expect(nodes[1]?.parent).toBe(0);
        expect(nodes[3]?.parent).toBe(2);
        expect(nodes[4]?.parent).toBe(3);
    });

    it('conta filhos diretos e o tamanho da subarvore', () => {
        expect(nodes[0]?.childCount).toBe(4);
        expect(nodes[2]?.childCount).toBe(2);

        // store.book tem dois itens, cada um com seus filhos: 8 nos no total.
        expect(nodes[2]?.subtreeSize).toBe(8);
        expect(nodes[3]?.subtreeSize).toBe(3);
        expect(nodes[5]?.subtreeSize).toBe(1);
    });

    it('guarda o valor so nas folhas', () => {
        expect(nodes[5]?.value).toBe(5);
        expect(nodes[7]?.value).toBe('Ávila');
        expect(nodes[11]?.value).toBe(true);
        expect(nodes[12]?.value).toBeNull();
        // Container nao carrega valor: quem diz o que ele e sao os filhos.
        expect(nodes[2]?.value).toBeNull();
    });

    it('achata um valor solto na raiz', () => {
        const scalar = flatten('texto');

        expect(scalar).toHaveLength(1);
        expect(scalar[0]?.kind).toBe('string');
        expect(scalar[0]?.childCount).toBe(0);
        expect(scalar[0]?.subtreeSize).toBe(1);
    });
});

describe('kindOf', () => {
    it('classifica cada tipo do JSON', () => {
        expect(kindOf(null)).toBe('null');
        expect(kindOf([])).toBe('array');
        expect(kindOf({})).toBe('object');
        expect(kindOf('')).toBe('string');
        expect(kindOf(0)).toBe('number');
        expect(kindOf(false)).toBe('boolean');
    });
});

describe('visibleRows', () => {
    it('mostra tudo quando nada esta colapsado', () => {
        expect(visibleRows(nodes, new Set())).toEqual([
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,
        ]);
    });

    it('esconde a subarvore inteira do no colapsado', () => {
        // O array `book` colapsado leva os dois itens e os filhos deles.
        expect(visibleRows(nodes, new Set([2]))).toEqual([0, 1, 2, 10, 11, 12]);
    });

    it('esconde so os netos quando o pai esta aberto', () => {
        expect(visibleRows(nodes, new Set([3]))).toEqual([
            0, 1, 2, 3, 6, 7, 8, 9, 10, 11, 12,
        ]);
    });

    it('respeita colapso aninhado', () => {
        expect(visibleRows(nodes, new Set([1, 3]))).toEqual([0, 1, 10, 11, 12]);
    });

    it('nao se perde no ultimo no', () => {
        expect(visibleRows(nodes, new Set([12]))).toEqual([
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12,
        ]);
    });
});

describe('collapseFrom', () => {
    it('colapsa os containers com filhos a partir de uma profundidade', () => {
        expect([...collapseFrom(nodes, 2)]).toEqual([2, 3, 6]);
    });

    it('nao colapsa container vazio nem folha', () => {
        const collapsed = collapseFrom(nodes, 0);

        // `tags` e vazio: colapsar nao esconderia nada.
        expect(collapsed.has(9)).toBe(false);
        expect(collapsed.has(5)).toBe(false);
        expect(collapsed.has(0)).toBe(true);
    });
});

describe('ancestorsOf', () => {
    it('sobe ate a raiz', () => {
        expect(ancestorsOf(nodes, 4)).toEqual([3, 2, 1, 0]);
        expect(ancestorsOf(nodes, 0)).toEqual([]);
    });
});

describe('isContainer e leafText', () => {
    it('separa container de folha', () => {
        expect(isContainer(nodes[0]!)).toBe(true);
        expect(isContainer(nodes[2]!)).toBe(true);
        expect(isContainer(nodes[12]!)).toBe(false);
    });

    it('escreve o valor da folha como a linha mostra', () => {
        expect(leafText(nodes[5]!)).toBe('5');
        expect(leafText(nodes[7]!)).toBe('"Ávila"');
        expect(leafText(nodes[11]!)).toBe('true');
        expect(leafText(nodes[12]!)).toBe('null');
    });
});

describe('searchNodes', () => {
    it('devolve nada com consulta vazia', () => {
        expect(searchNodes(nodes, '   ')).toEqual({
            matches: [],
            ancestors: new Set(),
            truncated: false,
        });
    });

    it('acha pela chave', () => {
        const found = searchNodes(nodes, 'price');

        expect(found.matches).toEqual([5, 8]);
    });

    it('acha pelo valor', () => {
        expect(searchNodes(nodes, '10').matches).toEqual([8]);
    });

    it('ignora acento e caixa', () => {
        expect(searchNodes(nodes, 'AVILA').matches).toEqual([7]);
    });

    it('devolve os ancestrais que precisam estar abertos', () => {
        const found = searchNodes(nodes, 'price');

        // O `[1]` esta colapsado por padrao; os pais dele precisam abrir.
        expect([...found.ancestors].sort((left, right) => left - right)).toEqual([
            0, 1, 2, 3, 6,
        ]);
    });

    it('para no teto de resultados e avisa', () => {
        const found = searchNodes(nodes, 'i', 2);

        expect(found.matches).toHaveLength(2);
        expect(found.truncated).toBe(true);
    });

    it('casa chave aninhada sem casar o caminho', () => {
        // "store.book[0]" nao e texto de nenhum no: a busca nao olha o caminho.
        expect(searchNodes(nodes, 'store.book').matches).toEqual([]);
    });
});
