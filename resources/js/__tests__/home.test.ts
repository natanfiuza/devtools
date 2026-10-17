import { describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Home from '@/pages/Home.vue';
import { allTools } from '@/tools/registry';
import type { PublicPlan } from '@/types';

/**
 * Home publica: hero, chips de categoria, grid, destaque do AI SQL Builder e
 * precos.
 *
 * As traducoes sao marcadores - inclusive as das tools, geradas a partir do
 * registro real -, para que qualquer texto fixo no componente apareca no lugar
 * de um marcador. O grid usa o registro de verdade: e o que o criterio de
 * aceite pede (as 8 tools do MVP na home), e o contrato do registro ja esta
 * coberto em tools-registry.test.ts.
 *
 * Os planos vem por prop, como o `HomeController` manda: preco e numero ja
 * formatados no servidor.
 */

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            localeSlug: 'pt-br',
            // Titulo e descricao resolvidos no servidor (App\Support\Seo).
            seo: {
                meta: {
                    title: 'NAV_FERRAMENTAS',
                    description: 'SUBTITULO_HOME',
                },
            },
        },
    }),
    router: { visit: vi.fn() },
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render() {
            // O titulo vem por prop; as metas sao da view (app.blade.php).
            return h('div', { 'data-test': 'head-title' }, [
                h('span', { 'data-test': 'head-title-text' }, this.title),
                this.$slots.default?.(),
            ]);
        },
    }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        render() {
            return h('a', { href: this.href }, this.$slots.default?.());
        },
    }),
}));

const toolTraducoes: Record<string, unknown> = {
    status: { soon: 'SELO_EM_BREVE', available: 'SELO_DISPONIVEL' },
    categories: {
        json: 'CAT_JSON',
        sql: 'CAT_SQL',
        encode: 'CAT_ENCODE',
        security: 'CAT_SEG',
        time: 'CAT_TEMPO',
        text: 'CAT_TEXTO',
    },
};

for (const tool of allTools) {
    toolTraducoes[tool.slug] = {
        name: `NOME_${tool.slug}`,
        description: `DESC_${tool.slug}`,
    };
}

const traducoes = {
    app: {
        nav: { tools: 'NAV_FERRAMENTAS' },
        search: { trigger: 'BUSCAR' },
    },
    tools: toolTraducoes,
    home: {
        title: 'TITULO_HOME',
        subtitle: 'SUBTITULO_HOME',
        tools: {
            title: 'TITULO_FERRAMENTAS',
            subtitle: 'SUBTITULO_FERRAMENTAS',
            filter: 'FILTRAR',
            all: 'TODAS',
            empty: 'VAZIO',
        },
        ai: {
            badge: 'SELO_IA',
            title: 'TITULO_IA',
            body: 'CORPO_IA',
            note: 'NOTA_IA',
            preview_prompt: 'PEDIDO_IA',
            preview_sql: 'SQL_IA',
        },
        cta: {
            register: 'CRIAR_CONTA',
            contact: 'FALAR_POR_EMAIL',
        },
        pricing: {
            title: 'TITULO_PLANOS',
            subtitle: 'SUBTITULO_PLANOS',
            free: 'GRATIS',
            interval: { month: 'POR_MES', year: 'POR_ANO' },
            features: {
                uses: 'USOS:{count}',
                tokens: 'TOKENS:{count}',
                schema: 'SCHEMA:{size}',
                prompt: 'DESCRICAO:{count}',
            },
        },
    },
};

/** Os tres planos como o controlador manda: preco e numeros ja formatados. */
const planos: PublicPlan[] = [
    {
        slug: 'free',
        name: 'Free',
        price: 'R$ 0,00',
        isFree: true,
        interval: null,
        monthlyTokens: '0',
        lifetimeFreeUses: '5',
        promptChars: '500',
        schemaSize: '8 KB',
    },
    {
        slug: 'pro-monthly',
        name: 'Pro',
        price: 'PRECO_MENSAL',
        isFree: false,
        interval: 'month',
        monthlyTokens: '2.000.000',
        lifetimeFreeUses: '0',
        promptChars: '4.000',
        schemaSize: '64 KB',
    },
    {
        slug: 'pro-yearly',
        name: 'Pro',
        price: 'PRECO_ANUAL',
        isFree: false,
        interval: 'year',
        monthlyTokens: '2.000.000',
        lifetimeFreeUses: '0',
        promptChars: '4.000',
        schemaSize: '64 KB',
    },
];

function montar(contactEmail: string | null = 'contato@exemplo.com'): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Home, {
        props: { plans: planos, contactEmail },
        global: { plugins: [i18n] },
    });
}

function cartoes(wrapper: VueWrapper) {
    return wrapper.findAll('li[data-test^="tool-card-"]');
}

describe('hero e destaque', () => {
    it('escreve o hero e o titulo da aba pelo i18n', () => {
        const wrapper = montar();

        expect(wrapper.get('h1').text()).toBe('TITULO_HOME');
        expect(wrapper.text()).toContain('SUBTITULO_HOME');

        // O titulo curto fica na aba; a frase da home e o resumo que a busca e
        // o card de compartilhamento mostram - os dois resolvidos no servidor
        // e entregues pelas props compartilhadas.
        expect(wrapper.get('[data-test="head-title-text"]').text()).toBe(
            'NAV_FERRAMENTAS',
        );
        expect(wrapper.findAll('meta')).toHaveLength(0);
    });

    it('abre a busca pelo gatilho do hero', () => {
        const wrapper = montar();

        expect(wrapper.get('[data-test="search-trigger"]').text()).toContain(
            'BUSCAR',
        );
    });

    it('mostra o AI SQL Builder com o preview estatico', () => {
        const wrapper = montar();
        const texto = wrapper.text();

        expect(texto).toContain('SELO_IA');
        expect(texto).toContain('TITULO_IA');
        expect(texto).toContain('CORPO_IA');
        expect(texto).toContain('NOTA_IA');
        expect(texto).toContain('PEDIDO_IA');
        expect(wrapper.get('pre').text()).toBe('SQL_IA');
    });
});

describe('grid de ferramentas', () => {
    it('mostra as oito tools do registro, na ordem de exibicao', () => {
        const wrapper = montar();

        expect(cartoes(wrapper)).toHaveLength(8);
        expect(
            cartoes(wrapper).map(
                (card) => card.attributes('data-test')?.replace('tool-card-', ''),
            ),
        ).toEqual(allTools.map((tool) => tool.slug));
    });

    /*
    | F3: sete tools funcionam, entao sete cards viram link para a propria pagina
    | (a F2 entregou a primeira, a F3 as seis simples). O selo "em breve" fica so
    | com o AI SQL Builder - e card que nao funciona nao linka, senao seria um
    | link morto no indice (ADR-007).
    */
    it('linka o card das tools que funcionam e deixa a outra com o selo', () => {
        const wrapper = montar();

        expect(wrapper.findAll('[data-test="tool-card-badge"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('SELO_EM_BREVE');
        expect(wrapper.text()).not.toContain('SELO_DISPONIVEL');

        const links = wrapper.get('[data-test="tool-grid"]').findAll('a');

        expect(links).toHaveLength(7);

        // O primeiro do grid e o JSON Viewer, a primeira da ordem de exibicao.
        expect(links[0]?.attributes('href')).toBe('/pt-br/tools/json-viewer');

        // E nenhum link aponta para tool que nao funciona.
        for (const link of links) {
            const slug = link.attributes('href')?.replace('/pt-br/tools/', '');

            expect(
                allTools.find((tool) => tool.slug === slug)?.status,
            ).toBe('available');
        }

        // O card sem link e o do AI SQL Builder - o segundo do grid.
        const semLink = cartoes(wrapper)[1];

        expect(semLink?.attributes('data-test')).toBe(
            'tool-card-ai-sql-builder',
        );
        expect(semLink?.find('a').exists()).toBe(false);
    });

    it('escreve nome e descricao de cada card pelo i18n', () => {
        const wrapper = montar();
        const primeiro = cartoes(wrapper)[0];

        expect(primeiro?.text()).toContain('NOME_json-viewer');
        expect(primeiro?.text()).toContain('DESC_json-viewer');
    });

    it('filtra o grid pelos chips de categoria', () => {
        const wrapper = montar();

        const todas = wrapper.get('[data-test="tool-chip-all"]');
        expect(todas.text()).toBe('TODAS');
        expect(todas.attributes('aria-pressed')).toBe('true');
        expect(wrapper.findAll('[data-test^="tool-chip-"]')).toHaveLength(7);

        // SQL tem duas tools no catalogo.
        const sql = wrapper.get('[data-test="tool-chip-sql"]');
        expect(sql.text()).toContain('CAT_SQL');
        expect(sql.attributes('aria-pressed')).toBe('false');
    });
});

describe('filtro por categoria', () => {
    it('mostra so as tools da categoria escolhida e volta para todas', async () => {
        const wrapper = montar();

        await wrapper.get('[data-test="tool-chip-sql"]').trigger('click');

        expect(cartoes(wrapper)).toHaveLength(2);
        expect(
            cartoes(wrapper).map((card) => card.attributes('data-test')),
        ).toEqual(['tool-card-ai-sql-builder', 'tool-card-sql-formatter']);
        expect(
            wrapper.get('[data-test="tool-chip-sql"]').attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper.get('[data-test="tool-chip-all"]').attributes('aria-pressed'),
        ).toBe('false');

        await wrapper.get('[data-test="tool-chip-all"]').trigger('click');

        expect(cartoes(wrapper)).toHaveLength(8);
    });
});

describe('precos', () => {
    it('monta um card por plano, na ordem que o servidor mandou', () => {
        const wrapper = montar();

        expect(
            wrapper
                .findAll('li[data-test^="plan-card-"]')
                .map((card) => card.attributes('data-test')),
        ).toEqual(['plan-card-free', 'plan-card-pro-monthly', 'plan-card-pro-yearly']);

        expect(wrapper.text()).toContain('TITULO_PLANOS');
        expect(wrapper.text()).toContain('SUBTITULO_PLANOS');
    });

    it('escreve preco, intervalo e linhas de limite com o dado do servidor', () => {
        const wrapper = montar();

        const gratuito = wrapper.get('[data-test="plan-card-free"]');
        const mensal = wrapper.get('[data-test="plan-card-pro-monthly"]');

        // O gratuito nao mostra "R$ 0,00": mostra o texto do idioma.
        expect(gratuito.text()).toContain('GRATIS');
        expect(gratuito.text()).not.toContain(planos[0]?.price ?? '');
        expect(gratuito.text()).toContain('USOS:5');
        expect(gratuito.text()).toContain('SCHEMA:8 KB');
        expect(gratuito.text()).toContain('DESCRICAO:500');

        expect(mensal.text()).toContain('PRECO_MENSAL');
        expect(mensal.text()).toContain('POR_MES');
        expect(mensal.text()).toContain('TOKENS:2.000.000');
        expect(mensal.text()).toContain('SCHEMA:64 KB');
    });

    it('chama o cadastro no gratuito e o contato no pago (venda manual)', () => {
        const wrapper = montar();

        expect(
            wrapper
                .get('[data-test="plan-card-free"]')
                .get('a')
                .attributes('href'),
        ).toBe('/pt-br/register');

        const contato = wrapper.get('[data-test="plan-contact"]');
        expect(contato.attributes('href')).toBe('mailto:contato@exemplo.com');
        expect(contato.text()).toBe('FALAR_POR_EMAIL');
        expect(wrapper.get('[data-test="plan-card-pro-yearly"]').text()).toContain(
            'PRECO_ANUAL',
        );
    });

    it('nao oferece link de contato quando nao ha e-mail configurado', () => {
        const wrapper = montar(null);

        expect(wrapper.find('[data-test="plan-contact"]').exists()).toBe(false);
        expect(
            wrapper.get('[data-test="plan-card-pro-monthly"]').get('button')
                .attributes('disabled'),
        ).toBeDefined();
    });

    it('nao escreve preco nem limite no codigo da tela', () => {
        const wrapper = montar();
        const texto = wrapper.text();

        // Os numeros so podem vir da tabela `plans` (via prop).
        expect(texto).not.toContain('2990');
        expect(texto).not.toContain('29,90');
        expect(texto).not.toContain('2000000');
    });
});
