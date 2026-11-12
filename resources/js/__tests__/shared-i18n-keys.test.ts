import { describe, expect, it } from 'vitest';
import { exportMessages, type NamespaceMessages } from './support/langMessages';

/**
 * Guarda das chaves do picker compartilhado.
 *
 * O vue-i18n devolve a propria chave quando ela nao existe, entao uma chave
 * ausente em TODOS os idiomas passa pela paridade e pela compilacao - foi o
 * que aconteceu com `tools.shared.color_picker.preview.eyedropper`. Os testes
 * de UI usam mensagens falsas e tambem nao veriam o vazio.
 *
 * Aqui os literais estaticos usados nos componentes compartilhados sao
 * comparados com o que os arquivos de `lang/` realmente entregam. Chaves
 * montadas em template (`...formats.${format}`) ficam de fora: nao ha como
 * resolver sem executar o componente.
 */

const componentSources = import.meta.glob('../components/colors/*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
}) as Record<string, string>;

const KEY_PATTERN =
    /tools\.shared\.color_picker\.([a-z_]+\.[a-z_]+(?:\.[a-z_]+)*)/g;

function usedKeys(): string[] {
    const keys = new Set<string>();

    for (const source of Object.values(componentSources)) {
        for (const match of source.matchAll(KEY_PATTERN)) {
            keys.add(match[1] as string);
        }
    }

    return [...keys].sort();
}

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

describe('chaves i18n do picker compartilhado', () => {
    it('existem nos cinco idiomas', () => {
        const keys = usedKeys();
        const exported = exportMessages();
        const missing: string[] = [];

        for (const [locale, namespaces] of Object.entries(exported)) {
            const tools = namespaces.tools ?? {};

            for (const key of keys) {
                const value = valueAt(tools, `shared.color_picker.${key}`);

                if (typeof value !== 'string' || value.trim() === '') {
                    missing.push(`${locale}:${key}`);
                }
            }
        }

        // Sem chaves o glob quebrou silenciosamente; o teste nao provaria nada.
        expect(keys.length).toBeGreaterThan(0);
        expect(missing).toEqual([]);
    });
});
