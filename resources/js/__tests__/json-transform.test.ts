import { describe, expect, it } from 'vitest';
import {
    compareKeys,
    minifyJson,
    prettyJson,
    JSON_INDENT,
    sortJson,
} from '@/tools/json-viewer/lib/transform';
import type { JsonValue } from '@/lib/json/types';

/**
 * Formatacao, minificacao e ordenacao de chaves. Tudo por **valor**, e nao por
 * texto: quem manda no painel de saida e a arvore, e ela sai do mesmo valor que
 * o texto formatado.
 */

const DOC: JsonValue = {
    b: 1,
    a: { d: [3, 1, 2], c: 'x' },
    lista: [{ z: 2, a: 1 }, 5, null],
};

describe('prettyJson', () => {
    it('indenta com dois espacos', () => {
        expect(JSON_INDENT).toBe(2);
        expect(prettyJson({ a: [1] })).toBe('{\n  "a": [\n    1\n  ]\n}');
    });

    it('preserva a ordem das chaves', () => {
        expect(prettyJson({ b: 1, a: 2 })).toBe('{\n  "b": 1,\n  "a": 2\n}');
    });

    it('devolve o valor solto sem quebra de linha', () => {
        expect(prettyJson('texto')).toBe('"texto"');
        expect(prettyJson(null)).toBe('null');
    });
});

describe('minifyJson', () => {
    it('tira todo espaco que nao esta dentro de string', () => {
        expect(minifyJson({ a: [1, 2], b: 'x y' })).toBe(
            '{"a":[1,2],"b":"x y"}',
        );
    });

    it('e o inverso do pretty', () => {
        expect(JSON.parse(prettyJson(DOC))).toEqual(DOC);
        expect(minifyJson(JSON.parse(prettyJson(DOC)) as JsonValue)).toBe(
            minifyJson(DOC),
        );
    });
});

describe('compareKeys', () => {
    it('compara por code unit', () => {
        expect(compareKeys('a', 'b')).toBe(-1);
        expect(compareKeys('b', 'a')).toBe(1);
        expect(compareKeys('a', 'a')).toBe(0);
    });

    it('nao ordena numero como numero', () => {
        // Chave e string em JSON: "10" vem antes de "9" na comparacao de texto.
        expect(compareKeys('10', '9')).toBe(-1);
    });

    it('poe maiuscula antes de minuscula', () => {
        expect(compareKeys('A', 'a')).toBe(-1);
    });
});

describe('sortJson', () => {
    it('ordena as chaves de todos os objetos', () => {
        expect(Object.keys(sortJson(DOC) as Record<string, JsonValue>)).toEqual(
            ['a', 'b', 'lista'],
        );
    });

    it('ordena recursivamente, inclusive dentro de array', () => {
        const sorted = sortJson(DOC) as Record<string, JsonValue>;
        const interno = (sorted.a as Record<string, JsonValue>).d;

        // Array preserva a ordem dos itens...
        expect(interno).toEqual([3, 1, 2]);
        // ...mas o objeto dentro dele e ordenado.
        expect(
            Object.keys(
                (sorted.lista as JsonValue[])[0] as Record<string, JsonValue>,
            ),
        ).toEqual(['a', 'z']);
    });

    it('nao muda o valor original', () => {
        const sorted = sortJson(DOC);

        expect(sorted).not.toBe(DOC);
        expect(Object.keys(DOC)).toEqual(['b', 'a', 'lista']);
    });

    it('devolve folhas como estao', () => {
        expect(sortJson(0)).toBe(0);
        expect(sortJson('x')).toBe('x');
        expect(sortJson(null)).toBeNull();
    });

    it('ordena objeto vazio sem reclamar', () => {
        expect(sortJson({})).toEqual({});
    });
});
