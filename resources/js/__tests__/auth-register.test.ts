import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { registerDirectives } from '@/lib/directives';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Register from '@/pages/auth/Register.vue';

/**
 * Tela de cadastro (ADR-022, ADR-023, ADR-036).
 *
 * O servidor entrega as props compartilhadas; aqui se verifica a fiacao da
 * tela: texto vindo do i18n, URLs com o idioma da pagina, suporte a mensagem
 * flash de status e os botoes sociais com acao de cadastro (?from=register).
 */

const { pageProps } = vi.hoisted(() => ({
    pageProps: {
        locale: 'pt_BR',
        localeSlug: 'pt-br',
        turnstileSiteKey: null as string | null,
        locales: [
            {
                code: 'pt_BR',
                slug: 'pt-br',
                name: 'Português (Brasil)',
                html: 'pt-BR',
            },
            { code: 'en', slug: 'en', name: 'English', html: 'en' },
        ],
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps }),
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => null,
    }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        render() {
            return h('a', { href: this.href }, this.$slots.default?.());
        },
    }),
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
                this.$slots.default?.({ errors: {}, processing: false }),
            );
        },
    }),
}));

const traducoes = {
    auth: {
        register: {
            title: 'TITULO_CADASTRO',
            description: 'DESCRICAO_CADASTRO',
            name: 'NOME',
            name_placeholder: 'DICA_NOME',
            email: 'EMAIL',
            email_placeholder: 'DICA_EMAIL',
            password: 'SENHA',
            password_confirmation: 'CONFIRMA_SENHA',
            locale: 'IDIOMA',
            locale_help: 'AJUDA_IDIOMA',
            newsletter: 'NEWSLETTER',
            terms: 'Ao criar {terms} e {privacy}',
            terms_link: 'TERMOS',
            privacy_link: 'PRIVACIDADE',
            submit: 'CADASTRAR',
            have_account: 'JA_TEM_CONTA',
            login: 'ENTRAR',
        },
        social: {
            continue_with: 'COM {provider}',
            login_with: 'ENTRAR COM {provider}',
            register_with: 'CADASTRAR COM {provider}',
            divider: 'DIVISOR',
            providers: { google: 'GOOGLE', github: 'GITHUB' },
        },
    },
};

function montarRegister(
    props: { status?: string; passwordRules?: string } = {},
): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Register, {
        props: { passwordRules: 'min:8', ...props },
        global: {
            plugins: [
                i18n,
                {
                    install: (app: Parameters<typeof registerDirectives>[0]) =>
                        registerDirectives(app),
                },
            ],
        },
    });
}

beforeEach(() => {
    pageProps.locale = 'pt_BR';
    pageProps.localeSlug = 'pt-br';
    pageProps.turnstileSiteKey = null;
});

describe('Turnstile', () => {
    it('nao renderiza o widget quando a chave publica nao esta configurada', () => {
        const wrapper = montarRegister();

        expect(wrapper.find('[data-test="turnstile"]').exists()).toBe(false);
        expect(wrapper.find('input[name="turnstile"]').exists()).toBe(false);
    });

    it('renderiza o widget e o campo oculto quando a chave existe', async () => {
        pageProps.turnstileSiteKey = 'site-key';

        const wrapper = montarRegister();
        await flushPromises();

        expect(wrapper.find('[data-test="turnstile"]').exists()).toBe(true);
        expect(wrapper.get('input[name="turnstile"]').attributes('type')).toBe(
            'hidden',
        );
    });
});

describe('textos e status', () => {
    it('escreve titulo, descricao e rotulos pelo i18n', () => {
        const wrapper = montarRegister();
        const texto = wrapper.text();

        expect(texto).toContain('TITULO_CADASTRO');
        expect(texto).toContain('DESCRICAO_CADASTRO');
        expect(texto).toContain('JA_TEM_CONTA');
    });

    it('mostra o aviso de status quando presente', () => {
        const wrapper = montarRegister({
            status: 'Entrar com Google ainda não está disponível.',
        });

        expect(wrapper.get('[role="status"]').text()).toBe(
            'Entrar com Google ainda não está disponível.',
        );
    });

    it('nao mostra aviso de status quando ausente', () => {
        expect(montarRegister().find('[role="status"]').exists()).toBe(false);
    });
});

describe('links e formulario', () => {
    it('manda o formulario para o cadastro do idioma da pagina', () => {
        const wrapper = montarRegister();
        const form = wrapper.get('form');

        expect(form.attributes('action')).toBe('/pt-br/register');
        expect(form.attributes('method')).toBe('post');
    });

    it('oferece link para o login', () => {
        const wrapper = montarRegister();

        expect(wrapper.get('a[href="/pt-br/login"]').text()).toBe('ENTRAR');
    });
});

describe('botoes sociais no cadastro', () => {
    it('mostra Google e GitHub com o rotulo de cadastro', () => {
        const wrapper = montarRegister();

        expect(wrapper.get('[data-test="social-google"]').text()).toBe(
            'CADASTRAR COM GOOGLE',
        );
        expect(wrapper.get('[data-test="social-github"]').text()).toBe(
            'CADASTRAR COM GITHUB',
        );
    });

    it('aponta para a rota de maquina com from=register', () => {
        pageProps.localeSlug = 'fr';

        const wrapper = montarRegister();

        expect(
            wrapper.get('[data-test="social-google"]').attributes('href'),
        ).toBe('/auth/google/redirect?from=register');
        expect(
            wrapper.get('[data-test="social-github"]').attributes('href'),
        ).toBe('/auth/github/redirect?from=register');
    });
});
