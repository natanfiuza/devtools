import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import PublicProfile from '@/pages/colors/PublicProfile.vue';
import type { ProfilePaletteRow, PublicProfileProps } from '@/types/colors';

/**
 * Perfil publico por username (F6, ADR-134).
 *
 * O teste cobre a vitrine: nome, contadores e paletas publicas; o proprio
 * perfil nao oferece seguir; o visitante sem conta tambem nao ve o botao; e a
 * paginacao e do servidor, preservando a rota do perfil.
 */

const { routerMock, pageState, fetchMock } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    pageState: {
        url: '/en/u/ana',
        props: {
            localeSlug: 'en',
            auth: { user: null as { id: number } | null },
            seo: { meta: { title: 'Paletas de Ana' } },
        },
    },
    fetchMock: vi.fn(),
}));

vi.stubGlobal('fetch', fetchMock);

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
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
}));

const TRADUCOES = {
    colors: {
        profile: {
            own_badge: 'SEU_PERFIL',
            following_zero: 'NAO_SEGUE',
            following_one: 'SEGUINDO_1',
            following_many: 'SEGUINDO {count}',
            member_since: 'MEMBRO {date}',
            palettes_title: 'PALETAS_PUBLICAS',
            empty_title: 'VAZIO',
            empty_description: 'VAZIO_DETALHE',
        },
        social: {
            like: 'CURTIR',
            liked: 'CURTIDA',
            like_error: 'ERRO_CURTIDA',
            likes_zero: 'SEM_CURTIDAS',
            likes_one: '1_CURTIDA',
            likes_many: '{count}_CURTIDAS',
            follow: 'SEGUIR',
            following: 'SEGUINDO',
            follow_error: 'ERRO_SEGUIR',
            followers_zero: 'SEM_SEGUIDORES',
            followers_one: '1_SEGUIDOR',
            followers_many: '{count}_SEGUIDORES',
        },
        list: {
            kind_scale: 'ESCALA',
            kind_extracted: 'DA_IMAGEM',
            colors_count: '{count} CORES',
            updated_at: 'ATUALIZADA {date}',
            sort_updated_at: 'ATUALIZACAO',
            preview_alt: 'PREVIA DE {name}',
            results: '{from}-{to} DE {total}',
        },
        pagination: {
            previous: 'ANTERIOR',
            next: 'PROXIMA',
            page: 'PAGINA {page} DE {last}',
        },
    },
};

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: TRADUCOES,
    });

    return instance;
}

function row(overrides: Partial<ProfilePaletteRow> = {}): ProfilePaletteRow {
    return {
        id: '01KPALETTE00000000000000001',
        name: 'Oceano',
        description: 'Azuis profundos',
        kind: 'scale',
        visibility: 'public',
        colors_count: 11,
        duplications_count: 0,
        archived: false,
        slug: 'oceano',
        preview: ['#111111', '#222222', '#333333'],
        tags: [],
        updated_at: '2026-11-10T12:00:00+00:00',
        likes_count: 4,
        ...overrides,
    };
}

function props(
    overrides: Partial<PublicProfileProps> = {},
): PublicProfileProps {
    return {
        profile: {
            id: 42,
            name: 'Ana',
            username: 'ana',
            avatar: null,
            followers_count: 2,
            following_count: 3,
            member_since: '2026-11-10T12:00:00+00:00',
        },
        palettes: {
            data: [row()],
            current_page: 1,
            last_page: 1,
            per_page: 12,
            total: 1,
            from: 1,
            to: 1,
        },
        following: false,
        canFollow: false,
        isOwnProfile: false,
        ...overrides,
    };
}

function mountPage(overrides: Partial<PublicProfileProps> = {}) {
    return mount(PublicProfile, {
        props: props(overrides),
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });
}

function query<T extends HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

function exists(selector: string): boolean {
    return document.querySelector(selector) !== null;
}

describe('Perfil publico', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        fetchMock.mockReset();
        pageState.url = '/en/u/ana';
        pageState.props.auth = { user: null };
        document.body.innerHTML = '';
    });

    it('mostra nome, username, contadores e desde quando a conta existe', () => {
        mountPage();

        expect(query('[data-test="profile-name"]').textContent).toContain(
            'Ana',
        );
        expect(query('[data-test="profile-username"]').textContent).toContain(
            '@ana',
        );
        expect(
            query('[data-test="profile-following-count"]').textContent,
        ).toContain('SEGUINDO 3');
        expect(
            query('[data-test="profile-member-since"]').textContent,
        ).toContain('MEMBRO');
        expect(exists('[data-test="profile-own-badge"]')).toBe(false);
    });

    it('mostra as paletas publicas com link e curtidas', () => {
        mountPage();

        expect(
            query('[data-test="profile-palette-name"]').textContent,
        ).toContain('Oceano');
        expect(
            query<HTMLAnchorElement>(
                '[data-test="profile-palette-name"]',
            ).getAttribute('href'),
        ).toBe('/en/colors/p/oceano');
        expect(
            query('[data-test="profile-palette-likes"]').textContent,
        ).toContain('4_CURTIDAS');
        expect(
            query('[data-test="profile-palette-preview"]').children,
        ).toHaveLength(3);
    });

    it('avisa quando o perfil ainda nao publicou nada', () => {
        mountPage({
            palettes: {
                data: [],
                current_page: 1,
                last_page: 1,
                per_page: 12,
                total: 0,
                from: null,
                to: null,
            },
        });

        expect(exists('[data-test="profile-empty"]')).toBe(true);
        expect(exists('[data-test="profile-palette-list"]')).toBe(false);
    });

    it('marca o proprio perfil e nao oferece seguir', () => {
        pageState.props.auth = { user: { id: 42 } };

        mountPage({ isOwnProfile: true, canFollow: false });

        expect(exists('[data-test="profile-own-badge"]')).toBe(true);
        expect(exists('[data-test="follow-button"]')).toBe(false);
    });

    it('o visitante sem conta ve a contagem, sem o botao de seguir', () => {
        mountPage({ canFollow: false });

        expect(exists('[data-test="follow-button"]')).toBe(false);
    });

    it('segue a conta e atualiza os seguidores no botao', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({ following: true, followers_count: 3 }),
        });

        mountPage({ canFollow: true });

        expect(query('[data-test="followers-count"]').textContent).toContain(
            '2_SEGUIDORES',
        );
        expect(query('[data-test="follow-button"]').textContent).toContain(
            'SEGUIR',
        );

        query<HTMLButtonElement>('[data-test="follow-button"]').click();
        await flushPromises();

        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/api/tools/colors/users/42/follow',
        );
        expect(query('[data-test="follow-button"]').textContent).toContain(
            'SEGUINDO',
        );
        expect(query('[data-test="followers-count"]').textContent).toContain(
            '3_SEGUIDORES',
        );
    });

    it('pede a proxima pagina ao servidor preservando a rota', () => {
        mountPage({
            palettes: {
                data: [row()],
                current_page: 1,
                last_page: 2,
                per_page: 1,
                total: 2,
                from: 1,
                to: 1,
            },
        });

        query<HTMLButtonElement>('[data-test="palette-page-next"]').click();

        expect(routerMock.get).toHaveBeenCalledTimes(1);

        const call = routerMock.get.mock.calls[0] as [
            string,
            { page: number },
            { preserveScroll: boolean; preserveState: boolean },
        ];

        expect(call[0]).toBe('/en/u/ana');
        expect(call[1]).toEqual({ page: 2 });
        expect(call[2]).toMatchObject({
            preserveScroll: true,
            preserveState: true,
        });
    });
});
