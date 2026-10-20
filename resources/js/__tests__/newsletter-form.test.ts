import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import NewsletterForm from '@/components/NewsletterForm.vue';

/**
 * Inscricao na newsletter pelo rodape (ADR-024).
 *
 * O formulario e so o primeiro passo do double opt-in: ele entrega o e-mail
 * para a rota do idioma da pagina e o servidor responde com o aviso. As
 * traducoes sao marcadores para denunciar texto fixo no componente.
 */

const { pageProps, formErrors } = vi.hoisted(() => ({
    pageProps: { localeSlug: 'pt-br' },
    formErrors: {} as Record<string, string>,
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps }),
    Form: defineComponent({
        name: 'InertiaForm',
        props: {
            action: { type: String, default: '' },
            method: { type: String, default: 'post' },
            resetOnSuccess: { type: Array, default: () => [] },
        },
        render() {
            return h(
                'form',
                { action: this.action, method: this.method },
                this.$slots.default?.({
                    errors: formErrors,
                    processing: false,
                    hasErrors: Object.keys(formErrors).length > 0,
                    recentlySuccessful: false,
                }),
            );
        },
    }),
}));

const traducoes = {
    newsletter: {
        form: {
            label: 'ROTULO',
            description: 'DESCRICAO',
            email: 'EMAIL',
            email_placeholder: 'DICA_EMAIL',
            submit: 'ASSINAR',
        },
    },
};

function montarFormulario(): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(NewsletterForm, { global: { plugins: [i18n] } });
}

beforeEach(() => {
    pageProps.localeSlug = 'pt-br';

    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
});

describe('conteudo', () => {
    it('escreve rotulo, descricao e botao pelo i18n', () => {
        const wrapper = montarFormulario();

        expect(wrapper.text()).toContain('ROTULO');
        expect(wrapper.text()).toContain('DESCRICAO');
        expect(
            wrapper.get('[data-test="newsletter-subscribe-button"]').text(),
        ).toBe('ASSINAR');
    });

    it('da nome acessivel ao campo de e-mail', () => {
        const wrapper = montarFormulario();
        const campo = wrapper.get('#newsletter-email');

        expect(wrapper.get('label[for="newsletter-email"]').text()).toBe(
            'EMAIL',
        );
        expect(campo.attributes('type')).toBe('email');
        expect(campo.attributes('placeholder')).toBe('DICA_EMAIL');
        expect(campo.attributes('autocomplete')).toBe('email');
        expect(campo.attributes('required')).toBeDefined();
    });

    it('mostra o erro que o servidor devolveu', () => {
        formErrors.email = 'E-mail invalido.';

        const wrapper = montarFormulario();

        expect(wrapper.get('#newsletter-email-error').text()).toBe(
            'E-mail invalido.',
        );
    });

    it('mostra a mensagem de erro vazia quando nao ha erro', () => {
        const wrapper = montarFormulario();

        expect(wrapper.get('#newsletter-email-error').text()).toBe('');
    });
});

describe('envio', () => {
    it('posta para a newsletter do idioma da pagina', () => {
        const wrapper = montarFormulario();
        const form = wrapper.get('form');

        expect(form.attributes('action')).toBe('/pt-br/newsletter');
        expect(form.attributes('method')).toBe('post');
    });

    it('segue o idioma das props compartilhadas', () => {
        pageProps.localeSlug = 'fr';

        expect(montarFormulario().get('form').attributes('action')).toBe(
            '/fr/newsletter',
        );
    });
});
