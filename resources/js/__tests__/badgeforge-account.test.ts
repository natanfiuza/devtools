import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import BadgeForge from '@/pages/dashboard/BadgeForge.vue';
import LogoLibraryDialog from '@/tools/badgeforge/LogoLibraryDialog.vue';
import PresetDialog from '@/tools/badgeforge/PresetDialog.vue';
import { clearCustomLogos } from '@/tools/badgeforge/customLogos';
import type { AccountBadgeForgeProps } from '@/types';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import type {
    BadgeForgeCustomLogoSummary,
    BadgeForgePreset,
    BadgeForgeToolProps,
} from '@/tools/badgeforge/types';

/**
 * Pagina de conta do BadgeForge (`/dashboard/badgeforge`, ADR-079).
 *
 * A tela reusa o contrato da tool; as props continuam levando so metadados, e
 * a previa de um logo so busca os paths quando o usuario pede.
 */

const { routerMock, formPatch } = vi.hoisted(() => ({
    routerMock: {
        delete: vi.fn(),
        patch: vi.fn(),
    },
    formPatch: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useForm: () =>
            reactive({
                name: '',
                logo: null,
                errors: {} as Record<string, string>,
                processing: false,
                post: vi.fn(),
                patch: formPatch,
                reset: vi.fn(),
                clearErrors: vi.fn(),
            }),
        usePage: () => ({
            props: {
                localeSlug: 'pt-br',
                auth: { user: { id: 1 } },
            },
        }),
        router: routerMock,
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
    account: {
        badgeforge: {
            title: 'CONTA_BADGEFORGE',
            description: 'DESCRICAO_CONTA',
            open_tool: 'ABRIR_TOOL',
            limits: 'LIMITES',
            limit_logos: 'LIMITE_LOGOS',
            limit_presets: 'LIMITE_PRESETS',
            unlimited: 'ILIMITADO',
            used_of: '{used} DE {limit}',
            upgrade: 'UPGRADE',
            logos: 'LOGOS',
            presets: 'PRESETS',
            empty_logos: 'SEM_LOGOS',
            empty_presets: 'SEM_PRESETS',
            logo_paths: '{count} CAMINHOS',
            preview: 'PREVIA',
            preview_loading: 'CARREGANDO',
            preview_error: 'FALHOU',
            orphaned: 'ORFAO',
            open_preset: 'ABRIR_PRESET',
            rename: 'RENOMEAR',
            delete: 'EXCLUIR',
            save: 'SALVAR',
            cancel: 'CANCELAR',
            name: 'NOME',
            delete_title: 'EXCLUIR {name}',
            delete_description: 'SEM_VOLTA',
        },
    },
    tools: {
        badgeforge: {
            management: {
                name: 'NOME_LOGO',
                name_placeholder: 'MEU_LOGO',
                logo_file: 'ARQUIVO',
                upload: 'ENVIAR',
                uploading: 'ENVIANDO',
                upload_hint: 'ATE {size} KB',
                limit_note: 'USADOS {used} DE {limit}',
                unlimited_note: 'USADOS {used} SEM_LIMITE',
                upgrade_cta: 'PLANO PERMITE {limit}',
                upgrade: 'MELHORAR',
                logos_title: 'MEUS_LOGOS',
                logos_description: 'DESC_LOGOS',
                presets_title: 'MEUS_PRESETS',
                presets_description: 'DESC_PRESETS',
                empty_logos: 'SEM_LOGOS',
                empty_presets: 'SEM_PRESETS',
                path_count: '{count} CAMINHOS',
                preview: 'PREVIA_LOGO',
                preview_loading: 'CARREGANDO_PREVIA',
                preview_error: 'ERRO_PREVIA',
                save: 'SALVAR',
                cancel: 'CANCELAR',
                rename: 'RENOMEAR',
                delete: 'EXCLUIR',
                delete_title: 'EXCLUIR {name}',
                delete_description: 'SEM_VOLTA',
                preset_name_placeholder: 'MEU_PRESET',
                save_preset: 'SALVAR_PRESET',
                apply_preset: 'APLICAR',
                preset_limit_note: 'PRESETS {used} DE {limit}',
                preset_unlimited_note: 'PRESETS {used} SEM_LIMITE',
                preset_orphaned: 'LOGO_ORFAO',
            },
        },
    },
};

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
        style: 'flat',
        font: null,
        color: '#44cc11',
        labelColor: '#333333',
        logo: 'composer',
        logoColor: '#ffffff',
        logoSize: 12,
        logoPosition: 'left',
        link: '',
        cacheSeconds: 600,
    },
    logoOrphaned: true,
};

function accountProps(
    overrides: Partial<BadgeForgeToolProps> = {},
): AccountBadgeForgeProps {
    return {
        toolProps: {
            ...(toolProps as BadgeForgeToolProps),
            limits: {
                ...(toolProps as BadgeForgeToolProps).limits,
                customLogos: {
                    ...(toolProps as BadgeForgeToolProps).limits.customLogos,
                    limit: 2,
                    used: 1,
                },
                presets: {
                    ...(toolProps as BadgeForgeToolProps).limits.presets,
                    limit: null,
                    used: 1,
                },
            },
            management: {
                canManage: true,
                canSavePresets: true,
                isPaid: false,
            },
            customLogos: [CUSTOM_LOGO],
            presets: [PRESET],
            ...overrides,
        },
        toolUrl: '/tools/badgeforge',
        upgradeUrl: '/planos',
    };
}

function mountPage(props: AccountBadgeForgeProps) {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt-BR', translations: TRADUCOES });

    return mount(BadgeForge, {
        props,
        global: { plugins: [i18n] },
    });
}

beforeEach(() => {
    document.body.innerHTML = '';
});

afterEach(() => {
    clearCustomLogos();
    vi.unstubAllGlobals();
});

describe('BadgeForge na conta', () => {
    it('mostra limites, upgrade e os itens do usuario', () => {
        const wrapper = mountPage(accountProps());

        const limites = wrapper.find('[data-test="badgeforge-limits"]').text();

        expect(limites).toContain('1 DE 2');
        expect(limites).toContain('ILIMITADO');
        expect(
            wrapper.find('[data-test="badgeforge-upgrade"]').attributes('href'),
        ).toBe('/planos');

        expect(wrapper.text()).toContain('Minha marca');
        expect(wrapper.text()).toContain('2 CAMINHOS');
        expect(wrapper.text()).toContain('Release');
        expect(
            wrapper
                .find('[data-test="badgeforge-preset-orphaned"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="badgeforge-empty-logos"]').exists() ===
                false,
        ).toBe(true);
    });

    it('abre o preset no construtor com ?preset=<id>', () => {
        const wrapper = mountPage(accountProps());

        expect(
            wrapper.find('[data-test="badgeforge-preset-open"]').attributes(
                'href',
            ),
        ).toBe(`/tools/badgeforge?preset=${PRESET.id}`);
    });

    it('esconde o upgrade e mostra ilimitado no plano pago', () => {
        const props = accountProps();

        props.toolProps = {
            ...props.toolProps,
            limits: {
                ...props.toolProps.limits,
                customLogos: {
                    ...props.toolProps.limits.customLogos,
                    limit: null,
                    used: 3,
                },
            },
            management: { ...props.toolProps.management, isPaid: true },
        };

        const wrapper = mountPage(props);

        expect(
            wrapper.find('[data-test="badgeforge-upgrade"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-test="badgeforge-limits"]').text()).toBe(
            'LIMITESLIMITE_LOGOSILIMITADOLIMITE_PRESETSILIMITADO',
        );
    });

    it('busca a previa do logo so quando o usuario pede', async () => {
        const fetchMock = vi.fn((_input: string, _init?: unknown) =>
            Promise.resolve({
                ok: true,
                json: () =>
                    Promise.resolve({
                        viewBox: '0 0 24 24',
                        paths: [{ d: 'M0 0h24v24H0z' }],
                    }),
            }),
        );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPage(accountProps());

        expect(fetchMock).not.toHaveBeenCalled();

        await wrapper
            .find('[data-test="badgeforge-logo-preview-toggle"]')
            .trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            `/badgeforge/logos/${CUSTOM_LOGO.id}`,
        );
        expect(
            wrapper.find('[data-test="badge-logo-preview"]').html(),
        ).toContain('M0 0h24v24H0z');
    });
});

describe('Dialogos de gestao', () => {
    const i18nDoTeste = () => {
        const i18n = createI18nInstance();
        applyTranslations(i18n, { locale: 'pt-BR', translations: TRADUCOES });

        return i18n;
    };

    const builderForm = {
        label: 'build',
        message: 'passing',
        style: 'flat',
        font: 'default',
        color: '#9f9f9f',
        labelColor: '#555555',
        logo: 'composer',
        logoColor: '#ffffff',
        logoSize: '',
        logoPosition: 'left' as const,
        link: '',
        cacheSeconds: '',
    };

    it('bloqueia o upload no limite Free e mostra o upgrade', async () => {
        mount(LogoLibraryDialog, {
            props: {
                open: true,
                logos: [CUSTOM_LOGO],
                limits: {
                    limit: 2,
                    used: 2,
                    maxBytes: 262144,
                    maxPaths: 200,
                    maxPathLength: 4000,
                    maxTotalPathLength: 100000,
                    maxCacheSeconds: 3600,
                    nameMaxLength: 80,
                },
                upgradeUrl: '/planos',
            },
            global: { plugins: [i18nDoTeste()] },
        });

        await flushPromises();

        const body = document.body.innerHTML;

        expect(body).toContain('Minha marca');
        expect(body).toContain('USADOS 2 DE 2');
        expect(body).toContain('PLANO PERMITE 2');
        expect(
            document.querySelector<HTMLAnchorElement>(
                '[data-test="badge-logo-upgrade"] a',
            )?.getAttribute('href'),
        ).toBe('/planos');
        expect(
            document.querySelector<HTMLButtonElement>(
                '[data-test="badge-logo-upload-submit"]',
            )?.disabled,
        ).toBe(true);
    });

    it('exclui o logo so depois da confirmacao', async () => {
        routerMock.delete.mockClear();
        document.body.innerHTML = '';

        mount(LogoLibraryDialog, {
            props: {
                open: true,
                logos: [CUSTOM_LOGO],
                limits: {
                    limit: null,
                    used: 1,
                    maxBytes: 262144,
                    maxPaths: 200,
                    maxPathLength: 4000,
                    maxTotalPathLength: 100000,
                    maxCacheSeconds: 3600,
                    nameMaxLength: 80,
                },
                upgradeUrl: '/planos',
            },
            global: { plugins: [i18nDoTeste()] },
        });

        await flushPromises();

        document
            .querySelector<HTMLButtonElement>('[data-test="badge-logo-delete"]')
            ?.click();
        await flushPromises();

        expect(
            document.querySelector('[data-test="badge-logo-delete-confirm"]'),
        ).not.toBeNull();
        expect(routerMock.delete).not.toHaveBeenCalled();

        document
            .querySelector<HTMLButtonElement>(
                '[data-test="badge-logo-delete-confirmed"]',
            )
            ?.click();
        await flushPromises();

        expect(routerMock.delete).toHaveBeenCalledTimes(1);
        expect(String(routerMock.delete.mock.calls[0]?.[0])).toBe(
            `/badgeforge/logos/${CUSTOM_LOGO.id}`,
        );
    });

    it('avisa o preset orfao e emite apply ao aplicar', async () => {
        document.body.innerHTML = '';

        const wrapper = mount(PresetDialog, {
            props: {
                open: true,
                presets: [PRESET],
                limits: { limit: null, used: 1, nameMaxLength: 80 },
                builderForm,
            },
            global: { plugins: [i18nDoTeste()] },
        });

        await flushPromises();

        expect(document.body.innerHTML).toContain('LOGO_ORFAO');
        expect(document.body.innerHTML).toContain('PRESETS 1 SEM_LIMITE');

        document
            .querySelector<HTMLButtonElement>('[data-test="badge-preset-apply"]')
            ?.click();
        await flushPromises();

        expect(wrapper.emitted('apply')?.[0]?.[0]).toEqual(PRESET);
        expect(wrapper.emitted('update:open')?.[0]).toEqual([false]);
    });
});
