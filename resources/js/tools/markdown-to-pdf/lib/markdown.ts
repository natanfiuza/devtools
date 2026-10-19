import DOMPurify from 'dompurify';
import { Marked } from 'marked';

/**
 * Parser de Markdown com suporte a GitHub Flavored Markdown (GFM) e sanitizacao de seguranca.
 *
 * Processamento 100% no cliente (ADR-002): todo o HTML gerado e sanitizado com DOMPurify
 * antes de ser inserido no DOM para visualizacao ou exportacao, prevenindo injecao de scripts (XSS).
 */

const markedInstance = new Marked({
    gfm: true,
    breaks: true,
});

export function parseMarkdown(markdown: string): string {
    if (!markdown || markdown.trim() === '') {
        return '';
    }

    const rawHtml = markedInstance.parse(markdown) as string;

    // No ambiente do browser ou jsdom, sanitiza contra XSS
    if (typeof window !== 'undefined') {
        return DOMPurify.sanitize(rawHtml, {
            USE_PROFILES: { html: true },
            ADD_ATTR: ['target', 'rel', 'class'],
        });
    }

    return rawHtml;
}

export function countWords(text: string): number {
    const trimmed = text.trim();

    if (trimmed === '') {
        return 0;
    }

    return trimmed.split(/\s+/).filter(Boolean).length;
}

export function countChars(text: string): number {
    return text.length;
}
