import { describe, expect, it } from 'vitest';
import {
    buildPath,
    escapeKey,
    indexSegment,
    isIdentifier,
    keySegment,
    nodePath,
} from '@/tools/json-viewer/lib/path';
import { flatten } from '@/tools/json-viewer/lib/tree';
import type { JsonValue } from '@/tools/json-viewer/lib/types';

/**
 * JSONPath do no: o que a pessoa copia da linha e cola em outra ferramenta.
 * Chave que nao e identificador precisa vir citada, senao o caminho vira dois
 * niveis e a copia mente.
 */

describe('isIdentifier', () => {
    it('aceita nome simples', () => {
        expect(isIdentifier('store')).toBe(true);
        expect(isIdentifier('book_1')).toBe(true);
        expect(isIdentifier('$price')).toBe(true);
        expect(isIdentifier('a1')).toBe(true);
    });

    it('recusa o que precisa de citacao', () => {
        expect(isIdentifier('')).toBe(false);
        expect(isIdentifier('a.b')).toBe(false);
        expect(isIdentifier('com espaco')).toBe(false);
        expect(isIdentifier('1a')).toBe(false);
        expect(isIdentifier('a-b')).toBe(false);
        expect(isIdentifier('ção')).toBe(false);
    });
});

describe('escapeKey', () => {
    it('escapa a barra antes da aspa', () => {
        expect(escapeKey("it's")).toBe("it\\'s");
        expect(escapeKey('back\\slash')).toBe('back\\\\slash');
        // A ordem importa: escapar a aspa primeiro criaria uma barra nova.
        expect(escapeKey("a\\'b")).toBe("a\\\\\\'b");
    });
});

describe('segmentos', () => {
    it('usa ponto para identificador e citacao para o resto', () => {
        expect(keySegment('store')).toBe('.store');
        expect(keySegment('a.b')).toBe("['a.b']");
        expect(keySegment('')).toBe("['']");
    });

    it('escreve o indice do array entre colchetes', () => {
        expect(indexSegment(0)).toBe('[0]');
        expect(indexSegment(12)).toBe('[12]');
    });

    it('monta o caminho a partir da raiz', () => {
        expect(buildPath([])).toBe('$');
        expect(buildPath(['.store', '.book', '[0]'])).toBe('$.store.book[0]');
    });
});

describe('nodePath', () => {
    const doc: JsonValue = {
        store: {
            'a.b': [{ "it's": 1 }, 'texto'],
        },
        'com espaco': true,
    };
    const nodes = flatten(doc);

    /** Indice da chave, com guarda: `findIndex` devolve -1 quando nao acha. */
    function keyIndex(key: string): number {
        const index = nodes.findIndex((node) => node.key === key);

        expect(index, `chave ${key}`).toBeGreaterThanOrEqual(0);

        return index;
    }

    function pathOfKey(key: string): string {
        return nodePath(nodes, keyIndex(key));
    }

    it('comeca na raiz', () => {
        expect(nodePath(nodes, 0)).toBe('$');
    });

    it('junta chave e indice ate a raiz', () => {
        expect(pathOfKey('store')).toBe('$.store');
        // O indice 5 e o segundo item do array `a.b`: folha, sem chave.
        expect(nodes[5]?.value).toBe('texto');
        expect(nodePath(nodes, 5)).toBe("$.store['a.b'][1]");
    });

    it('cita a chave que nao e identificador', () => {
        expect(pathOfKey('com espaco')).toBe("$['com espaco']");
    });

    it('escapa a aspa dentro da citacao', () => {
        expect(pathOfKey("it's")).toBe("$.store['a.b'][0]['it\\'s']");
    });

    it('devolve a raiz para indice fora da lista', () => {
        expect(nodePath(nodes, 999)).toBe('$');
    });
});
