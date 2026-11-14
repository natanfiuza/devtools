import { describe, expect, it } from 'vitest';
import {
    TOKEN_IMPORT_ERROR_CODES,
    TOKEN_IMPORT_WARNING_CODES,
} from '@/tools/palette-generator/lib/tokenImport';
import { exportMessages, type NamespaceMessages } from './support/langMessages';

/**
 * Cobertura i18n da importacao de tokens (F5, Etapa 6).
 *
 * O dialogo monta a chave do diagnostico a partir do codigo devolvido pelo
 * parser. Uma chave ausente nao quebra a compilacao nem a paridade de
 * namespace: o vue-i18n simplesmente mostra o codigo cru. Este teste fecha o
 * contrato nos cinco idiomas, para todo codigo que o parser pode emitir.
 */

const LOCALES = ['en', 'es', 'fr', 'pt_BR', 'pt_PT'];

const UI_KEYS = [
    'button',
    'applied',
    'title',
    'description',
    'choose',
    'hint',
    'reading',
    'file_errors.file_too_large',
    'file_errors.file_type',
    'file_errors.file_unreadable',
    'diagnostics.title',
    'diagnostics.errors',
    'diagnostics.warnings',
    'diagnostics.hidden',
    'diagnostics.path',
    'preview.title',
    'preview.format',
    'preview.kind',
    'preview.base',
    'preview.colors_label',
    'preview.colors',
    'preview.roles',
    'preview.no_roles',
    'preview.name',
    'preview.filename',
    'preview.apply',
    'preview.cancel',
    'preview.replace',
    'preview.apply_hint',
    'formats.native',
    'formats.dtcg',
    'formats.style-dictionary',
    'kinds.scale',
    'kinds.extracted',
];

function valueAt(messages: NamespaceMessages, path: string): unknown {
    let current: unknown = messages;

    for (const segment of path.split('.')) {
        if (
            current === null ||
            typeof current !== 'object' ||
            Array.isArray(current)
        ) {
            return undefined;
        }

        current = (current as Record<string, unknown>)[segment];
    }

    return current;
}

describe('i18n da importação de tokens', () => {
    it('tem todos os textos de interface nos cinco idiomas', () => {
        const exported = exportMessages();
        const missing: string[] = [];

        expect(Object.keys(exported).sort()).toEqual(LOCALES);

        for (const locale of LOCALES) {
            const tools = exported[locale]?.tools ?? {};

            for (const key of UI_KEYS) {
                const value = valueAt(tools, `shared.token_import.${key}`);

                if (typeof value !== 'string' || value.trim() === '') {
                    missing.push(`${locale}:${key}`);
                }
            }
        }

        expect(missing).toEqual([]);
    });

    it('cobre todos os códigos de diagnóstico do parser', () => {
        const exported = exportMessages();
        const codes = [
            ...TOKEN_IMPORT_ERROR_CODES,
            ...TOKEN_IMPORT_WARNING_CODES,
        ];
        const missing: string[] = [];

        // Sem codigos o parser mudou de contrato e o teste nao provaria nada.
        expect(codes.length).toBeGreaterThan(0);

        for (const locale of LOCALES) {
            const tools = exported[locale]?.tools ?? {};

            for (const code of codes) {
                const value = valueAt(
                    tools,
                    `shared.token_import.codes.${code}`,
                );

                if (typeof value !== 'string' || value.trim() === '') {
                    missing.push(`${locale}:${code}`);
                }
            }
        }

        expect(missing).toEqual([]);
    });
});
