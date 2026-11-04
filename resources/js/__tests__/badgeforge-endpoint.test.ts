import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import EndpointBadgeDialog from '@/tools/badgeforge/EndpointBadgeDialog.vue';
import EndpointBadgesPanel from '@/tools/badgeforge/EndpointBadgesPanel.vue';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import type {
    BadgeForgeEndpointBadge,
    BadgeForgeToolProps,
} from '@/tools/badgeforge/types';

/**
 * Endpoints JSON (F5, ADR-083).
 *
 * A lista mostra so a identidade e as URLs publicas; a URL de origem nunca
 * entra nas props nem volta para a tela. Cadastrar, renomear e excluir sao as
 * unicas acoes que tocam o servidor.
 */

const { forms, postMock, patchMock, deleteMock } = vi.hoisted(() => ({
    forms: [] as Array<Record<string, unknown>>,
    postMock: vi.fn(),
    patchMock: vi.fn(),
    deleteMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: (initial: Record<string, unknown>) => {
            const form: Record<string, unknown> = reactive({
                ...initial,
                errors: {},
                processing: false,
                post: postMock,
                patch: patchMock,
                reset: vi.fn(),
                clearErrors: vi.fn(),
            });

            forms.push(form);

            return form;
        },
        router: { delete: deleteMock },
    };
});

const PROPS = toolProps as BadgeForgeToolProps;

const TRADUCOES = {
    tools: {
        ui: {
            actions: { copied: 'COPIADO' },
        },
        badgeforge: {
            management: {
                save: 'SALVAR',
                cancel: 'CANCELAR',
                rename: 'RENOMEAR',
                delete: 'EXCLUIR',
                delete_title: 'EXCLUIR {name}',
            },
            endpoint: {
                title: 'ENDPOINTS',
                description: 'DESC_ENDPOINTS',
                create_button: 'NOVO_ENDPOINT',
                readonly_note: 'SOMENTE_LEITURA',
                empty: 'VAZIO',
                dialog_title: 'CADASTRAR_ENDPOINT',
                dialog_description: 'DESC_CADASTRO',
                name: 'NOME',
                name_placeholder: 'DEPLOYS',
                url: 'URL_ORIGEM',
                url_placeholder: 'https://exemplo.com/badge.json',
                url_hint: 'FICA_NO_SERVIDOR',
                create: 'CADASTRAR',
                creating: 'CADASTRANDO',
                copy_svg: 'COPIAR_SVG',
                copy_png: 'COPIAR_PNG',
                delete_description: 'CACHE_DESCARTADO',
            },
        },
    },
};

const ENDPOINT: BadgeForgeEndpointBadge = {
    id: '01K0ENDPOINT000000000000001',
    name: 'Deploys',
    svgUrl: '/api/badges/endpoint/01K0ENDPOINT000000000000001.svg',
    pngUrl: '/api/badges/endpoint/01K0ENDPOINT000000000000001.png',
    createdAt: '2026-11-04T12:00:00+00:00',
};

function toolPropsFor(
    overrides: {
        canCreate?: boolean;
        endpointBadges?: BadgeForgeEndpointBadge[];
    } = {},
): BadgeForgeToolProps {
    return {
        ...PROPS,
        management: {
            ...PROPS.management,
            canCreateEndpointBadges: overrides.canCreate ?? true,
        },
        endpointBadges: overrides.endpointBadges ?? [ENDPOINT],
    };
}

function i18n() {
    const instance = createI18nInstance();
    applyTranslations(instance, { locale: 'pt-BR', translations: TRADUCOES });

    return instance;
}

function mountPanel(props: BadgeForgeToolProps) {
    return mount(EndpointBadgesPanel, {
        props: { toolProps: props },
        global: { plugins: [i18n()] },
    });
}

let clipboard: { writeText: ReturnType<typeof vi.fn> };

beforeEach(() => {
    forms.length = 0;
    postMock.mockClear();
    patchMock.mockClear();
    deleteMock.mockClear();
    document.body.innerHTML = '';

    clipboard = { writeText: vi.fn(() => Promise.resolve()) };
    Object.defineProperty(navigator, 'clipboard', {
        value: clipboard,
        configurable: true,
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('EndpointBadgesPanel', () => {
    it('lista, previa e copia as URLs publicas sem a origem', async () => {
        const wrapper = mountPanel(toolPropsFor());

        expect(
            wrapper
                .find('[data-test="badgeforge-endpoint-preview"]')
                .attributes('src'),
        ).toBe(ENDPOINT.svgUrl);

        await wrapper
            .find('[data-test="badgeforge-endpoint-copy-svg"]')
            .trigger('click');
        await vi.dynamicImportSettled();
        await wrapper
            .find('[data-test="badgeforge-endpoint-copy-png"]')
            .trigger('click');
        await vi.dynamicImportSettled();

        expect(clipboard.writeText).toHaveBeenNthCalledWith(1, ENDPOINT.svgUrl);
        expect(clipboard.writeText).toHaveBeenNthCalledWith(2, ENDPOINT.pngUrl);
        expect(wrapper.html()).not.toContain('example.com');
    });

    it('mostra o estado vazio e o botao de cadastro', () => {
        const wrapper = mountPanel(toolPropsFor({ endpointBadges: [] }));

        expect(
            wrapper.find('[data-test="badgeforge-endpoint-empty"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="badgeforge-endpoint-create"]').exists(),
        ).toBe(true);
    });

    it('sem permissao mantem a lista e bloqueia cadastro e renomeacao', () => {
        const wrapper = mountPanel(toolPropsFor({ canCreate: false }));

        expect(
            wrapper.find('[data-test="badgeforge-endpoint-readonly"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="badgeforge-endpoint-create"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="badgeforge-endpoint-rename"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="badgeforge-endpoint-copy-svg"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="badgeforge-endpoint-delete"]').exists(),
        ).toBe(true);
    });

    it('renomeia com PATCH e limita o nome pelo contrato', async () => {
        const wrapper = mountPanel(toolPropsFor());

        await wrapper
            .find('[data-test="badgeforge-endpoint-rename"]')
            .trigger('click');

        const input = wrapper.find(
            '[data-test="badgeforge-endpoint-rename-input"]',
        );

        expect(input.attributes('maxlength')).toBe('80');
        await input.setValue('Producao');

        await wrapper
            .find('[data-test="badgeforge-endpoint-rename-save"]')
            .trigger('click');

        expect(patchMock).toHaveBeenCalledTimes(1);
        expect(String(patchMock.mock.calls[0]?.[0])).toBe(
            '/badgeforge/endpoints/01K0ENDPOINT000000000000001',
        );
        expect(forms[0]?.name).toBe('Producao');
    });

    it('so exclui depois de confirmar', async () => {
        const wrapper = mountPanel(toolPropsFor());

        await wrapper
            .find('[data-test="badgeforge-endpoint-delete"]')
            .trigger('click');

        expect(
            wrapper
                .find('[data-test="badgeforge-endpoint-delete-confirm"]')
                .exists(),
        ).toBe(true);
        expect(deleteMock).not.toHaveBeenCalled();

        await wrapper
            .find('[data-test="badgeforge-endpoint-delete-confirmed"]')
            .trigger('click');

        expect(deleteMock).toHaveBeenCalledTimes(1);
        expect(String(deleteMock.mock.calls[0]?.[0])).toBe(
            '/badgeforge/endpoints/01K0ENDPOINT000000000000001',
        );
    });

    it('abre o formulario de cadastro pelo botao do painel', async () => {
        const wrapper = mountPanel(toolPropsFor({ endpointBadges: [] }));

        await wrapper
            .find('[data-test="badgeforge-endpoint-create"]')
            .trigger('click');
        await flushPromises();

        expect(
            document.querySelector('[data-test="badge-endpoint-form"]'),
        ).not.toBeNull();
    });
});

describe('EndpointBadgeDialog', () => {
    it('cadastra nome e URL com os limites do contrato', async () => {
        mount(EndpointBadgeDialog, {
            props: { open: true, toolProps: toolPropsFor() },
            global: { plugins: [i18n()] },
            attachTo: document.body,
        });
        await flushPromises();

        const name = document.querySelector<HTMLInputElement>(
            '[data-test="badge-endpoint-name"]',
        );
        const url = document.querySelector<HTMLInputElement>(
            '[data-test="badge-endpoint-url"]',
        );

        expect(name?.maxLength).toBe(80);
        expect(url?.maxLength).toBe(2048);
        expect(document.body.innerHTML).toContain('FICA_NO_SERVIDOR');

        if (name === null || url === null) {
            throw new Error('formulario de endpoint nao renderizou');
        }

        name.value = 'Deploys';
        name.dispatchEvent(new Event('input'));
        url.value = 'https://example.com/badge.json';
        url.dispatchEvent(new Event('input'));

        document
            .querySelector<HTMLButtonElement>('[data-test="badge-endpoint-submit"]')
            ?.click();
        await flushPromises();

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(String(postMock.mock.calls[0]?.[0])).toBe('/badgeforge/endpoints');
        expect(forms[0]?.name).toBe('Deploys');
        expect(forms[0]?.url).toBe('https://example.com/badge.json');
    });
});
