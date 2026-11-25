import { addLoss, type Loss } from './lossy';

/**
 * A arvore intermediaria entre os quatro formatos.
 *
 * Todo parser entrega esta arvore e todo serializador consome esta arvore; os
 * quatro formatos nao se conhecem. A alternativa - converter JSON para YAML e
 * YAML para TOML escrevendo seis conversores - multiplicaria as regras de cada
 * par e faria cada formato depender dos outros tres.
 *
 * O objeto e uma **lista de pares**, e nao um objeto do JavaScript: a arvore
 * nao reordena nada sozinha e uma chave chamada `__proto__` nao vira prototipo
 * de nada - ela e so um nome.
 *
 * A ordem que a arvore recebe e a que a enumeracao do objeto do parser entrega.
 * Para as chaves comuns essa e a ordem do documento; para chave que parece
 * inteiro (`"2"`) o `JSON.parse` e o `yaml` ja entregam a chave primeiro, e
 * nenhuma arvore depois deles desfaz isso. E uma limitacao do JavaScript, e nao
 * uma escolha da tool - o ACEITE da F1 a registra.
 */

export interface ObjectEntry {
    key: string;
    value: DataValue;
}

export type DataValue =
    | { type: 'object'; entries: ObjectEntry[] }
    | { type: 'array'; items: DataValue[] }
    | { type: 'string'; value: string }
    | { type: 'number'; value: number }
    | { type: 'boolean'; value: boolean }
    | { type: 'null' };

/** O `null` da arvore, que nao tem outro estado. */
export const NULL_VALUE: DataValue = { type: 'null' };

export function objectValue(entries: ObjectEntry[]): DataValue {
    return { type: 'object', entries };
}

export function arrayValue(items: DataValue[]): DataValue {
    return { type: 'array', items };
}

export function stringValue(value: string): DataValue {
    return { type: 'string', value };
}

/**
 * Texto de um valor escalar, do jeito que o XML o escreve.
 *
 * `null` devolve `null` (quem chama decide o que fazer com um valor que o
 * formato nao tem) e objeto/array tambem: eles nao sao texto. `NaN` e
 * `Infinity` viram texto - o XML so tem texto - e o aviso correspondente e
 * registrado por quem pediu.
 */
export function scalarText(value: DataValue): string | null {
    switch (value.type) {
        case 'string':
            return value.value;
        case 'number':
            return String(value.value);
        case 'boolean':
            return value.value ? 'true' : 'false';
        default:
            return null;
    }
}

/** Bytes em base64 sem `Buffer`: o navegador nao tem um, e o worker tambem nao. */
function base64(bytes: Uint8Array): string {
    let binary = '';
    const CHUNK = 0x8000;

    for (let index = 0; index < bytes.length; index += CHUNK) {
        binary += String.fromCharCode(...bytes.subarray(index, index + CHUNK));
    }

    return btoa(binary);
}

/**
 * A arvore a partir do que um parser de biblioteca devolveu.
 *
 * Os tres parsers falam JavaScript nativo, e as conversoes de tipo sao as
 * unicas perdas que acontecem **na entrada**: a data do TOML (que chega como
 * `Date`), o binario do YAML (que chega como `Uint8Array`) e o inteiro grande
 * do YAML (que chega como `bigint` quando a opcao esta ligada) nao existem na
 * arvore, entao viram texto com aviso. Toda outra perda e do formato de
 * destino e acontece na saida.
 */
export function fromPlain(value: unknown, losses: Loss[]): DataValue {
    if (value === null) {
        return NULL_VALUE;
    }

    switch (typeof value) {
        case 'string':
            return stringValue(value);
        case 'number':
            return { type: 'number', value };
        case 'boolean':
            return { type: 'boolean', value };
        case 'bigint':
            addLoss(losses, 'bigint-as-text');

            return stringValue(value.toString());
        case 'object':
            break;
        default:
            addLoss(losses, 'value-dropped');

            return NULL_VALUE;
    }

    if (value instanceof Date) {
        addLoss(losses, 'date-as-text');

        return stringValue(value.toISOString());
    }

    // `ArrayBuffer.isView`, e nao `instanceof Uint8Array`: o binario do YAML
    // chega como `Uint8Array` no navegador e como `Buffer` no Node, e os dois
    // (e o que vier de outro contexto de execucao) sao vistas de bytes.
    if (ArrayBuffer.isView(value)) {
        addLoss(losses, 'binary-as-base64');

        return stringValue(
            base64(
                new Uint8Array(
                    value.buffer,
                    value.byteOffset,
                    value.byteLength,
                ),
            ),
        );
    }

    if (Array.isArray(value)) {
        return arrayValue(value.map((item) => fromPlain(item, losses)));
    }

    return objectValue(
        Object.entries(value).map(([key, entry]) => ({
            key,
            value: fromPlain(entry, losses),
        })),
    );
}
