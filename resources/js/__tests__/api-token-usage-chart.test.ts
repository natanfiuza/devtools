import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import ApiTokenUsageChart from '@/components/settings/ApiTokenUsageChart.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import type { ApiTokenUsageStats } from '@/types';

const mockTranslations = {
    settings: {
        api_tokens: {
            usage_chart: {
                title: 'Estatísticas de consumo de IA',
                description: 'Acompanhe a quantidade diária de tokens de IA consumidos e requisições via tokens de API.',
                views: {
                    total: 'Total diário',
                    by_token: 'Por token de API',
                },
                periods: {
                    current_month: 'Mês atual',
                    previous_month: 'Mês anterior',
                    custom: 'Personalizado',
                },
                custom: {
                    start_date: 'Data inicial',
                    end_date: 'Data final',
                    apply: 'Aplicar',
                },
                summary: {
                    tokens: 'Tokens consumidos',
                    requests: 'Requisições',
                },
                tooltip: {
                    date: 'Data',
                    total_tokens: '{count} tokens',
                    requests: '{count} requisições',
                    share: '{percent}% do dia',
                },
                legend: {
                    revoked_tokens: 'Tokens revogados',
                    tokens: 'Tokens',
                },
                empty: {
                    title: 'Nenhum consumo no período',
                    description: 'Nenhuma consulta foi realizada pelos tokens de API nas datas selecionadas.',
                },
            },
        },
    },
};

function i18nMock() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: mockTranslations });
    return i18n;
}

const mockStats: ApiTokenUsageStats = {
    period: {
        type: 'current_month',
        startDate: '2026-10-01',
        endDate: '2026-10-31',
    },
    days: [
        {
            date: '2026-10-01',
            dayLabel: '01',
            formattedDate: '01/10/2026',
            totalTokens: 0,
            totalRequests: 0,
            breakdown: [],
        },
        {
            date: '2026-10-02',
            dayLabel: '02',
            formattedDate: '02/10/2026',
            totalTokens: 1500,
            totalRequests: 2,
            breakdown: [
                {
                    tokenId: 1,
                    tokenName: 'Token Prod',
                    tokens: 1000,
                    requests: 1,
                    isRevoked: false,
                },
                {
                    tokenId: 'revoked',
                    tokenName: 'Tokens revogados',
                    tokens: 500,
                    requests: 1,
                    isRevoked: true,
                },
            ],
        },
    ],
    tokens: [
        { id: 1, name: 'Token Prod', isRevoked: false },
        { id: 'revoked', name: 'Tokens revogados', isRevoked: true },
    ],
    summary: {
        totalTokens: 1500,
        totalRequests: 2,
    },
};

function mountChart(stats: ApiTokenUsageStats = mockStats): VueWrapper {
    return mount(ApiTokenUsageChart, {
        props: { stats },
        global: { plugins: [i18nMock()] },
    });
}

describe('ApiTokenUsageChart', () => {
    it('renderiza o card do gráfico com os totais de tokens e requisições', () => {
        const wrapper = mountChart();

        expect(wrapper.find('[data-test="api-tokens-usage-chart-card"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="chart-summary-tokens"]').text()).toContain('1.500');
        expect(wrapper.find('[data-test="chart-summary-requests"]').text()).toContain('2');
        expect(wrapper.find('[data-test="api-tokens-usage-svg"]').exists()).toBe(true);
    });

    it('permite alternar entre as visões Total diário e Por token de API', async () => {
        const wrapper = mountChart();

        const totalBtn = wrapper.find('[data-test="chart-view-total-btn"]');
        const byTokenBtn = wrapper.find('[data-test="chart-view-by-token-btn"]');

        expect(totalBtn.exists()).toBe(true);
        expect(byTokenBtn.exists()).toBe(true);

        // Por padrão inicia em 'total', sem exibir a legenda
        expect(wrapper.find('[data-test="chart-tokens-legend"]').exists()).toBe(false);

        // Clica em 'Por token'
        await byTokenBtn.trigger('click');

        // Agora a legenda deve aparecer
        expect(wrapper.find('[data-test="chart-tokens-legend"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Token Prod');
        expect(wrapper.text()).toContain('Tokens revogados');

        // Clica de volta em 'Total'
        await totalBtn.trigger('click');
        expect(wrapper.find('[data-test="chart-tokens-legend"]').exists()).toBe(false);
    });

    it('exibe controles de data customizada e emite evento ao aplicar', async () => {
        const customStats: ApiTokenUsageStats = {
            ...mockStats,
            period: {
                type: 'custom',
                startDate: '2026-10-05',
                endDate: '2026-10-20',
            },
        };

        const wrapper = mountChart(customStats);

        expect(wrapper.find('[data-test="chart-custom-range-controls"]').exists()).toBe(true);

        const startInput = wrapper.find<HTMLInputElement>('[data-test="chart-custom-start-input"]');
        const endInput = wrapper.find<HTMLInputElement>('[data-test="chart-custom-end-input"]');
        const applyBtn = wrapper.find('[data-test="chart-custom-apply-btn"]');

        expect(startInput.element.value).toBe('2026-10-05');
        expect(endInput.element.value).toBe('2026-10-20');

        await startInput.setValue('2026-10-10');
        await endInput.setValue('2026-10-25');
        await applyBtn.trigger('click');

        expect(wrapper.emitted('periodChange')).toBeTruthy();
        expect(wrapper.emitted('periodChange')![0]).toEqual([
            {
                period: 'custom',
                startDate: '2026-10-10',
                endDate: '2026-10-25',
            },
        ]);
    });

    it('exibe estado vazio quando não há consumo no período', () => {
        const emptyStats: ApiTokenUsageStats = {
            period: {
                type: 'current_month',
                startDate: '2026-10-01',
                endDate: '2026-10-31',
            },
            days: [
                {
                    date: '2026-10-01',
                    dayLabel: '01',
                    formattedDate: '01/10/2026',
                    totalTokens: 0,
                    totalRequests: 0,
                    breakdown: [],
                },
            ],
            tokens: [],
            summary: {
                totalTokens: 0,
                totalRequests: 0,
            },
        };

        const wrapper = mountChart(emptyStats);

        expect(wrapper.find('[data-test="chart-empty-state"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Nenhum consumo no período');
    });
});
