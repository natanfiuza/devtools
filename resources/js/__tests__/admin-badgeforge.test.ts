import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import BadgeAnalytics from '@/pages/admin/BadgeAnalytics.vue';
import BadgeCustomProviders from '@/pages/admin/BadgeCustomProviders.vue';
import BadgeProviders from '@/pages/admin/BadgeProviders.vue';
import type {
    AdminBadgeAnalytics,
    AdminBadgeCustomProviderRow,
    AdminBadgeProviderRow,
    Paginator,
} from '@/types';

/**
 * BadgeForge no painel (F7, ADR-086/ADR-087).
 *
 * O catalogo so liga/desliga, o provider customizado aparece com a origem (a
 * unica tela que a ve) e o volume le o agregado diario. As rotas testadas sao
 * as de verdade (`@/routes/admin`), entao o teste vale como contrato.
 */

const { routerGet, routerPatch } = vi.hoisted(() => ({
    routerGet: vi.fn(),
    routerPatch: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet, patch: routerPatch },
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
}));

const TRADUCOES = {
    admin: {
        common: {
            empty_title: 'NADA_AQUI',
            actions: 'ACOES',
            total: 'TOTAL {total}',
            page_of: 'PAGINA {page} DE {last}',
            previous: 'ANTERIOR',
            next: 'PROXIMA',
        },
        badge_providers: {
            title: 'CATALOGO',
            description: 'DESCRICAO_CATALOGO',
            readonly: 'SOMENTE_LEITURA_CATALOGO',
            empty: 'VAZIO_CATALOGO',
            category: 'CATEGORIA',
            definition: 'DEFINICAO',
            enabled: 'ATIVO',
            disabled: 'DESLIGADO',
            toggle: 'ALTERNAR_PROVIDER',
            toggle_definition: 'ALTERNAR_DEFINICAO',
        },
        badge_custom_providers: {
            title: 'CUSTOMIZADOS',
            description: 'DESCRICAO_CUSTOMIZADOS',
            readonly: 'SOMENTE_LEITURA_CUSTOMIZADOS',
            empty: 'VAZIO_CUSTOMIZADOS',
            name: 'NOME',
            owner: 'DONO',
            template: 'ORIGEM',
            metrics: 'METRICAS',
            state: 'SITUACAO',
            enabled: 'ATIVO',
            disabled: 'DESLIGADO',
            toggle: 'ALTERNAR_CUSTOMIZADO',
        },
        badgeforge_analytics: {
            title: 'VOLUME',
            description: 'DESCRICAO_VOLUME',
            window: 'JANELA',
            window_days: '{days} DIAS',
            views: 'VISUALIZACOES',
            cache_hits: 'ACERTOS_DE_CACHE',
            cache_rate: 'TAXA_DE_CACHE',
            cache_detail: '{hits} DO CACHE',
            cache_none: 'SEM_CACHE',
            no_data: 'SEM_DADOS',
            note: 'NOTA_VOLUME',
            bar_title: 'BARRA_{date}',
        },
    },
};

function montar(component: Parameters<typeof mount>[0], props: Record<string, unknown>) {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: TRADUCOES });

    return mount(component, {
        props,
        global: { plugins: [i18n] },
    });
}

function provider(overrides: Partial<AdminBadgeProviderRow> = {}): AdminBadgeProviderRow {
    return {
        id: 1,
        slug: 'github',
        name: 'GitHub',
        category: 'social',
        enabled: true,
        definitions: [
            { id: 10, slug: 'stars', name: 'Estrelas', label: 'Estrelas', enabled: true },
        ],
        ...overrides,
    };
}

function customProvider(
    overrides: Partial<AdminBadgeCustomProviderRow> = {},
): AdminBadgeCustomProviderRow {
    return {
        id: '01AAAAAAAAAAAAAAAAAAAAAAAA',
        name: 'api-interna',
        ownerName: 'Dona API',
        ownerEmail: 'owner@example.com',
        urlTemplate: 'https://api.example.com/repos/{owner}/{repo}.json',
        pathParams: ['owner', 'repo'],
        examplePath: 'natanfiuza/devtools',
        enabled: true,
        createdAt: '2026-11-04',
        definitions: [
            {
                id: '01BBBBBBBBBBBBBBBBBBBBBBBB',
                slug: 'stars',
                label: 'Estrelas',
                pointer: '/stargazers_count',
                formatter: 'number',
            },
        ],
        ...overrides,
    };
}

function paginator<T extends { id: number | string }>(
    data: T[],
    overrides: Partial<Paginator<T>> = {},
): Paginator<T> {
    return {
        data,
        current_page: 1,
        last_page: 1,
        total: data.length,
        ...overrides,
    };
}

function analytics(overrides: Partial<AdminBadgeAnalytics> = {}): AdminBadgeAnalytics {
    return {
        window: 7,
        windows: [7, 30, 90, 400],
        start: '2026-10-29',
        end: '2026-11-04',
        totals: { views: 10, cacheHits: 4, cacheRate: 0.4 },
        days: [
            { date: '2026-11-03', views: 0, cacheHits: 0 },
            { date: '2026-11-04', views: 10, cacheHits: 4 },
        ],
        ...overrides,
    };
}

beforeEach(() => {
    routerGet.mockClear();
    routerPatch.mockClear();
});

describe('BadgeProviders', () => {
    it('liga e desliga o provider pela rota do catalogo', async () => {
        const wrapper = montar(BadgeProviders, { providers: [provider()] });

        const bloco = wrapper.find('[data-test="badge-provider-github"]');

        expect(bloco.exists()).toBe(true);
        expect(bloco.text()).toContain('CATEGORIA');
        expect(wrapper.find('[data-test="badge-definition-stars"]').text())
            .toContain('Estrelas');

        await bloco.find('button[role="checkbox"]').trigger('click');

        expect(routerPatch).toHaveBeenCalledTimes(1);

        const [url, data, options] = routerPatch.mock.calls[0] ?? [];

        expect(url).toBe('/admin/badgeforge/providers/1');
        expect(data).toEqual({ enabled: false });
        expect(options).toMatchObject({ preserveScroll: true, preserveState: true });
    });

    it('desliga a definicao pela rota aninhada', async () => {
        const wrapper = montar(BadgeProviders, { providers: [provider()] });

        await wrapper.find('[data-test="badge-definition-stars"] button[role="checkbox"]')
            .trigger('click');

        const [url, data] = routerPatch.mock.calls[0] ?? [];

        expect(url).toBe('/admin/badgeforge/providers/1/definitions/10');
        expect(data).toEqual({ enabled: false });
    });
});

describe('BadgeCustomProviders', () => {
    it('mostra a origem, o dono e as metricas e alterna o estado', async () => {
        const wrapper = montar(BadgeCustomProviders, {
            providers: paginator([customProvider()]),
        });

        const texto = wrapper.text();

        expect(texto).toContain('api-interna');
        expect(texto).toContain('Dona API');
        expect(texto).toContain('owner@example.com');
        expect(texto).toContain('https://api.example.com/repos/{owner}/{repo}.json');
        expect(texto).toContain('owner, repo');
        expect(texto).toContain('stars = /stargazers_count');
        expect(texto).toContain('(number)');

        await wrapper.find('button[role="checkbox"]').trigger('click');

        const [url, data] = routerPatch.mock.calls[0] ?? [];

        expect(url).toBe('/admin/badgeforge/custom-providers/01AAAAAAAAAAAAAAAAAAAAAAAA');
        expect(data).toEqual({ enabled: false });

        expect(wrapper.findAll('button').some((button) => button.text() === 'EXCLUIR'))
            .toBe(false);
    });

    it('pede a proxima pagina no indice', async () => {
        const wrapper = montar(BadgeCustomProviders, {
            providers: paginator([customProvider()], { current_page: 1, last_page: 2 }),
        });

        const proxima = wrapper.findAll('button').find((button) => button.text() === 'PROXIMA');

        expect(proxima).toBeDefined();

        await proxima?.trigger('click');

        const [url, params] = routerGet.mock.calls[0] ?? [];

        expect(url).toBe('/admin/badgeforge/custom-providers');
        expect(params).toEqual({ page: 2 });
    });
});

describe('BadgeAnalytics', () => {
    it('mostra totais, taxa de cache e a serie do agregado', () => {
        const wrapper = montar(BadgeAnalytics, { analytics: analytics() });

        const totais = wrapper.find('[data-test="admin-badgeforge-analytics-totals"]').text();

        expect(totais).toContain('10');
        expect(totais).toContain('4');
        expect(totais).toContain('40%');
        expect(totais).toContain('4 DO CACHE');

        const grafico = wrapper.find('[data-test="admin-badgeforge-analytics-chart"]');

        expect(grafico.attributes('role')).toBe('img');
        expect(grafico.findAll('rect')).toHaveLength(2);
        expect(grafico.text()).toContain('BARRA_2026-11-04');

        expect(wrapper.find('[data-test="admin-badgeforge-analytics-empty"]').exists())
            .toBe(false);
    });

    it('troca a janela recarregando so a prop analytics', async () => {
        const wrapper = montar(BadgeAnalytics, { analytics: analytics() });

        await wrapper.find('[data-test="admin-badgeforge-window-30"]').trigger('click');

        const [url, params, options] = routerGet.mock.calls[0] ?? [];

        expect(url).toBe('/admin/badgeforge/analytics');
        expect(params).toEqual({ window: 30 });
        expect(options).toMatchObject({
            preserveScroll: true,
            preserveState: true,
            only: ['analytics'],
        });
    });

    it('nao recarrega ao clicar na janela atual e avisa quando nao ha volume', async () => {
        const wrapper = montar(BadgeAnalytics, {
            analytics: analytics({
                totals: { views: 0, cacheHits: 0, cacheRate: null },
                days: [
                    { date: '2026-11-03', views: 0, cacheHits: 0 },
                    { date: '2026-11-04', views: 0, cacheHits: 0 },
                ],
            }),
        });

        await wrapper.find('[data-test="admin-badgeforge-window-7"]').trigger('click');

        expect(routerGet).not.toHaveBeenCalled();
        expect(wrapper.find('[data-test="admin-badgeforge-analytics-empty"]').text())
            .toContain('SEM_DADOS');
        expect(wrapper.find('[data-test="admin-badgeforge-analytics-chart"]').exists())
            .toBe(false);
    });
});
