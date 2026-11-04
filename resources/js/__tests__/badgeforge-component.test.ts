import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import PresetDialog from '@/tools/badgeforge/PresetDialog.vue';
import Tool from '@/tools/badgeforge/Tool.vue';
import { clearCustomLogos } from '@/tools/badgeforge/customLogos';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import type {
    BadgeForgeCustomLogoSummary,
    BadgeForgePreset,
    BadgeForgeToolProps,
} from '@/tools/badgeforge/types';

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
            modes: { static: 'Estático', provider: 'Provedor' },
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
            management: {
                logos_button: 'Meus logos',
                presets_button: 'Presets',
                login_cta: 'Entre para enviar seus próprios logos.',
                login_action: 'Entrar',
            },
        },
    },
};

const PROPS = toolProps as BadgeForgeToolProps;

/**
 * Conta Free gerenciada (ADR-078, ADR-079): um logo custom e um preset ja
 * existentes, sem os paths - eles so chegam quando o logo e escolhido.
 */
const CUSTOM_LOGO: BadgeForgeCustomLogoSummary = {
    id: '01K0CUSTOMLOGO0000000000001',
    slug: 'u_01K0CUSTOMLOGO0000000000001',
    name: 'Minha marca',
    viewBox: '0 0 24 24',
    pathCount: 2,
    sizeBytes: 128,
};

const PRESET: BadgeForgePreset = {
    id: '01K0PRESET00000000000000001',
    name: 'Release',
    form: {
        label: 'release',
        message: 'v1.0',
        style: 'flat-square',
        font: null,
        color: '#44cc11',
        labelColor: '#333333',
        logo: 'composer',
        logoColor: '#ffffff',
        logoSize: 12,
        logoPosition: 'right',
        link: 'https://exemplo.com',
        cacheSeconds: 600,
    },
    logoOrphaned: false,
};

function managedProps(
    overrides: Partial<BadgeForgeToolProps> = {},
): BadgeForgeToolProps {
    return {
        ...PROPS,
        limits: {
            ...PROPS.limits,
            customLogos: { ...PROPS.limits.customLogos, limit: 2, used: 1 },
            presets: { ...PROPS.limits.presets, limit: null, used: 1 },
        },
        management: {
            canManage: true,
            canSavePresets: true,
            canCreatePersonalBadges: false,
            isPaid: false,
        },
        customLogos: [CUSTOM_LOGO],
        presets: [PRESET],
        ...overrides,
    };
}

let clipboard: { writeText: ReturnType<typeof vi.fn> };

function mountTool(props: BadgeForgeToolProps = PROPS) {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt-BR', translations: TRADUCOES });

    return mount(Tool, {
        props,
        global: { plugins: [i18n] },
    });
}

/** Select cujo valor atual e o slug informado (o de logo, nao o de estilo). */
function selectWithValue(
    wrapper: ReturnType<typeof mountTool>,
    value: string,
) {
    return wrapper
        .findAllComponents({ name: 'Select' })
        .find((select) => select.props('modelValue') === value);
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

afterEach(() => {
    clearCustomLogos();
    vi.unstubAllGlobals();
    window.history.replaceState({}, '', '/');
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

        const triggers = wrapper.findAll(
            '[data-test="badge-snippet-tabs"] [role="tab"]',
        );

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

describe('BadgeForge Tool - modo provider', () => {
    it('troca o construtor local pela imagem do endpoint de provider', async () => {
        const wrapper = mountTool();

        await wrapper.find('[data-test="badge-mode-provider"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-test="badge-preview"]').exists()).toBe(false);
        expect(
            wrapper.find('[data-test="badge-provider-mode"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="badge-provider-preview"]')
                .attributes('src'),
        ).toContain('/api/badges/github/stars/natanfiuza/devtools.svg');
    });
});

describe('BadgeForge Tool - gestao da conta', () => {
    it('esconde a gestao do convidado e mostra o CTA de login', () => {
        const wrapper = mountTool();

        expect(wrapper.find('[data-test="badge-management"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="badge-login-cta"]').exists()).toBe(
            true,
        );
    });

    it('mostra os botoes de logos e presets para quem pode gerenciar', () => {
        const wrapper = mountTool(managedProps());

        expect(wrapper.find('[data-test="badge-management"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="badge-manage-logos"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-test="badge-manage-presets"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-test="badge-login-cta"]').exists()).toBe(
            false,
        );
    });

    it('busca os paths uma unica vez, so ao selecionar o logo custom', async () => {
        let resolveFetch: ((value: unknown) => void) | undefined;
        const fetchMock = vi.fn(
            (_input: string, _init?: unknown) =>
                new Promise((resolve) => {
                    resolveFetch = resolve;
                }),
        );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountTool(managedProps());

        await wrapper.find('[data-test="badge-label"]').setValue('build');
        await wrapper.find('[data-test="badge-message"]').setValue('passing');
        await flushPromises();

        // Digitar nao dispara requisicao nenhuma.
        expect(fetchMock).not.toHaveBeenCalled();

        selectWithValue(wrapper, 'none')?.vm.$emit(
            'update:modelValue',
            CUSTOM_LOGO.slug,
        );
        await wrapper.vm.$nextTick();

        // A URL ja leva o slug opaco antes de os paths chegarem (ADR-078).
        expect(wrapper.find('[data-test="badge-snippet"]').text()).toContain(
            `logo=${CUSTOM_LOGO.slug.toLowerCase()}`,
        );
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            `/badgeforge/logos/${CUSTOM_LOGO.id}`,
        );
        expect(previewSvg(wrapper)).not.toContain('M0 0h24v24H0z');

        resolveFetch?.({
            ok: true,
            json: () =>
                Promise.resolve({
                    viewBox: '0 0 24 24',
                    paths: [{ d: 'M0 0h24v24H0z' }],
                }),
        });
        await flushPromises();
        await wrapper.vm.$nextTick();

        expect(previewSvg(wrapper)).toContain('M0 0h24v24H0z');

        // Voltar e escolher de novo usa o cache: continua uma so requisicao.
        selectWithValue(wrapper, CUSTOM_LOGO.slug)?.vm.$emit(
            'update:modelValue',
            'none',
        );
        await wrapper.vm.$nextTick();
        selectWithValue(wrapper, 'none')?.vm.$emit(
            'update:modelValue',
            CUSTOM_LOGO.slug,
        );
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('aplica o snapshot do preset ao formulario', async () => {
        const wrapper = mountTool(managedProps());

        wrapper.findComponent(PresetDialog).vm.$emit('apply', PRESET);
        await wrapper.vm.$nextTick();

        const label = wrapper.find('[data-test="badge-label"]');

        expect((label.element as HTMLInputElement).value).toBe('release');

        const snippet = wrapper.find('[data-test="badge-snippet"]').text();

        expect(snippet).toContain('logo=composer');
        expect(snippet).toContain('logoSize=12');
    });

    it('aplica preset orfao sem logo e sem buscar paths', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountTool(
            managedProps({
                presets: [
                    {
                        ...PRESET,
                        id: '01K0PRESETORFAO0000000000001',
                        logoOrphaned: true,
                        form: {
                            ...PRESET.form,
                            logo: 'u_01K0DELETED0000000000000001',
                        },
                    },
                ],
            }),
        );

        wrapper
            .findComponent(PresetDialog)
            .vm.$emit('apply', wrapper.props('presets')[0]);
        await wrapper.vm.$nextTick();

        const snippet = wrapper.find('[data-test="badge-snippet"]').text();

        expect(snippet).not.toContain('logo=u_');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('aplica o preset indicado em ?preset= e limpa o parametro', async () => {
        window.history.replaceState({}, '', `?preset=${PRESET.id}`);

        const wrapper = mountTool(managedProps());
        await wrapper.vm.$nextTick();

        const label = wrapper.find('[data-test="badge-label"]');

        expect((label.element as HTMLInputElement).value).toBe('release');
        expect(window.location.search).toBe('');
    });
});
