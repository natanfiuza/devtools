import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import AccountLayout from '@/layouts/account/AccountLayout.vue';
import Billing from '@/pages/dashboard/Billing.vue';
import History from '@/pages/dashboard/History.vue';
import type {
    AccountBillingProps,
    AccountHistoryItem,
    AccountHistoryProps,
    AccountPaymentRow,
    AccountPlan,
    AccountSubscription,
    Paginator,
} from '@/types';

/**
 * Painel do usuario: a navegacao unica da conta e as telas que ainda nao tem o
 * que mostrar.
 *
 * Mesma regra do painel administrativo: as traducoes sao marcadores (para
 * denunciar texto fixo no componente) e as rotas sao as de verdade, geradas
 * pelo wayfinder - o que se prova aqui e o contrato de para onde a tela aponta.
 */

type FakePage = { url: string; props: Record<string, unknown> };

/**
 * A pagina falsa do Inertia. Precisa ser reativa e escrita pela proxy: o
 * `useCurrentUrl` guarda um computed de modulo, que so se recalcula quando a
 * URL muda por dentro dele.
 */
const { fakePage } = vi.hoisted(() => ({
    fakePage: { current: null } as { current: FakePage | null },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    fakePage.current = reactive<FakePage>({
        url: '/dashboard',
        props: {
            localeSlug: 'pt-br',
            locale: 'pt_BR',
            // A sidebar le `sidebarOpen` no `AppShell` e o usuario no `NavUser`.
            sidebarOpen: true,
            auth: {
                user: { id: 1, name: 'Ana Souza', email: 'ana@exemplo.com' },
            },
        },
    });

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

/** Abre uma URL da conta, como o Inertia faria. */
function abrir(url: string): void {
    if (fakePage.current !== null) {
        fakePage.current.url = url;
    }
}

// O menu do usuario nao faz parte do que se testa aqui e traz junto o popover.
vi.mock('@/components/UserMenuContent.vue', () => ({
    default: defineComponent({
        name: 'UserMenuContent',
        render: () => h('div'),
    }),
}));

vi.mock('@/components/ui/sonner', () => ({
    Toaster: defineComponent({ name: 'Toaster', render: () => h('div') }),
}));

const traducoes = {
    account: {
        nav: {
            menu: 'AREA_DA_CONTA',
            settings: 'CONFIGURACOES',
            overview: 'VISAO_GERAL',
            history: 'HISTORICO',
            billing: 'FATURAS',
            back_to_site: 'VOLTAR_AO_SITE',
        },
        history: {
            title: 'TITULO_HISTORICO',
            description: 'DESCRICAO_HISTORICO',
            empty_title: 'NADA_POR_AQUI',
            empty: 'DICA_HISTORICO',
            table: {
                date: 'DATA',
                tool: 'FERRAMENTA',
                dialect: 'DIALETO',
                usage: 'CONSUMO',
                cache: 'CACHE',
                cache_hit: 'HIT',
                cache_miss: 'CHAMADA_IA',
                status: 'SITUACAO',
                actions: 'ACOES',
                view_details: 'VER_DETALHES',
            },
            statuses: {
                done: 'CONCLUIDO',
                processing: 'PROCESSANDO',
                queued: 'NA_FILA',
                failed: 'FALHOU',
            },
            modal: {
                title: 'TITULO_MODAL',
                description: 'DESCRICAO_MODAL',
                sql: 'SQL_GERADO',
                summary: 'RESUMO_MODAL',
                copy_sql: 'COPIAR_SQL',
                copied: 'COPIADO',
                close: 'FECHAR',
                error: 'CODIGO_ERRO',
                tokens_in: 'TOKENS_IN',
                tokens_out: 'TOKENS_OUT',
            },
            total: 'TOTAL {total}',
            page_of: 'PAGINA {page} DE {last}',
            previous: 'ANTERIOR',
            next: 'PROXIMA',
        },
        billing: {
            title: 'TITULO_FATURAS',
            description: 'DESCRICAO_FATURAS',
            subscription: 'ASSINATURA',
            plan: 'PLANO',
            period: 'PERIODO',
            status: 'SITUACAO',
            gateway: 'COBRANCA',
            gateway_manual: 'COBRANCA_MANUAL',
            no_subscription: 'PLANO_GRATUITO',
            free_hint: 'DICA_FREE',
            no_period: 'SEM_PERIODO',
            pending_intent: {
                badge: 'EM_ANALISE',
                title: 'INTENCAO_EM_ANALISE',
                description:
                    'SUA INTENCAO DO PLANO {plan} EM {date} ESTA EM ANALISE',
                view_details: 'VER_PEDIDO',
            },
            subscription_statuses: {
                active: 'ATIVA',
                canceled: 'ENCERRADA',
            },
            payments: 'PAGAMENTOS',
            amount: 'VALOR',
            method: 'FORMA',
            paid_at: 'PAGO_EM',
            note: 'OBSERVACAO',
            empty: 'NENHUM_PAGAMENTO',
            total: 'TOTAL {total}',
            page_of: 'PAGINA {page} DE {last}',
            previous: 'ANTERIOR',
            next: 'PROXIMA',
            statuses: { paid: 'PAGO', refunded: 'ESTORNADO' },
            methods: {
                pix: 'PIX',
                transfer: 'TRANSFERENCIA',
                cash: 'DINHEIRO',
                other: 'OUTRO',
            },
        },
    },
    settings: {
        nav: {
            profile: 'PERFIL',
            security: 'SEGURANCA',
            appearance: 'APARENCIA',
        },
    },
};

function i18nDoTeste() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return i18n;
}

/** Monta o layout da conta, como o Inertia faz ao abrir uma tela autenticada. */
function montarConta(): VueWrapper {
    return mount(AccountLayout, { global: { plugins: [i18nDoTeste()] } });
}

/** Os grupos da navegacao lateral, na ordem em que aparecem. */
function grupos(wrapper: VueWrapper) {
    return wrapper.findAll('[data-slot="sidebar-group"]');
}

/** O item marcado como aberto. A regra e haver sempre exatamente um. */
function itemAberto(wrapper: VueWrapper) {
    return wrapper
        .findAll('a')
        .filter((link) => link.attributes('data-active') === 'true');
}

beforeEach(() => {
    abrir('/dashboard');
});

describe('navegacao da conta', () => {
    it('junta a conta inteira numa navegacao so, em dois grupos', () => {
        const wrapper = montarConta();
        const secoes = grupos(wrapper);

        expect(
            secoes.map((grupo) =>
                grupo.get('[data-slot="sidebar-group-label"]').text(),
            ),
        ).toEqual(['AREA_DA_CONTA', 'CONFIGURACOES']);

        expect(
            secoes.map((grupo) => grupo.findAll('a').map((a) => a.text())),
        ).toEqual([
            ['VISAO_GERAL', 'HISTORICO', 'FATURAS'],
            ['PERFIL', 'SEGURANCA', 'APARENCIA'],
        ]);

        // As URLs de settings continuam as mesmas do starter kit.
        expect(
            secoes.flatMap((grupo) =>
                grupo.findAll('a').map((a) => a.attributes('href')),
            ),
        ).toEqual([
            '/dashboard',
            '/dashboard/history',
            '/dashboard/billing',
            '/settings/profile',
            '/settings/security',
            '/settings/appearance',
        ]);
    });

    it('leva ao painel pelo logo e de volta ao site pelo rodape', () => {
        const wrapper = montarConta();

        expect(
            wrapper.get('[data-slot="sidebar-header"] a').attributes('href'),
        ).toBe('/dashboard');
        expect(
            wrapper
                .findAll('a')
                .find((a) => a.text().includes('VOLTAR_AO_SITE'))
                ?.attributes('href'),
        ).toBe('/pt-br');
    });

    const TELAS: [string, string][] = [
        ['/dashboard', 'VISAO_GERAL'],
        ['/dashboard/history', 'HISTORICO'],
        ['/dashboard/billing', 'FATURAS'],
        ['/settings/profile', 'PERFIL'],
        ['/settings/security', 'SEGURANCA'],
        ['/settings/appearance', 'APARENCIA'],
    ];

    it.each(TELAS)('marca so a secao aberta em %s', (url, esperado) => {
        abrir(url);

        const wrapper = montarConta();
        const abertos = itemAberto(wrapper);

        expect(abertos).toHaveLength(1);
        expect(abertos[0]?.text()).toBe(esperado);
        expect(wrapper.get('h1').text()).toBe(esperado);
    });

    it('nao confunde a visao geral com as telas filhas dela', () => {
        // `/dashboard` e prefixo de `/dashboard/history`: no menu, o item aberto
        // e o da tela (comparacao exata), nao o da raiz.
        abrir('/dashboard/history');

        const wrapper = montarConta();

        expect(itemAberto(wrapper).map((link) => link.text())).toEqual([
            'HISTORICO',
        ]);
        expect(
            grupos(wrapper)[0]
                ?.findAll('a')
                .map((a) => a.text()),
        ).toEqual(['VISAO_GERAL', 'HISTORICO', 'FATURAS']);
    });

    it('mantem as faturas abertas quando a lista muda de pagina', () => {
        // A paginacao vive na query string: ela nao e outra secao.
        abrir('/dashboard/billing?page=2');

        const wrapper = montarConta();

        expect(itemAberto(wrapper)[0]?.text()).toBe('FATURAS');
        expect(wrapper.get('h1').text()).toBe('FATURAS');
    });
});

describe('historico', () => {
    it('explica o vazio enquanto nenhuma ferramenta consumiu cota', () => {
        const wrapper = mount(History, {
            global: { plugins: [i18nDoTeste()] },
        });

        const vazio = wrapper.get('[data-test="historico-vazio"]');

        expect(wrapper.get('h2').text()).toBe('TITULO_HISTORICO');
        expect(vazio.text()).toContain('NADA_POR_AQUI');
        expect(vazio.text()).toContain('DICA_HISTORICO');
    });
});

const PLANO_FREE: AccountPlan = {
    name: 'Free',
    interval: null,
    isFree: true,
    price: 'R$ 0,00',
};

const ASSINATURA: AccountSubscription = {
    plan: 'Pro mensal',
    interval: 'monthly',
    gateway: 'manual',
    status: 'active',
    start: '01/10/2026',
    end: '31/10/2026',
    current: true,
};

function pagina<T>(
    rows: T[],
    extra: Partial<Paginator<T>> = {},
): Paginator<T> {
    return {
        data: rows,
        current_page: 1,
        last_page: 1,
        total: rows.length,
        ...extra,
    };
}

function fatura(id: number): AccountPaymentRow {
    return {
        id,
        plan: 'Pro mensal',
        amount: 'R$ 29,90',
        method: 'pix',
        status: 'paid',
        paidAt: '01/10/2026 10:00',
        note: null,
    };
}

function montarFaturas(props: AccountBillingProps): VueWrapper {
    return mount(Billing, {
        props,
        global: { plugins: [i18nDoTeste()] },
    });
}

describe('faturas', () => {
    it('mostra o plano gratuito e o vazio dos pagamentos', () => {
        const wrapper = montarFaturas({
            plan: PLANO_FREE,
            subscription: null,
            payments: pagina([]),
            total: 0,
        });

        expect(
            wrapper.get('[data-test="assinatura-ausente"]').text(),
        ).toContain('PLANO_GRATUITO');
        expect(wrapper.find('[data-test="assinatura"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="pagamentos-vazio"]').text()).toBe(
            'NENHUM_PAGAMENTO',
        );
        // Sem fatura nao ha tabela nem paginacao.
        expect(wrapper.find('table').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('ANTERIOR');
        expect(wrapper.find('[data-test="intencao-pendente"]').exists()).toBe(
            false,
        );
    });

    it('mostra o banner de intencao de assinatura em analise quando pendente', () => {
        const wrapper = montarFaturas({
            plan: PLANO_FREE,
            subscription: null,
            pendingIntent: {
                id: 1,
                planName: 'Pro mensal',
                planSlug: 'pro-monthly',
                status: 'pending',
                paymentMethod: 'pix',
                createdAt: '18/10/2026',
            },
            payments: pagina([]),
            total: 0,
        });

        const banner = wrapper.get('[data-test="intencao-pendente"]');
        expect(banner.text()).toContain('INTENCAO_EM_ANALISE');
        expect(banner.text()).toContain('EM_ANALISE');
        expect(banner.text()).toContain(
            'SUA INTENCAO DO PLANO Pro mensal EM 18/10/2026 ESTA EM ANALISE',
        );
        expect(banner.get('a').attributes('href')).toBe(
            '/subscribe?plan=pro-monthly',
        );
        expect(banner.text()).toContain('VER_PEDIDO');
    });

    it('escreve a assinatura e a fatura na lingua do painel', () => {
        const wrapper = montarFaturas({
            plan: { ...PLANO_FREE, name: 'Pro mensal', isFree: false },
            subscription: ASSINATURA,
            payments: pagina([fatura(1)]),
            total: 1,
        });

        const assinatura = wrapper.get('[data-test="assinatura"]');

        // O periodo e o mesmo par que o servidor formatou.
        expect(assinatura.text()).toContain('01/10/2026 – 31/10/2026');
        expect(assinatura.text()).toContain('ATIVA');
        expect(assinatura.text()).toContain('COBRANCA_MANUAL');

        const linha = wrapper.get('tbody tr');

        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        expect(linha.text()).toContain('PIX');
        expect(linha.text()).toContain('PAGO');
        expect(wrapper.text()).toContain('TOTAL 1');
        expect(wrapper.text()).toContain('PAGINA 1 DE 1');
    });

    it('mostra o estorno sem tirar a fatura da lista', () => {
        const wrapper = montarFaturas({
            plan: PLANO_FREE,
            subscription: null,
            payments: pagina([{ ...fatura(2), status: 'refunded' }]),
            total: 1,
        });

        expect(wrapper.get('tbody tr').text()).toContain('ESTORNADO');
    });

    it('leva o numero da pagina seguinte para a URL', () => {
        const wrapper = montarFaturas({
            plan: PLANO_FREE,
            subscription: null,
            payments: pagina([fatura(3)], {
                current_page: 1,
                last_page: 2,
                total: 21,
            }),
            total: 21,
        });

        const proxima = wrapper
            .findAll('a')
            .find((link) => link.text() === 'PROXIMA');

        expect(wrapper.text()).toContain('TOTAL 21');
        expect(proxima?.attributes('href')).toBe('/dashboard/billing?page=2');
    });
});

describe('historico', () => {
    function itemHistorico(id: string): AccountHistoryItem {
        return {
            id,
            tool: 'AI SQL Builder',
            dialect: 'MySQL',
            status: 'done',
            quotaUnit: 'tokens',
            tokensUsed: 150,
            tokensIn: 100,
            tokensOut: 50,
            cacheHit: false,
            createdAt: '20/10/2026 14:00',
            sql: 'SELECT * FROM users;',
            summary: 'Busca todos os usuários.',
            errorCode: null,
        };
    }

    function montarHistorico(props: AccountHistoryProps): VueWrapper {
        return mount(History, {
            props,
            global: { plugins: [i18nDoTeste()] },
        });
    }

    it('mostra o estado vazio quando nao ha registros', () => {
        const wrapper = montarHistorico({
            history: pagina([]),
            total: 0,
        });

        expect(wrapper.find('[data-test="historico-vazio"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="historico-tabela"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('NADA_POR_AQUI');
        expect(wrapper.text()).toContain('DICA_HISTORICO');
    });

    it('renderiza as linhas da tabela e botao de detalhes quando ha consultas', () => {
        const item = itemHistorico('ulid-123');
        const wrapper = montarHistorico({
            history: pagina([item]),
            total: 1,
        });

        expect(wrapper.find('[data-test="historico-vazio"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="historico-tabela"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="historico-linha-ulid-123"]').exists()).toBe(true);

        const linha = wrapper.get('[data-test="historico-linha-ulid-123"]');
        expect(linha.text()).toContain('20/10/2026 14:00');
        expect(linha.text()).toContain('AI SQL Builder');
        expect(linha.text()).toContain('MySQL');
        expect(linha.text()).toContain('150 tokens');
        expect(linha.text()).toContain('CHAMADA_IA');
        expect(linha.text()).toContain('CONCLUIDO');
        expect(linha.find('[data-test="detalhes-btn-ulid-123"]').exists()).toBe(true);
    });

    it('abre o modal com sql e resumo ao clicar em detalhes', async () => {
        const item = itemHistorico('ulid-456');
        const wrapper = montarHistorico({
            history: pagina([item]),
            total: 1,
        });

        const btn = wrapper.get('[data-test="detalhes-btn-ulid-456"]');
        await btn.trigger('click');

        // Modal aberto deve conter os dados da consulta
        const bodyText = document.body.innerHTML;
        expect(bodyText).toContain('TITULO_MODAL');
        expect(bodyText).toContain('SELECT * FROM users;');
        expect(bodyText).toContain('Busca todos os usuários.');
    });
});
