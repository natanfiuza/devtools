import type { LayoutExportMessages } from './types';

/**
 * Leitura das traducoes do documento e escapes dos formatos.
 *
 * O documento e gerado no idioma escolhido a partir de `messages` (o subtree
 * que o servidor envia para os cinco idiomas), nunca do vue-i18n ativo. Texto
 * livre do usuario (nome, origem) passa pelos escapes antes de entrar no
 * Markdown ou num comentario CSS.
 */

export type LayoutExportNamedValues = Record<string, string | number>;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/** Valor de `path` (a partir do subtree) ou `null`; nunca lanca. */
export function lookupMessage(
    tree: LayoutExportMessages,
    path: string,
): string | null {
    let current: unknown = tree;

    for (const segment of path.split('.')) {
        if (!isRecord(current)) {
            return null;
        }

        current = current[segment];
    }

    return typeof current === 'string' ? current : null;
}

/** Troca `{chave}` pelos valores; chave desconhecida fica como esta. */
export function interpolate(
    template: string,
    named?: LayoutExportNamedValues,
): string {
    if (!named) {
        return template;
    }

    return template.replace(/\{(\w+)\}/g, (match, key: string) => {
        const value = named[key];

        return value === undefined ? match : String(value);
    });
}

/**
 * Texto do subtree com interpolacao. Chave ausente aparece como o caminho
 * completo: o teste de paridade garante que isso nunca chega ao usuario.
 */
export function message(
    messages: LayoutExportMessages,
    path: string,
    named?: LayoutExportNamedValues,
): string {
    const template =
        lookupMessage(messages, path) ?? `tools.layout-harmonizer.${path}`;

    return interpolate(template, named);
}

/** Espaco unico e sem controles: nada quebra linha ou titulo. */
function oneLine(text: string): string {
    return text
        .replace(/\p{Cc}+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Texto livre em titulo, paragrafo ou celula de tabela do Markdown. */
export function escapeMarkdown(text: string): string {
    return oneLine(text).replace(/[\\`*_<>|#]/g, (char) => `\\${char}`);
}

/** Texto livre em comentario CSS: nada fecha o comentario nem quebra linha. */
export function escapeCssComment(text: string): string {
    return oneLine(text.replace(/\*\//g, '* /'));
}
