import { analyzeJson } from '@/lib/json/parse';
import type { JsonValue } from '@/lib/json/types';
import type { JsonChange, JsonDiffResult } from '../types';

/**
 * O diff **semantico** do JSON.
 *
 * O modo texto compara arquivo: uma chave reordenada e uma linha diferente. O
 * modo JSON compara **documento**: `{"a":1,"b":2}` e `{"b":2,"a":1}` sao o
 * mesmo JSON, e o diff diz que nao ha diferenca. As duas visoes existem porque
 * as duas perguntas existem: "o arquivo mudou?" e "o dado mudou?".
 *
 * A comparacao achata o documento em **caminhos**: cada folha vira um par
 * `caminho → valor`, e dois documentos sao iguais quando tem as mesmas folhas
 * com os mesmos valores. Caminho de objeto usa ponto para chave que e nome de
 * identificador e colchete com aspas para o resto (`$.a.b`, `$["chave estranha"]`);
 * caminho de array usa o indice (`$.items[0].id`). A raiz e `$`.
 *
 * Arrays sao comparados **por indice**, como o JSON Path e como o proprio
 * documento: `$.items[0]` com `$.items[0]`. Inserir um item no comeco desloca
 * todos os seguintes - e por isso que o modo texto continua existindo para quem
 * quer ver o arquivo, e nao o dado.
 *
 * Vasilhame vazio (`{}`, `[]`) e folha: sem isso, esvaziar um objeto nao
 * apareceria como mudanca nenhuma.
 */

const IDENTIFIER = /^[A-Za-z_$][A-Za-z0-9_$]*$/;

function childPath(path: string, key: string): string {
    if (IDENTIFIER.test(key)) {
        return `${path}.${key}`;
    }

    return `${path}[${JSON.stringify(key)}]`;
}

/**
 * Achata o valor em folhas, na ordem do documento.
 *
 * A ordem e a da enumeracao do objeto - a mesma que o `JSON.parse` entrega, e
 * a mesma limitacao do conversor: chave que parece inteiro sai primeiro. Para
 * o diff isso quase nao pesa (o que importa e o conjunto de caminhos), mas a
 * lista que a tela mostra segue essa ordem.
 */
function flatten(
    value: JsonValue,
    path: string,
    leaves: Map<string, string>,
): void {
    if (Array.isArray(value)) {
        if (value.length === 0) {
            leaves.set(path, '[]');

            return;
        }

        value.forEach((item, index) => {
            flatten(item, `${path}[${index}]`, leaves);
        });

        return;
    }

    if (value !== null && typeof value === 'object') {
        const entries = Object.entries(value as Record<string, JsonValue>);

        if (entries.length === 0) {
            leaves.set(path, '{}');

            return;
        }

        for (const [key, item] of entries) {
            flatten(item, childPath(path, key), leaves);
        }

        return;
    }

    // O `JSON.parse` so produz valores que o `JSON.stringify` escreve (nao ha
    // `undefined`, `NaN` nem `Infinity` vindos de texto JSON).
    leaves.set(path, JSON.stringify(value));
}

export function jsonDiff(leftText: string, rightText: string): JsonDiffResult {
    const left = analyzeJson(leftText);

    if (!left.ok) {
        return {
            ok: false,
            side: 'left',
            line: left.line,
            column: left.column,
        };
    }

    const right = analyzeJson(rightText);

    if (!right.ok) {
        return {
            ok: false,
            side: 'right',
            line: right.line,
            column: right.column,
        };
    }

    const leftLeaves = new Map<string, string>();
    const rightLeaves = new Map<string, string>();

    flatten(left.value, '$', leftLeaves);
    flatten(right.value, '$', rightLeaves);

    const changes: JsonChange[] = [];
    let added = 0;
    let removed = 0;
    let changed = 0;

    for (const [path, value] of leftLeaves) {
        if (!rightLeaves.has(path)) {
            changes.push({ path, kind: 'removed', left: value, right: null });
            removed += 1;
            continue;
        }

        const rightValue = rightLeaves.get(path) ?? '';

        if (rightValue !== value) {
            changes.push({
                path,
                kind: 'changed',
                left: value,
                right: rightValue,
            });
            changed += 1;
        }
    }

    for (const [path, value] of rightLeaves) {
        if (!leftLeaves.has(path)) {
            changes.push({ path, kind: 'added', left: null, right: value });
            added += 1;
        }
    }

    return { ok: true, changes, added, removed, changed };
}
