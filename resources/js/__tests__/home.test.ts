import { describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Home from '@/pages/Home.vue';
import Pricing from '@/pages/Pricing.vue';
import { allTools, availableTools } from '@/tools/registry';
import type { PublicPlan } from '@/types';

/**
 * Home publica: hero, chips de categoria, grid, destaque do AI SQL Builder;
 * e pagina dedicada de Planos (`Pricing.vue`).
 *
 * As traducoes sao marcadores - inclusive as das tools, geradas a partir do
 * registro real -, para que qualquer texto fixo no componente apareca no lugar
 * de um marcador. O grid usa o registro de verdade: e o que o criterio de
 * aceite pede (as 8 tools do MVP na home), e o contrato do registro ja esta
 * coberto em tools-registry.test.ts.
 *
 * Os planos vem por prop em `Pricing.vue`, como o `PricingController` manda:
 * preco e numero ja formatados no servidor.
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
            return h(
                'a',
                { href: this.href, class: this.$attrs.class },
                this.$slots.default?.(),
            );
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
        nav: { tools: 'NAV_FERRAMENTAS', pricing: 'PLANOS' },
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
            cta: 'ABRIR_IA',
            preview_prompt: 'PEDIDO_IA',
            preview_sql: 'SQL_IA',
        },
        api: {
            badge: 'SELO_API',
            title: 'TITULO_API',
            body: 'CORPO_API',
            note: 'NOTA_API',
            cta_docs: 'DOCS_API',
            cta_tokens: 'TOKENS_API',
            preview_title: 'PREVIEW_TITULO_API',
            preview_code: 'CODE_API',
        },
        donation: {
            badge: 'SELO_DOACAO',
            title: 'TITULO_DOACAO',
            body: 'CORPO_DOACAO',
            note: 'NOTA_DOACAO',
            cta: 'APOIAR_PIX',
        },
        cta: {
            register: 'CRIAR_CONTA',
            subscribe: 'ASSINAR_PLANO',
            contact: 'FALAR_POR_EMAIL',
        },
        pricing: {
            title: 'TITULO_PLANOS',
            subtitle: 'SUBTITULO_PLANOS',
            free: 'GRATIS',
            yearly_badge: 'DOIS_MESES_GRATIS',
            interval: { month: 'POR_MES', year: 'POR_ANO' },
            features: {
                uses: 'USOS:{count}',
                tokens: 'TOKENS:{count}',
                schema: 'SCHEMA:{size}',
                prompt: 'DESCRICAO:{count}',
                username: 'VANTAGEM_USERNAME',
                history: 'VANTAGEM_HISTORICO',
                priority: 'VANTAGEM_SUPORTE',
            },
            advantages: {
                title: 'TITULO_VANTAGENS',
                subtitle: 'SUBTITULO_VANTAGENS',
                items: {
                    username: { title: 'BV_USER_T', body: 'BV_USER_B' },
                    quota: { title: 'BV_TOK_T', body: 'BV_TOK_B' },
                    history: { title: 'BV_HIST_T', body: 'BV_HIST_B' },
                    priority: { title: 'BV_SUP_T', body: 'BV_SUP_B' },
                    discount: { title: 'BV_YEAR_T', body: 'BV_YEAR_B' },
                },
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

function montar(): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Home, {
        global: { plugins: [i18n] },
    });
}

function montarPlanos(): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Pricing, {
        props: { plans: planos },
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

    it('leva o CTA do destaque para a pagina da tool e o secundario para os planos', () => {
        const wrapper = montar();
        const cta = wrapper.get('[data-test="home-ai-cta"]');

        // O destaque deixou de ser "em breve" na F5: o CTA abre a pagina da
        // ferramenta, e o visitante encontra o login la. O caminho sai do
        // registro, como nos cards.
        expect(cta.text()).toBe('ABRIR_IA');
        expect(cta.attributes('href')).toBe('/pt-br/tools/ai-sql-builder');
        expect(wrapper.get('a[href="/pt-br/pricing"]').text()).toBe('PLANOS');
    });

    it('mostra o card da API com o preview estatico e CTAs', () => {
        const wrapper = montar();
        const texto = wrapper.text();

        expect(texto).toContain('SELO_API');
        expect(texto).toContain('TITULO_API');
        expect(texto).toContain('CORPO_API');
        expect(texto).toContain('NOTA_API');
        expect(texto).toContain('PREVIEW_TITULO_API');
        expect(texto).toContain('CODE_API');

        const docsCta = wrapper.get('[data-test="home-api-docs-cta"]');
        expect(docsCta.text()).toBe('DOCS_API');
        expect(docsCta.attributes('href')).toBe('/pt-br/api-docs');

        const registerCta = wrapper.get('[data-test="home-api-register-cta"]');
        expect(registerCta.text()).toBe('CRIAR_CONTA');
        expect(registerCta.attributes('href')).toBe('/pt-br/register');
    });

    it('mostra a chamada de doacao com link para a pagina de doacao Pix', () => {
        const wrapper = montar();
        const texto = wrapper.text();

        expect(texto).toContain('SELO_DOACAO');
        expect(texto).toContain('TITULO_DOACAO');
        expect(texto).toContain('CORPO_DOACAO');
        expect(texto).toContain('NOTA_DOACAO');

        const donateCta = wrapper.get('[data-test="home-donate-cta"]');
        expect(donateCta.text()).toBe('APOIAR_PIX');
        expect(donateCta.attributes('href')).toBe('/pt-br/donate');
    });
});

describe('grid de ferramentas', () => {
    it('mostra as tools do registro, na ordem de exibicao', () => {
        const wrapper = montar();

        expect(cartoes(wrapper)).toHaveLength(allTools.length);
        expect(
            cartoes(wrapper).map((card) =>
                card.attributes('data-test')?.replace('tool-card-', ''),
            ),
        ).toEqual(allTools.map((tool) => tool.slug));
    });

    /*
    | A F5 fechou a ultima tool do catalogo: nao ha mais card "em breve", e todo
    | card linka para a propria pagina. Um card sem link so voltaria a existir
    | se alguma tool regredisse para `soon` - e o sitemap deixaria de lista-la
    | junto (ADR-007).
    */
    it('linka o card inteiro de todas as tools do registro', () => {
        const wrapper = montar();

        expect(wrapper.findAll('[data-test="tool-card-badge"]')).toHaveLength(
            0,
        );
        expect(wrapper.text()).not.toContain('SELO_EM_BREVE');
        expect(wrapper.text()).not.toContain('SELO_DISPONIVEL');

        const links = wrapper.get('[data-test="tool-grid"]').findAll('a');

        expect(links).toHaveLength(availableTools.length);
        expect(availableTools).toHaveLength(allTools.length);

        // O primeiro do grid e o JSON Viewer, a primeira da ordem de exibicao,
        // com stretched link (`after:inset-0`) cobrindo o card todo.
        expect(links[0]?.attributes('href')).toBe('/pt-br/tools/json-viewer');
        expect(links[0]?.attributes('class')).toContain('after:inset-0');

        // O segundo e o AI SQL Builder, que a F5 tornou navegavel.
        expect(cartoes(wrapper)[1]?.attributes('data-test')).toBe(
            'tool-card-ai-sql-builder',
        );
        expect(links[1]?.attributes('href')).toBe(
            '/pt-br/tools/ai-sql-builder',
        );

        // E todo link aponta para a pagina de uma tool do registro.
        for (const link of links) {
            const slug = link.attributes('href')?.replace('/pt-br/tools/', '');

            expect(allTools.find((tool) => tool.slug === slug)?.status).toBe(
                'available',
            );
        }
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
            wrapper
                .get('[data-test="tool-chip-sql"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper
                .get('[data-test="tool-chip-all"]')
                .attributes('aria-pressed'),
        ).toBe('false');

        await wrapper.get('[data-test="tool-chip-all"]').trigger('click');

        expect(cartoes(wrapper)).toHaveLength(allTools.length);
    });
});

describe('precos (pagina dedicada)', () => {
    it('monta um card por plano, na ordem que o servidor mandou', () => {
        const wrapper = montarPlanos();

        expect(
            wrapper
                .findAll('li[data-test^="plan-card-"]')
                .map((card) => card.attributes('data-test')),
        ).toEqual([
            'plan-card-free',
            'plan-card-pro-monthly',
            'plan-card-pro-yearly',
        ]);

        expect(wrapper.text()).toContain('TITULO_PLANOS');
        expect(wrapper.text()).toContain('SUBTITULO_PLANOS');
    });

    it('escreve preco, intervalo, vantagens e linhas de limite com o dado do servidor', () => {
        const wrapper = montarPlanos();

        const gratuito = wrapper.get('[data-test="plan-card-free"]');
        const mensal = wrapper.get('[data-test="plan-card-pro-monthly"]');
        const anual = wrapper.get('[data-test="plan-card-pro-yearly"]');

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
        expect(mensal.text()).toContain('VANTAGEM_USERNAME');
        expect(mensal.text()).toContain('VANTAGEM_HISTORICO');
        expect(mensal.text()).toContain('VANTAGEM_SUPORTE');

        expect(anual.text()).toContain('DOIS_MESES_GRATIS');
        expect(wrapper.text()).toContain('BV_YEAR_T');
    });

    it('chama o cadastro no gratuito e o formulario de intencao de assinatura no pago', () => {
        const wrapper = montarPlanos();

        expect(
            wrapper
                .get('[data-test="plan-card-free"]')
                .get('a')
                .attributes('href'),
        ).toBe('/pt-br/register');

        const mensalCta = wrapper
            .get('[data-test="plan-card-pro-monthly"]')
            .get('[data-test="plan-subscribe-pro-monthly"]');
        expect(mensalCta.attributes('href')).toBe(
            '/subscribe?plan=pro-monthly',
        );
        expect(mensalCta.text()).toBe('ASSINAR_PLANO');

        const anualCta = wrapper
            .get('[data-test="plan-card-pro-yearly"]')
            .get('[data-test="plan-subscribe-pro-yearly"]');
        expect(anualCta.attributes('href')).toBe('/subscribe?plan=pro-yearly');
    });

    it('nao escreve preco nem limite no codigo da tela', () => {
        const wrapper = montarPlanos();
        const texto = wrapper.text();

        // Os numeros so podem vir da tabela `plans` (via prop).
        expect(texto).not.toContain('890');
        expect(texto).not.toContain('8,90');
        expect(texto).not.toContain('2000000');
    });
});
