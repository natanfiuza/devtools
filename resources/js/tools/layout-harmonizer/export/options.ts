import type { LayoutExportLimits } from './types';

/**
 * Defaults e validacao das opcoes do CSS exportado (F5, ADR-176).
 *
 * Prefixo e escopo sao do usuario, mas nunca viram trecho de CSS livre: o
 * prefixo e um identificador ASCII e o escopo e `:root` ou uma classe simples.
 * Qualquer outra coisa e recusada antes de gerar o arquivo.
 */

export const DEFAULT_LAYOUT_CSS_PREFIX = 'lh';
export const DEFAULT_LAYOUT_CSS_SCOPE = ':root';

export const DEFAULT_LAYOUT_EXPORT_LIMITS: LayoutExportLimits = {
    prefixMaxLength: 32,
    scopeMaxLength: 64,
};

export const LAYOUT_CSS_PREFIX_PATTERN = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/;
export const LAYOUT_CSS_SCOPE_CLASS_PATTERN = /^\.[A-Za-z_][A-Za-z0-9_-]*$/;

/** Codigo do erro; o painel traduz `export.errors.<codigo>`. */
export type LayoutExportOptionError =
    | 'prefix_required'
    | 'prefix_too_long'
    | 'prefix_invalid'
    | 'scope_required'
    | 'scope_too_long'
    | 'scope_invalid';

export interface LayoutExportOptionValidation {
    prefixError: LayoutExportOptionError | null;
    scopeError: LayoutExportOptionError | null;
    valid: boolean;
}

export interface LayoutExportOptionInput {
    prefix: string;
    scope: string;
    limits?: LayoutExportLimits;
}

export function validateLayoutExportOptions(
    input: LayoutExportOptionInput,
): LayoutExportOptionValidation {
    const limits = input.limits ?? DEFAULT_LAYOUT_EXPORT_LIMITS;
    const prefix = input.prefix.trim();
    const scope = input.scope.trim();

    let prefixError: LayoutExportOptionError | null = null;

    if (prefix === '') {
        prefixError = 'prefix_required';
    } else if (prefix.length > limits.prefixMaxLength) {
        prefixError = 'prefix_too_long';
    } else if (!LAYOUT_CSS_PREFIX_PATTERN.test(prefix)) {
        prefixError = 'prefix_invalid';
    }

    let scopeError: LayoutExportOptionError | null = null;

    if (scope === '') {
        scopeError = 'scope_required';
    } else if (scope.length > limits.scopeMaxLength) {
        scopeError = 'scope_too_long';
    } else if (
        scope !== DEFAULT_LAYOUT_CSS_SCOPE &&
        !LAYOUT_CSS_SCOPE_CLASS_PATTERN.test(scope)
    ) {
        scopeError = 'scope_invalid';
    }

    return {
        prefixError,
        scopeError,
        valid: prefixError === null && scopeError === null,
    };
}

/**
 * Seletores combinados do escopo. O claro e o default (sem atributo) e
 * tambem `data-theme="light"`; o escuro so responde a `data-theme="dark"`.
 */
export function layoutCssSelectors(scope: string): {
    light: string;
    dark: string;
} {
    const trimmed = scope.trim();
    const base = trimmed === '' ? DEFAULT_LAYOUT_CSS_SCOPE : trimmed;

    return {
        light: `${base}, ${base}[data-theme="light"]`,
        dark: `${base}[data-theme="dark"]`,
    };
}
