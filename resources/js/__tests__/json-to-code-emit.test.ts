import { describe, expect, it } from 'vitest';
import { emitPhp } from '@/tools/json-to-code/lib/emit/php';
import { emitPydantic } from '@/tools/json-to-code/lib/emit/pydantic';
import { emitTypeScript } from '@/tools/json-to-code/lib/emit/typescript';
import { generate, TARGET_EXTENSIONS } from '@/tools/json-to-code/lib/generate';
import { infer } from '@/tools/json-to-code/lib/infer';
import { planTypes } from '@/tools/json-to-code/lib/plan';
import type { JsonValue } from '@/lib/json/types';

/**
 * O codigo que sai em cada alvo.
 *
 * O texto esperado esta escrito por inteiro, e nao em pedacos: o que se cobra
 * aqui e o **arquivo**, com a indentacao, as linhas em branco e a ordem dos
 * campos que alguem vai colar no projeto. Um `toContain` passaria com o arquivo
 * torto.
 */

function escrever(value: JsonValue, rootName = 'Root'): Record<string, string> {
    const plan = planTypes(infer(value), rootName);

    return {
        typescript: emitTypeScript(plan),
        php: emitPhp(plan),
        pydantic: emitPydantic(plan),
    };
}

describe('os primitivos', () => {
    const DOCUMENTO: JsonValue = {
        nome: 'Ana',
        idade: 30,
        ativo: true,
        observacao: null,
        tags: ['a', 'b'],
    };

    it('escreve TypeScript', () => {
        expect(escrever(DOCUMENTO).typescript).toBe(
            `export interface Root {
    nome: string;
    idade: number;
    ativo: boolean;
    observacao: null;
    tags: string[];
}
`,
        );
    });

    it('escreve PHP', () => {
        expect(escrever(DOCUMENTO).php).toBe(
            `<?php

declare(strict_types=1);

final class Root
{
    public function __construct(
        public readonly string $nome,
        public readonly int $idade,
        public readonly bool $ativo,
        public readonly null $observacao,
        /** @var string[] */
        public readonly array $tags,
    ) {
    }
}
`,
        );
    });

    it('escreve Pydantic', () => {
        expect(escrever(DOCUMENTO).pydantic).toBe(
            `from pydantic import BaseModel


class Root(BaseModel):
    nome: str
    idade: int
    ativo: bool
    observacao: None
    tags: list[str]
`,
        );
    });
});

describe('objetos aninhados', () => {
    const DOCUMENTO: JsonValue = {
        cliente: { nome: 'Ana', telefone: null },
        itens: [
            { sku: 'A', qtd: 2 },
            { sku: 'B', qtd: 1, desconto: 0.5 },
        ],
        metadados: {},
    };

    it('escreve uma interface por objeto', () => {
        expect(escrever(DOCUMENTO).typescript).toBe(
            `export interface Root {
    cliente: Cliente;
    itens: Itens[];
    metadados: Record<string, unknown>;
}

export interface Cliente {
    nome: string;
    telefone: null;
}

export interface Itens {
    sku: string;
    qtd: number;
    desconto?: number;
}
`,
        );
    });

    it('escreve uma classe por objeto, com o `@var` onde o nativo perde', () => {
        expect(escrever(DOCUMENTO).php).toBe(
            `<?php

declare(strict_types=1);

final class Root
{
    public function __construct(
        public readonly Cliente $cliente,
        /** @var Itens[] */
        public readonly array $itens,
        /** @var array<string, mixed> */
        public readonly array $metadados,
    ) {
    }
}

final class Cliente
{
    public function __construct(
        public readonly string $nome,
        public readonly null $telefone,
    ) {
    }
}

final class Itens
{
    public function __construct(
        public readonly string $sku,
        public readonly int $qtd,
        public readonly ?float $desconto = null,
    ) {
    }
}
`,
        );
    });

    it('escreve o filho antes do pai no Pydantic', () => {
        // O Python le a classe no momento em que a define: o tipo tem que
        // existir antes de quem o usa.
        expect(escrever(DOCUMENTO).pydantic).toBe(
            `from typing import Any

from pydantic import BaseModel


class Cliente(BaseModel):
    nome: str
    telefone: None


class Itens(BaseModel):
    sku: str
    qtd: int
    desconto: float | None = None


class Root(BaseModel):
    cliente: Cliente
    itens: list[Itens]
    metadados: dict[str, Any]
`,
        );
    });
});

describe('o que o JSON nao diz', () => {
    it('faz uniao quando os itens nao se parecem', () => {
        const alvos = escrever({ valor: [1, 'texto'] });

        expect(alvos.typescript).toContain('valor: (string | number)[];');
        expect(alvos.php).toContain('/** @var (string|int)[] */');
        expect(alvos.pydantic).toContain('valor: list[str | int]');
    });

    it('anula o campo que veio nulo', () => {
        const alvos = escrever({ site: ['a', null] });

        expect(alvos.typescript).toContain('site: (string | null)[];');
    });

    it('nao escreve tipo nenhum para o array vazio e o objeto vazio', () => {
        const alvos = escrever({ vazio: [], solto: {} });

        expect(alvos.typescript).toBe(`export interface Root {
    vazio: unknown[];
    solto: Record<string, unknown>;
}
`);
        expect(alvos.php).toContain('/** @var mixed[] */');
        expect(alvos.php).toContain('/** @var array<string, mixed> */');
        expect(alvos.pydantic).toContain('vazio: list[Any]');
        expect(alvos.pydantic).toContain('solto: dict[str, Any]');
    });
});

describe('nomes que precisam de ajuste', () => {
    it('cita a chave no TypeScript sem renomear', () => {
        const typescript = escrever({ 'first-name': 'a', nome: 'b' }).typescript;

        expect(typescript).toContain('"first-name": string;');
        expect(typescript).toContain('nome: string;');
    });

    it('renomeia a chave no PHP e no Python, sem repetir nome', () => {
        const documento: JsonValue = {
            class: 'a',
            'first-name': 'b',
            first_name: 'c',
        };

        expect(escrever(documento).php).toContain('public readonly string $class,');
        expect(escrever(documento).php).toContain('public readonly string $first_name,');
        expect(escrever(documento).php).toContain('public readonly string $first_name2,');
        expect(escrever(documento).pydantic).toContain('class_: str');
        expect(escrever(documento).pydantic).toContain('first_name: str');
        expect(escrever(documento).pydantic).toContain('first_name2: str');
    });

    it('desvia do nome reservado de classe', () => {
        const typescript = escrever({ foreach: { a: 1 } }).typescript;
        const php = escrever({ foreach: { a: 1 } }).php;

        expect(typescript).toContain('export interface Foreach {');
        // `class Foreach` nao compila: o nome de classe no PHP e reservado.
        expect(php).toContain('final class Foreach_');
        expect(php).toContain('public readonly Foreach_ $foreach,');
    });

    it('poe o campo opcional no fim no PHP', () => {
        // O PHP exige parametro com valor padrao depois de todos os
        // obrigatorios, e a ordem do JSON nao sabe disso: `b` faltou num dos
        // itens, entao ele sai da frente de `a`, que veio em todos.
        expect(escrever({ itens: [{ b: 'x', a: 1 }, { a: 2 }] }).php).toContain(
            `final class Itens
{
    public function __construct(
        public readonly int $a,
        public readonly ?string $b = null,
    ) {
    }
}`,
        );
    });
});

describe('generate', () => {
    it('conta os tipos declarados', () => {
        const generation = generate(
            { cliente: { nome: 'Ana' }, tags: ['x'] },
            { target: 'typescript', rootName: 'Root' },
        );

        expect(generation?.types).toBe(2);
    });

    it('usa o nome de raiz digitado, corrigido', () => {
        const generation = generate(
            { nome: 'Ana' },
            { target: 'typescript', rootName: 'meu pedido' },
        );

        expect(generation?.code).toContain('export interface meu_pedido {');
    });

    it('cai no nome padrao quando o campo esta vazio', () => {
        const generation = generate({ nome: 'Ana' }, { target: 'php', rootName: '' });

        expect(generation?.code).toContain('final class Root');
    });

    it('nao gera nada quando a raiz nao e um objeto com chaves', () => {
        // DTO de numero nao existe em PHP nem em Python, e classe sem campo nao
        // descreve nada: a tela diz o que precisa em vez de escrever vazio.
        expect(generate(42, { target: 'typescript', rootName: 'Root' })).toBeNull();
        expect(generate('texto', { target: 'php', rootName: 'Root' })).toBeNull();
        expect(generate([1, 2], { target: 'pydantic', rootName: 'Root' })).toBeNull();
        expect(generate({}, { target: 'typescript', rootName: 'Root' })).toBeNull();
        expect(generate(null, { target: 'typescript', rootName: 'Root' })).toBeNull();
    });

    it('troca o alvo sem mudar a inferencia', () => {
        const documento: JsonValue = { tags: ['a'] };

        expect(generate(documento, { target: 'typescript', rootName: 'Root' })?.code).toContain(
            'string[]',
        );
        expect(generate(documento, { target: 'php', rootName: 'Root' })?.code).toContain('array $tags');
        expect(generate(documento, { target: 'pydantic', rootName: 'Root' })?.code).toContain(
            'list[str]',
        );
    });

    it('da uma extensao de arquivo por alvo', () => {
        expect(TARGET_EXTENSIONS).toEqual({ typescript: 'ts', php: 'php', pydantic: 'py' });
    });
});
