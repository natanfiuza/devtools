import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { AnalyticsProps } from '@/types';

/**
 * Banner de consentimento e tag do GA4 (ADR-013).
 *
 * O teste monta o layout publico - e onde as duas pecas vivem juntas - com os
 * filhos pesados em stub: o que interessa aqui e o banner, o botao "Cookies"
 * do rodape e a tag no <head>.
 *
 * As traducoes sao marcadores, para qualquer texto fixo aparecer no lugar de um
 * marcador. O `lib/analytics` entra de verdade: o cookie e a tag sao o que se
 * verifica.
 */

const ctx = vi.hoisted(() => ({
    page: { props: {} as Record<string, unknown> },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ctx.page,
    router: { visit: vi.fn() },
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        render() {
            return h('a', { href: this.href }, this.$slots.default?.());
        },
    }),
}));

const traducoes = {
    app: {
        brand: 'MARCA',
        nav: {
            tools: 'NAV_FERRAMENTAS',
            pricing: 'NAV_PLANOS',
            styleguide: 'NAV_GUIA',
        },
        actions: {
            login: 'ACAO_ENTRAR',
            dashboard: 'ACAO_PAINEL',
            search: 'ACAO_BUSCAR',
            open_menu: 'ACAO_MENU',
            skip_to_content: 'ACAO_PULAR',
        },
        search: { trigger: 'BUSCAR' },
        theme: { label: 'TEMA' },
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
        footer: {
            language: 'IDIOMA',
            privacy: 'PRIVACIDADE',
            terms: 'TERMOS',
            cookies: 'COOKIES',
            rights: 'DIREITOS',
        },
    },
};

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
 * Monta o layout publico com as props compartilhadas do momento. O objeto de
 * props e novo a cada teste - a identidade dele e o sinal de "carregamento
 * novo" para o composable, como na resposta do Inertia.
 */
function montar(patch: Partial<AnalyticsProps> = {}): VueWrapper {
    ctx.page.props = {
        localeSlug: 'pt-br',
        auth: { user: null },
        analytics: propsDeAnalytics(patch),
    };

    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(PublicLayout, {
        global: {
            plugins: [i18n],
            stubs: {
                AppWordmark: true,
                CommandPalette: true,
                LocaleSwitcher: true,
                NewsletterForm: true,
                SearchTrigger: true,
                ThemeToggle: true,
            },
        },
    });
}

function banner(wrapper: VueWrapper) {
    return wrapper.find('[data-test="consent-banner"]');
}

function botao(wrapper: VueWrapper, nome: string) {
    return wrapper.get(`[data-test="${nome}"]`);
}

/** O interruptor da medicao, dentro dos detalhes. */
function interruptor(wrapper: VueWrapper) {
    return botao(wrapper, 'consent-analytics-toggle');
}

/** As tags do GA que estao no <head>. */
function tags(): Element[] {
    return Array.from(document.querySelectorAll('script[data-analytics]'));
}

async function clicar(wrapper: VueWrapper, nome: string): Promise<void> {
    await botao(wrapper, nome).trigger('click');
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

describe('banner', () => {
    it('aparece sem decisao, com os botoes, os detalhes e a politica', () => {
        const wrapper = montar();

        expect(banner(wrapper).exists()).toBe(true);
        expect(banner(wrapper).text()).toContain('TITULO_CONSENT');
        expect(banner(wrapper).text()).toContain('CORPO_CONSENT');
        expect(botao(wrapper, 'consent-accept').text()).toBe('ACEITAR');
        expect(botao(wrapper, 'consent-reject').text()).toBe('RECUSAR');

        const detalhes = botao(wrapper, 'consent-details');
        expect(detalhes.text()).toContain('DETALHES');
        expect(detalhes.text()).toContain('NECESSARIOS');
        expect(detalhes.text()).toContain('MEDICAO');
        expect(
            botao(wrapper, 'consent-privacy').attributes('href'),
        ).toBe('/pt-br/privacy');
    });

    it('nao aparece quando ja ha decisao', () => {
        for (const decisao of ['granted', 'denied'] as const) {
            const wrapper = montar({ consent: decisao });

            expect(banner(wrapper).exists()).toBe(false);
        }
    });

    it('nao tem controle nos cookies necessarios', () => {
        const wrapper = montar();

        // "Necessarios" e o que faz o site funcionar: so texto, sem interruptor.
        const necessarios = botao(wrapper, 'consent-necessary');

        expect(
            necessarios.findAll('button, input, [role="checkbox"]'),
        ).toHaveLength(0);
        expect(necessarios.text()).toContain('CORPO_NECESSARIOS');
    });
});

describe('decisao', () => {
    it('aceitar grava o cookie, injeta a tag uma unica vez e poe o padrao negado na frente', async () => {
        const wrapper = montar();

        await clicar(wrapper, 'consent-accept');

        expect(document.cookie).toContain('consent=granted');
        expect(tags()).toHaveLength(1);
        expect(tags()[0]?.getAttribute('src')).toContain('id=G-TESTE123');

        // Consent Mode: o padrao negado e o primeiro comando, e so o aceite
        // libera a medicao. Publicidade fica negada nos dois.
        expect(window.dataLayer?.[0]).toEqual([
            'consent',
            'default',
            {
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                analytics_storage: 'denied',
            },
        ]);
        expect(window.dataLayer?.[1]).toEqual([
            'consent',
            'update',
            { analytics_storage: 'granted' },
        ]);

        // O "Nada de anuncios" do banner nao fica so por conta do Consent Mode:
        // os sinais de publicidade vao desligados no proprio config (ADR-032).
        expect(window.dataLayer?.[3]).toEqual([
            'config',
            'G-TESTE123',
            {
                allow_google_signals: false,
                allow_ad_personalization_signals: false,
            },
        ]);

        // O banner sai da tela com a decisao tomada.
        expect(banner(wrapper).exists()).toBe(false);

        // Aceitar de novo (o rodape reabre) nao cria uma segunda tag.
        await clicar(wrapper, 'consent-reopen');
        await clicar(wrapper, 'consent-reject');
        await clicar(wrapper, 'consent-reopen');
        await clicar(wrapper, 'consent-accept');

        expect(tags()).toHaveLength(1);
    });

    it('recusar grava o cookie e nao injeta nada', async () => {
        const wrapper = montar();

        await clicar(wrapper, 'consent-reject');

        expect(document.cookie).toContain('consent=denied');
        expect(tags()).toHaveLength(0);
        expect(window.dataLayer).toBeUndefined();
    });

    it('recusar depois do aceite para a medicao pelo Consent Mode', async () => {
        const wrapper = montar();

        await clicar(wrapper, 'consent-accept');
        expect(tags()).toHaveLength(1);

        await clicar(wrapper, 'consent-reopen');
        await clicar(wrapper, 'consent-reject');

        expect(document.cookie).toContain('consent=denied');
        // O gtag.js ja rodou: quem para o envio e o update negado.
        expect(window.dataLayer).toContainEqual([
            'consent',
            'update',
            { analytics_storage: 'denied' },
        ]);
    });

    it('aceitar de novo depois de recusar religa a medicao sem segunda tag', async () => {
        const wrapper = montar();

        await clicar(wrapper, 'consent-accept');
        await clicar(wrapper, 'consent-reopen');
        await clicar(wrapper, 'consent-reject');
        await clicar(wrapper, 'consent-reopen');
        await clicar(wrapper, 'consent-accept');

        expect(document.cookie).toContain('consent=granted');
        expect(tags()).toHaveLength(1);

        // A ultima decisao e a que vale (ADR-013): a recusa parou a medicao
        // pelo Consent Mode e o aceite de novo a religa, sem tag nova.
        const commands = window.dataLayer ?? [];

        expect(commands[commands.length - 1]).toEqual([
            'consent',
            'update',
            { analytics_storage: 'granted' },
        ]);
    });

    it('sem id configurado nao injeta tag nem com aceite', async () => {
        const wrapper = montar({ ga4Id: null });

        await clicar(wrapper, 'consent-accept');

        expect(document.cookie).toContain('consent=granted');
        expect(tags()).toHaveLength(0);
    });

    it('o interruptor da medicao reflete a decisao e escreve a mesma', async () => {
        const wrapper = montar();

        // Sem resposta, o interruptor fica indefinido.
        expect(interruptor(wrapper).attributes('data-state')).toBe(
            'indeterminate',
        );

        // Aceitar pelo botao ja e a decisao: o banner sai da tela.
        await clicar(wrapper, 'consent-accept');
        expect(banner(wrapper).exists()).toBe(false);

        // Reaberto, o interruptor mostra o que esta valendo.
        await clicar(wrapper, 'consent-reopen');
        expect(interruptor(wrapper).attributes('data-state')).toBe('checked');

        // Desligar o interruptor e a decisao - nao ha "salvar" separado.
        await clicar(wrapper, 'consent-analytics-toggle');
        expect(document.cookie).toContain('consent=denied');
        expect(banner(wrapper).exists()).toBe(false);

        await clicar(wrapper, 'consent-reopen');
        expect(interruptor(wrapper).attributes('data-state')).toBe('unchecked');
    });
});

describe('reabrir', () => {
    it('o botao "Cookies" do rodape traz o banner de volta', async () => {
        const wrapper = montar({ consent: 'granted' });

        expect(banner(wrapper).exists()).toBe(false);
        expect(botao(wrapper, 'consent-reopen').text()).toBe('COOKIES');

        await clicar(wrapper, 'consent-reopen');

        expect(banner(wrapper).exists()).toBe(true);
    });
});
