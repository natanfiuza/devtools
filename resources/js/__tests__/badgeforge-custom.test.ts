import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import CustomProvidersPanel from '@/tools/badgeforge/CustomProvidersPanel.vue';
import Tool from '@/tools/badgeforge/Tool.vue';
import {
    buildCustomBadgeUrl,
    defaultCustomProviderForm,
    exampleCustomParams,
    invalidCustomParam,
} from '@/tools/badgeforge/lib/customProviderUrl';
import type {
    BadgeForgeCustomProvider,
    BadgeForgeToolProps,
} from '@/tools/badgeforge/types';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';

/**
 * Providers customizados (F7, ADR-087): URL opaca, painel de gestao e modo
 * `custom` do construtor.
 *
 * A origem (`url_template`) nunca chega ao cliente; o que o navegador monta e
 * a URL publica com os params de path, e a previa e a propria `<img>` do
 * servidor. O painel so toca o servidor em acao explicita (criar/editar/
 * excluir) e o form so envia o que o servidor aceita.
 */

const { formPost, formPatch, routerDelete } = vi.hoisted(() => ({
    formPost: vi.fn(),
    formPatch: vi.fn(),
    routerDelete: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    let transform:
        | ((data: Record<string, unknown>) => unknown)
        | null = null;

    const form = reactive({
        name: '',
        url_template: '',
        path_params: [] as string[],
        example_path: '',
        metrics: [] as Array<Record<string, string>>,
        errors: {} as Record<string, string>,
        processing: false,
        transform: (callback: (data: Record<string, unknown>) => unknown) => {
            transform = callback;
        },
        post: (url: string, options: unknown) => {
            formPost(
                url,
                transform === null ? { ...form } : transform({ ...form }),
                options,
            );
        },
        patch: (url: string, options: unknown) => {
            formPatch(
                url,
                transform === null ? { ...form } : transform({ ...form }),
                options,
            );
        },
        reset: vi.fn(),
        clearErrors: vi.fn(),
    });

    return {
        useForm: () => form,
        usePage: () => ({
            props: {
                localeSlug: 'pt-br',
                auth: { user: { id: 1 } },
            },
        }),
        router: { delete: routerDelete },
        Head: defineComponent({
            name: 'InertiaHead',
            props: { title: { type: String, default: '' } },
            render: () => h('div'),
        }),
        Link: defineComponent({
            name: 'InertiaLink',
            props: { href: { type: String, default: '' } },
            setup(props, { slots, attrs }) {
                return () =>
                    h(
                        'a',
                        { ...attrs, href: props.href },
                        slots.default ? slots.default() : [],
                    );
            },
        }),
    };
});

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
            modes: { static: 'Estático', provider: 'Provedor', custom: 'Meu' },
            fields: {
                style: 'Estilo',
                font: 'Fonte',
                color: 'Cor',
                label_color: 'Cor do rótulo',
                logo: 'Logo',
                logo_color: 'Cor do logo',
                logo_size: 'Tamanho do logo',
                logo_position: 'Posição do logo',
                link: 'Link',
            },
            placeholders: {
                color: '#9f9f9f',
                label_color: '#555555',
                logo_color: '#ffffff',
                link: 'https://exemplo.com',
            },
            positions: { left: 'Esquerda', right: 'Direita' },
            logo_none: 'Sem logo',
            fonts: { default: 'Padrão do estilo' },
            formats: { svg: 'SVG', png: 'PNG' },
            preview: { title: 'Prévia' },
            snippets: {
                title: 'Incorporar',
                url: 'URL',
                markdown: 'Markdown',
                html: 'HTML',
            },
            sections: { colors: 'Cores', logo: 'Logo', advanced: 'Avançado' },
            management: {
                cancel: 'Cancelar',
                rename: 'Renomear',
                delete: 'Excluir',
                delete_title: 'Excluir {name}',
            },
        },
    },
};

const PROPS = toolProps as BadgeForgeToolProps;
const ORIGIN = 'http://localhost:3000';

// Os dialogos teleportam para o body: desmontar evita orfaos entre testes.
enableAutoUnmount(afterEach);

const PROVIDER: BadgeForgeCustomProvider = {
    id: '01K0CUSTOMPROVIDER00000001',
    name: 'Catálogo',
    enabled: true,
    examplePath: 'natanfiuza/devtools',
    pathParams: ['owner', 'repo'],
    definitions: [
        {
            slug: 'stars',
            label: 'Estrelas',
            pointer: '/stargazers_count',
            formatter: 'number',
        },
        {
            slug: 'forks',
            label: 'Forks',
            pointer: '/forks_count',
            formatter: 'number',
        },
    ],
};

function customProps(
    overrides: {
        canCreate?: boolean;
        providers?: BadgeForgeCustomProvider[];
    } = {},
): BadgeForgeToolProps {
    return {
        ...PROPS,
        limits: {
            ...PROPS.limits,
            customProviders: {
                ...PROPS.limits.customProviders,
                limit: 10,
                used: overrides.providers?.length ?? 1,
            },
        },
        management: {
            ...PROPS.management,
            canCreateCustomProviders: overrides.canCreate ?? true,
        },
        customProviders: overrides.providers ?? [PROVIDER],
    };
}

function i18n() {
    const instance = createI18nInstance();
    applyTranslations(instance, { locale: 'pt-BR', translations: TRADUCOES });

    return instance;
}

function mountTool(props: BadgeForgeToolProps) {
    return mount(Tool, { props, global: { plugins: [i18n()] } });
}

function mountPanel(props: BadgeForgeToolProps) {
    return mount(CustomProvidersPanel, {
        props: { toolProps: props, upgradeUrl: '/planos' },
        global: { plugins: [i18n()] },
    });
}

function fill(testId: string, value: string): void {
    const input = document.querySelector<HTMLInputElement>(
        `[data-test="${testId}"]`,
    );

    if (input === null) {
        throw new Error(`Campo ausente: ${testId}`);
    }

    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function submitDialog(): void {
    const form = document.querySelector<HTMLFormElement>(
        '[data-test="badge-custom-provider-form"]',
    );

    if (form === null) {
        throw new Error('Formulario ausente');
    }

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}

beforeEach(() => {
    document.body.innerHTML = '';
    formPost.mockClear();
    formPatch.mockClear();
    routerDelete.mockClear();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('URL do provider customizado', () => {
    it('quebra o examplePath na ordem dos placeholders', () => {
        expect(exampleCustomParams(PROVIDER)).toEqual({
            owner: 'natanfiuza',
            repo: 'devtools',
        });
        expect(PROPS.customEndpoint).toBe('/api/badges/custom');
    });

    it('monta a URL opaca com os segmentos codificados', () => {
        const form = defaultCustomProviderForm(customProps());
        const definition = PROVIDER.definitions[0] as BadgeForgeCustomProvider['definitions'][number];

        const url = buildCustomBadgeUrl(
            ORIGIN,
            PROPS.customEndpoint,
            PROVIDER,
            definition,
            { owner: 'vuejs', repo: 'core' },
            form,
            PROPS,
        );

        expect(url).toBe(
            `${ORIGIN}/api/badges/custom/${PROVIDER.id}/stars/vuejs/core.svg`,
        );
        expect(url).not.toContain('cacheSeconds');
        expect(url).not.toContain('refresh');
    });

    it('mantem segmentos validos crus e os cosmeticos na query', () => {
        const form = {
            ...defaultCustomProviderForm(customProps()),
            style: 'flat-square',
            color: '#ff0000',
            logo: 'github',
            logoPosition: 'right' as const,
        };

        const url = buildCustomBadgeUrl(
            ORIGIN,
            PROPS.customEndpoint,
            PROVIDER,
            PROVIDER.definitions[0]!,
            { owner: 'some.owner_1-2', repo: 'devtools' },
            form,
            PROPS,
        );

        expect(url).toBe(
            `${ORIGIN}/api/badges/custom/${PROVIDER.id}/stars/some.owner_1-2/devtools.svg` +
                '?style=flat-square&color=%23ff0000&logo=github&logoPosition=right',
        );
    });

    it('troca a extensao e acrescenta download a pedido', () => {
        const form = defaultCustomProviderForm(customProps());

        const url = buildCustomBadgeUrl(
            ORIGIN,
            `${PROPS.customEndpoint}/`,
            PROVIDER,
            PROVIDER.definitions[1]!,
            exampleCustomParams(PROVIDER),
            form,
            PROPS,
            { format: 'png', download: true },
        );

        expect(url).toBe(
            `${ORIGIN}/api/badges/custom/${PROVIDER.id}/forks/natanfiuza/devtools.png?download=1`,
        );
    });

    it('nao monta URL com param ausente ou invalido', () => {
        const form = defaultCustomProviderForm(customProps());

        const build = (params: Record<string, string>): string =>
            buildCustomBadgeUrl(
                ORIGIN,
                PROPS.customEndpoint,
                PROVIDER,
                PROVIDER.definitions[0]!,
                params,
                form,
                PROPS,
            );

        expect(build({ owner: 'natanfiuza' })).toBe('');
        expect(build({ owner: 'natanfiuza', repo: '../etc' })).toBe('');
        expect(build({ owner: 'natanfiuza', repo: 'a b' })).toBe('');
        expect(build({ owner: '..', repo: 'devtools' })).toBe('');
    });

    it('recusa param acima do limite de comprimento', () => {
        const tooLong = 'a'.repeat(
            PROPS.limits.customProviders.paramMaxLength + 1,
        );

        expect(
            invalidCustomParam(
                PROVIDER,
                { owner: 'natanfiuza', repo: tooLong },
                PROPS.limits.customProviders.paramMaxLength,
            ),
        ).toBe('repo');
    });

    it('parte do primeiro provider e da primeira metrica', () => {
        const form = defaultCustomProviderForm(customProps());

        expect(form.provider).toBe(PROVIDER.id);
        expect(form.definition).toBe('stars');
        expect(form.params).toEqual({
            owner: 'natanfiuza',
            repo: 'devtools',
        });
        expect(form.logo).toBe('none');
        expect(form.font).toBe('default');
    });
});

describe('modo custom do construtor', () => {
    it('so oferece a aba quando o dono tem provider', () => {
        expect(
            mountTool(PROPS).find('[data-test="badge-mode-custom"]').exists(),
        ).toBe(false);

        expect(
            mountTool(customProps())
                .find('[data-test="badge-mode-custom"]')
                .exists(),
        ).toBe(true);
    });

    it('troca o formulario e a previa pela URL do servidor', async () => {
        const wrapper = mountTool(customProps());

        await wrapper.find('[data-test="badge-mode-custom"]').trigger('click');

        expect(wrapper.find('[data-test="badge-custom-mode"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="badge-provider-mode"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.find('[data-test="badge-custom-param-owner"]').element,
        ).toBeInstanceOf(HTMLInputElement);
        expect(
            wrapper
                .find('[data-test="badge-custom-preview"]')
                .attributes('src'),
        ).toBe(
            `${ORIGIN}/api/badges/custom/${PROVIDER.id}/stars/natanfiuza/devtools.svg`,
        );
    });

    it('limpa a previa sem montar URL quando o param e invalido', async () => {
        const wrapper = mountTool(customProps());

        await wrapper.find('[data-test="badge-mode-custom"]').trigger('click');
        await wrapper
            .find('[data-test="badge-custom-param-repo"]')
            .setValue('a/b');

        expect(
            wrapper.find('[data-test="badge-custom-invalid"]').exists(),
        ).toBe(true);

        // A previa e debounced: o <img> so sai depois da janela de 250 ms.
        await new Promise((resolve) => setTimeout(resolve, 300));
        await flushPromises();

        expect(
            wrapper.find('[data-test="badge-custom-invalid-preview"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-test="badge-custom-preview"]').exists()).toBe(
            false,
        );
    });

    it('monta a URL mesmo com o provider desabilitado (o servidor responde 404)', async () => {
        const props = customProps({
            providers: [{ ...PROVIDER, enabled: false }],
        });
        const wrapper = mountTool(props);

        await wrapper.find('[data-test="badge-mode-custom"]').trigger('click');

        expect(
            wrapper.find('[data-test="badge-custom-provider"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="badge-custom-preview"]')
                .attributes('src'),
        ).toBe(
            `${ORIGIN}/api/badges/custom/${PROVIDER.id}/stars/natanfiuza/devtools.svg`,
        );
    });
});

describe('painel de providers customizados', () => {
    it('lista o provider, marca o desabilitado e copia a URL de cada metrica', async () => {
        const clipboard = { writeText: vi.fn(() => Promise.resolve()) };
        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });

        const wrapper = mountPanel(
            customProps({
                providers: [
                    PROVIDER,
                    { ...PROVIDER, id: '01K0CUSTOMPROVIDER00000002', name: 'Off', enabled: false },
                ],
            }),
        );

        expect(wrapper.findAll('[data-test="badgeforge-custom-row"]')).toHaveLength(
            2,
        );
        expect(wrapper.text()).toContain('Catálogo');
        expect(
            wrapper.find('[data-test="badgeforge-custom-disabled"]').exists(),
        ).toBe(true);

        await wrapper
            .findAll('[data-test="badgeforge-custom-copy-svg"]')[0]!
            .trigger('click');
        await flushPromises();

        expect(clipboard.writeText).toHaveBeenCalledWith(
            `${ORIGIN}/api/badges/custom/${PROVIDER.id}/stars/natanfiuza/devtools.svg`,
        );

        await wrapper
            .findAll('[data-test="badgeforge-custom-copy-png"]')[1]!
            .trigger('click');
        await flushPromises();

        expect(clipboard.writeText).toHaveBeenLastCalledWith(
            `${ORIGIN}/api/badges/custom/${PROVIDER.id}/forks/natanfiuza/devtools.png`,
        );
    });

    it('exclui so depois da confirmacao', async () => {
        const wrapper = mountPanel(customProps());

        await wrapper
            .find('[data-test="badgeforge-custom-delete"]')
            .trigger('click');

        expect(
            wrapper.find('[data-test="badgeforge-custom-delete-confirm"]').exists(),
        ).toBe(true);
        expect(routerDelete).not.toHaveBeenCalled();

        await wrapper
            .find('[data-test="badgeforge-custom-delete-confirmed"]')
            .trigger('click');

        expect(routerDelete).toHaveBeenCalledTimes(1);
        expect(String(routerDelete.mock.calls[0]?.[0])).toBe(
            `/badgeforge/custom-providers/${PROVIDER.id}`,
        );
    });

    it('Free ve a nota de upgrade, nao cria e ainda exclui', () => {
        const wrapper = mountPanel(
            customProps({ canCreate: false, providers: [] }),
        );

        expect(
            wrapper.find('[data-test="badgeforge-custom-readonly"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="badgeforge-custom-upgrade"]')
                .attributes('href'),
        ).toBe('/planos');
        expect(
            wrapper.find('[data-test="badgeforge-custom-create"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="badgeforge-custom-empty"]').exists(),
        ).toBe(true);
    });

    it('mostra o aviso de limite atingido', () => {
        const props = customProps();
        props.limits.customProviders.limit = 1;
        props.limits.customProviders.used = 1;

        const wrapper = mountPanel(props);

        expect(wrapper.find('[data-test="badgeforge-custom-limit"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-test="badgeforge-custom-create"]').attributes(
                'disabled',
            ),
        ).toBeDefined();
    });
});

describe('dialogo de provider customizado', () => {
    it('abre a edicao sem template, slug imutavel e envia so nome e metricas', async () => {
        const wrapper = mountPanel(customProps());

        await wrapper
            .find('[data-test="badgeforge-custom-edit"]')
            .trigger('click');
        await flushPromises();

        expect(
            document.querySelector('[data-test="badge-custom-provider-form"]'),
        ).not.toBeNull();
        expect(
            document.querySelector('[data-test="badge-custom-provider-template"]'),
        ).toBeNull();
        expect(
            document.querySelector('[data-test="badge-custom-provider-immutable"]'),
        ).not.toBeNull();

        const slugs = document.querySelectorAll<HTMLInputElement>(
            '[data-test="badge-custom-provider-metric-slug"]',
        );

        expect(slugs).toHaveLength(2);
        expect(slugs[0]?.value).toBe('stars');
        expect(slugs[0]?.readOnly).toBe(true);

        const labels = document.querySelectorAll<HTMLInputElement>(
            '[data-test="badge-custom-provider-metric-label"]',
        );

        expect(labels[1]?.value).toBe('Forks');

        fill('badge-custom-provider-name', 'Catálogo novo');
        await flushPromises();

        expect(
            document.querySelector<HTMLInputElement>(
                '[data-test="badge-custom-provider-name"]',
            )?.value,
        ).toBe('Catálogo novo');

        submitDialog();
        await flushPromises();

        expect(formPatch).toHaveBeenCalledTimes(1);
        expect(String(formPatch.mock.calls[0]?.[0])).toBe(
            `/badgeforge/custom-providers/${PROVIDER.id}`,
        );

        const payload = formPatch.mock.calls[0]?.[1] as Record<string, unknown>;

        expect(Object.keys(payload).sort()).toEqual(['metrics', 'name']);
        expect(payload.name).toBe('Catálogo novo');
        expect(payload.metrics).toEqual([
            {
                slug: 'stars',
                label: 'Estrelas',
                pointer: '/stargazers_count',
                formatter: 'number',
            },
            {
                slug: 'forks',
                label: 'Forks',
                pointer: '/forks_count',
                formatter: 'number',
            },
        ]);
    });

    it('cria com template, params separados por virgula e metricas', async () => {
        const wrapper = mountPanel(customProps({ providers: [] }));

        expect(
            wrapper.find('[data-test="badgeforge-custom-create"]').exists(),
        ).toBe(true);

        await wrapper
            .find('[data-test="badgeforge-custom-create"]')
            .trigger('click');
        await flushPromises();

        expect(
            document.querySelector('[data-test="badge-custom-provider-template"]'),
        ).not.toBeNull();

        fill('badge-custom-provider-name', 'Meu catálogo');
        fill(
            'badge-custom-provider-template',
            'https://api.exemplo.com/repos/{owner}/{repo}',
        );
        fill('badge-custom-provider-params', 'owner, repo');
        fill('badge-custom-provider-example', 'octocat/hello');
        fill('badge-custom-provider-metric-slug', 'stars');
        fill('badge-custom-provider-metric-label', 'Estrelas');
        fill('badge-custom-provider-metric-pointer', '/stargazers_count');

        expect(
            document.querySelector<HTMLInputElement>(
                '[data-test="badge-custom-provider-params"]',
            )?.value,
        ).toBe('owner, repo');

        // O `Input` emite o v-model num watcher: o flush chega antes do submit.
        await flushPromises();

        submitDialog();
        await flushPromises();

        expect(formPost).toHaveBeenCalledTimes(1);
        expect(String(formPost.mock.calls[0]?.[0])).toBe(
            '/badgeforge/custom-providers',
        );

        const payload = formPost.mock.calls[0]?.[1] as Record<string, unknown>;

        expect(payload.path_params).toEqual(['owner', 'repo']);
        expect(payload.example_path).toBe('octocat/hello');
        expect(payload.metrics).toEqual([
            {
                slug: 'stars',
                label: 'Estrelas',
                pointer: '/stargazers_count',
                formatter: 'number',
            },
        ]);
    });
});
