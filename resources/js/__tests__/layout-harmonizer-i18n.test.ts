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
    'seed.background_alpha_invalid',
    'secondary.label',
    'secondary.hint',
    'secondary.invalid',
    'secondary.background_label',
    'secondary.background_hint',
    'secondary.background_invalid',
    'secondary.background_alpha_invalid',
    'secondary.manual',
    'secondary.auto',
    'secondary.reset',
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
    'states.seed_background_alpha_invalid',
    'states.secondary_alpha_without_background',
    'states.invalid_strategy',
    'states.stale_board',
    'feedback_preview.title',
    'feedback_preview.description',
    'a11y.view_mode',
    'a11y.board_section',
    'a11y.contrast_summary',
    'export.title',
    'export.description',
    'export.login_cta',
    'export.login_hint',
    'export.language_label',
    'export.prefix_label',
    'export.prefix_hint',
    'export.scope_label',
    'export.scope_root',
    'export.scope_class',
    'export.scope_class_label',
    'export.download_markdown',
    'export.download_css',
    'export.download_zip',
    'export.pending_regeneration',
    'export.compatible_notice',
    'export.readonly_notice',
    'export.errors.prefix_required',
    'export.errors.prefix_too_long',
    'export.errors.prefix_invalid',
    'export.errors.scope_required',
    'export.errors.scope_too_long',
    'export.errors.scope_invalid',
    'export.targets.aa',
    'export.targets.aaa',
    'export.css.header',
    'export.css.meta',
    'export.css.theme_default',
    'export.warnings.title',
    'export.warnings.none',
    'export.warnings.contrast',
    'export.warnings.locked',
    'export.warnings.manual',
    'export.warnings.accepted',
    'export.warnings.clipped',
    'export.warnings.compatible',
    'export.warnings.readonly',
    'export.warnings.limitations',
    'export.md.purpose',
    'export.md.metadata_title',
    'export.md.language',
    'export.md.strategy',
    'export.md.target',
    'export.md.seed_original',
    'export.md.seed_effective',
    'export.md.seed_background',
    'export.md.secondary',
    'export.md.secondary_auto',
    'export.md.secondary_manual',
    'export.md.secondary_original',
    'export.md.secondary_effective',
    'export.md.secondary_background',
    'export.md.versions',
    'export.md.origin',
    'export.md.instructions_title',
    'export.md.instructions_intro',
    'export.md.instructions_semantic',
    'export.md.instructions_manual',
    'export.md.instructions_contrast',
    'export.md.instructions_map',
    'export.md.themes_title',
    'export.md.themes_note',
    'export.md.tokens_title',
    'export.md.columns.token',
    'export.md.columns.purpose',
    'export.md.columns.hex',
    'export.md.columns.oklch',
    'export.md.columns.source',
    'export.md.columns.locked',
    'export.md.columns.foreground',
    'export.md.columns.background',
    'export.md.columns.ratio',
    'export.md.columns.aa',
    'export.md.columns.aaa',
    'export.md.locked_yes',
    'export.md.locked_no',
    'export.md.rules_title',
    'export.md.rules.backgrounds',
    'export.md.rules.text',
    'export.md.rules.buttons',
    'export.md.rules.links',
    'export.md.rules.feedback',
    'export.md.rules.focus',
    'export.md.contrast_title',
    'export.md.pass',
    'export.md.fail',
    'export.md.generated_title',
    'export.md.generation_note',
] as const;

/**
 * Placeholders que cada mensagem interpolada precisa manter nos 5 idiomas: o
 * exportador troca `{chave}` depois, e uma traducao sem ele perderia o dado
 * (versao, meta, alvo) silenciosamente.
 */
const NAMED_KEYS: Record<string, string[]> = {
    'export.prefix_hint': ['prefix'],
    'export.errors.prefix_too_long': ['max'],
    'export.errors.scope_too_long': ['max'],
    'export.css.header': ['name'],
    'export.css.meta': [
        'state',
        'language',
        'schema',
        'algorithm',
        'suggestions',
        'snapshot',
    ],
    'export.warnings.contrast': [
        'theme',
        'foreground',
        'background',
        'ratio',
        'target',
    ],
    'export.warnings.locked': ['theme', 'token'],
    'export.warnings.manual': ['theme', 'token', 'hex'],
    'export.warnings.accepted': ['theme', 'token', 'from', 'to', 'target'],
    'export.warnings.clipped': ['theme', 'token'],
    'export.warnings.compatible': ['algorithm', 'suggestions'],
    'export.warnings.readonly': ['schema', 'algorithm', 'suggestions'],
    'export.md.language': ['language'],
    'export.md.strategy': ['strategy'],
    'export.md.target': ['target'],
    'export.md.seed_original': ['hex'],
    'export.md.seed_effective': ['hex'],
    'export.md.seed_background': ['hex'],
    'export.md.secondary': ['mode'],
    'export.md.secondary_original': ['hex'],
    'export.md.secondary_effective': ['hex'],
    'export.md.secondary_background': ['hex'],
    'export.md.versions': [
        'state',
        'schema',
        'algorithm',
        'suggestions',
        'snapshot',
    ],
    'export.md.origin': ['name'],
    'export.md.instructions_contrast': ['target'],
    'export.md.tokens_title': ['theme'],
    'export.md.contrast_title': ['theme'],
    'export.md.generation_note': ['state', 'snapshot'],
};

function placeholdersOf(text: string): string[] {
    const found = [...text.matchAll(/\{(\w+)\}/g)].map(
        (match) => match[1] ?? '',
    );

    return [...new Set(found)].sort();
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

    it('mantem os placeholders das mensagens interpoladas', () => {
        for (const locale of LOCALES) {
            const messages = block(locale);

            for (const [key, expected] of Object.entries(NAMED_KEYS)) {
                const value = valueAt(messages, key);

                expect(typeof value, `${locale}:${key}`).toBe('string');
                expect(
                    placeholdersOf(value as string),
                    `${locale}:${key}`,
                ).toEqual([...expected].sort());
            }
        }
    });
});
