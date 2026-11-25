import type { Loss } from './lossy';
import { parseSource } from './parse';
import { toJsonText, toTomlText, toXmlText, toYamlText } from './serialize';
import type { DataValue } from './tree';
import type {
    ConvertRequest,
    ConvertResult,
    DataFormat,
    SerializeResult,
} from '../types';

/**
 * A conversao inteira: texto -> arvore -> texto.
 *
 * E uma funcao **pura** e sem estado, e por isso a mesma nos dois lugares onde
 * roda - na thread principal (entrada pequena) e no worker (entrada grande).
 * O resultado carrega o texto convertido **e** o que o destino nao
 * representou; a tela escolhe como mostrar, e nada aqui sabe de idioma.
 *
 * Mesmo formato e permitido de proposito: JSON -> JSON e a normalizacao
 * (indentacao, ordem preservada, numero nao finito barrado), e nao um erro.
 */

function serialize(
    value: DataValue,
    format: DataFormat,
    losses: Loss[],
): SerializeResult {
    switch (format) {
        case 'json':
            return { ok: true, text: toJsonText(value, losses) };
        case 'yaml':
            return { ok: true, text: toYamlText(value) };
        case 'toml':
            return toTomlText(value, losses);
        case 'xml':
            return toXmlText(value, losses);
    }
}

export function convert(request: ConvertRequest): ConvertResult {
    const parsed = parseSource(request.text, request.from);

    if (!parsed.ok) {
        return {
            ok: false,
            code: 'parse',
            detail: parsed.message,
            line: parsed.line,
            column: parsed.column,
        };
    }

    // As perdas de entrada (comentario, data, binario, inteiro grande) ja vem
    // do parser, com a contagem do que **o texto tinha**; aqui so as do destino
    // sao somadas, pelo serializador.
    const losses = [...parsed.losses];

    const serialized = serialize(parsed.value, request.to, losses);

    if (!serialized.ok) {
        return {
            ok: false,
            code: serialized.code,
            detail: serialized.detail,
            line: null,
            column: null,
        };
    }

    return {
        ok: true,
        text: serialized.text,
        format: request.to,
        losses,
    };
}
