import { describe, expect, it } from 'vitest';
import {
    LAYOUT_STRATEGIES,
    LAYOUT_TOKEN_IDS,
} from '@/tools/layout-harmonizer/engine';
import { exportMessages, type NamespaceMessages } from './support/langMessages';

/**
 * Guarda das chaves da F2 nos cinco idiomas.
 *
 * Cobre as chaves montadas em template pelos componentes (`tokens.<id>`,
 * `strategy.<slug>.name`, `states.<code>`) - o vue-i18n devolve a propria
 * chave quando ela nao existe, e a paridade do PHP so compara o que existe de
 * fato em todos os arquivos.
 */

const LOCALES = ['en', 'es', 'fr', 'pt_BR', 'pt_PT'] as const;

const STATIC_KEYS = [
    'name',
    'description',
    'keywords',
    'intro',
    'seed.title',
    'seed.description',
    'seed.empty_value',
    'seed.background_label',
    'seed.background_hint',
    'seed.background_invalid',
    'seed.summary.original',
    'seed.summary.effective',
    'seed.summary.background',
    'seed.summary.strategy',
    'seed.summary.alpha_note',
    'seed.summary.achromatic',
    'strategy.label',
    'strategy.hint',
    'view.light',
    'view.dark',
    'view.compare',
    'view.board_light',
    'view.board_dark',
    'families.brand',
    'families.surface',
    'families.feedback',
    'families.content',
    'families.interaction',
    'provenance.seed',
    'provenance.manual',
    'provenance.rule',
    'provenance.feedback',
    'contrast.aa',
    'contrast.aa_fail',
    'contrast.against',
    'contrast.on',
    'contrast.summary_title',
    'contrast.all_pass',
    'copy.hex_copied',
    'copy.oklch_copied',
    'copy.hex_aria',
    'copy.oklch_aria',
    'clipped.badge',
    'clipped.note',
    'states.empty_title',
    'states.empty_description',
    'states.invalid_seed',
    'states.seed_alpha_without_background',
    'states.invalid_strategy',
    'states.stale_board',
    'feedback_preview.title',
    'feedback_preview.description',
    'a11y.view_mode',
    'a11y.board_section',
    'a11y.contrast_summary',
] as const;

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

function block(locale: string): NamespaceMessages {
    const tools = (exportMessages()[locale]?.tools ?? {}) as NamespaceMessages;
    const harmonizer = tools['layout-harmonizer'];

    expect(harmonizer, `tools.layout-harmonizer em ${locale}`).toBeDefined();

    return harmonizer as NamespaceMessages;
}

function dynamicKeys(): string[] {
    const keys: string[] = [...STATIC_KEYS];

    for (const id of LAYOUT_TOKEN_IDS) {
        keys.push(`tokens.${id}`);
    }

    for (const strategy of LAYOUT_STRATEGIES) {
        keys.push(`strategy.${strategy}.name`);
        keys.push(`strategy.${strategy}.description`);
    }

    return keys;
}

describe('traducao do Layout Harmonizer', () => {
    it('existe nos cinco idiomas, com nome proprio em ingles', () => {
        for (const locale of LOCALES) {
            expect(valueAt(block(locale), 'name')).toBe('Layout Harmonizer');
        }
    });

    it('preenche todas as chaves estaticas e dinamicas nos cinco idiomas', () => {
        const keys = dynamicKeys();
        const missing: string[] = [];

        for (const locale of LOCALES) {
            const messages = block(locale);

            for (const key of keys) {
                const value = valueAt(messages, key);

                if (typeof value !== 'string' || value.trim() === '') {
                    missing.push(`${locale}:${key}`);
                }
            }
        }

        expect(missing).toEqual([]);
    });

    it('nao tem `@` nem `|` crus no bloco (sintaxe do Laravel)', () => {
        for (const locale of LOCALES) {
            expect(JSON.stringify(block(locale))).not.toMatch(/[@|]/);
        }
    });
});
