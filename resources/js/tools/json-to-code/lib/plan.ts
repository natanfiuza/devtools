import type { Field, TypeNode } from './infer';
import { typeNameFrom } from './names';

/**
 * Do modelo de tipos para a **lista de declaracoes** que os emissores escrevem.
 *
 * Um modelo de tipos e uma arvore; TypeScript, PHP e Python querem uma lista de
 * tipos nomeados. Este modulo faz essa passagem e resolve a parte chata dela:
 * **dar nome** a cada objeto aninhado, sem repetir nome nenhum.
 *
 * A ordem e em largura, a raiz primeiro e depois os aninhados na ordem em que
 * aparecem no documento. O TypeScript e o PHP declaram em qualquer ordem; o
 * Python precisa que o tipo apareca antes de quem o usa, e por isso o emissor
 * dele **inverte** a lista - filho antes do pai. Inverter a largura resolve
 * isso porque a unica seta possivel vai do pai para o filho.
 */

export interface TypeDeclaration {
    name: string;
    fields: Field[];
    /**
     * Quantos niveis o tipo desceu da raiz - a raiz e zero.
     *
     * E o que permite ao Python ordenar sem refazer a travessia: filho antes do
     * pai e, simplesmente, profundidade de tras para frente.
     */
    depth: number;
}

export interface TypePlan {
    declarations: TypeDeclaration[];
    /** O nome de cada objeto declarado - a identidade do no e a chave. */
    names: Map<TypeNode, string>;
}

/** Um objeto que virou declaracao: o no, o nome e a profundidade dele. */
interface NamedObject {
    node: Extract<TypeNode, { kind: 'object' }>;
    name: string;
    depth: number;
}

/** Reserva para chave que nao deixa nenhuma letra aproveitavel (`_`, `123`, `-`). */
const NESTED_FALLBACK = 'Type';

export function planTypes(root: TypeNode, rootName: string): TypePlan {
    const declarations: TypeDeclaration[] = [];
    const names = new Map<TypeNode, string>();
    const used = new Set<string>();
    const pending = objects(root, rootName, 0);

    while (pending.length > 0) {
        const item = pending.shift();

        if (item === undefined || names.has(item.node)) {
            continue;
        }

        const name = unique(item.name, used);

        names.set(item.node, name);
        declarations.push({ name, fields: item.node.fields, depth: item.depth });

        for (const field of item.node.fields) {
            pending.push(
                ...objects(field.type, typeNameFrom(field.key, NESTED_FALLBACK), item.depth + 1),
            );
        }
    }

    return { declarations, names };
}

/**
 * Os objetos deste tipo que precisam de nome.
 *
 * Desce por array e por uniao porque o nome acompanha o **campo**: `tags` com
 * `[{...}]` declara `Tags`, e `[{...}, "x"]` tambem - o objeto e um dos membros,
 * e nao um campo proprio.
 */
function objects(node: TypeNode, name: string, depth: number): NamedObject[] {
    switch (node.kind) {
        case 'object':
            return [{ node, name, depth }];
        case 'array':
            return objects(node.element, name, depth);
        case 'union':
            return node.members.flatMap((member) => objects(member, name, depth));
        default:
            return [];
    }
}

/**
 * Nome livre: `Tags`, `Tags2`, `Tags3`...
 *
 * A comparacao e **sem caixa** - `Nome` e `nome` sao o mesmo nome. O TypeScript
 * e o Python distinguem os dois, mas o PHP nao: nome de classe la e
 * insensivel a caixa, e `class Root` com `class root` nao compila. Uma regra so
 * para os tres custa um sufixo em um caso raro e poupa a explicacao.
 */
function unique(name: string, used: Set<string>): string {
    if (!used.has(name.toLowerCase())) {
        used.add(name.toLowerCase());

        return name;
    }

    let suffix = 2;

    while (used.has(`${name}${suffix}`.toLowerCase())) {
        suffix += 1;
    }

    used.add(`${name}${suffix}`.toLowerCase());

    return `${name}${suffix}`;
}
