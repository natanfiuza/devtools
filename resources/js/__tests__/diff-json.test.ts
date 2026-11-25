import { describe, expect, it } from 'vitest';
import { jsonDiff } from '@/tools/diff/lib/jsonDiff';
import type { JsonDiffOk } from '@/tools/diff/types';

/**
 * O diff semantico do JSON.
 *
 * A pergunta aqui e outra do modo texto: dois documentos com as mesmas folhas
 * sao o mesmo dado, mesmo que a chave esteja em outra ordem ou o espaco seja
 * outro. Cada teste pina um pedaco do dialeto de caminho (`$.a`, `$.a[0]`,
 * `$["chave estranha"]`) e uma leitura: o que conta como adicionado, removido e
 * alterado.
 */

function ok(left: string, right: string): JsonDiffOk {
    const resultado = jsonDiff(left, right);

    expect(resultado.ok, JSON.stringify(resultado)).toBe(true);

    return resultado as JsonDiffOk;
}

describe('diff JSON: o que nao e diferenca', () => {
    it('ignora espaco e quebra de linha', () => {
        expect(
            ok('{"a":1,"b":[1,2]}', '{\n  "a": 1,\n  "b": [1, 2]\n}'),
        ).toEqual({
            ok: true,
            changes: [],
            added: 0,
            removed: 0,
            changed: 0,
        });
    });

    it('ignora a ordem das chaves', () => {
        expect(ok('{"a":1,"b":2}', '{"b":2,"a":1}').changes).toEqual([]);
    });

    it('ignora a ordem da chave que parece inteiro', () => {
        expect(ok('{"b":1,"2":2}', '{"2":2,"b":1}').changes).toEqual([]);
    });
});

describe('diff JSON: o que e diferenca', () => {
    it('aponta o caminho do valor alterado', () => {
        expect(ok('{"a":1}', '{"a":2}').changes).toEqual([
            { path: '$.a', kind: 'changed', left: '1', right: '2' },
        ]);
    });

    it('compara array por indice', () => {
        const resultado = ok(
            '{"tags":["json","yaml"]}',
            '{"tags":["json","toml"]}',
        );

        expect(resultado.changes).toEqual([
            {
                path: '$.tags[1]',
                kind: 'changed',
                left: '"yaml"',
                right: '"toml"',
            },
        ]);
    });

    it('ve o item que entrou no fim da lista como adicionado', () => {
        const resultado = ok('{"tags":["a"]}', '{"tags":["a","b"]}');

        expect(resultado.changes).toEqual([
            { path: '$.tags[1]', kind: 'added', left: null, right: '"b"' },
        ]);
        expect(resultado.added).toBe(1);
    });

    it('escreve o valor como o JSON o escreveria', () => {
        const resultado = ok('{"a":null,"b":true}', '{"a":"x","b":false}');

        expect(resultado.changes).toEqual([
            { path: '$.a', kind: 'changed', left: 'null', right: '"x"' },
            { path: '$.b', kind: 'changed', left: 'true', right: 'false' },
        ]);
    });

    it('achata o objeto vazio como folha', () => {
        // Sem a folha do vasilhame vazio, esvaziar um objeto nao apareceria
        // como mudanca nenhuma.
        const resultado = ok('{"a":{"b":1}}', '{"a":{}}');

        expect(resultado.changes).toEqual([
            { path: '$.a.b', kind: 'removed', left: '1', right: null },
            { path: '$.a', kind: 'added', left: null, right: '{}' },
        ]);
        expect(resultado.removed).toBe(1);
        expect(resultado.added).toBe(1);
    });

    it('achata a lista vazia como folha', () => {
        const resultado = ok('{"a":[1]}', '{"a":[]}');

        expect(resultado.changes).toEqual([
            { path: '$.a[0]', kind: 'removed', left: '1', right: null },
            { path: '$.a', kind: 'added', left: null, right: '[]' },
        ]);
    });

    it('trata a raiz como folha quando o documento e escalar', () => {
        expect(ok('1', '2').changes).toEqual([
            { path: '$', kind: 'changed', left: '1', right: '2' },
        ]);
        expect(ok('{}', '[]').changes).toEqual([
            { path: '$', kind: 'changed', left: '{}', right: '[]' },
        ]);
    });

    it('cita com colchete a chave que nao e identificador', () => {
        const resultado = ok('{"chave estranha":1}', '{"chave estranha":2}');

        expect(resultado.changes[0]?.path).toBe('$["chave estranha"]');
    });

    it('conta adicionados, removidos e alterados', () => {
        const resultado = ok(
            '{"a":1,"b":2,"c":3}',
            '{"a":1,"b":20,"d":4,"e":5}',
        );

        expect(resultado.changed).toBe(1);
        expect(resultado.removed).toBe(1);
        expect(resultado.added).toBe(2);
        expect(resultado.changes).toHaveLength(4);
    });
});

describe('diff JSON: documento invalido', () => {
    it('recusa o lado esquerdo com linha e coluna', () => {
        const resultado = jsonDiff('{\n  "a": 1\n  "b": 2\n}', '{"a":1}');

        expect(resultado.ok).toBe(false);

        if (!resultado.ok) {
            expect(resultado.side).toBe('left');
            expect(resultado.line).toBe(3);
            expect(resultado.column).toBeGreaterThanOrEqual(1);
        }
    });

    it('recusa o lado direito', () => {
        // O V8 atual as vezes escreve a posicao e as vezes nao; este documento
        // (virgula faltando) e dos que trazem.
        const resultado = jsonDiff('{"a":1}', '{\n  "a": 1\n  "b": 2\n}');

        expect(resultado.ok).toBe(false);

        if (!resultado.ok) {
            expect(resultado.side).toBe('right');
            expect(resultado.line).toBe(3);
        }
    });
});
