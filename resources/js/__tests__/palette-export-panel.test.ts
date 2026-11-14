import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import ExportPanel from '@/tools/palette-generator/components/ExportPanel.vue';
import type { PaletteRoleColors, PaletteRoles } from '@/types/colors';

/**
 * Painel de exportacao com os papeis do editor (F5).
 *
 * O painel so recebe `roles` no editor; no gerador ele continua primitivo.
 * Aqui o que importa e a ligacao: a nota de semanticas, as abas novas e o
 * conteudo DTCG/CSS refletindo os papeis recebidos.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));

const LIGHT_ROLES: PaletteRoleColors = {
    primary: '#332211',
    secondary: '#443322',
    accent: '#554433',
    background: '#fefefe',
    surface: '#f5f5f5',
    text: '#111111',
    text_muted: '#555555',
    border: '#dddddd',
};

const DARK_ROLES: PaletteRoleColors = {
    primary: '#aabbcc',
    secondary: '#99aabb',
    accent: '#8899aa',
    background: '#0a0a0a',
    surface: '#151515',
    text: '#fafafa',
    text_muted: '#a0a0a0',
    border: '#333333',
};

const ROLES: PaletteRoles = { light: LIGHT_ROLES, dark: DARK_ROLES };

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            tools: {
                'palette-generator': {
                    export: {
                        title: 'Exportar',
                        description: 'Descrição da exportação.',
                        name_label: 'Nome da paleta',
                        name_hint: 'Usado nas variáveis: {slug}',
                        name_invalid: 'O nome será normalizado para {slug}.',
                        copy: 'Copiar',
                        copied: '{name} copiado',
                        download: 'Baixar',
                        semantic_included:
                            'Os papéis semânticos do protótipo estão incluídos.',
                        tabs: {
                            css: 'CSS',
                            scss: 'SCSS',
                            json: 'JSON',
                            dtcg: 'DTCG',
                            style_dictionary: 'Style Dictionary',
                            typescript: 'TypeScript',
                            tailwind_v3: 'Tailwind v3',
                            tailwind_v4: 'Tailwind v4',
                        },
                    },
                },
            },
        },
    });

    return instance;
}

function mountPanel(roles?: PaletteRoles) {
    return mount(ExportPanel, {
        props: {
            scale: SCALE,
            copy: vi.fn(async () => true),
            ...(roles === undefined ? {} : { roles }),
        },
        global: { plugins: [i18n()] },
    });
}

describe('ExportPanel · design tokens', () => {
    it('lista os oito formatos, inclusive DTCG, Style Dictionary e TypeScript', () => {
        const wrapper = mountPanel();
        const tabs = wrapper.findAll('[data-test^="export-tab-"]');

        expect(tabs).toHaveLength(8);
        expect(wrapper.get('[data-test="export-tab-dtcg"]').text()).toBe(
            'DTCG',
        );
        expect(
            wrapper.get('[data-test="export-tab-style-dictionary"]').text(),
        ).toBe('Style Dictionary');
        expect(wrapper.get('[data-test="export-tab-typescript"]').text()).toBe(
            'TypeScript',
        );
    });

    it('sem papeis nao mostra a nota nem semanticas no CSS', () => {
        const wrapper = mountPanel();

        expect(
            wrapper.find('[data-test="export-semantic-note"]').exists(),
        ).toBe(false);
        expect(wrapper.get('[data-test="export-code"]').text()).not.toContain(
            '--color-light-',
        );
    });

    it('com papeis mostra a nota e inclui semanticas no DTCG', async () => {
        const wrapper = mountPanel(ROLES);

        expect(
            wrapper.get('[data-test="export-semantic-note"]').text(),
        ).toContain('papéis semânticos');

        await wrapper.get('[data-test="export-tab-dtcg"]').trigger('mousedown');

        const code = wrapper.get('[data-test="export-code"]').text();

        expect(code).toContain('"semantic"');
        expect(code).toContain(LIGHT_ROLES.primary);
        expect(code).toContain(DARK_ROLES.border);
    });

    it('com papeis o CSS tambem ganha os papeis', async () => {
        const wrapper = mountPanel(ROLES);
        const code = wrapper.get('[data-test="export-code"]').text();

        expect(code).toContain(
            `--color-light-primary: ${LIGHT_ROLES.primary};`,
        );
        expect(code).toContain(`--color-dark-border: ${DARK_ROLES.border};`);
    });
});
