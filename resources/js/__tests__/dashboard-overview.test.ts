import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Dashboard from '@/pages/Dashboard.vue';
import type { AccountOverviewProps } from '@/types';

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({
        name: 'HeadStub',
        props: ['title'],
        render() {
            return null;
        },
    }),
    Link: defineComponent({
        name: 'LinkStub',
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    }),
    usePage: () => ({
        props: {
            locale: 'pt_BR',
        },
    }),
}));

const traducoes = {
    account: {
        overview: {
            title: 'Visão geral',
            description: 'Seu plano, seu consumo e o seu próximo vencimento.',
            plan: 'Plano',
            plan_free: 'Gratuito',
            subscription: 'Assinatura',
            subscription_none: 'Sem assinatura ativa',
            subscription_manual: 'Cobrança manual',
            next_due: 'Próximo vencimento',
            no_due: 'Sem vencimento',
            quota: 'Cota',
            quota_limit: 'Franquia',
            quota_used: 'Usado',
            quota_remaining: 'Restante',
            quota_window: 'Período',
            quota_lifetime: 'Vitalício',
            quota_until: 'até {date}',
            quota_exhausted: 'A franquia deste plano acabou.',
            unit_tokens: 'tokens',
            unit_uses: 'usos',
            payments: 'Pagamentos',
            billing_link: 'Ver faturas',
            history_link: 'Ver histórico',
        },
    },
};

function i18nDoTeste() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });
    return i18n;
}

function baseProps(): AccountOverviewProps {
    return {
        plan: {
            name: 'Pro',
            interval: 'month',
            isFree: false,
            price: 'R$ 29,90',
            currency: 'BRL',
        },
        quota: {
            unit: 'tokens',
            limit: 24_000_000,
            used: 17_325,
            remaining: 23_982_675,
            exhausted: false,
            windowStart: '01/10/2026',
            windowEnd: '31/10/2026',
        },
        subscription: {
            plan: 'Pro',
            interval: 'month',
            gateway: 'manual',
            status: 'active',
            start: '01/10/2026',
            end: '31/10/2026',
            current: true,
            renewalCanceledAt: null,
            renewalFailedAt: null,
            renewable: false,
        },
        payments: 1,
    };
}

describe('Dashboard (Visão Geral)', () => {
    it('substitui {date} corretamente na janela da cota', () => {
        const wrapper = mount(Dashboard, {
            props: baseProps(),
            global: { plugins: [i18nDoTeste()] },
        });

        const janela = wrapper.get('[data-test="conta-janela"]');
        expect(janela.text()).toBe('até 31/10/2026');
        expect(janela.text()).not.toContain(':date');
    });

    it('mostra que a cota e vitalicia quando nao ha janela definida', () => {
        const props = baseProps();
        props.quota.windowStart = null;
        props.quota.windowEnd = null;

        const wrapper = mount(Dashboard, {
            props,
            global: { plugins: [i18nDoTeste()] },
        });

        const janela = wrapper.get('[data-test="conta-janela"]');
        expect(janela.text()).toBe('Vitalício');
    });

    it('mostra aviso de franquia esgotada quando exhausted for true', () => {
        const props = baseProps();
        props.quota.exhausted = true;

        const wrapper = mount(Dashboard, {
            props,
            global: { plugins: [i18nDoTeste()] },
        });

        const janela = wrapper.get('[data-test="conta-janela"]');
        expect(janela.text()).toBe('A franquia deste plano acabou.');
        expect(janela.classes()).toContain('text-destructive');
    });
});
