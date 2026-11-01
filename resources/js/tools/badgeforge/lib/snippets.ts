/**
 * Snippets de incorporacao. Funcoes puras: o componente so escolhe a aba.
 */

export function urlSnippet(url: string): string {
    return url;
}

/** Markdown com `[`, `]` e `\` escapados no texto alternativo. */
export function markdownSnippet(url: string, alt: string): string {
    const escaped = alt
        .replace(/\\/g, '\\\\')
        .replace(/\[/g, '\\[')
        .replace(/\]/g, '\\]');

    return `![${escaped}](${url})`;
}

export function htmlSnippet(
    url: string,
    alt: string,
    width: number,
    height: number,
): string {
    return `<img src="${escapeAttribute(url)}" alt="${escapeAttribute(alt)}" width="${width}" height="${height}" />`;
}

/** reStructuredText (`.. image::` + `:alt:`). */
export function rstSnippet(url: string, alt: string): string {
    return `.. image:: ${url}\n   :alt: ${alt}`;
}

function escapeAttribute(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
