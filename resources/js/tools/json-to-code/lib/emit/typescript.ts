import type { TypeNode } from '../infer';
import { tsProperty } from '../names';
import type { TypePlan } from '../plan';

/**
 * O alvo TypeScript: uma `interface` exportada por objeto.
 *
 * A chave **nao** e renomeada em nenhum caso - quando ela nao e identificador,
 * sai entre aspas (`"first-name": string`), que e o mesmo campo escrito de
 * outro jeito. `Record<string, unknown>` cobre os dois "nao sei" do modelo: o
 * objeto vazio e a uniao que se perdeu.
 *
 * O texto termina com quebra de linha, como arquivo.
 */
export function emitTypeScript(plan: TypePlan): string {
    const declarations = plan.declarations.map((entry) => {
        const lines = entry.fields.map((field) => {
            const optional = field.optional ? '?' : '';
            const type = render(field.type, plan);

            return `    ${tsProperty(field.key)}${optional}: ${type};`;
        });

        return `export interface ${entry.name} {\n${lines.join('\n')}\n}`;
    });

    return `${declarations.join('\n\n')}\n`;
}

function render(node: TypeNode, plan: TypePlan): string {
    switch (node.kind) {
        case 'string':
            return 'string';
        case 'int':
        case 'float':
            // Um `number` so: o TypeScript nao separa inteiro de decimal, e
            // separar seria inventar um tipo que a linguagem nao tem.
            return 'number';
        case 'bool':
            return 'boolean';
        case 'null':
            return 'null';
        case 'unknown':
            // `unknown` e nao `any`: o primeiro obriga quem consome a checar o
            // tipo, o segundo desliga a checagem sem avisar.
            return 'unknown';
        case 'open_object':
            return 'Record<string, unknown>';
        case 'array': {
            const element = render(node.element, plan);

            return node.element.kind === 'union'
                ? `(${element})[]`
                : `${element}[]`;
        }
        case 'union':
            return node.members
                .map((member) => render(member, plan))
                .join(' | ');
        case 'object':
            return plan.names.get(node) ?? 'Record<string, unknown>';
    }
}
