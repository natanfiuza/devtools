import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Notifications from '@/pages/dashboard/Notifications.vue';
import type { AccountNotification, NotificationPreferences } from '@/types';

/**
 * Pagina do sino e das preferencias (F7, ADR-142).
 *
 * Aqui se prova a fiacao: o texto do fato, o selo de nao lido, marcar uma e
 * todas, a pagina seguinte e os quatro toggles com escrita otimista que volta
 * na falha. O anonimo nunca aparece como nome de conta.
 */

const { routerMock, pageState } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    pageState: {
        url: '/dashboard/notifications',
        props: { localeSlug: 'en' },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
}));

const TRADUCOES = {
    notifications: {
        title: 'NOTIFICACOES',
        description: 'DESCRICAO',
        empty: 'VAZIO',
        mark_read: 'MARCAR_LIDA',
        mark_all_read: 'MARCAR_TODAS',
        unread_badge: 'NOVA',
        someone: 'ALGUEM',
        kinds: {
            palette_liked: 'CURTIU {actor} {palette}',
            palette_liked_anonymous: 'ANONIMO {palette}',
            user_followed: 'SEGUIU {actor}',
            palette_comment_approved: 'COMENTOU {actor} {palette}',
            comment_reply_approved: 'RESPONDEU {actor} {palette}',
            unknown: 'DESCONHECIDA',
        },
        preferences: {
            title: 'PREFERENCIAS',
            description: 'DESCRICAO_PREFERENCIAS',
            email_on_like: 'EMAIL_CURTIDA',
            email_on_follow: 'EMAIL_SEGUIDOR',
            email_on_comment: 'EMAIL_COMENTARIO',
            email_on_reply: 'EMAIL_RESPOSTA',
            saved: 'SALVO',
        },
        pagination: {
            previous: 'ANTERIOR',
            next: 'PROXIMA',
            status: 'PAGINA {page} DE {pages}',
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

function notification(
    overrides: Partial<AccountNotification> = {},
): AccountNotification {
    return {
        id: 'n-1',
        kind: 'palette_liked',
        read: false,
        created_at: '2026-11-15 10:00',
        data: {
            palette_id: 'pal-1',
            palette_name: 'Oceano',
            palette_slug: 'oceano',
            comment_id: null,
            actor_name: 'Ana',
            actor_username: 'ana',
            anonymous: false,
            follower_name: null,
            follower_username: null,
        },
        ...overrides,
    };
}

const PREFERENCIAS: NotificationPreferences = {
    email_on_like: false,
    email_on_follow: false,
    email_on_comment: true,
    email_on_reply: true,
};

function mountPage(
    data: AccountNotification[] = [notification()],
    preferences: NotificationPreferences = PREFERENCIAS,
    lastPage = 1,
) {
    return mount(Notifications, {
        props: {
            notifications: {
                data,
                current_page: 1,
                last_page: lastPage,
                per_page: 20,
                total: data.length,
                from: data.length === 0 ? null : 1,
                to: data.length === 0 ? null : data.length,
            },
            preferences,
        },
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });
}

describe('Pagina de notificacoes', () => {
    beforeEach(() => {
        routerMock.get.mockClear();
        routerMock.post.mockClear();
        routerMock.patch.mockClear();
    });

    it('lista o fato e marca uma notificacao como lida', async () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('CURTIU Ana Oceano');
        expect(wrapper.find('[data-test="notification-unread"]').exists()).toBe(
            true,
        );

        await wrapper
            .get('[data-test="notification-mark-read"]')
            .trigger('click');

        expect(routerMock.post).toHaveBeenCalledWith(
            '/dashboard/notifications/n-1/read',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('marca todas como lidas', async () => {
        const wrapper = mountPage();

        await wrapper
            .get('[data-test="notifications-mark-all"]')
            .trigger('click');

        expect(routerMock.post).toHaveBeenCalledWith(
            '/dashboard/notifications/read-all',
            {},
            expect.anything(),
        );
    });

    it('nunca apresenta o anonimo como nome de conta', () => {
        const wrapper = mountPage([
            notification({
                data: {
                    ...notification().data,
                    actor_name: null,
                    actor_username: null,
                    anonymous: true,
                },
            }),
        ]);

        expect(wrapper.text()).toContain('ANONIMO Oceano');
        expect(
            wrapper.find('[data-test="notification-actor-link"]').exists(),
        ).toBe(false);
    });

    it('nao mostra acoes quando a lista esta vazia', () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[data-test="notifications-empty"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-test="notifications-mark-all"]').exists(),
        ).toBe(false);
    });

    it('leva a pagina seguinte quando existe paginacao', async () => {
        const wrapper = mountPage([notification()], PREFERENCIAS, 3);

        await wrapper
            .get('[data-test="notifications-page-next"]')
            .trigger('click');

        expect(routerMock.get).toHaveBeenCalledWith(
            '/dashboard/notifications?page=2',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('salva a preferencia com os quatro valores e confirma', async () => {
        const wrapper = mountPage();

        await wrapper
            .get('[data-test="notification-preference-email_on_like"]')
            .trigger('click');

        expect(routerMock.patch).toHaveBeenCalledWith(
            '/dashboard/notifications/preferences',
            {
                email_on_like: true,
                email_on_follow: false,
                email_on_comment: true,
                email_on_reply: true,
            },
            expect.objectContaining({ preserveScroll: true }),
        );

        const options = routerMock.patch.mock.calls[0]?.[2] as {
            onSuccess: () => void;
        };
        options.onSuccess();

        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .find('[data-test="notification-preferences-saved"]')
                .exists(),
        ).toBe(true);
    });

    it('desfaz o toggle quando o servidor recusa', async () => {
        const wrapper = mountPage();
        const checkbox = wrapper.get(
            '[data-test="notification-preference-email_on_like"]',
        );

        expect(checkbox.attributes('aria-checked')).toBe('false');

        await checkbox.trigger('click');

        expect(
            wrapper
                .get('[data-test="notification-preference-email_on_like"]')
                .attributes('aria-checked'),
        ).toBe('true');

        const options = routerMock.patch.mock.calls[0]?.[2] as {
            onError: () => void;
        };
        options.onError();

        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .get('[data-test="notification-preference-email_on_like"]')
                .attributes('aria-checked'),
        ).toBe('false');
    });
});
