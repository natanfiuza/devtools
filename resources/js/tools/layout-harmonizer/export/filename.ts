/**
 * Nomes dos arquivos exportados (F5, ADR-175).
 *
 * Dentro do ZIP os nomes sao fixos (`design-instructions.md`, `colors.css`);
 * os avulsos levam o idioma no Markdown. O nome do ZIP vem do nome do systema,
 * sanitizado como nos exportadores de paleta.
 */

const FALLBACK_NAME = 'layout-harmonizer';
const MAX_NAME_LENGTH = 32;

export const LAYOUT_MARKDOWN_FILENAME = 'design-instructions.md';
export const LAYOUT_CSS_FILENAME = 'colors.css';

/**
 * Nome livre -> slug estavel: minusculas, sem diacriticos, separado por hifen,
 * no maximo `maxLength` caracteres. Vazio (ou so simbolos) vira o fallback.
 */
export function sanitizeExportName(
    raw: string,
    maxLength = MAX_NAME_LENGTH,
): string {
    const slug = raw
        .normalize('NFD')
        .replace(/\p{M}+/gu, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, maxLength)
        .replace(/-+$/, '');

    return slug === '' ? FALLBACK_NAME : slug;
}

/** Nome do ZIP: `{slug}-layout.zip`. */
export function zipFileName(name: string): string {
    return `${sanitizeExportName(name)}-layout.zip`;
}

/** Markdown avulso: `design-instructions.pt-BR.md` (BCP 47 com hifen). */
export function markdownFileName(language: string): string {
    return `design-instructions.${language.replaceAll('_', '-')}.md`;
}
