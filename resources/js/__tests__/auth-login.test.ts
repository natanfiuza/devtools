import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { registerDirectives } from '@/lib/directives';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Login from '@/pages/auth/Login.vue';

/**
 * Tela de login (ADR-022, ADR-023).
 *
 * O servidor entrega as props compartilhadas; aqui se verifica a fiacao da
 * tela: texto vindo do i18n, URLs com o idioma da pagina, "esqueci a senha"
 * ligado ao Fortify e os botoes sociais sempre visiveis. As traducoes sao
 * marcadores de proposito - se algum texto aparecer fixo no componente, o
 * marcador nao esta la e o teste quebra.
 */

const { pageProps } = vi.hoisted(() => ({
    pageProps: { localeSlug: 'pt-br' },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps }),
    Head: defineComponent({
        name: 'Head',
        props: { title: { type: String, default: '' } },
        render: () => null,
    }),
    // O <Link> de verdade navega pelo Inertia; aqui vira um <a> comum, que e o
    // que o navegador (e o teste) ve no HTML.
    Link: defineComponent({
        name: 'Link',
        props: { href: { type: String, default: '' } },
        render() {
            return h('a', { href: this.href }, this.$slots.default?.());
        },
    }),
    // O <Form> de verdade faz POST via Inertia; aqui basta um formulario que
    // exponha as mesmas slots (errors, processing) para a tela renderizar.
    Form: defineComponent({
        name: 'Form',
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
        login: {
            title: 'TITULO',
            description: 'DESCRICAO',
            email: 'EMAIL',
            email_placeholder: 'DICA_EMAIL',
            password: 'SENHA',
            password_placeholder: 'DICA_SENHA',
            remember: 'LEMBRAR',
            forgot: 'ESQUECI',
            submit: 'ENTRAR',
            no_account: 'SEM_CONTA',
            register: 'CRIAR_CONTA',
        },
        social: {
            continue_with: 'COM {provider}',
            divider: 'DIVISOR',
            providers: { google: 'GOOGLE', github: 'GITHUB' },
        },
    },
};

function montarLogin(
    props: { status?: string; canResetPassword?: boolean } = {},
): VueWrapper {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Login, {
        props: { canResetPassword: true, ...props },
        global: {
            plugins: [
                i18n,
                // A diretiva v-focus e registrada pelo app.ts; sem ela o
                // template nao compila.
                {
                    install: (app: Parameters<typeof registerDirectives>[0]) =>
                        registerDirectives(app),
                },
            ],
        },
    });
}

beforeEach(() => {
    pageProps.localeSlug = 'pt-br';
});

describe('textos', () => {
    it('escreve titulo, descricao e rotulos pelo i18n', () => {
        const wrapper = montarLogin();
        const texto = wrapper.text();

        expect(texto).toContain('TITULO');
        expect(texto).toContain('DESCRICAO');
        expect(texto).toContain('SEM_CONTA');
    });

    it('liga cada rotulo ao seu campo', () => {
        const wrapper = montarLogin();

        expect(wrapper.get('label[for="email"]').text()).toBe('EMAIL');
        expect(wrapper.get('label[for="password"]').text()).toBe('SENHA');
        expect(wrapper.get('label[for="remember"]').text()).toContain(
            'LEMBRAR',
        );

        expect(wrapper.get('#email').attributes('type')).toBe('email');
        expect(wrapper.get('#email').attributes('placeholder')).toBe(
            'DICA_EMAIL',
        );
        expect(wrapper.get('#password').attributes('placeholder')).toBe(
            'DICA_SENHA',
        );
    });

    it('mostra o aviso do servidor quando vem um status', () => {
        const wrapper = montarLogin({ status: 'Link enviado.' });

        expect(wrapper.get('[role="status"]').text()).toBe('Link enviado.');
    });

    it('nao mostra aviso nenhum sem status', () => {
        expect(montarLogin().find('[role="status"]').exists()).toBe(false);
    });
});

describe('links', () => {
    it('manda o formulario para o login do idioma da pagina', () => {
        const wrapper = montarLogin();
        const form = wrapper.get('form');

        expect(form.attributes('action')).toBe('/pt-br/login');
        expect(form.attributes('method')).toBe('post');
    });

    it('segue o idioma das props compartilhadas', () => {
        pageProps.localeSlug = 'fr';

        const wrapper = montarLogin();

        expect(wrapper.get('form').attributes('action')).toBe('/fr/login');
        expect(wrapper.find('a[href="/fr/register"]').exists()).toBe(true);
    });

    it('oferece o cadastro e o esqueci a senha', () => {
        const wrapper = montarLogin();

        expect(wrapper.get('a[href="/pt-br/register"]').text()).toBe(
            'CRIAR_CONTA',
        );
        expect(wrapper.get('a[href="/pt-br/forgot-password"]').text()).toBe(
            'ESQUECI',
        );
    });

    it('esconde o esqueci a senha quando o Fortify nao tem reset', () => {
        const wrapper = montarLogin({ canResetPassword: false });

        expect(wrapper.find('a[href="/pt-br/forgot-password"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('a[href="/pt-br/register"]').exists()).toBe(true);
    });
});

describe('login social', () => {
    it('mostra Google e GitHub com o nome do provedor', () => {
        const wrapper = montarLogin();

        expect(wrapper.get('[data-test="social-google"]').text()).toBe(
            'COM GOOGLE',
        );
        expect(wrapper.get('[data-test="social-github"]').text()).toBe(
            'COM GITHUB',
        );
    });

    it('aponta para a rota de maquina, sem prefixo de idioma', () => {
        pageProps.localeSlug = 'fr';

        const wrapper = montarLogin();

        // auth/{provider}/redirect nao tem {locale}: e rota de maquina
        // (ADR-022), entao nao muda com o idioma da pagina.
        expect(
            wrapper.get('[data-test="social-google"]').attributes('href'),
        ).toBe('/auth/google/redirect');
        expect(
            wrapper.get('[data-test="social-github"]').attributes('href'),
        ).toBe('/auth/github/redirect');
    });
});
