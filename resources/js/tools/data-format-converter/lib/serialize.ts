import { stringify as stringifyToml } from 'smol-toml';
import { stringify as stringifyYaml } from 'yaml';
import { addLoss, type Loss } from './lossy';
import { scalarText, type DataValue } from './tree';
import type { SerializeResult } from '../types';

/**
 * Arvore intermediaria -> texto.
 *
 * Dois serializadores sao escritos aqui (JSON e XML) e dois sao de biblioteca
 * (YAML e TOML). O JSON a mao existe por duas razoes: `JSON.stringify` de um
 * objeto do JavaScript reordena chave que parece inteiro, e o numero nao
 * finito vira `null` sem aviso - as duas coisas contrariam a arvore, que
 * guarda a ordem do documento e sabe que perdeu o valor.
 *
 * O XML a mao e o preco de nao ter biblioteca: a convencao e pequena, e a
 * alternativa (mais uma dependencia) nao se paga por uma arvore de 300 linhas.
 */

/*
| JSON
*/

function jsonText(value: DataValue, depth: number, losses: Loss[]): string {
    const pad = '  '.repeat(depth + 1);
    const close = '  '.repeat(depth);

    switch (value.type) {
        case 'null':
            return 'null';
        case 'boolean':
            return value.value ? 'true' : 'false';
        case 'string':
            return JSON.stringify(value.value);
        case 'number':
            if (!Number.isFinite(value.value)) {
                addLoss(losses, 'non-finite-as-null');

                return 'null';
            }

            return String(value.value);
        case 'array': {
            if (value.items.length === 0) {
                return '[]';
            }

            const items = value.items.map(
                (item) => `${pad}${jsonText(item, depth + 1, losses)}`,
            );

            return `[\n${items.join(',\n')}\n${close}]`;
        }
        case 'object': {
            if (value.entries.length === 0) {
                return '{}';
            }

            const entries = value.entries.map(
                (entry) =>
                    `${pad}${JSON.stringify(entry.key)}: ${jsonText(
                        entry.value,
                        depth + 1,
                        losses,
                    )}`,
            );

            return `{\n${entries.join(',\n')}\n${close}}`;
        }
    }
}

export function toJsonText(value: DataValue, losses: Loss[]): string {
    return jsonText(value, 0, losses);
}

/*
| YAML
*/

/**
 * A arvore no que o `yaml` escreve.
 *
 * Objeto vira `Map` para a ordem das chaves ser a da arvore - o objeto do
 * JavaScript reordenaria a chave que parece inteiro pela segunda vez. `NaN` e
 * `Infinity` passam como numero: o YAML os escreve (`.nan`, `.inf`) e nao ha
 * perda.
 */
function yamlPlain(value: DataValue): unknown {
    switch (value.type) {
        case 'object':
            return new Map(
                value.entries.map((entry) => [
                    entry.key,
                    yamlPlain(entry.value),
                ]),
            );
        case 'array':
            return value.items.map(yamlPlain);
        case 'null':
            return null;
        case 'string':
        case 'number':
        case 'boolean':
            return value.value;
    }
}

export function toYamlText(value: DataValue): string {
    // `lineWidth: 0` desliga o embrulho de linha longa: o texto escrito e o
    // texto que vai para o arquivo, sem quebra inventada pela biblioteca.
    return stringifyYaml(yamlPlain(value), { indent: 2, lineWidth: 0 });
}

/*
| TOML
*/

type TomlValue =
    string | number | boolean | TomlValue[] | { [key: string]: TomlValue };

/** O tipo do valor para a regra de array homogeneo do TOML 1.0. */
type ValueKind = 'string' | 'number' | 'boolean' | 'array' | 'object';

class TomlMixedArrayError extends Error {
    constructor(readonly path: string) {
        super(`mixed array at ${path}`);
    }
}

function pathOf(parent: string, key: string): string {
    return parent === '' ? key : `${parent}.${key}`;
}

/**
 * A arvore no que o `smol-toml` escreve, com as duas regras do formato:
 * `null` nao existe (a chave ou o item sai, com aviso) e array tem um tipo so.
 *
 * O objeto e criado sem prototipo: uma chave chamada `__proto__` vinda do
 * documento nao pode virar o prototipo de nada.
 */
function tomlValue(
    value: DataValue,
    losses: Loss[],
    path: string,
): TomlValue | undefined {
    switch (value.type) {
        case 'null':
            addLoss(losses, 'null-dropped');

            return undefined;
        case 'string':
        case 'number':
        case 'boolean':
            return value.value;
        case 'array': {
            const items: TomlValue[] = [];

            for (const item of value.items) {
                const plain = tomlValue(item, losses, path);

                if (plain !== undefined) {
                    items.push(plain);
                }
            }

            const kinds = new Set(items.map(kindOfPlain));

            if (kinds.size > 1) {
                throw new TomlMixedArrayError(path);
            }

            return items;
        }
        case 'object': {
            const table = Object.create(null) as { [key: string]: TomlValue };

            for (const entry of value.entries) {
                const plain = tomlValue(
                    entry.value,
                    losses,
                    pathOf(path, entry.key),
                );

                if (plain !== undefined) {
                    table[entry.key] = plain;
                }
            }

            return table;
        }
    }
}

function kindOfPlain(value: TomlValue): ValueKind {
    if (Array.isArray(value)) {
        return 'array';
    }

    if (typeof value === 'object') {
        return 'object';
    }

    return typeof value as 'string' | 'number' | 'boolean';
}

export function toTomlText(value: DataValue, losses: Loss[]): SerializeResult {
    if (value.type !== 'object') {
        // O TOML e uma tabela na raiz: um array ou um escalar solto nao tem
        // onde caber, e inventar uma chave seria mudar o documento.
        return { ok: false, code: 'toml-root', detail: null };
    }

    try {
        const table = tomlValue(value, losses, '');

        if (table === undefined) {
            return { ok: false, code: 'toml-root', detail: null };
        }

        return { ok: true, text: stringifyToml(table) };
    } catch (error) {
        if (error instanceof TomlMixedArrayError) {
            return { ok: false, code: 'toml-mixed-array', detail: error.path };
        }

        return { ok: false, code: 'toml-invalid', detail: null };
    }
}

/*
| XML
*/

/**
 * O nome de um elemento ou atributo XML.
 *
 * Aceita letra Unicode (o XML aceita), e recusa o que a especificacao recusa:
 * comeco que nao e letra nem `_`, e o prefixo `xml`, que e reservado. Aceitar
 * um nome que o navegador nao le daria um arquivo que parece certo e nao abre.
 */
const XML_NAME = /^[\p{L}_][\p{L}\p{N}\p{M}._:-]*$/u;

function validName(name: string): boolean {
    return XML_NAME.test(name) && !/^xml/i.test(name);
}

function escapeText(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function escapeAttribute(text: string): string {
    return escapeText(text).replace(/"/g, '&quot;');
}

class XmlNameError extends Error {
    constructor(readonly key: string) {
        super(`invalid XML name: ${key}`);
    }
}

class XmlScalarError extends Error {
    constructor(readonly key: string) {
        super(`value must be scalar: ${key}`);
    }
}

/** Texto de texto ou de atributo; `null` so existe para `null` da arvore. */
function xmlScalar(
    value: DataValue,
    losses: Loss[],
    key: string,
): string | null {
    if (value.type === 'null') {
        return null;
    }

    if (value.type === 'object' || value.type === 'array') {
        throw new XmlScalarError(key);
    }

    if (value.type === 'number' && !Number.isFinite(value.value)) {
        addLoss(losses, 'non-finite-as-text');
    }

    return scalarText(value);
}

/** Um elemento (ou varios, quando o valor e array) escrito como linhas. */
function elementLines(
    name: string,
    value: DataValue,
    depth: number,
    losses: Loss[],
): string[] {
    if (value.type === 'array') {
        return value.items.flatMap((item) =>
            elementLines(name, item, depth, losses),
        );
    }

    const pad = '  '.repeat(depth);

    if (value.type === 'object') {
        const attributes: string[] = [];
        let text = '';
        const children: string[] = [];

        for (const entry of value.entries) {
            if (entry.key.startsWith('@')) {
                const attributeName = entry.key.slice(1);
                const raw = xmlScalar(entry.value, losses, entry.key);

                if (raw === null) {
                    // O atributo nao tem valor: sai, e o aviso e o mesmo de
                    // qualquer `null` que o destino nao representa.
                    addLoss(losses, 'null-dropped');
                    continue;
                }

                if (!validName(attributeName)) {
                    throw new XmlNameError(attributeName);
                }

                attributes.push(` ${attributeName}="${escapeAttribute(raw)}"`);
                continue;
            }

            if (entry.key === '#text') {
                text = xmlScalar(entry.value, losses, entry.key) ?? '';
                continue;
            }

            if (!validName(entry.key)) {
                throw new XmlNameError(entry.key);
            }

            children.push(
                ...elementLines(entry.key, entry.value, depth + 1, losses),
            );
        }

        const open = `${pad}<${name}${attributes.join('')}`;

        if (children.length === 0) {
            return text === ''
                ? [`${open}/>`]
                : [`${open}>${escapeText(text)}</${name}>`];
        }

        const head = text === '' ? `${open}>` : `${open}>${escapeText(text)}`;

        return [head, ...children, `${pad}</${name}>`];
    }

    const raw = xmlScalar(value, losses, name);

    if (raw === null) {
        addLoss(losses, 'null-as-empty-element');

        return [`${pad}<${name}/>`];
    }

    return [`${pad}<${name}>${escapeText(raw)}</${name}>`];
}

export function toXmlText(value: DataValue, losses: Loss[]): SerializeResult {
    try {
        let name = 'root';
        let content = value;

        if (value.type === 'object' && value.entries.length === 1) {
            const entry = value.entries[0];

            if (entry !== undefined) {
                if (entry.key.startsWith('@') || entry.key === '#text') {
                    // Objeto so com atributo ou so com texto: nao ha nome de
                    // elemento para usar, e o documento precisa de um.
                    addLoss(losses, 'root-wrapped');
                } else if (!validName(entry.key)) {
                    throw new XmlNameError(entry.key);
                } else {
                    name = entry.key;
                    content = entry.value;
                }
            }
        } else {
            addLoss(losses, 'root-wrapped');
        }

        if (content.type === 'array') {
            // Varios irmaos no topo seriam varios elementos raiz, e o XML
            // aceita um so: a lista entra dentro do `<root>`, com o nome que
            // ela tinha (ou `item`, quando nao tinha).
            addLoss(losses, 'root-wrapped');
            content = {
                type: 'object',
                entries: [
                    { key: name === 'root' ? 'item' : name, value: content },
                ],
            };
            name = 'root';
        }

        const lines = elementLines(name, content, 0, losses);

        return {
            ok: true,
            text: `<?xml version="1.0" encoding="UTF-8"?>\n${lines.join('\n')}`,
        };
    } catch (error) {
        if (error instanceof XmlNameError) {
            return { ok: false, code: 'xml-name', detail: error.key };
        }

        if (error instanceof XmlScalarError) {
            return { ok: false, code: 'xml-attribute', detail: error.key };
        }

        return { ok: false, code: 'xml-invalid', detail: null };
    }
}
