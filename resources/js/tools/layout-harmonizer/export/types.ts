import type { LayoutSystemStateV1 } from '../lib/state';

/**
 * Contrato comum das exportacoes (F5, ADR-175).
 *
 * O unico dado de cor e o snapshot ja exibido: nada aqui regenera, salva ou
 * conversa com o servidor. `messages` chega do servidor com o subtree de
 * `tools.layout-harmonizer` no idioma escolhido, porque o cliente so recebe o
 * locale ativo.
 */

export const LAYOUT_EXPORT_LANGUAGES = [
    'en',
    'pt_BR',
    'pt_PT',
    'fr',
    'es',
] as const;

export type LayoutExportLanguage = (typeof LAYOUT_EXPORT_LANGUAGES)[number];

/** Guarda para o que vem do seletor ou do servidor (locale ativo). */
export function isLayoutExportLanguage(
    value: unknown,
): value is LayoutExportLanguage {
    return (
        typeof value === 'string' &&
        (LAYOUT_EXPORT_LANGUAGES as readonly string[]).includes(value)
    );
}

/** Subtree de traducoes de um idioma (chave -> string ou objeto). */
export type LayoutExportMessages = Record<string, unknown>;

export interface LayoutExportOrigin {
    name: string;
    public: boolean;
}

export interface LayoutExportInput {
    state: LayoutSystemStateV1;
    name: string;
    language: LayoutExportLanguage;
    messages: LayoutExportMessages;
    origin: LayoutExportOrigin | null;
}

export interface LayoutCssOptions {
    prefix: string;
    scope: string;
}

export type LayoutExportFormat = 'markdown' | 'css';

export interface LayoutExportFile {
    format: LayoutExportFormat;
    filename: string;
    mime: string;
    contents: string;
}

export interface LayoutExportLimits {
    prefixMaxLength: number;
    scopeMaxLength: number;
}

export const LAYOUT_EXPORT_MIME = {
    markdown: 'text/markdown;charset=utf-8',
    css: 'text/css;charset=utf-8',
} as const;

export const LAYOUT_EXPORT_ZIP_MIME = 'application/zip;charset=utf-8';
