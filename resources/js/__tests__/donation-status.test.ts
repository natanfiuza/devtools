import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import DonationStatus from '@/pages/DonationStatus.vue';
import type { DonationStatusProps } from '@/types';

const { mockRouter, pollStart, pollStop, mockUsePoll } = vi.hoisted(() => {
    const pollStart = vi.fn();
    const pollStop = vi.fn();

    return {
        mockRouter: {
            post: vi.fn(),
            visit: vi.fn(),
        },
        pollStart,
        pollStop,
        mockUsePoll: vi.fn(() => ({ start: pollStart, stop: pollStop })),
    };
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { localeSlug: 'pt-br' } }),
    router: mockRouter,
    usePoll: mockUsePoll,
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
}));

const traducoes = {
    donations: {
        status: {
            title: 'TITULO_STATUS',
            pending: 'PENDENTE',
            paid: 'PAGA',
            expired: 'EXPIRADA',
            refunded: 'DEVOLVIDA',
            failed: 'FALHOU',
            pending_hint: 'AVISO_PENDENTE',
            paid_hint: 'AVISO_PAGA',
            expired_hint: 'AVISO_EXPIRADA',
            refunded_hint: 'AVISO_DEVOLVIDA',
            failed_hint: 'AVISO_GENERICO',
            amount: 'VALOR',
            created_at: 'CRIADA',
            expires_at: 'EXPIRA',
            receipt: 'RECIBO',
            new: 'NOVA_DOACAO',
        },
        pix: {
            title: 'PIX_TITULO',
            description: 'PIX_DESCRICAO',
            copy: 'COPIAR',
            copy_failed: 'FALHA_COPIAR',
            copied: 'COPIADO',
            ticket: 'BILHETE',
            static_note: 'NOTA_ESTATICA',
            dynamic_note: 'NOTA_DINAMICA',
        },
        check: {
            button: 'BOTAO_JA_PAGUEI',
            loading: 'CONSULTANDO',
            hint: 'DICA_CONSULTA',
            error: 'ERRO_CONSULTA',
        },
    },
};

type DadosDoacao = DonationStatusProps['donation'];

type OpcoesPost = {
    onError?: () => void;
    onFinish?: () => void;
};

const ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

function dadosDoacao(overrides: Partial<DadosDoacao> = {}): DadosDoacao {
    return {
        ulid: ULID,
        status: 'pending',
        amount: 'R$ 25,00',
        createdAt: '21/10/2026 10:00',
        paidAt: null,
        expiresAt: '21/10/2026 11:00',
        qrCode: '000201...',
        qrImage: 'data:image/png;base64,abc',
        ticketUrl: null,
        static: false,
        canCheck: true,
        failureReason: null,
        ...overrides,
    };
}

function montar(
    props: { pollMs?: number; donation?: Partial<DadosDoacao> } = {},
): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(DonationStatus, {
        props: {
            pollMs: props.pollMs ?? 15000,
            donation: dadosDoacao(props.donation),
        },
        global: {
            plugins: [i18n],
        },
    });
}

describe('DonationStatus', () => {
    beforeEach(() => {
        mockRouter.post.mockClear();
        mockUsePoll.mockClear();
        pollStart.mockClear();
        pollStop.mockClear();
    });

    it('mostra o motivo da recusa no lugar do aviso generico', () => {
        const wrapper = montar({
            donation: {
                status: 'failed',
                canCheck: false,
                failureReason: 'MOTIVO_BANCO',
            },
        });

        expect(wrapper.get('[data-test="donation-status-hint"]').text()).toBe(
            'MOTIVO_BANCO',
        );
        expect(wrapper.text()).not.toContain('AVISO_GENERICO');
    });

    it('usa o aviso do estado quando nao ha motivo especifico', () => {
        const wrapper = montar({
            donation: { status: 'failed', canCheck: false },
        });

        expect(wrapper.get('[data-test="donation-status-hint"]').text()).toBe(
            'AVISO_GENERICO',
        );
    });

    it('so oferece o botao quando ha cobranca para consultar', () => {
        const comConsulta = montar();
        expect(comConsulta.find('[data-test="donation-check"]').exists()).toBe(
            true,
        );

        const semConsulta = montar({ donation: { canCheck: false } });
        expect(semConsulta.find('[data-test="donation-check"]').exists()).toBe(
            false,
        );
    });

    it('consulta o provedor pelo botao e mostra o carregamento', async () => {
        const wrapper = montar();

        await wrapper.get('[data-test="donation-check"]').trigger('click');

        expect(mockRouter.post).toHaveBeenCalledTimes(1);

        const [url, data, options] = mockRouter.post.mock.calls[0] as [
            string,
            Record<string, unknown>,
            OpcoesPost,
        ];

        expect(url).toBe(`/pt-br/donate/${ULID}/check`);
        expect(data).toEqual({});

        const botao = wrapper.get('[data-test="donation-check"]');
        expect(botao.attributes('aria-busy')).toBe('true');
        expect(botao.attributes('disabled')).toBeDefined();
        expect(botao.text()).toContain('CONSULTANDO');
        expect(
            wrapper.find('[data-test="donation-check-failed"]').exists(),
        ).toBe(false);

        // Erro de rede/sessao: a mensagem local aparece sem toast do servidor.
        options.onError?.();
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[data-test="donation-check-failed"]').text()).toBe(
            'ERRO_CONSULTA',
        );

        // O servidor respondeu e a navegacao terminou: volta ao estado normal.
        options.onFinish?.();
        await wrapper.vm.$nextTick();

        const concluido = wrapper.get('[data-test="donation-check"]');
        expect(concluido.attributes('aria-busy')).toBe('false');
        expect(concluido.attributes('disabled')).toBeUndefined();
        expect(concluido.text()).toContain('BOTAO_JA_PAGUEI');
    });

    it('inicia o auto-refresh com doacao pendente e intervalo ligado', () => {
        const wrapper = montar();

        expect(mockUsePoll).toHaveBeenCalledWith(
            15000,
            { only: ['donation'] },
            { autoStart: false, background: 'pause' },
        );
        expect(pollStart).toHaveBeenCalledTimes(1);
        expect(pollStop).not.toHaveBeenCalled();

        wrapper.unmount();

        expect(pollStop).toHaveBeenCalled();
    });

    it('nao inicia o auto-refresh com o intervalo desligado', () => {
        montar({ pollMs: 0 });

        expect(mockUsePoll).toHaveBeenCalledWith(
            1000,
            { only: ['donation'] },
            { autoStart: false, background: 'pause' },
        );
        expect(pollStart).not.toHaveBeenCalled();
    });

    it('nao inicia o auto-refresh para doacao ja resolvida', () => {
        montar({
            donation: { status: 'paid', paidAt: '21/10/2026 10:05' },
        });

        expect(pollStart).not.toHaveBeenCalled();
    });

    it('para o auto-refresh quando a doacao deixa de estar pendente', async () => {
        const wrapper = montar();

        expect(pollStart).toHaveBeenCalledTimes(1);
        expect(pollStop).not.toHaveBeenCalled();

        await wrapper.setProps({
            donation: dadosDoacao({
                status: 'paid',
                paidAt: '21/10/2026 10:05',
                expiresAt: null,
            }),
        });

        expect(pollStart).toHaveBeenCalledTimes(1);
        expect(pollStop).toHaveBeenCalledTimes(1);
    });
});
