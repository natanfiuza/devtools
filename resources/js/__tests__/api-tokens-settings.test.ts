import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import ApiTokens from '@/pages/settings/ApiTokens.vue';
import type { ApiToken, ApiTokensProps } from '@/types';

/**
 * Tokens da API publica v1 (ADR-050).
 *
 * A tela nao guarda o valor do token: ele vem do servidor uma unica vez, no
 * flash do POST, e some no carregamento seguinte. Revogar passa por um dialogo
 * de confirmacao e e definitivo.
 *
 * As traducoes sao marcadores para denunciar texto fixo no componente; as
 * rotas sao as geradas pelo wayfinder.
 */

const { routerMock, formPost, formReset, formFields } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
        patch: vi.fn(),
    },
    formPost: vi.fn(),
    formReset: vi.fn(),
    formFields: {
        name: '',
        errors: {} as Record<string, string>,
        processing: false,
    },
}));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    const form = reactive({
        ...formFields,
        post: formPost,
        reset: formReset,
    });

    return {
        useForm: () => {
            form.name = '';
            form.errors = {};
            form.processing = false;

            return form;
        },
        usePage: () => ({
            props: {
                localeSlug: 'pt-br',
            },
        }),
        router: routerMock,
        Head: defineComponent({
            name: 'InertiaHead',
            props: { title: { type: String, default: '' } },
            render: () => h('div'),
        }),
        Link: defineComponent({
            name: 'InertiaLink',
            props: { href: { type: String, default: '' } },
            setup(props, { slots, attrs }) {
                return () =>
                    h(
                        'a',
                        { ...attrs, href: props.href },
                        slots.default ? slots.default() : [],
                    );
            },
        }),
    };
});

const traducoes = {
    settings: {
        api_tokens: {
            title: 'TITULO_TOKENS',
            description: 'DESCRICAO_TOKENS',
            docs_banner: {
                title: 'TITULO_DOCS_API',
                description: 'DESCRICAO_DOCS_API',
                cta: 'VER_DOCS_API',
            },
            stats: {
                total_requests: 'TOTAL_REQUISICOES_HTTP',
                total_queries: 'TOTAL_CONSULTAS_SQL',
                active_tokens: 'TOKENS_ATIVOS',
            },
            requests_count: '{count} REQUISICOES_TOKEN',
            queries_count: '{count} CONSULTAS_TOKEN',
            create: {
                name: 'NOME_TOKEN',
                name_placeholder: 'DICA_NOME_TOKEN',
                submit: 'CRIAR_TOKEN',
            },
            once: {
                title: 'TOKEN_CRIADO',
                description: 'COPIE_AGORA',
                copy: 'COPIAR',
                copied: 'COPIADO',
            },
            created: 'CRIADO_EM',
            last_used: 'ULTIMO_USO',
            never: 'NUNCA',
            revoke: 'REVOGAR',
            revoke_title: 'TITULO_REVOGAR',
            revoke_description: 'DESCRICAO_REVOGAR',
            confirm: 'CONFIRMAR_REVOGAR',
            cancel: 'CANCELAR',
            empty_title: 'NENHUM_TOKEN',
            empty: 'DICA_CRIAR_TOKEN',
        },
    },
};

function i18nDoTeste() {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return i18n;
}

function montar(props: Partial<ApiTokensProps> = {}): VueWrapper {
    return mount(ApiTokens, {
        props: { tokens: [], plainTextToken: null, ...props },
        global: { plugins: [i18nDoTeste()] },
    });
}

const TOKEN: ApiToken = {
    id: 7,
    name: 'integracao',
    abilities: ['ai:query'],
    requestsCount: 12,
    sqlQueriesCount: 4,
    lastUsedAt: null,
    createdAt: '2026-10-22T10:00:00+00:00',
};

let clipboard: { writeText: ReturnType<typeof vi.fn> };

beforeEach(() => {
    vi.clearAllMocks();

    clipboard = { writeText: vi.fn(() => Promise.resolve()) };

    Object.defineProperty(navigator, 'clipboard', {
        value: clipboard,
        configurable: true,
    });

    formPost.mockImplementation(
        (_url: string, options?: { onSuccess?: () => void }) => {
            options?.onSuccess?.();
        },
    );
});

describe('lista de tokens', () => {
    it('explica o vazio quando a conta ainda nao criou token', () => {
        const wrapper = montar();

        const vazio = wrapper.get('[data-test="api-tokens-vazio"]');

        expect(vazio.text()).toContain('NENHUM_TOKEN');
        expect(vazio.text()).toContain('DICA_CRIAR_TOKEN');
        expect(wrapper.find('[data-test="api-tokens-lista"]').exists()).toBe(
            false,
        );
    });

    it('mostra nome, abilities e datas sem revelar o valor', () => {
        const wrapper = montar({
            tokens: [
                TOKEN,
                {
                    ...TOKEN,
                    id: 8,
                    name: 'ci',
                    lastUsedAt: '2026-10-21T08:30:00+00:00',
                },
            ],
        });

        const lista = wrapper.get('[data-test="api-tokens-lista"]');
        const linhas = lista.findAll('li');

        expect(linhas).toHaveLength(2);
        expect(linhas[0]?.text()).toContain('integracao');
        expect(linhas[0]?.text()).toContain('ai:query');
        expect(linhas[0]?.text()).toContain('CRIADO_EM 2026-10-22 10:00');
        expect(linhas[0]?.text()).toContain('ULTIMO_USO NUNCA');
        expect(linhas[0]?.text()).toContain('12 REQUISICOES_TOKEN');
        expect(linhas[0]?.text()).toContain('4 CONSULTAS_TOKEN');
        expect(linhas[1]?.text()).toContain('ULTIMO_USO 2026-10-21 08:30');
        // A lista nunca recebe o texto puro do token.
        expect(lista.text()).not.toContain('plainTextToken');
        expect(wrapper.find('[data-test="api-tokens-vazio"]').exists()).toBe(
            false,
        );
    });

    it('exibe os contadores acumulados e o link para a pagina de documentacao da API', () => {
        const wrapper = montar({
            tokens: [TOKEN],
            stats: {
                totalRequests: 48,
                totalSqlQueries: 19,
                activeTokens: 1,
            },
        });

        expect(
            wrapper.get('[data-test="api-tokens-stat-requests"]').text(),
        ).toBe('48');
        expect(
            wrapper.get('[data-test="api-tokens-stat-queries"]').text(),
        ).toBe('19');
        expect(wrapper.get('[data-test="api-tokens-stat-active"]').text()).toBe(
            '1',
        );

        const docsLink = wrapper.get('[data-test="api-tokens-docs-link"]');
        expect(docsLink.attributes('href')).toBe('/pt-br/api-docs');
        expect(docsLink.text()).toContain('VER_DOCS_API');
    });
});

describe('criacao', () => {
    it('envia o nome para a rota de criacao e limpa o formulario', async () => {
        const wrapper = montar();

        await wrapper
            .get('[data-test="api-token-name"]')
            .setValue('integracao');
        await wrapper.get('[data-test="api-token-form"]').trigger('submit');

        expect(formPost).toHaveBeenCalledWith(
            '/settings/api-tokens',
            expect.anything(),
        );
        expect(formReset).toHaveBeenCalled();
    });

    it('mostra o valor uma vez e copia para a area de transferencia', async () => {
        const wrapper = montar({ plainTextToken: '1|segredo-visivel' });
        const painel = wrapper.get('[data-test="api-token-novo"]');

        expect(painel.text()).toContain('TOKEN_CRIADO');
        expect(painel.text()).toContain('1|segredo-visivel');

        await painel.get('[data-test="api-token-copiar"]').trigger('click');
        await flushPromises();

        expect(clipboard.writeText).toHaveBeenCalledWith('1|segredo-visivel');
        expect(painel.text()).toContain('COPIADO');
    });
});

describe('revogacao', () => {
    it('so revoga depois da confirmacao no dialogo', async () => {
        const wrapper = montar({ tokens: [TOKEN] });

        await wrapper.get('[data-test="api-token-revogar-7"]').trigger('click');
        await nextTick();

        const confirmar = document.querySelector<HTMLElement>(
            '[data-test="api-token-revogar-confirmar"]',
        );

        expect(confirmar).not.toBeNull();
        expect(document.body.innerHTML).toContain('TITULO_REVOGAR');
        expect(document.body.innerHTML).toContain('DESCRICAO_REVOGAR');

        confirmar?.click();
        await nextTick();

        expect(routerMock.delete).toHaveBeenCalledWith(
            '/settings/api-tokens/7',
            expect.anything(),
        );
    });
});
