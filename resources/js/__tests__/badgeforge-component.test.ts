import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/badgeforge/Tool.vue';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import type { BadgeForgeToolProps } from '@/tools/badgeforge/types';

/**
 * A pagina do BadgeForge monta form | preview sem nenhuma requisicao: o SVG do
 * `<img>` e o espelho TypeScript, e os snippets saem das mesmas funcoes puras
 * cobertas em `badgeforge.test.ts`.
 */

const TRADUCOES = {
    tools: {
        ui: {
            actions: {
                copy: 'Copiar',
                copied: 'Copiado',
                copy_manual: 'Selecione e copie',
            },
        },
        badgeforge: {
            intro: 'Monte o badge e copie o trecho.',
            fields: {
                label: 'Rótulo',
                message: 'Mensagem',
                style: 'Estilo',
                color: 'Cor',
                label_color: 'Cor do rótulo',
                logo: 'Logo',
                logo_color: 'Cor do logo',
                logo_size: 'Tamanho do logo',
                logo_position: 'Posição do logo',
                link: 'Link',
                cache_seconds: 'Cache (segundos)',
            },
            placeholders: {
                label: 'build',
                message: 'passing',
                color: '#9f9f9f ou green',
                label_color: '#555555',
                logo_color: '#ffffff',
                link: 'https://exemplo.com',
            },
            positions: { left: 'Esquerda', right: 'Direita' },
            logo_none: 'Sem logo',
            sections: {
                content: 'Conteúdo',
                colors: 'Cores',
                logo: 'Logo',
                advanced: 'Avançado',
            },
            preview: {
                title: 'Prévia',
                dark: 'Fundo escuro',
                light: 'Fundo claro',
                empty: 'Digite um rótulo e uma mensagem para ver o badge.',
                dimensions: '{width} × {height} px',
            },
            snippets: {
                title: 'Incorporar',
                url: 'URL',
                markdown: 'Markdown',
                html: 'HTML',
                rst: 'reStructuredText',
            },
            actions: { sample: 'Exemplo', reset: 'Limpar' },
            social_note: 'O estilo social sempre usa as próprias cores.',
        },
    },
};

const PROPS = toolProps as BadgeForgeToolProps;

let clipboard: { writeText: ReturnType<typeof vi.fn> };

function mountTool() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt-BR', translations: TRADUCOES });

    return mount(Tool, {
        props: PROPS,
        global: { plugins: [i18n] },
    });
}

function decodeDataUri(src: string): string {
    return decodeURIComponent(
        src.replace('data:image/svg+xml;charset=utf-8,', ''),
    );
}

beforeEach(() => {
    clipboard = { writeText: vi.fn(() => Promise.resolve()) };
    Object.defineProperty(navigator, 'clipboard', {
        value: clipboard,
        configurable: true,
    });
});

describe('BadgeForge Tool', () => {
    it('mostra o estado vazio ate haver rotulo e mensagem', async () => {
        const wrapper = mountTool();

        expect(wrapper.find('[data-test="badge-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="badge-preview"]').exists()).toBe(
            false,
        );

        await wrapper.find('[data-test="badge-label"]').setValue('build');
        await wrapper.find('[data-test="badge-message"]').setValue('passing');

        const image = wrapper.find('[data-test="badge-preview"]');

        expect(image.exists()).toBe(true);
        expect(image.attributes('src')).toContain('data:image/svg+xml');
        expect(image.attributes('alt')).toBe('build: passing');
    });

    it('reflete cor, cor do rotulo e cor do logo no SVG', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');
        await wrapper.find('[data-test="badge-color"]').setValue('#ff0000');
        await wrapper
            .find('[data-test="badge-label-color"]')
            .setValue('#123456');
        await wrapper
            .find('[data-test="badge-logo-color"]')
            .setValue('#000000');

        const svg = decodeDataUri(
            wrapper.find('[data-test="badge-preview"]').attributes('src') ?? '',
        );

        expect(svg).toContain('fill="#ff0000"');
        expect(svg).toContain('fill="#123456"');
        expect(svg).toContain('fill="#000000"');
        expect(svg).toContain('<path');
    });

    it('aplica o estilo social e desabilita as cores', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');

        const social = wrapper.findComponent({ name: 'Select' });
        expect(social.exists()).toBe(true);

        social.vm.$emit('update:modelValue', 'social');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-test="badge-social-note"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-test="badge-color"]').attributes('disabled'),
        ).toBeDefined();

        const svg = decodeDataUri(
            wrapper.find('[data-test="badge-preview"]').attributes('src') ?? '',
        );

        expect(svg).toContain('fill="#007ec6"');
        expect(svg).toContain('fill="#555555"');
    });

    it('monta os quatro snippets a partir do formulario', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');

        const markdown = wrapper.find('[data-test="badge-snippet"]').text();

        expect(markdown).toContain('![build: passing](http');
        expect(markdown).toContain('/api/badges/static/build/passing.svg');

        const triggers = wrapper.findAll('[role="tab"]');

        expect(triggers.map((tab) => tab.text())).toEqual([
            'URL',
            'Markdown',
            'HTML',
            'reStructuredText',
        ]);
    });

    it('copia o snippet ativo e mostra o recado', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');
        await wrapper.find('[data-test="badge-copy"]').trigger('click');
        await vi.dynamicImportSettled();

        expect(clipboard.writeText).toHaveBeenCalledTimes(1);
        expect(String(clipboard.writeText.mock.calls[0]?.[0])).toContain(
            '![build: passing]',
        );
        expect(wrapper.find('[data-test="badge-copy-feedback"]').text()).toBe(
            'Copiado',
        );
    });

    it('limpa o formulario de volta ao padrao', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');
        expect(wrapper.find('[data-test="badge-preview"]').exists()).toBe(true);

        await wrapper.find('[data-test="badge-reset"]').trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-test="badge-preview"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.find('[data-test="badge-stage"]').attributes('class'),
        ).toContain('bg-neutral-100');
    });
});
