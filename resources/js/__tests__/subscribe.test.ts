import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Subscribe from '@/pages/Subscribe.vue';
import type {
    CheckoutSummary,
    PendingSubscriptionIntent,
    PublicPlan,
} from '@/types';

const { mockRouter } = vi.hoisted(() => ({
    mockRouter: {
        visit: vi.fn(),
        post: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            localeSlug: 'pt-br',
            seo: {
                meta: { title: 'Assinar Pro', description: 'Assine o Pro' },
            },
        },
    }),
    router: mockRouter,
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
    Form: defineComponent({
        name: 'InertiaForm',
        props: {
            action: { type: String, default: '' },
            method: { type: String, default: 'post' },
        },
        render() {
            return h(
                'form',
                { action: this.action, method: this.method },
                this.$slots.default?.({ errors: {}, processing: false }),
            );
        },
    }),
}));

const traducoes = {
    home: {
        pricing: {
            interval: { month: 'mês', year: 'ano' },
            yearly_badge: '2 meses grátis',
            advantages: {
                title: 'VANTAGENS_TITULO',
                subtitle: 'VANTAGENS_SUBTITULO',
                items: {
                    username: { title: 'USER_TITULO', body: 'USER_CORPO' },
                    history: { title: 'HIST_TITULO', body: 'HIST_CORPO' },
                    quota: { title: 'COTA_TITULO', body: 'COTA_CORPO' },
                    priority: { title: 'PRIO_TITULO', body: 'PRIO_CORPO' },
                    discount: { title: 'DESC_TITULO', body: 'DESC_CORPO' },
                },
            },
        },
        subscribe: {
            title: 'INTENCAO_TITULO',
            subtitle: 'INTENCAO_SUBTITULO',
            pending_title: 'PEDIDO_REGISTRADO',
            pending_body: 'PEDIDO_CORPO',
            submitted_toast: 'TOAST_SUCESSO',
            plan_label: 'ESCOLHA_PLANO',
            username_label: 'SEU_USERNAME',
            username_placeholder: 'username',
            username_help: 'AJUDA_USERNAME',
            phone_label: 'TELEFONE_WHATSAPP',
            phone_country_code: 'CODIGO_PAIS',
            phone_placeholder: '(11) 99999-9999',
            document_label: 'CPF_OU_CNPJ_OPCIONAL',
            document_placeholder: 'DOC_PLACEHOLDER',
            redirect_countdown: 'REDIRECIONANDO_EM_{seconds}S',
            redirect_now: 'IR_AGORA',
            redirect_cancel: 'PERMANECER_AQUI',
            payment_method_label: 'FORMA_PAGAMENTO',
            payment_methods: {
                pix: 'Pix',
                credit_card: 'Cartão de crédito',
                boleto: 'Boleto bancário',
            },
            notes_label: 'OBSERVACOES',
            notes_placeholder: 'OBS_PLACEHOLDER',
            submit: 'REGISTRAR_PEDIDO',
            update_submit: 'ATUALIZAR_PEDIDO',
            checkout: {
                unavailable: 'PAGAMENTO_ONLINE_INDISPONIVEL',
                path_label: 'COMO_QUER_ASSINAR',
                paths: {
                    one_time: {
                        title: 'PAGAR_AGORA',
                        body: 'PAGAR_AGORA_CORPO',
                    },
                    recurring: {
                        title: 'RENOVACAO_AUTOMATICA',
                        body: 'RENOVACAO_CORPO',
                    },
                    manual: {
                        title: 'ATENDIMENTO_MANUAL',
                        body: 'MANUAL_CORPO',
                    },
                },
                recurring_badge: 'RENOVACAO_ATIVA',
                recurring_blocked: 'RENOVACAO_BLOQUEADA',
                redirect_note: 'REDIRECIONA_MERCADO_PAGO',
                one_time_action: 'ACAO_PAGAR_AGORA',
                recurring_action: 'ACAO_ATIVAR_RENOVACAO',
                errors: {
                    unavailable: 'ERRO_INDISPONIVEL',
                    recurring_active: 'ERRO_RENOVACAO_ATIVA',
                    plan_unavailable: 'ERRO_PLANO',
                    gateway: 'ERRO_GATEWAY',
                },
                status: {
                    processing: {
                        title: 'PAGAMENTO_PROCESSANDO',
                        body: 'PROCESSANDO_CORPO',
                    },
                    approved: {
                        title: 'PAGAMENTO_APROVADO',
                        body: 'APROVADO_CORPO',
                    },
                    expired: {
                        title: 'CHECKOUT_EXPIRADO',
                        body: 'EXPIRADO_CORPO',
                    },
                    failed: {
                        title: 'PAGAMENTO_FALHOU',
                        body: 'FALHOU_CORPO',
                    },
                },
            },
        },
    },
};

const PLANOS_TESTE: PublicPlan[] = [
    {
        slug: 'pro-monthly',
        name: 'Pro mensal',
        price: 'R$ 8,90',
        isFree: false,
        interval: 'month',
        monthlyTokens: '2.000.000',
        lifetimeFreeUses: '0',
        promptChars: '4.000',
        schemaSize: '64 KB',
    },
    {
        slug: 'pro-yearly',
        name: 'Pro anual',
        price: 'R$ 89,00',
        isFree: false,
        interval: 'year',
        monthlyTokens: '2.000.000',
        lifetimeFreeUses: '0',
        promptChars: '4.000',
        schemaSize: '64 KB',
    },
];

function montar(
    props: {
        plans?: PublicPlan[];
        selectedPlan?: string;
        username?: string;
        pendingIntent?: PendingSubscriptionIntent | null;
        status?: string | null;
        onlineCheckoutAvailable?: boolean;
        recurringPlanSlugs?: string[];
        checkout?: CheckoutSummary | null;
    } = {},
): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Subscribe, {
        props: {
            plans: props.plans ?? PLANOS_TESTE,
            selectedPlan: props.selectedPlan ?? 'pro-monthly',
            username: props.username ?? 'natan',
            pendingIntent: props.pendingIntent ?? null,
            status: props.status ?? null,
            onlineCheckoutAvailable: props.onlineCheckoutAvailable ?? true,
            recurringPlanSlugs: props.recurringPlanSlugs ?? [],
            checkout: props.checkout ?? null,
        },
        global: {
            plugins: [i18n],
        },
    });
}

describe('Subscribe', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        mockRouter.visit.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('renderiza o campo documento como opcional (sem atributo required)', () => {
        const wrapper = montar({ onlineCheckoutAvailable: false });

        const inputDoc = wrapper.get('input#document');
        expect(inputDoc.attributes('required')).toBeUndefined();
        expect(wrapper.text()).toContain('CPF_OU_CNPJ_OPCIONAL');
    });

    it('renderiza o seletor de DDI com valor padrao do idioma e campo de telefone', () => {
        const wrapper = montar({ onlineCheckoutAvailable: false });

        const hiddenCountry = wrapper.get('input[name="country_code"]');
        expect(hiddenCountry.attributes('value')).toBe('+55');

        const inputPhone = wrapper.get('input#phone');
        expect(inputPhone.attributes('required')).toBeDefined();
    });

    it('extrai o DDI e o numero local quando pendingIntent ja possui telefone com codigo internacional', () => {
        const wrapper = montar({
            onlineCheckoutAvailable: false,
            pendingIntent: {
                id: 1,
                planSlug: 'pro-monthly',
                username: 'natan',
                phone: '+351 912345678',
                document: null,
                paymentMethod: 'pix',
                notes: '',
                createdAt: '18/10/2026',
            },
        });

        const hiddenCountry = wrapper.get('input[name="country_code"]');
        expect(hiddenCountry.attributes('value')).toBe('+351');

        const inputPhone = wrapper.get<HTMLInputElement>('input#phone');
        expect(inputPhone.element.value).toBe('912345678');
    });

    it('inicia o contador decrescente e redireciona ao expirar quando status e intent-submitted', async () => {
        const wrapper = montar({ status: 'intent-submitted' });

        const banner = wrapper.get('[data-test="subscribe-pending-banner"]');
        expect(banner.text()).toContain('PEDIDO_REGISTRADO');

        const countdown = wrapper.get('[data-test="subscribe-countdown"]');
        expect(countdown.text()).toContain('REDIRECIONANDO_EM_30S');

        // Avanca 10 segundos
        await vi.advanceTimersByTimeAsync(10000);
        expect(countdown.text()).toContain('REDIRECIONANDO_EM_20S');
        expect(mockRouter.visit).not.toHaveBeenCalled();

        // Avanca mais 20 segundos para zerar
        await vi.advanceTimersByTimeAsync(20000);
        expect(mockRouter.visit).toHaveBeenCalledWith('/pt-br');
    });

    it('permite cancelar o redirecionamento pelo botao permanecer', async () => {
        const wrapper = montar({ status: 'intent-submitted' });

        expect(wrapper.find('[data-test="subscribe-countdown"]').exists()).toBe(
            true,
        );

        const btnCancel = wrapper.get(
            '[data-test="subscribe-redirect-cancel"]',
        );
        await btnCancel.trigger('click');

        expect(wrapper.find('[data-test="subscribe-countdown"]').exists()).toBe(
            false,
        );

        // Avanca 40 segundos e garante que o visit nao foi chamado
        vi.advanceTimersByTime(40000);
        expect(mockRouter.visit).not.toHaveBeenCalled();
    });

    it('permite redirecionar imediatamente pelo botao ir agora', async () => {
        const wrapper = montar({ status: 'intent-submitted' });

        const btnNow = wrapper.get('[data-test="subscribe-redirect-now"]');
        await btnNow.trigger('click');

        expect(mockRouter.visit).toHaveBeenCalledWith('/pt-br');
    });

    it('abre o pagamento avulso com o plano escolhido', async () => {
        const wrapper = montar();

        expect(wrapper.find('[data-test="subscribe-form"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.get('[data-test="subscribe-online-panel"]').text(),
        ).toContain('ACAO_PAGAR_AGORA');

        await wrapper
            .get('[data-test="subscribe-checkout-submit"]')
            .trigger('click');

        expect(mockRouter.post).toHaveBeenCalledTimes(1);

        const [url, data] = mockRouter.post.mock.calls[0] as [
            string,
            Record<string, string>,
        ];

        expect(url).toBe('/subscribe/checkout');
        expect(data).toEqual({ plan: 'pro-monthly', mode: 'one_time' });
    });

    it('alterna para renovacao automatica e envia o modo recorrente', async () => {
        const wrapper = montar();

        const recurring = wrapper.get('[data-test="subscribe-path-recurring"]');
        await recurring.trigger('click');

        expect(recurring.attributes('aria-pressed')).toBe('true');

        await wrapper
            .get('[data-test="subscribe-checkout-submit"]')
            .trigger('click');

        expect(mockRouter.post).toHaveBeenCalledWith(
            '/subscribe/checkout',
            { plan: 'pro-monthly', mode: 'recurring' },
            expect.any(Object),
        );
    });

    it('bloqueia nova renovacao no mesmo plano e libera no outro', async () => {
        const wrapper = montar({ recurringPlanSlugs: ['pro-monthly'] });

        expect(
            wrapper.get('[data-test="subscribe-plan-pro-monthly"]').text(),
        ).toContain('RENOVACAO_ATIVA');

        const recurring = wrapper.get('[data-test="subscribe-path-recurring"]');
        expect(recurring.attributes('disabled')).toBeDefined();

        await recurring.trigger('click');
        expect(recurring.attributes('aria-pressed')).toBe('false');

        await wrapper
            .get('[data-test="subscribe-plan-pro-yearly"]')
            .trigger('click');

        expect(recurring.attributes('disabled')).toBeUndefined();
    });

    it('sem credenciais mostra o aviso e mantem o atendimento manual', () => {
        const wrapper = montar({ onlineCheckoutAvailable: false });

        expect(
            wrapper.get('[data-test="subscribe-online-unavailable"]').text(),
        ).toContain('PAGAMENTO_ONLINE_INDISPONIVEL');

        expect(wrapper.find('[data-test="subscribe-form"]').exists()).toBe(
            true,
        );
        expect(
            wrapper
                .get('[data-test="subscribe-path-one_time"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .get('[data-test="subscribe-path-manual"]')
                .attributes('aria-pressed'),
        ).toBe('true');
    });

    it('mostra o banner de checkout pendente', () => {
        const wrapper = montar({
            checkout: {
                ulid: '01ABC',
                status: 'pending',
                mode: 'recurring',
                planSlug: 'pro-monthly',
                createdAt: '21/10/2026',
            },
        });

        const banner = wrapper.get('[data-test="subscribe-checkout-banner"]');

        expect(banner.attributes('data-status')).toBe('pending');
        expect(banner.text()).toContain('PAGAMENTO_PROCESSANDO');
    });
});
