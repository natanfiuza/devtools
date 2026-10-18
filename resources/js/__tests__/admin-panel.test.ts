import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import Users from '@/pages/admin/Users.vue';
import type { AdminColumn, AdminUserRow, Paginator } from '@/types';

/**
 * Painel administrativo: navegacao, tabela densa e filtros (ADR-025).
 *
 * As traducoes sao marcadores para denunciar texto fixo no componente, e as
 * rotas sao as de verdade (`@/routes/admin`) para o teste valer como contrato
 * do que a tela aponta.
 */

type FakePage = { url: string; props: Record<string, unknown> };

/**
 * A pagina falsa do Inertia. Precisa ser reativa e escrita pela proxy: o
 * `useCurrentUrl` guarda um computed de modulo, que so se recalcula quando a
 * URL muda por dentro dele.
 */
const { fakePage, routerGet } = vi.hoisted(() => ({
    fakePage: { current: null } as { current: FakePage | null },
    routerGet: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    fakePage.current = reactive<FakePage>({
        url: '/admin',
        props: {
            localeSlug: 'pt-br',
            auth: {
                user: { id: 1, name: 'Admin', email: 'admin@exemplo.com' },
            },
        },
    });

    return {
        usePage: () => fakePage.current,
        router: {
            get: routerGet,
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

/** Abre uma URL do painel, como o Inertia faria. */
function abrir(url: string): void {
    if (fakePage.current !== null) {
        fakePage.current.url = url;
    }
}

// O menu do usuario e o aviso de tema/idioma nao fazem parte do que se testa
// aqui, e trazem junto o popover e os cookies.
vi.mock('@/components/LocaleSwitcher.vue', () => ({
    default: defineComponent({
        name: 'LocaleSwitcher',
        render: () => h('div'),
    }),
}));

vi.mock('@/components/ThemeToggle.vue', () => ({
    default: defineComponent({ name: 'ThemeToggle', render: () => h('div') }),
}));

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
    admin: {
        nav: {
            overview: 'VISAO_GERAL',
            users: 'USUARIOS',
            subscriptions: 'ASSINATURAS',
            subscribers: 'ASSINANTES',
            plans: 'PLANOS',
            intents: 'INTENCOES',
            payments: 'PAGAMENTOS',
            audit: 'AUDITORIA',
            panel: 'PAINEL',
            back_to_site: 'VOLTAR_AO_SITE',
        },
        common: {
            empty_title: 'NADA_AQUI',
            actions: 'ACOES',
            total: 'TOTAL {total}',
            page_of: 'PAGINA {page} DE {last}',
            previous: 'ANTERIOR',
            next: 'PROXIMA',
            search: 'BUSCAR',
            clear: 'LIMPAR',
        },
        users: {
            title: 'CONTAS',
            description: 'DESCRICAO_CONTAS',
            account: 'CONTA',
            plan: 'PLANO',
            role: 'PAPEL',
            status: 'SITUACAO',
            created: 'CRIADA',
            search_placeholder: 'DICA_BUSCA',
            role_all: 'TODOS_PAPEIS',
            role_user: 'PAPEL_USUARIO',
            role_admin: 'PAPEL_ADMIN',
            status_all: 'TODAS_SITUACOES',
            status_active: 'SITUACAO_ATIVA',
            status_suspended: 'SITUACAO_SUSPENSA',
            status_unverified: 'SITUACAO_NAO_VERIFICADA',
            unverified: 'NAO_VERIFICADA',
            open: 'ABRIR',
            empty: 'VAZIO_CONTAS',
        },
    },
};

function montarPainel(): VueWrapper {
    return mount(AdminLayout, { global: { plugins: [i18nDoTeste()] } });
}

beforeEach(() => {
    abrir('/admin');
    routerGet.mockClear();
});

describe('navegacao do painel', () => {
    it('lista as oito secoes com o caminho de cada uma', () => {
        const wrapper = montarPainel();

        const itens = wrapper.findAll('nav a');

        expect(itens.map((item) => item.text())).toEqual([
            'VISAO_GERAL',
            'USUARIOS',
            'ASSINATURAS',
            'ASSINANTES',
            'PLANOS',
            'INTENCOES',
            'PAGAMENTOS',
            'AUDITORIA',
        ]);
        expect(itens.map((item) => item.attributes('href'))).toEqual([
            '/admin',
            '/admin/users',
            '/admin/subscriptions',
            '/admin/subscribers',
            '/admin/plans',
            '/admin/intents',
            '/admin/payments',
            '/admin/audit',
        ]);
    });

    it('da nome acessivel a navegacao e uma saida para o site', () => {
        const wrapper = montarPainel();

        expect(wrapper.get('nav').attributes('aria-label')).toBe('PAINEL');
        expect(
            wrapper
                .findAll('a')
                .some((a) => a.text().includes('VOLTAR_AO_SITE')),
        ).toBe(true);
    });

    it('marca so a secao aberta e escreve o titulo dela na barra', () => {
        abrir('/admin/payments');

        const wrapper = montarPainel();
        const abertos = wrapper.findAll('nav a[aria-current="page"]');

        expect(abertos).toHaveLength(1);
        expect(abertos[0]?.text()).toBe('PAGAMENTOS');
        expect(wrapper.get('h1').text()).toBe('PAGAMENTOS');
    });

    it('mantem a secao aberta nas telas de detalhe', () => {
        // A conta aberta e filha da lista: em `/admin/users/5` quem esta
        // aberta e Contas.
        abrir('/admin/users/5');

        const wrapper = montarPainel();

        expect(wrapper.get('h1').text()).toBe('USUARIOS');
        expect(wrapper.findAll('nav a[aria-current="page"]')).toHaveLength(1);
    });

    it('chama a visao geral pelo nome dela na raiz do painel', () => {
        const wrapper = montarPainel();
        const abertos = wrapper.findAll('nav a[aria-current="page"]');

        expect(abertos).toHaveLength(1);
        expect(abertos[0]?.text()).toBe('VISAO_GERAL');
        expect(wrapper.get('h1').text()).toBe('VISAO_GERAL');
    });
});

const COLUNAS: AdminColumn[] = [
    { key: 'account', label: 'CONTA' },
    { key: 'createdAt', label: 'CRIADA', mono: true, align: 'right' },
];

function pagina(
    rows: AdminUserRow[],
    extra: Partial<Paginator<AdminUserRow>> = {},
) {
    return {
        data: rows,
        current_page: 1,
        last_page: 1,
        total: rows.length,
        ...extra,
    } satisfies Paginator<AdminUserRow>;
}

function conta(id: number, nome: string): AdminUserRow {
    return {
        id,
        name: nome,
        email: `conta${id}@exemplo.com`,
        role: 'user',
        isAdmin: false,
        suspended: false,
        verified: true,
        plan: 'Free',
        createdAt: '14/10/2026 10:00',
    };
}

describe('tabela densa', () => {
    function montarTabela(rows: Paginator<AdminUserRow>, loading = false) {
        return mount(AdminTable, {
            props: { columns: COLUNAS, rows, empty: 'VAZIO_CONTAS', loading },
            global: { plugins: [i18nDoTeste()] },
        });
    }

    it('escreve o cabecalho e as celulas pelo slot da coluna', () => {
        const wrapper = montarTabela(pagina([conta(1, 'Ana')]), false);

        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            'CONTA',
            'CRIADA',
        ]);
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
        // Numero alinhado a direita em mono (DESIGN: AdminTable).
        expect(wrapper.findAll('tbody td')[1]?.classes()).toContain(
            'font-mono',
        );
    });

    it('mostra o estado vazio com a ajuda da pagina', () => {
        const wrapper = montarTabela(pagina([]));

        expect(wrapper.text()).toContain('NADA_AQUI');
        expect(wrapper.text()).toContain('VAZIO_CONTAS');
        expect(wrapper.find('table').exists()).toBe(false);
        // Sem linhas nao ha o que paginar.
        expect(wrapper.text()).not.toContain('ANTERIOR');
    });

    it('esconde os numeros enquanto carrega', () => {
        const wrapper = montarTabela(pagina([conta(1, 'Ana')]), true);

        expect(wrapper.find('table').exists()).toBe(false);
        expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true);
    });

    it('desabilita a pagina que nao existe e emite o destino', async () => {
        const wrapper = montarTabela(
            pagina([conta(1, 'Ana')], {
                current_page: 1,
                last_page: 3,
                total: 42,
            }),
        );

        const botoes = wrapper.findAll('button');

        expect(wrapper.text()).toContain('TOTAL 42');
        expect(wrapper.text()).toContain('PAGINA 1 DE 3');
        expect(botoes[0]?.attributes('disabled')).toBeDefined();
        expect(botoes[1]?.attributes('disabled')).toBeUndefined();

        await botoes[1]?.trigger('click');

        expect(wrapper.emitted('page')).toEqual([[2]]);
    });

    it('mostra a coluna de acoes so quando a pagina oferece uma', () => {
        expect(montarTabela(pagina([conta(1, 'Ana')])).text()).not.toContain(
            'ACOES',
        );

        const wrapper = mount(AdminTable, {
            props: {
                columns: COLUNAS,
                rows: pagina([conta(1, 'Ana')]),
                empty: 'VAZIO_CONTAS',
            },
            slots: { actions: '<span>ABRIR</span>' },
            global: { plugins: [i18nDoTeste()] },
        });

        expect(wrapper.findAll('thead th')[2]?.text()).toBe('ACOES');
        expect(wrapper.text()).toContain('ABRIR');
    });
});

function i18nDoTeste() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return i18n;
}

describe('lista de contas', () => {
    function montarLista(
        users: Paginator<AdminUserRow>,
        filters = { q: '', role: '', status: '' },
    ): VueWrapper {
        return mount(Users, {
            props: { users, filters, total: users.total },
            global: { plugins: [i18nDoTeste()] },
        });
    }

    /** O que esta escrito no campo de busca. */
    function busca(wrapper: VueWrapper): string {
        const campo = wrapper.get('input[type="search"]')
            .element as HTMLInputElement;

        return campo.value;
    }

    it('filtra pela busca mantendo a barra acima da tabela', async () => {
        const wrapper = montarLista(pagina([conta(1, 'Ana')]));

        await wrapper.get('input[type="search"]').setValue('ana@exemplo.com');
        await wrapper.get('form').trigger('submit');

        expect(routerGet).toHaveBeenCalledWith(
            '/admin/users',
            { q: 'ana@exemplo.com', role: '', status: '' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    });

    it('tira o campo do lugar quando o filtro vem da URL', () => {
        const wrapper = montarLista(pagina([conta(1, 'Ana')]), {
            q: 'ana',
            role: 'admin',
            status: 'suspended',
        });

        expect(busca(wrapper)).toBe('ana');
    });

    it('limpa os tres filtros de uma vez', async () => {
        const wrapper = montarLista(pagina([conta(1, 'Ana')]), {
            q: 'ana',
            role: 'admin',
            status: 'suspended',
        });

        const limpar = wrapper
            .findAll('button')
            .find((botao) => botao.text() === 'LIMPAR');

        await limpar?.trigger('click');

        // `all` e so o sentinela do Select: na URL ele volta a ser vazio.
        expect(routerGet).toHaveBeenCalledWith(
            '/admin/users',
            { q: '', role: '', status: '' },
            { preserveState: true, preserveScroll: true, replace: true },
        );
        expect(busca(wrapper)).toBe('');
    });

    it('leva a pagina pedida para a URL', async () => {
        const wrapper = montarLista(
            pagina([conta(1, 'Ana')], {
                current_page: 1,
                last_page: 2,
                total: 30,
            }),
        );

        const proxima = wrapper
            .findAll('button')
            .find((botao) => botao.text() === 'PROXIMA');

        await proxima?.trigger('click');

        expect(routerGet).toHaveBeenCalledWith(
            '/admin/users',
            { q: '', role: '', status: '', page: 2 },
            { preserveState: true, preserveScroll: true },
        );
    });

    it('mostra o estado vazio quando a busca nao acha ninguem', () => {
        const wrapper = montarLista(pagina([]));

        expect(wrapper.text()).toContain('NADA_AQUI');
        expect(wrapper.text()).toContain('VAZIO_CONTAS');
    });
});
