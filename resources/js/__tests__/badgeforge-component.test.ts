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
                download: 'Baixar',
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
                font: 'Fonte',
                format: 'Formato',
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
            fonts: { default: 'Padrão do estilo' },
            formats: { svg: 'SVG', png: 'PNG' },
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

/** O preview e SVG inline: o markup do wrapper traz o documento gerado. */
function previewSvg(wrapper: ReturnType<typeof mountTool>): string {
    return wrapper.find('[data-test="badge-preview"]').html();
}

beforeEach(() => {
    clipboard = { writeText: vi.fn(() => Promise.resolve()) };
    Object.defineProperty(navigator, 'clipboard', {
        value: clipboard,
        configurable: true,
    });
});

describe('BadgeForge Tool', () => {
    it('nao repete a introducao que a pagina ja mostra', () => {
        const wrapper = mountTool();

        expect(wrapper.text()).not.toContain(TRADUCOES.tools.badgeforge.intro);
    });

    it('aceita os campos numericos sem derrubar a previa', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');
        await wrapper.find('[data-test="badge-logo-size"]').setValue('12');
        await wrapper.find('[data-test="badge-cache"]').setValue('600');

        expect(wrapper.find('[data-test="badge-logo-size"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="badge-cache"]').exists()).toBe(true);

        const svg = previewSvg(wrapper);

        expect(svg).toContain('width="12" height="12"');

        const snippet = wrapper.find('[data-test="badge-snippet"]').text();

        expect(snippet).toContain('logoSize=12');
        expect(snippet).toContain('cacheSeconds=600');
    });

    it('mostra o estado vazio ate haver rotulo e mensagem', async () => {
        const wrapper = mountTool();

        expect(wrapper.find('[data-test="badge-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="badge-preview"]').exists()).toBe(
            false,
        );

        await wrapper.find('[data-test="badge-label"]').setValue('build');
        await wrapper.find('[data-test="badge-message"]').setValue('passing');

        const preview = wrapper.find('[data-test="badge-preview"]');

        expect(preview.exists()).toBe(true);
        expect(preview.html()).toContain('<svg xmlns=');
        expect(preview.html()).not.toContain('data:image/svg');
        expect(preview.attributes('aria-label')).toBe('build: passing');
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

        const svg = previewSvg(wrapper);

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

        const svg = previewSvg(wrapper);

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

    it('aplica a fonte escolhida no preview e na URL', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');

        const fontSelect = wrapper
            .findAllComponents({ name: 'Select' })
            .find((select) => select.props('modelValue') === 'default');

        expect(fontSelect).toBeDefined();

        fontSelect?.vm.$emit('update:modelValue', 'fira-code');
        await wrapper.vm.$nextTick();

        expect(previewSvg(wrapper)).toContain(
            'font-family="Fira Code,DejaVu Sans,monospace"',
        );
        expect(wrapper.find('[data-test="badge-snippet"]').text()).toContain(
            'font=fira-code',
        );
    });

    it('troca o formato dos snippets e do botao de download', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-sample"]').trigger('click');

        const href = (): string =>
            wrapper.find('[data-test="badge-download"]').attributes('href') ??
            '';

        expect(href()).toContain('/build/passing.svg');
        expect(href()).toContain('download=1');

        const formatSelect = wrapper
            .findAllComponents({ name: 'Select' })
            .find((select) => select.props('modelValue') === 'svg');

        expect(formatSelect).toBeDefined();

        formatSelect?.vm.$emit('update:modelValue', 'png');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-test="badge-snippet"]').text()).toContain(
            '/build/passing.png',
        );
        expect(href()).toContain('/build/passing.png');
        expect(href()).toContain('download=1');
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
