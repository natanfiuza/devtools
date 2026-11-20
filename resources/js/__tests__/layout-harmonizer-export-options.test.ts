import { describe, expect, it } from 'vitest';
import {
    escapeCssComment,
    escapeMarkdown,
    interpolate,
    lookupMessage,
    message,
} from '@/tools/layout-harmonizer/export/messages';
import {
    DEFAULT_LAYOUT_CSS_PREFIX,
    DEFAULT_LAYOUT_CSS_SCOPE,
    DEFAULT_LAYOUT_EXPORT_LIMITS,
    layoutCssSelectors,
    validateLayoutExportOptions,
} from '@/tools/layout-harmonizer/export/options';

/**
 * Options do CSS e leitura das traducoes (F5, ADR-175/ADR-176).
 *
 * Prefixo e escopo nunca entram como CSS livre: o validador recusa qualquer
 * coisa fora do identificador ASCII e de `:root`/classe simples.
 */

describe('export/options', () => {
    it('aceita os defaults', () => {
        expect(DEFAULT_LAYOUT_CSS_PREFIX).toBe('lh');
        expect(DEFAULT_LAYOUT_CSS_SCOPE).toBe(':root');
        expect(DEFAULT_LAYOUT_EXPORT_LIMITS).toEqual({
            prefixMaxLength: 32,
            scopeMaxLength: 64,
        });

        const result = validateLayoutExportOptions({
            prefix: DEFAULT_LAYOUT_CSS_PREFIX,
            scope: DEFAULT_LAYOUT_CSS_SCOPE,
        });

        expect(result.valid).toBe(true);
        expect(result.prefixError).toBeNull();
        expect(result.scopeError).toBeNull();
    });

    it('recusa prefixo vazio, em maiuscula, com digito inicial ou simbolo', () => {
        const invalid = ['', 'Lh', '2lh', 'lh_2', 'lh--2', 'lh-', '-lh', 'l h'];

        for (const prefix of invalid) {
            const result = validateLayoutExportOptions({
                prefix,
                scope: ':root',
            });

            expect(result.valid, prefix).toBe(false);
            expect(result.prefixError, prefix).not.toBeNull();
        }
    });

    it('aceita prefixo com ate 32 caracteres e separado por hifen', () => {
        expect(
            validateLayoutExportOptions({ prefix: 'lh', scope: ':root' }).valid,
        ).toBe(true);
        expect(
            validateLayoutExportOptions({ prefix: 'brand-2', scope: ':root' })
                .valid,
        ).toBe(true);
        expect(
            validateLayoutExportOptions({
                prefix: 'a'.repeat(32),
                scope: ':root',
            }).valid,
        ).toBe(true);
        expect(
            validateLayoutExportOptions({
                prefix: 'a'.repeat(33),
                scope: ':root',
            }).prefixError,
        ).toBe('prefix_too_long');
    });

    it('recusa escopo que nao e :root nem classe simples', () => {
        const invalid = ['', 'app', '#app', '.2app', '.a b', '.a.b', '.'];

        for (const scope of invalid) {
            const result = validateLayoutExportOptions({
                prefix: 'lh',
                scope,
            });

            expect(result.valid, scope).toBe(false);
            expect(result.scopeError, scope).not.toBeNull();
        }

        expect(
            validateLayoutExportOptions({ prefix: 'lh', scope: '.app' }).valid,
        ).toBe(true);
        expect(
            validateLayoutExportOptions({ prefix: 'lh', scope: '.app-theme_2' })
                .valid,
        ).toBe(true);
    });

    it('respeita os limites configurados do servidor', () => {
        const limits = { prefixMaxLength: 4, scopeMaxLength: 6 };

        expect(
            validateLayoutExportOptions({
                prefix: 'abcde',
                scope: ':root',
                limits,
            }).prefixError,
        ).toBe('prefix_too_long');
        expect(
            validateLayoutExportOptions({
                prefix: 'lh',
                scope: '.themes',
                limits,
            }).scopeError,
        ).toBe('scope_too_long');
    });

    it('monta os seletores combinados do escopo', () => {
        expect(layoutCssSelectors(':root')).toEqual({
            light: ':root, :root[data-theme="light"]',
            dark: ':root[data-theme="dark"]',
        });
        expect(layoutCssSelectors('.app')).toEqual({
            light: '.app, .app[data-theme="light"]',
            dark: '.app[data-theme="dark"]',
        });
    });
});

describe('export/messages', () => {
    const TREE = {
        export: {
            md: { title: 'Title' },
            greeting: 'Hello {name}',
        },
        tokens: { primary: 'Main brand' },
    };

    it('le chaves aninhadas e devolve null quando falta', () => {
        expect(lookupMessage(TREE, 'tokens.primary')).toBe('Main brand');
        expect(lookupMessage(TREE, 'export.md.title')).toBe('Title');
        expect(lookupMessage(TREE, 'export.md.missing')).toBeNull();
        expect(lookupMessage(TREE, 'tokens')).toBeNull();
    });

    it('interpola {chave} e preserva o que nao tem valor', () => {
        expect(interpolate('Hello {name}', { name: 'Ana' })).toBe('Hello Ana');
        expect(interpolate('Hello {name}')).toBe('Hello {name}');
        expect(interpolate('Hello {name}', {})).toBe('Hello {name}');
    });

    it('devolve o caminho completo quando a traducao nao existe', () => {
        expect(message(TREE, 'export.missing')).toBe(
            'tools.layout-harmonizer.export.missing',
        );
    });

    it('neutraliza quebra de linha e controles no texto livre', () => {
        const control = String.fromCharCode(0, 7, 31);

        expect(escapeMarkdown(`a${control}b\nc`)).toBe('a b c');
        expect(escapeCssComment(`a${control}b\r\nc`)).toBe('a b c');
    });

    it('escapa a sintaxe do Markdown e o fim de comentario CSS', () => {
        expect(escapeMarkdown('a|b`c*d_e<f>g#h\\i')).toBe(
            'a\\|b\\`c\\*d\\_e\\<f\\>g\\#h\\\\i',
        );
        expect(escapeCssComment('fim */ inicio')).toBe('fim * / inicio');
    });
});
