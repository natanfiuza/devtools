import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import NotificationBell from '@/components/notifications/NotificationBell.vue';

/**
 * Sino da area da conta (F7, ADR-142).
 *
 * O contador vem nas props compartilhadas do Inertia - nada de polling. Aqui
 * provamos o que a tela desenha: o numero, o teto, o rotulo acessivel e o
 * destino do clique.
 */

const { pageState } = vi.hoisted(() => ({
    pageState: {
        url: '/dashboard',
        props: {
            notifications: { unread: 0 } as { unread: number } | null,
        },
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => pageState,
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
    notifications: {
        bell: {
            label: 'SINO',
            unread: 'NAO_LIDAS {count}',
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

function mountBell(unread: number | null) {
    pageState.props.notifications = unread === null ? null : { unread };

    return mount(NotificationBell, {
        global: { plugins: [i18n()] },
    });
}

describe('Sino de notificacoes', () => {
    beforeEach(() => {
        pageState.props.notifications = { unread: 0 };
    });

    it('mostra o contador e leva a pagina de notificacoes', () => {
        const wrapper = mountBell(3);
        const link = wrapper.get('[data-test="notification-bell"]');

        expect(link.attributes('href')).toBe('/dashboard/notifications');
        expect(link.attributes('aria-label')).toBe('NAO_LIDAS 3');
        expect(
            wrapper.get('[data-test="notification-bell-badge"]').text(),
        ).toBe('3');
    });

    it('nao mostra o selo quando nao ha nada nao lido', () => {
        const wrapper = mountBell(0);

        expect(
            wrapper.find('[data-test="notification-bell-badge"]').exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-test="notification-bell"]')
                .attributes('aria-label'),
        ).toBe('SINO');
    });

    it('limita o selo em 99+', () => {
        const wrapper = mountBell(120);

        expect(
            wrapper.get('[data-test="notification-bell-badge"]').text(),
        ).toBe('99+');
    });

    it('trata visitante sem o prop compartilhado', () => {
        const wrapper = mountBell(null);

        expect(
            wrapper.find('[data-test="notification-bell-badge"]').exists(),
        ).toBe(false);
    });
});
