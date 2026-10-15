import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import AccountLayout from '@/layouts/account/AccountLayout.vue';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import type { AnalyticsProps } from '@/types';

/**
 * Onde a medicao esta montada (ADR-032).
 *
 * A conta e medida como o site: o banner e a tag sobem junto com o layout. O
 * painel administrativo fica de fora - sem banner e sem tag, nem com aceite.
 *
 * As traducoes sao marcadores, para qualquer texto fixo aparecer no lugar de um
 * marcador. O `lib/analytics` entra de verdade: a tag no <head> e o que se
 * verifica.
 */

type FakePage = { url: string; props: Record<string, unknown> };

/**
 * A pagina falsa do Inertia. Precisa ser reativa e escrita pela proxy: os
 * layouts leem a URL por dentro de um computed de modulo (useCurrentUrl).
 */
const { fakePage } = vi.hoisted(() => ({
    fakePage: { current: null } as { current: FakePage | null },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    fakePage.current = reactive<FakePage>({ url: '/dashboard', props: {} });

    return {
        usePage: () => fakePage.current,
        router: {
            get: vi.fn(),
            post: vi.fn(),
            delete: vi.fn(),
            patch: vi.fn(),
        },
        Head: defineComponent({
            name: 'InertiaHead',
            props: { title: { type: String, default: '' } },
            render: () => h('div'),
        }),
        Link: defineComponent({
            name: 'InertiaLink',
            props: { href: { type: String, default: '' } },
            render() {
                return h('a', { href: this.href }, this.$slots.default?.());
            },
        }),
    };
});

// O menu do usuario e os controles de tema/idioma nao fazem parte do que se
// testa aqui, e trazem junto o popover e os cookies.
vi.mock('@/components/UserMenuContent.vue', () => ({
    default: defineComponent({
        name: 'UserMenuContent',
        render: () => h('div'),
    }),
}));

vi.mock('@/components/LocaleSwitcher.vue', () => ({
    default: defineComponent({ name: 'LocaleSwitcher', render: () => h('div') }),
}));

vi.mock('@/components/ThemeToggle.vue', () => ({
    default: defineComponent({ name: 'ThemeToggle', render: () => h('div') }),
}));

vi.mock('@/components/ui/sonner', () => ({
    Toaster: defineComponent({ name: 'Toaster', render: () => h('div') }),
}));

const traducoes = {
    app: {
        consent: {
            title: 'TITULO_CONSENT',
            body: 'CORPO_CONSENT',
            accept: 'ACEITAR',
            reject: 'RECUSAR',
            details: 'DETALHES',
            necessary: 'NECESSARIOS',
            necessary_body: 'CORPO_NECESSARIOS',
            analytics: 'MEDICAO',
            analytics_body: 'CORPO_MEDICAO',
            privacy: 'POLITICA',
        },
    },
    account: {
        nav: {
            menu: 'AREA_DA_CONTA',
            settings: 'CONFIGURACOES',
            overview: 'VISAO_GERAL',
            history: 'HISTORICO',
            billing: 'FATURAS',
        },
    },
    settings: {
        nav: {
            profile: 'PERFIL',
            security: 'SEGURANCA',
            appearance: 'APARENCIA',
        },
    },
    admin: {
        nav: {
            overview: 'VISAO_GERAL',
            users: 'USUARIOS',
            subscribers: 'ASSINANTES',
            plans: 'PLANOS',
            payments: 'PAGAMENTOS',
            audit: 'AUDITORIA',
            panel: 'PAINEL',
            back_to_site: 'VOLTAR_AO_SITE',
        },
    },
};

function i18nDoTeste() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return i18n;
}

function propsDeAnalytics(patch: Partial<AnalyticsProps> = {}): AnalyticsProps {
    return {
        ga4Id: 'G-TESTE123',
        consent: null,
        cookie: 'consent',
        days: 180,
        ...patch,
    };
}

/**
 * Abre uma tela com a decisao do momento nas props compartilhadas. O objeto de
 * props e novo a cada teste - a identidade dele e o sinal de "carregamento
 * novo" para o composable, como na resposta do Inertia.
 */
function abrir(url: string, patch: Partial<AnalyticsProps> = {}): void {
    if (fakePage.current !== null) {
        fakePage.current.url = url;
        fakePage.current.props = {
            localeSlug: 'pt-br',
            sidebarOpen: true,
            auth: { user: { id: 1, name: 'Ana Souza', email: 'ana@exemplo.com' } },
            analytics: propsDeAnalytics(patch),
        };
    }
}

function montarConta(patch: Partial<AnalyticsProps> = {}): VueWrapper {
    abrir('/dashboard', patch);

    return mount(AccountLayout, { global: { plugins: [i18nDoTeste()] } });
}

function montarPainel(patch: Partial<AnalyticsProps> = {}): VueWrapper {
    abrir('/admin', patch);

    return mount(AdminLayout, { global: { plugins: [i18nDoTeste()] } });
}

/** As tags do GA que estao no <head>. */
function tags(): Element[] {
    return Array.from(document.querySelectorAll('script[data-analytics]'));
}

async function aceitar(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('[data-test="consent-accept"]').trigger('click');
    await nextTick();
}

function limparCookies(): void {
    for (const parte of document.cookie.split(';')) {
        const nome = parte.split('=')[0]?.trim();

        if (nome !== undefined && nome !== '') {
            document.cookie = `${nome}=;path=/;max-age=0`;
        }
    }
}

beforeEach(() => {
    limparCookies();
    delete window.dataLayer;
});

afterEach(() => {
    for (const tag of tags()) {
        tag.remove();
    }
});

describe('area da conta', () => {
    it('mostra o banner para quem ainda nao decidiu e nao injeta tag', () => {
        const wrapper = montarConta();

        expect(wrapper.find('[data-test="consent-banner"]').exists()).toBe(true);
        expect(tags()).toHaveLength(0);
    });

    it('injeta a tag depois do aceite', async () => {
        const wrapper = montarConta();

        await aceitar(wrapper);

        expect(document.cookie).toContain('consent=granted');
        expect(tags()).toHaveLength(1);
        expect(tags()[0]?.getAttribute('src')).toContain('id=G-TESTE123');
    });

    it('nao injeta nada com a decisao ja recusada', () => {
        const wrapper = montarConta({ consent: 'denied' });

        expect(wrapper.find('[data-test="consent-banner"]').exists()).toBe(false);
        expect(tags()).toHaveLength(0);
    });
});

describe('painel administrativo', () => {
    it('nao tem banner nem tag, nem com o aceite ja gravado', () => {
        const wrapper = montarPainel({ consent: 'granted' });

        expect(wrapper.find('[data-test="consent-banner"]').exists()).toBe(false);
        expect(tags()).toHaveLength(0);
        expect(window.dataLayer).toBeUndefined();
    });
});
