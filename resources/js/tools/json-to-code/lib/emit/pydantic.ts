import { acceptsNull, type Field, type TypeNode } from '../infer';
import { pythonClassName, pythonField } from '../names';
import type { TypePlan } from '../plan';

/**
 * O alvo Pydantic: um `BaseModel` por objeto.
 *
 * Duas coisas que o formato obriga:
 *
 * - **o modelo tem que existir antes de quem o usa**. O Python le a classe no
 *   momento em que a define, entao o tipo aninhado vem **antes** do pai. A
 *   lista do plano e reordenada por **profundidade de tras para frente**, e
 *   isso basta: a unica referencia possivel vai do pai para o filho, entao o
 *   mais fundo vem primeiro. A ordenacao e estavel, e por isso os irmaos
 *   continuam na ordem do documento;
 * - **`Any` so entra quando precisa**, com o import junto: `list[Any]` para o
 *   array vazio e `dict[str, Any]` para o objeto vazio. Sem isso, o arquivo
 *   gerado teria um import que ninguem usa.
 *
 * Chave nao identificador e renomeada (`first-name` vira `first_name`), e o MVP
 * nao emite alias - como no PHP. Aqui ha um caso a mais: chave que comeca com
 * `_` e **atributo privado** no Pydantic, e o campo sumiria sem erro nenhum; o
 * `_` vai para o fim.
 *
 * O texto termina com quebra de linha, como arquivo.
 */
export function emitPydantic(plan: TypePlan): string {
    const renamed = pythonNames(plan);
    const context = { plan, renamed };

    // Filho antes do pai: o mais fundo primeiro. A ordenacao estavel preserva a
    // ordem do documento entre irmaos, que e a que a pessoa reconhece.
    const classes = [...plan.declarations]
        .sort((left, right) => right.depth - left.depth)
        .map((entry) => {
            const name = renamed.get(entry.name) ?? entry.name;

            return baseModel(name, entry.fields, context);
        });

    const imports = plan.declarations.some((entry) => entry.fields.some((field) => usesAny(field.type)))
        ? 'from typing import Any\n\nfrom pydantic import BaseModel'
        : 'from pydantic import BaseModel';

    return `${imports}\n\n\n${classes.join('\n\n\n')}\n`;
}

interface PythonContext {
    plan: TypePlan;
    /** Nome do plano para nome no Python: `None`, `True` e `False` ganham `_`. */
    renamed: Map<string, string>;
}

function pythonNames(plan: TypePlan): Map<string, string> {
    const renamed = new Map<string, string>();
    const used = new Set<string>();

    for (const declaration of plan.declarations) {
        const base = pythonClassName(declaration.name);
        let candidate = base;
        let suffix = 2;

        while (used.has(candidate)) {
            candidate = `${base}${suffix}`;
            suffix += 1;
        }

        used.add(candidate);
        renamed.set(declaration.name, candidate);
    }

    return renamed;
}

function baseModel(name: string, fields: Field[], context: PythonContext): string {
    const used = new Set<string>();
    const lines = fields.map((field) => {
        const base = pythonField(field.key);
        let property = base;
        let suffix = 2;

        // Duas chaves podem cair no mesmo nome (`first-name` e `first_name`).
        while (used.has(property)) {
            property = `${base}${suffix}`;
            suffix += 1;
        }

        used.add(property);

        const type = render(field.type, context);

        if (!field.optional) {
            return `    ${property}: ${type}`;
        }

        // O campo que faltou pode faltar de verdade: o valor padrao e `None`, e
        // o tipo diz isso.
        return `    ${property}: ${acceptsNull(field.type) ? type : `${type} | None`} = None`;
    });

    return `class ${name}(BaseModel):\n${lines.join('\n')}`;
}

function render(node: TypeNode, context: PythonContext): string {
    switch (node.kind) {
        case 'string':
            return 'str';
        case 'int':
            return 'int';
        case 'float':
            return 'float';
        case 'bool':
            return 'bool';
        case 'null':
            return 'None';
        case 'unknown':
            return 'Any';
        case 'open_object':
            return 'dict[str, Any]';
        case 'array':
            return `list[${render(node.element, context)}]`;
        case 'union':
            return node.members.map((member) => render(member, context)).join(' | ');
        case 'object': {
            const planned = context.plan.names.get(node);

            return planned === undefined ? 'dict[str, Any]' : (context.renamed.get(planned) ?? 'dict[str, Any]');
        }
    }
}

/** `true` quando o tipo precisa de `Any` em algum lugar (o import segue junto). */
function usesAny(node: TypeNode): boolean {
    switch (node.kind) {
        case 'unknown':
        case 'open_object':
            return true;
        case 'array':
            return usesAny(node.element);
        case 'union':
            return node.members.some((member) => usesAny(member));
        case 'object':
            return node.fields.some((field) => usesAny(field.type));
        default:
            return false;
    }
}
