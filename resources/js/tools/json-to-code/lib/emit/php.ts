import { acceptsNull, type Field, type TypeNode } from '../infer';
import { phpClassName, phpProperty } from '../names';
import type { TypePlan } from '../plan';

/**
 * O alvo PHP: um DTO por objeto, com propriedades promovidas.
 *
 * `final class`, `declare(strict_types=1)` e `public readonly` no construtor -
 * o DTO que nao muda depois de criado, que e o que um corpo de requisicao e.
 *
 * Tres decisoes que o formato obriga:
 *
 * - **campo opcional vai para o fim**. O PHP exige que parametro com valor
 *   padrao venha depois de todos os obrigatorios, e a ordem do JSON nao sabe
 *   disso. O codigo gerado muda a ordem dos campos quando precisa - e so entao;
 * - **`array` nao diz o que tem dentro**. Por isso o campo de lista ganha o
 *   `@var` (`@var string[]`, `@var Autor[]`), que e onde o PHP guarda o que o
 *   tipo nativo perdeu. Ele sai **somente** quando ha o que dizer;
 * - **chave nao identificador e renomeada** (`first-name` vira `$first_name`):
 *   propriedade no PHP precisa ser identificador, e o MVP nao emite alias. Se
 *   duas chaves cairem no mesmo nome, a segunda ganha um numero.
 *
 * O texto termina com quebra de linha, como arquivo.
 */
export function emitPhp(plan: TypePlan): string {
    const renamed = phpNames(plan);
    const classes = plan.declarations.map((entry) => {
        const name = renamed.get(entry.name) ?? entry.name;

        return phpClass(name, entry.fields, { plan, renamed });
    });

    return `<?php\n\ndeclare(strict_types=1);\n\n${classes.join('\n\n')}\n`;
}

interface PhpContext {
    plan: TypePlan;
    /** Nome do plano para nome no PHP: a palavra reservada ganha `_` no fim. */
    renamed: Map<string, string>;
}

/**
 * Os nomes deste plano como o PHP os aceita, sem repetir nenhum.
 *
 * A reserva vale para o nome de **classe**: propriedade no PHP aceita palavra
 * reservada desde a versao 7, entao `$class` e `$list` passam sem mexer.
 */
function phpNames(plan: TypePlan): Map<string, string> {
    const renamed = new Map<string, string>();
    const used = new Set<string>();

    for (const declaration of plan.declarations) {
        const base = phpClassName(declaration.name);
        let candidate = base;
        let suffix = 2;

        while (used.has(candidate.toLowerCase())) {
            candidate = `${base}${suffix}`;
            suffix += 1;
        }

        used.add(candidate.toLowerCase());
        renamed.set(declaration.name, candidate);
    }

    return renamed;
}

function phpClass(name: string, fields: Field[], context: PhpContext): string {
    // Obrigatorios primeiro: e a ordem que o PHP exige dos parametros.
    const ordered = [
        ...fields.filter((field) => !field.optional),
        ...fields.filter((field) => field.optional),
    ];

    if (ordered.length === 0) {
        // Nao acontece com o modelo de hoje - objeto sem chave e `open_object`,
        // e nao vira classe. A guarda existe para o construtor nao sair vazio
        // por acidente se isso mudar.
        return `final class ${name}\n{\n    public function __construct()\n    {\n    }\n}`;
    }

    const used = new Set<string>();
    const properties = ordered.map((field) => property(field, context, used));

    return [
        `final class ${name}`,
        '{',
        '    public function __construct(',
        ...properties,
        '    ) {',
        '    }',
        '}',
    ].join('\n');
}

function property(field: Field, context: PhpContext, used: Set<string>): string {
    // A chave pode virar o mesmo nome que outra (`first-name` e `first_name`):
    // a segunda ganha um sufixo, senao a classe teria a propriedade duas vezes.
    const base = phpProperty(field.key);
    let name = base;
    let suffix = 2;

    while (used.has(name)) {
        name = `${base}${suffix}`;
        suffix += 1;
    }

    used.add(name);

    const type = field.optional
        ? nullable(render(field.type, context), field.type)
        : render(field.type, context);

    const doc = docblock(field, context);
    const value = field.optional ? ' = null' : '';

    return `${doc}        public readonly ${type} $${name}${value},`;
}

/** O tipo de um campo opcional: anulavel, mas sem `?` repetido. */
function nullable(type: string, node: TypeNode): string {
    if (type === 'mixed' || acceptsNull(node)) {
        return type;
    }

    return `?${type}`;
}

/**
 * O `@var` do campo, ou nada quando ele nao acrescenta nada.
 *
 * Sai da comparacao com o tipo nativo: `string` nao precisa de comentario,
 * `string[]` precisa - o nativo ali e so `array`.
 */
function docblock(field: Field, context: PhpContext): string {
    const native = render(field.type, context);
    const doc = phpDocType(field.type, context);

    if (doc === native) {
        return '';
    }

    const complete =
        field.optional && !acceptsNull(field.type) && doc !== 'mixed' ? `${doc}|null` : doc;

    return `        /** @var ${complete} */\n`;
}

function render(node: TypeNode, context: PhpContext): string {
    switch (node.kind) {
        case 'string':
            return 'string';
        case 'int':
            return 'int';
        case 'float':
            return 'float';
        case 'bool':
            return 'bool';
        case 'null':
            return 'null';
        case 'unknown':
            // `mixed` e o `unknown` do PHP: o tipo que nao promete nada.
            return 'mixed';
        case 'open_object':
        case 'array':
            return 'array';
        case 'union': {
            const { members } = node;
            const nulled = members.filter((member) => member.kind === 'null');
            const rest = members.filter((member) => member.kind !== 'null');

            if (nulled.length === 1 && rest.length === 1 && rest[0] !== undefined) {
                // `int | null` e escrito `?int`, que e o mesmo tipo com a
                // forma que todo mundo le.
                return nullable(render(rest[0], context), rest[0]);
            }

            return members.map((member) => render(member, context)).join('|');
        }
        case 'object': {
            const planned = context.plan.names.get(node);

            return planned === undefined ? 'array' : (context.renamed.get(planned) ?? 'array');
        }
    }
}

/** O mesmo tipo, escrito do jeito que o PHPDoc entende. */
function phpDocType(node: TypeNode, context: PhpContext): string {
    switch (node.kind) {
        case 'unknown':
            return 'mixed';
        case 'open_object':
            return 'array<string, mixed>';
        case 'array': {
            const element = phpDocType(node.element, context);

            return node.element.kind === 'union' ? `(${element})[]` : `${element}[]`;
        }
        case 'union':
            return node.members.map((member) => phpDocType(member, context)).join('|');
        default:
            return render(node, context);
    }
}
