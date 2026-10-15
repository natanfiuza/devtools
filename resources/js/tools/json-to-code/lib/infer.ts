import type { JsonValue } from '@/lib/json/types';

/**
 * Do valor JSON para o **modelo de tipos**.
 *
 * Este modulo nao sabe escrever TypeScript, PHP nem Python: ele so descreve o
 * que o documento tem. Sao os emissores que decidem como cada coisa se escreve
 * em cada linguagem, e e por isso que a inferencia e uma so.
 *
 * Quatro coisas que o JSON nao diz e o modelo precisa decidir:
 *
 * - **campo ausente**. Num array de objetos, `[{a: 1}, {b: 2}]` nao diz que `a`
 *   e `b` sao opcionais - diz que naquele item eles nao estavam. O modelo marca
 *   os dois como opcionais, que e o que o dado **prova**;
 * - **`null`**. Nao e ausencia: e um valor que estava la e nao tinha conteudo.
 *   Vira um membro da uniao, e cada emissor o escreve do seu jeito (`| null`,
 *   `?T`, `| None`);
 * - **inteiro e decimal**. `JSON.parse` devolve `number` para os dois, e `1` e
 *   `1.0` viram o mesmo `1`. A distincao sai do **valor** (`Number.isInteger`),
 *   entao o modelo e honesto sobre o que deu para ver - e o `1.0` de um
 *   documento vira `int` se o motor o entregar como inteiro. Esta anotado como
 *   limite conhecido na documentacao da tool;
 * - **vazio**. `[]` nao diz nada sobre o elemento e `{}` nao diz nada sobre as
 *   chaves, entao cada um tem o seu tipo (`unknown` e `open_object`) em vez de
 *   virar `any` ou uma lista de nada.
 */

/** Um campo de um objeto: a chave, o tipo e se ele faltou em algum item. */
export interface Field {
    key: string;
    type: TypeNode;
    optional: boolean;
}

/**
 * O modelo.
 *
 * `unknown` e `open_object` sao os dois "nao sei": o primeiro e o elemento de um
 * array vazio, o segundo e um objeto sem chave nenhuma.
 */
export type TypeNode =
    | { kind: 'string' }
    | { kind: 'int' }
    | { kind: 'float' }
    | { kind: 'bool' }
    | { kind: 'null' }
    | { kind: 'unknown' }
    | { kind: 'open_object' }
    | { kind: 'array'; element: TypeNode }
    | { kind: 'object'; fields: Field[] }
    | { kind: 'union'; members: TypeNode[] };

export function infer(value: JsonValue): TypeNode {
    if (value === null) {
        return { kind: 'null' };
    }

    if (Array.isArray(value)) {
        return {
            kind: 'array',
            // Um array vazio nao diz nada sobre o elemento - e `unknown` e
            // exatamente "nao deu para dizer".
            //
            // A lista passa por `merge` e nao por `unionOf`: e `merge` que funde
            // dois objetos numa forma so, com os campos que faltaram marcados
            // como opcionais.
            element:
                value.length === 0
                    ? { kind: 'unknown' }
                    : value.map(infer).reduce((left, right) => merge(left, right)),
        };
    }

    if (typeof value === 'string') {
        return { kind: 'string' };
    }

    if (typeof value === 'boolean') {
        return { kind: 'bool' };
    }

    if (typeof value === 'number') {
        return Number.isInteger(value) ? { kind: 'int' } : { kind: 'float' };
    }

    const fields = Object.entries(value).map(([key, item]): Field => ({
        key,
        type: infer(item),
        optional: false,
    }));

    return fields.length === 0 ? { kind: 'open_object' } : { kind: 'object', fields };
}

/**
 * A uniao de tipos que nao sao iguais.
 *
 * `unknown` nao entra: ele e ausencia de informacao, e uma uniao com ele nao
 * diria mais nada. Se so houver `unknown`, ele e o resultado.
 *
 * `int` com `float` vira `float` - em JSON os dois sao o mesmo numero, e
 * `1 | 1.5` nao existe em linguagem nenhuma.
 */
export function unionOf(members: TypeNode[]): TypeNode {
    const flat = members.flatMap((member) =>
        member.kind === 'union' ? member.members : [member],
    );

    const known = flat.filter((member) => member.kind !== 'unknown');

    if (known.length === 0) {
        return { kind: 'unknown' };
    }

    // Anotado de proposito: o TypeScript deduz o filtro como predicado de tipo e
    // estreitaria a lista para "tudo menos int e float" - e e justamente o
    // `float` que entra nela logo abaixo.
    const others: TypeNode[] = known.filter(
        (member) => member.kind !== 'int' && member.kind !== 'float',
    );

    if (others.length !== known.length) {
        others.push(
            known.some((member) => member.kind === 'float') ? { kind: 'float' } : { kind: 'int' },
        );
    }

    const unique = new Map<string, TypeNode>();

    for (const member of others) {
        unique.set(canonical(member), member);
    }

    const types = [...unique.values()].sort((a, b) => rank(a) - rank(b));

    // Um tipo so nao e uniao: `int | int` nao existe, e envolver seria mentir
    // sobre o formato do modelo.
    return types.length === 1
        ? (types[0] as TypeNode)
        : { kind: 'union', members: types };
}

/**
 * O que dois tipos de um mesmo campo tem em comum.
 *
 * Objetos **fundem** em vez de virar uniao: `[{a: 1}, {a: 2, b: 3}]` e uma lista
 * de uma coisa so, com `b` opcional - e nao uma lista de duas formas. Uniao fica
 * para o que nao da para fundir (`[{a: 1}, "texto"]`).
 */
export function merge(left: TypeNode, right: TypeNode): TypeNode {
    if (left.kind === 'unknown') {
        return right;
    }

    if (right.kind === 'unknown') {
        return left;
    }

    if (left.kind === right.kind) {
        if (left.kind === 'array' && right.kind === 'array') {
            return { kind: 'array', element: merge(left.element, right.element) };
        }

        if (left.kind === 'object' && right.kind === 'object') {
            return mergeObjects(left, right);
        }

        return left;
    }

    // Inteiro e decimal sao o mesmo numero em JSON: juntos, o tipo e o mais
    // largo dos dois.
    if (
        (left.kind === 'int' && right.kind === 'float') ||
        (left.kind === 'float' && right.kind === 'int')
    ) {
        return { kind: 'float' };
    }

    return unionOf([left, right]);
}

function mergeObjects(
    left: Extract<TypeNode, { kind: 'object' }>,
    right: Extract<TypeNode, { kind: 'object' }>,
): TypeNode {
    const fields: Field[] = [];

    // A ordem e a do primeiro objeto que trouxe a chave: o codigo gerado sai na
    // ordem do documento, que e a ordem que quem le o JSON espera reencontrar.
    for (const key of new Set([...left.fields.map((field) => field.key), ...right.fields.map((field) => field.key)])) {
        const here = left.fields.find((field) => field.key === key);
        const there = right.fields.find((field) => field.key === key);

        if (here !== undefined && there !== undefined) {
            fields.push({
                key,
                type: merge(here.type, there.type),
                optional: here.optional || there.optional,
            });

            continue;
        }

        const only = here ?? there;

        if (only !== undefined) {
            // A chave faltou de um dos lados: o dado prova que ela pode faltar.
            fields.push({ key, type: only.type, optional: true });
        }
    }

    return { kind: 'object', fields };
}

/**
 * `true` quando o tipo ja admite `null`.
 *
 * Quem marca campo opcional precisa saber: em PHP e em Python, um tipo que ja
 * aceita `null` nao ganha o anulavel de novo. `unknown` conta como sim - ele e
 * qualquer coisa, e `null` e uma delas.
 */
export function acceptsNull(node: TypeNode): boolean {
    if (node.kind === 'null' || node.kind === 'unknown') {
        return true;
    }

    return node.kind === 'union' && node.members.some((member) => member.kind === 'null');
}

/**
 * Assinatura de um tipo, para comparar e ordenar.
 *
 * Ela existe porque dois nos com o mesmo formato sao objetos diferentes em
 * memoria: `[1, 1]` tem dois `{kind: 'int'}` distintos, e compara-los por
 * identidade acusaria uma uniao.
 */
export function canonical(node: TypeNode): string {
    switch (node.kind) {
        case 'array':
            return `[${canonical(node.element)}]`;
        case 'object':
            return `{${node.fields
                .map((field) => `${field.key}${field.optional ? '?' : ''}:${canonical(field.type)}`)
                .join(',')}}`;
        case 'union':
            return `(${node.members.map(canonical).sort().join('|')})`;
        default:
            return node.kind;
    }
}

/** Ordem de leitura dos membros da uniao: primitivos, conteineres, `null` no fim. */
function rank(node: TypeNode): number {
    switch (node.kind) {
        case 'string':
            return 0;
        case 'int':
            return 1;
        case 'float':
            return 2;
        case 'bool':
            return 3;
        case 'array':
            return 4;
        case 'object':
            return 5;
        case 'open_object':
            return 6;
        case 'null':
            return 7;
        default:
            return 8;
    }
}
