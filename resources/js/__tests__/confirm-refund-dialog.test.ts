import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import ConfirmRefundDialog from '@/components/admin/ConfirmRefundDialog.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import type { AdminPaymentRow } from '@/types';

const { routerPost } = vi.hoisted(() => ({
    routerPost: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        post: routerPost,
    },
}));

const samplePayment: AdminPaymentRow = {
    id: 42,
    account: 'cliente@exemplo.com',
    plan: 'Pro Mensal',
    amount: 'R$ 29,90',
    method: 'pix',
    status: 'paid',
    paidAt: '2026-10-23 10:00',
    note: null,
};

const traducoes = {
    admin: {
        common: {
            cancel: 'CANCELAR',
        },
        payments: {
            refund: 'ESTORNAR',
            refund_confirm: 'CONFIRMAR_ESTORNO',
            account: 'CONTA',
            plan: 'PLANO',
            plan_none: 'SEM_PLANO',
            amount: 'VALOR',
            method: 'FORMA',
            methods: {
                pix: 'PIX',
            },
        },
    },
};

function i18nDoTeste() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });
    return i18n;
}

describe('ConfirmRefundDialog', () => {
    beforeEach(() => {
        routerPost.mockReset();
        document.body.innerHTML = '';
    });

    it('nao renderiza o conteudo aberto quando payment e nulo', () => {
        const wrapper = mount(ConfirmRefundDialog, {
            props: {
                payment: null,
            },
            global: {
                plugins: [i18nDoTeste()],
            },
        });

        expect(document.body.innerHTML).not.toContain('CONFIRMAR_ESTORNO');
        wrapper.unmount();
    });

    it('exibe titulo, descricao e card com resumo do pagamento quando aberto', async () => {
        const wrapper = mount(ConfirmRefundDialog, {
            props: {
                payment: samplePayment,
            },
            global: {
                plugins: [i18nDoTeste()],
            },
        });

        await nextTick();

        expect(document.body.innerHTML).toContain('ESTORNAR');
        expect(document.body.innerHTML).toContain('CONFIRMAR_ESTORNO');
        expect(document.body.innerHTML).toContain('cliente@exemplo.com');
        expect(document.body.innerHTML).toContain('Pro Mensal');
        expect(document.body.innerHTML).toContain('R$ 29,90');
        expect(document.body.innerHTML).toContain('PIX');

        wrapper.unmount();
    });

    it('emite evento close ao clicar no botao cancelar', async () => {
        const wrapper = mount(ConfirmRefundDialog, {
            props: {
                payment: samplePayment,
            },
            global: {
                plugins: [i18nDoTeste()],
            },
        });

        await nextTick();

        const cancelButton = document.querySelector<HTMLButtonElement>(
            'button[type="button"]:not([data-test="confirm-refund-submit"])',
        );
        expect(cancelButton).not.toBeNull();
        cancelButton?.click();
        await nextTick();

        expect(wrapper.emitted('close')).toHaveLength(1);
        wrapper.unmount();
    });

    it('dispara post de estorno e trava botoes durante a requisicao', async () => {
        const wrapper = mount(ConfirmRefundDialog, {
            props: {
                payment: samplePayment,
            },
            global: {
                plugins: [i18nDoTeste()],
            },
        });

        await nextTick();

        const submitButton = document.querySelector<HTMLButtonElement>(
            '[data-test="confirm-refund-submit"]',
        );
        expect(submitButton).not.toBeNull();

        submitButton?.click();
        await nextTick();

        expect(routerPost).toHaveBeenCalledWith(
            expect.stringContaining('admin/payments/42/refund'),
            {},
            expect.objectContaining({ preserveScroll: true }),
        );

        // Durante o voo da requisicao, o botao fica desabilitado
        expect(submitButton?.disabled).toBe(true);

        // Simulando a finalizacao da requisicao via onFinish
        const postOptions = routerPost.mock.calls[0]?.[2] as
            { onFinish?: () => void } | undefined;
        postOptions?.onFinish?.();
        await nextTick();

        expect(wrapper.emitted('close')).toHaveLength(1);
        wrapper.unmount();
    });
});
