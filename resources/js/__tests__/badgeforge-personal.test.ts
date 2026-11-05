import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import PersonalBadgeDialog from '@/tools/badgeforge/PersonalBadgeDialog.vue';
import PersonalBadgesPanel from '@/tools/badgeforge/PersonalBadgesPanel.vue';
import { defaultProviderForm } from '@/tools/badgeforge/lib/providerUrl';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import type {
    BadgeForgePersonalBadge,
    BadgeForgeToolProps,
} from '@/tools/badgeforge/types';

/**
 * Badges pessoais (F4, ADR-081).
 *
 * O pago cria pelo construtor e o Free ve a lista em modo somente leitura. O
 * PAT e write-only: o input e `password`, nunca vem preenchido e e limpo ao
 * fechar. Atualizar a imagem e um POST autenticado seguido de `?v=` local; a
 * URL publica nunca ganha `?refresh=1`.
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
                transform: (callback: () => unknown) => {
                    form.transformCallback = callback;

                    return form;
                },
                reset: vi.fn(),
                clearErrors: vi.fn(),
            });

            forms.push(form);

            return form;
        },
        router: { delete: deleteMock },
        Link: defineComponent({
            name: 'InertiaLink',
            props: { href: { type: String, default: '' } },
            setup(props, { slots, attrs }) {
                return () =>
                    h('a', { ...attrs, href: props.href }, slots.default?.());
            },
        }),
        Head: defineComponent({
            name: 'InertiaHead',
            props: { title: { type: String, default: '' } },
            render: () => h('div'),
        }),
    };
});

const PROPS = toolProps as BadgeForgeToolProps;

const TRADUCOES = {
    tools: {
        ui: {
            actions: { copied: 'COPIADO' },
        },
        badgeforge: {
            modes: { static: 'ESTATICO', provider: 'PROVEDOR' },
            management: {
                save: 'SALVAR',
                cancel: 'CANCELAR',
                rename: 'RENOMEAR',
                delete: 'EXCLUIR',
                delete_title: 'EXCLUIR {name}',
                delete_description: 'SEM_VOLTA',
            },
            provider: {
                metrics: {
                    github_stars: 'ESTRELAS',
                    docker_pulls: 'PULLS',
                },
            },
            personal: {
                create_button: 'CRIAR_PESSOAL',
                title: 'PESSOAIS',
                description: 'DESC_PESSOAIS',
                dialog_title: 'CRIAR_BADGE_PESSOAL',
                dialog_description: 'DESC_CRIAR',
                name: 'NOME',
                name_placeholder: 'MEU_BADGE',
                metric_note: 'METRICA {metric}',
                private: 'PRIVADA',
                private_hint: 'EXIGE_TOKEN',
                credential: 'TOKEN',
                credential_placeholder: 'NUNCA_MOSTRADO',
                credential_username: 'USUARIO',
                credential_username_placeholder: 'USUARIO_DOCKER',
                params_invalid: 'PARAMS_INVALIDOS',
                create: 'CRIAR',
                creating: 'CRIANDO',
                upgrade_cta: 'PRECISA_PLANO_PAGO',
                upgrade: 'MELHORAR',
                readonly_note: 'SOMENTE_LEITURA',
                empty: 'VAZIO',
                copy_svg: 'COPIAR_SVG',
                copy_png: 'COPIAR_PNG',
                refresh: 'ATUALIZAR',
                refreshing: 'ATUALIZANDO',
                refresh_failed: 'FALHOU_ATUALIZAR',
                change_credential: 'TROCAR_TOKEN',
                credential_set: 'TOKEN_SALVO',
                credential_missing: 'TOKEN_AUSENTE',
                credential_not_required: 'ITEM_PUBLICO',
            },
        },
    },
};

const BADGE: BadgeForgePersonalBadge = {
    id: '01K0PERSONALBADGE0000000001',
    name: 'Estrelas',
    type: 'provider',
    providerSlug: 'github',
    definitionSlug: 'stars',
    params: { owner: 'natanfiuza', repo: 'devtools' },
    private: false,
    credentialSet: false,
    credentialUsername: null,
    url: '/b/natanfiuza/01K0PERSONALBADGE0000000001.svg',
    pngUrl: '/b/natanfiuza/01K0PERSONALBADGE0000000001.png',
    lastGeneratedAt: '2026-11-03T12:00:00+00:00',
    expiresAt: '2026-11-03T12:05:00+00:00',
    createdAt: '2026-11-03T11:00:00+00:00',
};

const PRIVATE_BADGE: BadgeForgePersonalBadge = {
    ...BADGE,
    private: true,
    credentialSet: true,
    credentialUsername: null,
};

function toolPropsFor(
    overrides: {
        paid?: boolean;
        personalBadges?: BadgeForgePersonalBadge[];
        providers?: BadgeForgeToolProps['providers'];
    } = {},
): BadgeForgeToolProps {
    const paid = overrides.paid ?? true;

    return {
        ...PROPS,
        limits: {
            ...PROPS.limits,
            personalBadges: {
                limit: paid ? null : 0,
                used: 1,
                nameMaxLength: 80,
            },
        },
        management: {
            canManage: true,
            canSavePresets: paid,
            canCreatePersonalBadges: paid,
            canCreateEndpointBadges: true,
            canCreateCustomProviders: paid,
            isPaid: paid,
        },
        personalBadges: overrides.personalBadges ?? [BADGE],
        providers: overrides.providers ?? PROPS.providers,
    };
}

function i18n() {
    const instance = createI18nInstance();
    applyTranslations(instance, { locale: 'pt-BR', translations: TRADUCOES });

    return instance;
}

function mountPanel(props: BadgeForgeToolProps) {
    return mount(PersonalBadgesPanel, {
        props: { toolProps: props, upgradeUrl: '/planos' },
        global: { plugins: [i18n()] },
    });
}

const builderForm = {
    label: 'build',
    message: 'passing',
    style: 'flat',
    font: 'default',
    color: '#9f9f9f',
    labelColor: '#555555',
    logo: 'none',
    logoColor: '#ffffff',
    logoSize: '',
    logoPosition: 'left' as const,
    link: '',
    cacheSeconds: '',
};

function mountDialog(
    props: BadgeForgeToolProps,
    overrides: {
        mode?: 'static' | 'provider';
        providerForm?: ReturnType<typeof defaultProviderForm>;
    } = {},
) {
    return mount(PersonalBadgeDialog, {
        props: {
            open: true,
            toolProps: props,
            mode: overrides.mode ?? 'provider',
            builderForm,
            providerForm: overrides.providerForm ?? defaultProviderForm(props),
            upgradeUrl: '/planos',
        },
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

describe('PersonalBadgesPanel', () => {
    it('lista, copia a URL publica e o PNG sem ?refresh', async () => {
        const wrapper = mountPanel(toolPropsFor());

        expect(
            wrapper.find('[data-test="badgeforge-personal-preview"]').attributes(
                'src',
            ),
        ).toBe(BADGE.url);
        expect(wrapper.text()).toContain('ESTRELAS');

        await wrapper
            .find('[data-test="badgeforge-personal-copy-svg"]')
            .trigger('click');
        await vi.dynamicImportSettled();
        await wrapper
            .find('[data-test="badgeforge-personal-copy-png"]')
            .trigger('click');
        await vi.dynamicImportSettled();

        expect(clipboard.writeText).toHaveBeenNthCalledWith(1, BADGE.url);
        expect(clipboard.writeText).toHaveBeenNthCalledWith(2, BADGE.pngUrl);
        expect(String(clipboard.writeText.mock.calls[0]?.[0])).not.toContain(
            'refresh',
        );
    });

    it('depois do downgrade mantem a lista e bloqueia as edicoes', () => {
        const wrapper = mountPanel(
            toolPropsFor({ paid: false, personalBadges: [PRIVATE_BADGE] }),
        );

        expect(
            wrapper.find('[data-test="badgeforge-personal-upgrade"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="badgeforge-personal-upgrade-link"]')
                .attributes('href'),
        ).toBe('/planos');
        expect(
            wrapper.find('[data-test="badgeforge-personal-row"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="badgeforge-personal-copy-svg"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="badgeforge-personal-delete"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="badgeforge-personal-rename"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-test="badgeforge-personal-refresh"]').exists(),
        ).toBe(false);
        expect(
            wrapper
                .find('[data-test="badgeforge-personal-credential"]')
                .exists(),
        ).toBe(false);
    });

    it('mostra o status da credencial de cada badge', () => {
        const wrapper = mountPanel(
            toolPropsFor({
                personalBadges: [
                    BADGE,
                    PRIVATE_BADGE,
                    { ...BADGE, id: '01K0OUTRA00000000000000001' },
                ],
            }),
        );

        const statuses = wrapper
            .findAll('[data-test="badgeforge-personal-credential-status"]')
            .map((item) => item.text());

        expect(statuses).toEqual([
            'ITEM_PUBLICO',
            'TOKEN_SALVO',
            'ITEM_PUBLICO',
        ]);
    });

    it('atualiza pelo POST autenticado e aplica ?v= so na tela', async () => {
        const fetchMock = vi.fn(() =>
            Promise.resolve({ ok: true, json: () => Promise.resolve({}) }),
        );
        vi.stubGlobal('fetch', fetchMock);

        const wrapper = mountPanel(toolPropsFor());

        await wrapper
            .find('[data-test="badgeforge-personal-refresh"]')
            .trigger('click');
        await flushPromises();

        const [url, init] = fetchMock.mock.calls[0] as unknown as [
            string,
            Record<string, string>,
        ];

        expect(url).toBe(`/badgeforge/badges/01K0PERSONALBADGE0000000001/refresh`);
        expect(init.method).toBe('POST');
        expect(init.headers).toMatchObject({
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        });
        expect(
            wrapper.find('[data-test="badgeforge-personal-preview"]').attributes(
                'src',
            ),
        ).toContain('?v=');
        expect(
            wrapper
                .find('[data-test="badgeforge-personal-refresh-error"]')
                .exists(),
        ).toBe(false);
    });

    it('avisa quando a atualizacao falha', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve({ ok: false })),
        );

        const wrapper = mountPanel(toolPropsFor());

        await wrapper
            .find('[data-test="badgeforge-personal-refresh"]')
            .trigger('click');
        await flushPromises();

        expect(
            wrapper
                .find('[data-test="badgeforge-personal-refresh-error"]')
                .exists(),
        ).toBe(true);
    });

    it('so exclui depois de confirmar', async () => {
        const wrapper = mountPanel(toolPropsFor());

        await wrapper
            .find('[data-test="badgeforge-personal-delete"]')
            .trigger('click');

        expect(
            wrapper
                .find('[data-test="badgeforge-personal-delete-confirm"]')
                .exists(),
        ).toBe(true);
        expect(deleteMock).not.toHaveBeenCalled();

        await wrapper
            .find('[data-test="badgeforge-personal-delete-confirmed"]')
            .trigger('click');

        expect(deleteMock).toHaveBeenCalledTimes(1);
        expect(String(deleteMock.mock.calls[0]?.[0])).toBe(
            '/badgeforge/badges/01K0PERSONALBADGE0000000001',
        );
    });

    it('renomeia com PATCH e limita o nome pelo contrato', async () => {
        const wrapper = mountPanel(toolPropsFor());

        await wrapper
            .find('[data-test="badgeforge-personal-rename"]')
            .trigger('click');

        const input = wrapper.find(
            '[data-test="badgeforge-personal-rename-input"]',
        );

        expect(input.attributes('maxlength')).toBe('80');
        await input.setValue('Meu repo');

        await wrapper
            .find('[data-test="badgeforge-personal-rename-save"]')
            .trigger('click');

        expect(patchMock).toHaveBeenCalledTimes(1);
        expect(String(patchMock.mock.calls[0]?.[0])).toBe(
            '/badgeforge/badges/01K0PERSONALBADGE0000000001',
        );
        expect(forms[0]?.name).toBe('Meu repo');
    });

    it('troca o PAT de badge Docker repetindo o username', async () => {
        const dockerDefinition: BadgeForgePersonalBadge = {
            ...PRIVATE_BADGE,
            providerSlug: 'docker',
            definitionSlug: 'pulls',
            params: { namespace: 'library', repository: 'postgres' },
        };
        const wrapper = mountPanel(
            toolPropsFor({ personalBadges: [dockerDefinition] }),
        );

        await wrapper
            .find('[data-test="badgeforge-personal-credential"]')
            .trigger('click');

        const input = wrapper.find(
            '[data-test="badgeforge-personal-credential-input"]',
        );

        expect(input.attributes('type')).toBe('password');
        expect(input.attributes('autocomplete')).toBe('new-password');
        expect(
            wrapper
                .find('[data-test="badgeforge-personal-credential-username"]')
                .exists(),
        ).toBe(true);

        await input.setValue('ghp_secret');
        await wrapper
            .find('[data-test="badgeforge-personal-credential-username"]')
            .setValue('natanfiuza');
        await wrapper
            .find('[data-test="badgeforge-personal-credential-save"]')
            .trigger('click');

        expect(patchMock).toHaveBeenCalledTimes(1);
        expect(String(patchMock.mock.calls[0]?.[0])).toBe(
            '/badgeforge/badges/01K0PERSONALBADGE0000000001/credential',
        );

        const payload = (
            forms[1] as { transformCallback: () => Record<string, unknown> }
        ).transformCallback();

        expect(payload).toEqual({
            credential: 'ghp_secret',
            credential_username: 'natanfiuza',
        });
    });
});

describe('PersonalBadgeDialog', () => {
    it('Free ve o CTA de upgrade no lugar do formulario', async () => {
        mountDialog(toolPropsFor({ paid: false }));
        await flushPromises();

        expect(
            document.querySelector('[data-test="badge-personal-upgrade"]'),
        ).not.toBeNull();
        expect(
            document.querySelector('[data-test="badge-personal-form"]'),
        ).toBeNull();
        expect(document.body.innerHTML).toContain('PRECISA_PLANO_PAGO');
    });

    it('pago cria badge de provider sem fetch e sem cacheSeconds', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const props = toolPropsFor();
        const providerForm = {
            ...defaultProviderForm(props),
            style: 'flat-square',
            logo: 'github',
            logoSize: '12',
        };

        mountDialog(props, { providerForm });
        await flushPromises();

        const name = document.querySelector<HTMLInputElement>(
            '[data-test="badge-personal-name"]',
        );

        expect(name?.maxLength).toBe(80);

        if (name === null) {
            throw new Error('campo de nome nao renderizou');
        }

        name.value = 'Estrelas do repo';
        name.dispatchEvent(new Event('input'));

        document
            .querySelector<HTMLButtonElement>('[data-test="badge-personal-submit"]')
            ?.click();
        await flushPromises();

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(String(postMock.mock.calls[0]?.[0])).toBe('/badgeforge/badges');

        const payload = (
            forms[0] as { transformCallback: () => Record<string, unknown> }
        ).transformCallback();

        expect(payload).toEqual({
            name: 'Estrelas do repo',
            type: 'provider',
            provider_slug: 'github',
            definition_slug: 'stars',
            params: { owner: 'natanfiuza', repo: 'devtools' },
            private: false,
            credential: null,
            credential_username: null,
            form: {
                label: '',
                message: '',
                style: 'flat-square',
                font: 'default',
                color: '#9f9f9f',
                labelColor: '#555555',
                logo: 'github',
                logoColor: '#ffffff',
                logoSize: '12',
                logoPosition: 'left',
                link: '',
                cacheSeconds: '',
            },
        });
        expect(JSON.stringify(payload)).not.toContain('cacheSeconds":6');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('nao envia PAT quando o item e publico', async () => {
        mountDialog(toolPropsFor());
        await flushPromises();

        const name = document.querySelector<HTMLInputElement>(
            '[data-test="badge-personal-name"]',
        );

        if (name === null) {
            throw new Error('campo de nome nao renderizou');
        }

        name.value = 'Publica';
        name.dispatchEvent(new Event('input'));

        document
            .querySelector<HTMLButtonElement>('[data-test="badge-personal-submit"]')
            ?.click();
        await flushPromises();

        const payload = (
            forms[0] as { transformCallback: () => Record<string, unknown> }
        ).transformCallback();

        expect(payload.credential).toBeNull();
        expect(payload.credential_username).toBeNull();
    });

    it('PAT e write-only: password, vazio ao abrir e limpo ao fechar', async () => {
        const wrapper = mountDialog(toolPropsFor());
        await flushPromises();

        document
            .querySelector<HTMLButtonElement>(
                '[data-test="badge-personal-private-check"]',
            )
            ?.click();
        await flushPromises();

        const input = document.querySelector<HTMLInputElement>(
            '[data-test="badge-personal-credential"]',
        );

        expect(input?.type).toBe('password');
        expect(input?.autocomplete).toBe('new-password');
        expect(input?.value).toBe('');

        if (input === null) {
            throw new Error('campo de PAT nao renderizou');
        }

        input.value = 'ghp_secret';
        input.dispatchEvent(new Event('input'));
        await flushPromises();

        // O valor vive no input, nunca no HTML serializado.
        expect(document.body.innerHTML).not.toContain('ghp_secret');

        await wrapper.setProps({ open: false });
        await flushPromises();
        await wrapper.setProps({ open: true });
        await flushPromises();

        // Fechar limpa o segredo (e desmarca o privado); reabrir volta vazio.
        document
            .querySelector<HTMLButtonElement>(
                '[data-test="badge-personal-private-check"]',
            )
            ?.click();
        await flushPromises();

        expect(
            document.querySelector<HTMLInputElement>(
                '[data-test="badge-personal-credential"]',
            )?.value,
        ).toBe('');
    });

    it('Docker privado exige o username nao secreto', async () => {
        const props = toolPropsFor();
        const providerForm = {
            ...defaultProviderForm(props),
            provider: 'docker',
            definition: 'pulls',
            params: { namespace: 'library', repository: 'postgres' },
        };

        mountDialog(props, { providerForm });
        await flushPromises();

        document
            .querySelector<HTMLButtonElement>(
                '[data-test="badge-personal-private-check"]',
            )
            ?.click();
        await flushPromises();

        expect(
            document.querySelector(
                '[data-test="badge-personal-credential-username"]',
            ),
        ).not.toBeNull();
    });

    it('bloqueia o envio enquanto os params do provider sao invalidos', async () => {
        const props = toolPropsFor();
        const providerForm = {
            ...defaultProviderForm(props),
            params: { owner: '', repo: 'devtools' },
        };

        mountDialog(props, { providerForm });
        await flushPromises();

        expect(
            document.querySelector('[data-test="badge-personal-invalid"]'),
        ).not.toBeNull();
        expect(
            document.querySelector<HTMLButtonElement>(
                '[data-test="badge-personal-submit"]',
            )?.disabled,
        ).toBe(true);
    });
});
