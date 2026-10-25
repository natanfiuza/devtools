import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Contact from '@/pages/Contact.vue';

const { mockFormState, mockPost } = vi.hoisted(() => ({
    mockFormState: {
        name: '',
        email: '',
        subject: 'general',
        message: '',
        hp_website: '',
        errors: {} as Record<string, string>,
        processing: false,
    },
    mockPost: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            localeSlug: 'pt-br',
            seo: {
                meta: { title: 'Contato', description: 'Fale conosco' },
            },
        },
    }),
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
    useForm: (initial: Record<string, unknown>) => {
        Object.assign(mockFormState, initial);

        return {
            ...mockFormState,
            post: mockPost,
            reset: vi.fn(),
        };
    },
}));

const traducoes = {
    contact: {
        title: 'Fale com a gente',
        subtitle: 'Tem alguma dúvida? Mande uma mensagem.',
        fields: {
            name: 'Nome',
            name_placeholder: 'Seu nome',
            email: 'E-mail',
            email_placeholder: "seu{'@'}email.com",
            email_hint: 'Usaremos este e-mail exclusivamente para responder.',
            subject: 'Assunto',
            subject_placeholder: 'Selecione o motivo',
            message: 'Mensagem',
            message_placeholder: 'Descreva sua mensagem…',
            characters: '{count} / {max} caracteres',
        },
        subjects: {
            general: 'Dúvida geral',
            donation: 'Problema com doação / pagamento',
            feedback: 'Sugestão ou melhoria',
            bug: 'Reportar bug',
            other: 'Outro assunto',
        },
        submit: 'Enviar mensagem',
        submitting: 'Enviando…',
        success_title: 'Mensagem enviada com sucesso!',
        success_body: 'Recebemos sua mensagem.',
        send_another: 'Enviar outra mensagem',
        sent_toast: 'Sua mensagem foi enviada com sucesso!',
        throttle: 'Muitas mensagens enviadas.',
    },
};

function montarPagina(
    props: { name: string | null; email: string | null; status: string | null } = {
        name: null,
        email: null,
        status: null,
    },
): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Contact, {
        props,
        global: {
            plugins: [i18n],
            stubs: {
                PageHead: true,
            },
        },
    });
}

describe('Contact.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockFormState.name = '';
        mockFormState.email = '';
        mockFormState.subject = 'general';
        mockFormState.message = '';
        mockFormState.hp_website = '';
        mockFormState.errors = {};
        mockFormState.processing = false;
    });

    it('renderiza os campos principais do formulario', () => {
        const wrapper = montarPagina();

        expect(wrapper.find('[data-test="contact-form"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="contact-name"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="contact-email"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="contact-subject-trigger"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="contact-message"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="contact-submit"]').exists()).toBe(true);
    });

    it('pre-preenche os campos quando dados do usuario sao fornecidos', () => {
        const wrapper = montarPagina({
            name: 'Natan Fiuza',
            email: 'natan@exemplo.com',
            status: null,
        });

        expect(wrapper.find('[data-test="contact-name"]').attributes('value') || mockFormState.name).toBe('Natan Fiuza');
        expect(wrapper.find('[data-test="contact-email"]').attributes('value') || mockFormState.email).toBe('natan@exemplo.com');
    });

    it('dispara o envio do formulario no submit', async () => {
        const wrapper = montarPagina();

        await wrapper.find('[data-test="contact-form"]').trigger('submit');

        expect(mockPost).toHaveBeenCalledTimes(1);
    });

    it('exibe o banner de sucesso quando status for sent', () => {
        const wrapper = montarPagina({
            name: null,
            email: null,
            status: 'sent',
        });

        expect(wrapper.find('[data-test="contact-success-banner"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="contact-form"]').exists()).toBe(false);
    });
});
