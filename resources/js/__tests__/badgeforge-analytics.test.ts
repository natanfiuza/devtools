import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import AnalyticsPanel from '@/tools/badgeforge/AnalyticsPanel.vue';
import type { AccountBadgeForgeAnalytics } from '@/types';

/**
 * Secao de analytics da dashboard (F7, ADR-086).
 *
 * Free ve totais e por imagem; pago ganha serie, quebras, taxa de cache e CSV.
 * A troca de janela recarrega so a prop `analytics` pela dashboard.
 */

const { routerGet } = vi.hoisted(() => ({
    routerGet: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet },
}));

const TRADUCOES = {
    account: {
        badgeforge: {
            analytics: {
                title: 'ANALYTICS_TITULO',
                description: 'ANALYTICS_DESCRICAO',
                period: 'Periodo',
                period_days: '{days} dias',
                views: 'Visualizacoes',
                uniques: 'Visitantes unicos',
                cache_rate: 'Taxa de cache',
                cache_detail: '{hits} de {total}',
                no_data: 'SEM_DADOS',
                chart_heading: 'Visualizacoes por dia',
                chart_title: 'Visualizacoes de {start} a {end}',
                chart_bar_title: '{date}: {views} visualizacoes, {uniques} visitantes unicos',
                referers: 'Referrers',
                countries: 'Paises',
                formats: 'Formatos',
                subjects_title: 'Por imagem',
                subject_name: 'Imagem',
                subject_type: 'Tipo',
                subjects: {
                    personal: 'Badge pessoal',
                    endpoint: 'Endpoint JSON',
                    custom: 'Provider customizado',
                },
                unknown_subject: 'IMAGEM_EXCLUIDA',
                export: 'Exportar CSV',
                free_note: 'NOTA_FREE_{days}',
                upgrade: 'Fazer upgrade',
                unavailable: 'ANALYTICS_INDISPONIVEL',
            },
        },
    },
};

function paidAnalytics(
    overrides: Partial<AccountBadgeForgeAnalytics> = {},
): AccountBadgeForgeAnalytics {
    return {
        isPaid: true,
        window: 30,
        windows: [7, 30, 90],
        freeWindowDays: 30,
        start: '2026-10-06',
        end: '2026-11-04',
        totals: { views: 12, uniques: 7 },
        subjects: [
            {
                type: 'custom',
                id: '01AAAAAAAAAAAAAAAAAAAAAAAA',
                name: 'Minha API',
                views: 9,
                uniques: 5,
            },
            {
                type: 'personal',
                id: '01BBBBBBBBBBBBBBBBBBBBBBBB',
                name: '',
                views: 3,
                uniques: 2,
            },
        ],
        days: [
            { date: '2026-11-03', views: 4, uniques: 3 },
            { date: '2026-11-04', views: 8, uniques: 4 },
        ],
        referers: [{ domain: 'github.com', views: 6 }],
        countries: [{ country: 'BR', views: 8 }],
        formats: [{ format: 'svg', views: 11 }],
        cache: { hits: 2, total: 3, rate: 0.6667 },
        exportUrl: '/badgeforge/analytics/export?window=30',
        ...overrides,
    };
}

function montar(analytics: AccountBadgeForgeAnalytics | null) {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: TRADUCOES });

    return mount(AnalyticsPanel, {
        props: { analytics, upgradeUrl: '/pricing' },
        global: { plugins: [i18n] },
    });
}

beforeEach(() => {
    routerGet.mockClear();
});

describe('AnalyticsPanel', () => {
    it('mostra totais, taxa de cache, serie, quebras e CSV no plano pago', () => {
        const wrapper = montar(paidAnalytics());

        const totais = wrapper.find('[data-test="badgeforge-analytics-totals"]').text();

        expect(totais).toContain('12');
        expect(totais).toContain('7');
        expect(totais).toContain('67%');
        expect(totais).toContain('2 de 3');

        const chart = wrapper.find('[data-test="badgeforge-analytics-chart"]');

        expect(chart.exists()).toBe(true);
        expect(chart.attributes('role')).toBe('img');
        expect(chart.attributes('aria-label')).toContain('2026-11-03');

        expect(wrapper.find('[data-test="badgeforge-analytics-referers"]').text())
            .toContain('github.com');
        expect(wrapper.find('[data-test="badgeforge-analytics-countries"]').text())
            .toContain('BR');
        expect(wrapper.find('[data-test="badgeforge-analytics-formats"]').text())
            .toContain('svg');

        const linhas = wrapper.find('[data-test="badgeforge-analytics-subjects"]').text();

        expect(linhas).toContain('Minha API');
        expect(linhas).toContain('IMAGEM_EXCLUIDA');
        expect(linhas).toContain('Badge pessoal');
        expect(linhas).toContain('Provider customizado');

        expect(
            wrapper.find('[data-test="badgeforge-analytics-export"]').attributes('href'),
        ).toBe('/badgeforge/analytics/export?window=30');

        expect(wrapper.find('[data-test="badgeforge-analytics-free-note"]').exists())
            .toBe(false);
    });

    it('recarrega apenas a prop analytics ao trocar a janela', async () => {
        const wrapper = montar(paidAnalytics());

        await wrapper.find('[data-test="badgeforge-analytics-window-7"]').trigger('click');

        expect(routerGet).toHaveBeenCalledTimes(1);

        const [url, params, options] = routerGet.mock.calls[0] ?? [];

        expect(url).toContain('window=7');
        expect(params).toEqual({});
        expect(options).toMatchObject({
            preserveScroll: true,
            preserveState: true,
            only: ['analytics'],
        });
    });

    it('free ve totais e por imagem, com a nota de upgrade e sem CSV', () => {
        const wrapper = montar(
            paidAnalytics({
                isPaid: false,
                window: 30,
                windows: [],
                days: [],
                referers: [],
                countries: [],
                formats: [],
                cache: null,
                exportUrl: null,
            }),
        );

        expect(wrapper.find('[data-test="badgeforge-analytics"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="badgeforge-analytics-chart"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="badgeforge-analytics-export"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="badgeforge-analytics-free-note"]').text())
            .toContain('NOTA_FREE_30');
        expect(wrapper.find('[data-test="badgeforge-analytics-upgrade"]').attributes('href'))
            .toBe('/pricing');
    });

    it('avisa quando a secao esta indisponivel', () => {
        const wrapper = montar(null);

        expect(wrapper.find('[data-test="badgeforge-analytics-unavailable"]').text())
            .toContain('ANALYTICS_INDISPONIVEL');
        expect(wrapper.find('[data-test="badgeforge-analytics"]').exists()).toBe(false);
    });
});
