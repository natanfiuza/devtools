import { describe, expect, it } from 'vitest';
import {
    acceptsNull,
    infer,
    merge,
    unionOf,
    type TypeNode,
} from '@/tools/json-to-code/lib/infer';
import {
    identifier,
    isIdentifier,
    phpClassName,
    pythonClassName,
    pythonField,
    tsProperty,
    typeNameFrom,
} from '@/tools/json-to-code/lib/names';
import { planTypes } from '@/tools/json-to-code/lib/plan';

/**
 * O modelo de tipos: inferencia, nomes e o plano das declaracoes.
 *
 * Os tres modulos andam juntos porque um explica o outro - a inferencia decide
 * o formato, os nomes decidem o identificador e o plano decide quem vira tipo
 * declarado. O codigo escrito sai no json-to-code-emit.test.ts.
 */

/** Atalho para o que aparece em quase todo caso: um objeto com campos. */
function objeto(node: TypeNode): Extract<TypeNode, { kind: 'object' }> {
    if (node.kind !== 'object') {
        throw new Error(`esperava um objeto, veio ${node.kind}`);
    }

    return node;
}

describe('inferencia', () => {
    it('infere os primitivos pelo valor', () => {
        expect(infer('texto')).toEqual({ kind: 'string' });
        expect(infer(true)).toEqual({ kind: 'bool' });
        expect(infer(null)).toEqual({ kind: 'null' });
        expect(infer(7)).toEqual({ kind: 'int' });
        expect(infer(7.25)).toEqual({ kind: 'float' });
    });

    it('separa inteiro de decimal pelo valor, e nao pelo texto', () => {
        // `1` e `1.0` sao o mesmo numero depois do `JSON.parse`: o que decide e
        // o que o motor entregou, e isso esta anotado como limite conhecido.
        expect(infer(1.0)).toEqual({ kind: 'int' });
        expect(infer(0.5)).toEqual({ kind: 'float' });
    });

    it('nao inventa formato para o que veio vazio', () => {
        expect(infer([])).toEqual({
            kind: 'array',
            element: { kind: 'unknown' },
        });
        expect(infer({})).toEqual({ kind: 'open_object' });
    });

    it('junta os itens de um array num tipo so', () => {
        expect(infer([1, 2, 3])).toEqual({
            kind: 'array',
            element: { kind: 'int' },
        });
        // Inteiro com decimal e o mesmo numero em JSON: o tipo e o mais largo.
        expect(infer([1, 2.5])).toEqual({
            kind: 'array',
            element: { kind: 'float' },
        });
        expect(infer([1, 1])).toEqual({
            kind: 'array',
            element: { kind: 'int' },
        });
    });

    it('faz uniao quando os itens nao se parecem', () => {
        expect(infer([1, 'a'])).toEqual({
            kind: 'array',
            element: {
                kind: 'union',
                members: [{ kind: 'string' }, { kind: 'int' }],
            },
        });
        expect(infer([{ a: 1 }, null])).toEqual({
            kind: 'array',
            element: {
                kind: 'union',
                members: [
                    {
                        kind: 'object',
                        fields: [
                            {
                                key: 'a',
                                type: { kind: 'int' },
                                optional: false,
                            },
                        ],
                    },
                    { kind: 'null' },
                ],
            },
        });
    });

    it('funde os objetos de um array, marcando o campo que faltou', () => {
        // Nao sao dois formatos: e um formato com `b` opcional - que e o que o
        // dado prova.
        expect(infer([{ a: 1 }, { a: 2, b: 'x' }])).toEqual({
            kind: 'array',
            element: {
                kind: 'object',
                fields: [
                    { key: 'a', type: { kind: 'int' }, optional: false },
                    { key: 'b', type: { kind: 'string' }, optional: true },
                ],
            },
        });
    });

    it('mantem a ordem do documento nos campos', () => {
        const node = objeto(infer({ z: 1, a: 2, m: 3 }));

        expect(node.fields.map((field) => field.key)).toEqual(['z', 'a', 'm']);
    });

    it('aninha objeto dentro de objeto', () => {
        const node = objeto(infer({ autor: { nome: 'Ana' } }));
        const autor = node.fields[0]?.type;

        expect(autor?.kind).toBe('object');
    });
});

describe('uniao e fusao', () => {
    it('nao faz uniao de um tipo so', () => {
        expect(unionOf([{ kind: 'int' }, { kind: 'int' }])).toEqual({
            kind: 'int',
        });
    });

    it('descarta o desconhecido quando ha informacao', () => {
        expect(unionOf([{ kind: 'unknown' }, { kind: 'string' }])).toEqual({
            kind: 'string',
        });
        expect(unionOf([{ kind: 'unknown' }])).toEqual({ kind: 'unknown' });
    });

    it('poe os primitivos antes e o null por ultimo', () => {
        const node = unionOf([
            { kind: 'null' },
            { kind: 'object', fields: [] },
            { kind: 'bool' },
            { kind: 'string' },
        ]);

        expect(
            node.kind === 'union' && node.members.map((member) => member.kind),
        ).toEqual(['string', 'bool', 'object', 'null']);
    });

    it('achata uniao dentro de uniao', () => {
        const node = merge(
            { kind: 'union', members: [{ kind: 'int' }, { kind: 'bool' }] },
            { kind: 'string' },
        );

        expect(
            node.kind === 'union' && node.members.map((member) => member.kind),
        ).toEqual(['string', 'int', 'bool']);
    });

    it('sabe quem ja aceita null', () => {
        expect(acceptsNull({ kind: 'null' })).toBe(true);
        expect(acceptsNull({ kind: 'unknown' })).toBe(true);
        expect(
            acceptsNull({
                kind: 'union',
                members: [{ kind: 'string' }, { kind: 'null' }],
            }),
        ).toBe(true);
        expect(acceptsNull({ kind: 'string' })).toBe(false);
    });
});

describe('nomes', () => {
    it('reconhece identificador, inclusive com acento', () => {
        expect(isIdentifier('nome')).toBe(true);
        expect(isIdentifier('_id')).toBe(true);
        expect(isIdentifier('ação')).toBe(true);
        expect(isIdentifier('first-name')).toBe(false);
        expect(isIdentifier('2x')).toBe(false);
        expect(isIdentifier('')).toBe(false);
    });

    it('corrige o texto livre do nome da raiz', () => {
        expect(identifier('meu tipo!', 'Root')).toBe('meu_tipo_');
        expect(identifier('', 'Root')).toBe('Root');
        expect(identifier('   ', 'Root')).toBe('Root');
        expect(identifier('123', 'Root')).toBe('_123');
        // O que a pessoa escreveu e respeitado: so o que nao cabe vira `_`.
        expect(identifier('meu_tipo', 'Root')).toBe('meu_tipo');
    });

    it('monta o nome do tipo a partir da chave', () => {
        expect(typeNameFrom('created_at', 'Type')).toBe('CreatedAt');
        expect(typeNameFrom('tags', 'Type')).toBe('Tags');
        expect(typeNameFrom('first-name', 'Type')).toBe('FirstName');
        expect(typeNameFrom('_', 'Type')).toBe('Type');
        // Sem singularizacao: adivinhar o singular erraria em `status` e `data`.
        expect(typeNameFrom('enderecos', 'Type')).toBe('Enderecos');
    });

    it('cita a chave no TypeScript so quando precisa', () => {
        expect(tsProperty('nome')).toBe('nome');
        expect(tsProperty('ação')).toBe('ação');
        expect(tsProperty('first-name')).toBe('"first-name"');
    });

    it('tira a palavra reservada do campo no Python', () => {
        expect(pythonField('nome')).toBe('nome');
        expect(pythonField('class')).toBe('class_');
        expect(pythonField('from')).toBe('from_');
    });

    it('tira o sublinhado da frente do campo no Python', () => {
        // No Pydantic, nome com `_` na frente e atributo privado: o campo
        // sumiria da classe sem erro nenhum.
        expect(pythonField('_id')).toBe('id_');
        expect(pythonField('__meta')).toBe('meta_');
    });

    it('desvia das reservadas que pegam o nome de classe', () => {
        // PHP compara sem caixa: `class Foreach` nao compila.
        expect(phpClassName('Root')).toBe('Root');
        expect(phpClassName('Class')).toBe('Class_');
        expect(phpClassName('List')).toBe('List_');
        expect(phpClassName('Object')).toBe('Object_');
        // Python so tem tres reservadas maiusculas.
        expect(pythonClassName('Root')).toBe('Root');
        expect(pythonClassName('None')).toBe('None_');
        expect(pythonClassName('Class')).toBe('Class');
    });
});

describe('plano das declaracoes', () => {
    it('declara a raiz primeiro e depois os aninhados', () => {
        const plan = planTypes(
            objeto(infer({ cliente: { nome: 'Ana' }, etiquetas: ['a'] })),
            'Root',
        );

        expect(plan.declarations.map((entry) => entry.name)).toEqual([
            'Root',
            'Cliente',
        ]);
    });

    it('desce por array e por uniao para achar o objeto do campo', () => {
        const plan = planTypes(
            objeto(
                infer({ tags: [{ nome: 'a' }], outros: [{ x: 1 }, 'texto'] }),
            ),
            'Root',
        );

        expect(plan.declarations.map((entry) => entry.name)).toEqual([
            'Root',
            'Tags',
            'Outros',
        ]);
    });

    it('nao repete nome quando duas chaves caem no mesmo', () => {
        const plan = planTypes(
            objeto(infer({ tags: [{ a: 1 }], Tags: [{ b: 2 }] })),
            'Root',
        );

        expect(plan.declarations.map((entry) => entry.name)).toEqual([
            'Root',
            'Tags',
            'Tags2',
        ]);
    });

    it('nao repete nome ignorando a caixa', () => {
        // `Root` com `root` nao compila em PHP, que compara nome de classe sem
        // caixa - e a regra e uma so para os tres alvos.
        const plan = planTypes(
            objeto(infer({ Cliente: { a: 1 }, cliente: { b: 2 } })),
            'Root',
        );

        expect(plan.declarations.map((entry) => entry.name)).toEqual([
            'Root',
            'Cliente',
            'Cliente2',
        ]);
    });

    it('nao declara nada quando a raiz nao e objeto', () => {
        expect(planTypes(infer([1, 2]), 'Root').declarations).toEqual([]);
        expect(planTypes(infer({}), 'Root').declarations).toEqual([]);
    });
});
