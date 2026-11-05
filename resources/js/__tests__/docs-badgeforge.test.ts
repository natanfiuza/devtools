import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import BadgeForgeDocs, {
    type BadgeForgeDocsProps,
} from '@/pages/BadgeForgeDocs.vue';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';

/**
 * Documentacao publica do BadgeForge (`/{locale}/docs/badgeforge`, ADR-082).
 *
 * O catalogo vem do servidor; a pagina so monta os exemplos. Os marcadores de
 * traducao garantem que titulo, metrica e URL saem de i18n e de props, nunca
 * de texto fixo no componente.
 */

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            localeSlug: 'pt-br',
            seo: { meta: { title: 'META_DOCS', description: '' } },
        },
    }),
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render() {
            return h('div', { 'data-test': 'head-title' }, [
                h('span', { 'data-test': 'head-title-text' }, this.title),
            ]);
        },
    }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        render() {
            return h(
                'a',
                { href: this.href, ...this.$attrs },
                this.$slots.default?.(),
            );
        },
    }),
}));

const TRADUCOES = {
    docs: {
        badgeforge: {
            hero: {
                eyebrow: 'SOBRANCELHA',
                title: 'TITULO_DOCS',
                subtitle: 'SUBTITULO_DOCS',
                open_tool: 'ABRIR_CONSTRUTOR',
            },
            endpoints: {
                title: 'ENDPOINTS',
                static: 'ESTATICAS',
                provider: 'PROVIDERS',
                personal: 'PESSOAIS',
                json: 'ENDPOINT_JSON',
            },
            quick_start: {
                title: 'INICIO',
                body: 'CORPO_INICIO',
                copy: 'COPIAR',
                copied: 'COPIADO',
            },
            static: {
                title: 'SECAO_ESTATICA',
                body: 'CORPO_ESTATICO',
                example_label: 'EXEMPLO',
            },
            providers: {
                title: 'SECAO_PROVIDERS',
                intro: 'INTRO_PROVIDERS',
                metric: 'METRICA',
                path: 'CAMINHO',
                access: 'ACESSO',
                public: 'PUBLICO',
                private: 'PRIVADO',
                documentation: 'DOC_API',
                unknown_note: 'NOTA_UNKNOWN',
                descriptions: { github: 'DESC_GITHUB' },
            },
            personal: {
                title: 'SECAO_PESSOAL',
                intro: 'INTRO_PESSOAL',
                url_label: 'FORMATO_URL',
                username_required: 'PRECISA_USERNAME',
                providers_title: 'ITENS_PRIVADOS',
                providers_body: 'CORPO_PRIVADOS',
                github: 'GITHUB_PAT',
                npm: 'NPM_PAT',
                docker: 'DOCKER_PAT',
                packagist: 'PACKAGIST_PUBLICO',
                security_title: 'COMO_GUARDA',
                security_body: 'CORPO_SEGURANCA',
                manage_cta: 'GERENCIAR',
                tool_cta: 'CRIAR_PESSOAL',
                refresh_note: 'NOTA_REFRESH',
            },
            endpoint: {
                title: 'SECAO_ENDPOINT_JSON',
                intro: 'INTRO_ENDPOINT_JSON',
                url_label: 'URL_ENDPOINT_JSON',
                contract_title: 'CONTRATO_SHIELDS',
                contract_body: 'CORPO_CONTRATO',
                cache_title: 'CACHE_ENDPOINT',
                cache_body: 'CACHE_ENDPOINT_DE_{ttl}_SEGUNDOS',
                limits_title: 'LIMITES_ENDPOINT',
                limits_anonymous: 'ANONIMO_{limit}',
                limits_free: 'FREE_{limit}',
                limits_paid: 'PAGO_{limit}',
                security_title: 'SEGURANCA_ENDPOINT',
                security_body: 'CORPO_SEGURANCA_ENDPOINT',
                manage_cta: 'GERENCIAR_ENDPOINTS',
            },
            custom: {
                title: 'SECAO_CUSTOM',
                intro: 'INTRO_CUSTOM',
                url_label: 'URL_CUSTOM',
                paid_title: 'PAGO_CUSTOM',
                paid_body: 'CORPO_PAGO_CUSTOM',
                pointer_title: 'POINTER_CUSTOM',
                pointer_body: 'CORPO_POINTER_CUSTOM',
                security_title: 'SEGURANCA_CUSTOM',
                security_body: 'CORPO_SEGURANCA_CUSTOM',
                manage_cta: 'GERENCIAR_CUSTOM',
            },
            api: {
                title: 'SECAO_API',
                intro: 'INTRO_API',
                base_url_label: 'URL_BASE_API',
                abilities_title: 'ESCOPOS_TOKEN',
                abilities_read: 'ESCOPO_LEITURA',
                abilities_write: 'ESCOPO_ESCRITA',
                token_body: 'CORPO_TOKEN',
                tokens_cta: 'CRIAR_TOKEN',
                spec_cta: 'ABRIR_SPEC',
            },
            cache: {
                title: 'SECAO_CACHE',
                body: 'CACHE_DE_{ttl}_SEGUNDOS',
                stale_title: 'STALE',
                stale_body: 'CORPO_STALE',
                refresh_title: 'REFRESH',
                refresh_body: 'CORPO_REFRESH',
            },
            errors: {
                title: 'SECAO_ERROS',
                intro: 'INTRO_ERROS',
                cached: 'ERRO_CACHE',
                fallback: 'ERRO_FALLBACK',
                unknown: 'ERRO_UNKNOWN',
                not_found: 'ERRO_404',
                private: 'ERRO_PRIVADO',
            },
            security: {
                title: 'SECAO_SEGURANCA',
                anonymous: 'SEG_ANONIMO',
                credential: 'SEG_CREDENCIAL',
                logs: 'SEG_LOGS',
                url: 'SEG_URL',
            },
        },
    },
    tools: {
        ui: { actions: { copied: 'COPIADO', copy_manual: 'COPIE_NA_MAO' } },
        badgeforge: {
            provider: {
                metrics: { github_stars: 'ESTRELAS_GH' },
            },
        },
    },
};

const PADRAO = {
    staticEndpoint: '/api/badges/static',
    providerEndpoint: '/api/badges',
    personalEndpoint: '/b',
    jsonEndpoint: '/api/badges/endpoint',
    customEndpoint: '/api/badges/custom',
    cacheTtl: 300,
    endpointCacheTtl: 300,
    endpointRateLimits: { anonymous: 60, free: 300, paid: 3000 },
    origin: 'https://devtools.example',
    jsonApiBaseUrl: '/api/v1/badgeforge',
    openApiUrl: 'https://devtools.example/openapi/badges.yaml',
    tokensSettingsUrl: '/pt-br/settings/api-tokens',
};

function mountDocs(
    overrides: Partial<BadgeForgeDocsProps> = {},
): ReturnType<typeof mount> {
    const i18n = createI18nInstance();
    applyTranslations(i18n, { locale: 'pt-BR', translations: TRADUCOES });

    return mount(BadgeForgeDocs, {
        props: {
            ...PADRAO,
            providers: (toolProps as { providers: BadgeForgeDocsProps['providers'] })
                .providers,
            ...overrides,
        },
        global: { plugins: [i18n] },
    });
}

describe('BadgeForge Docs', () => {
    it('mostra os nove providers com as quinze metricas do catalogo', () => {
        const wrapper = mountDocs();

        expect(
            wrapper.findAll('[data-test^="badgeforge-docs-provider-"]'),
        ).toHaveLength(9);
        expect(
            wrapper.findAll('[data-test^="badgeforge-docs-metric-"]'),
        ).toHaveLength(15);
    });

    it('mostra o path literal das definicoes sem params', () => {
        const wrapper = mountDocs();
        const php = wrapper.find('[data-test="badgeforge-docs-metric-php-v"]');

        expect(php.text()).toContain('/api/badges/php/v/latest.svg');
    });

    it('traduz o nome da metrica e mostra o caminho do exemplo', () => {
        const wrapper = mountDocs();

        const github = wrapper.find('[data-test="badgeforge-docs-provider-github"]');

        expect(github.text()).toContain('ESTRELAS_GH');
        expect(github.text()).toContain(
            '/api/badges/github/stars/natanfiuza/devtools.svg',
        );
        expect(github.text()).toContain('DESC_GITHUB');
    });

    it('marca item privado e publico pela definicao', () => {
        const wrapper = mountDocs();

        const npmDm = wrapper.find('[data-test="badgeforge-docs-metric-npm-dm"]');
        const npmV = wrapper.find('[data-test="badgeforge-docs-metric-npm-v"]');

        expect(npmDm.text()).toContain('PUBLICO');
        expect(npmV.text()).toContain('PRIVADO');
    });

    it('monta o exemplo estatico e o markdown com a origem configurada', () => {
        const wrapper = mountDocs();

        expect(
            wrapper.find('[data-test="badgeforge-docs-static-example"]').text(),
        ).toBe('https://devtools.example/api/badges/static/build/passing.svg');
        expect(
            wrapper.find('[data-test="badgeforge-docs-static-markdown"]').text(),
        ).toBe(
            '![build: passing](https://devtools.example/api/badges/static/build/passing.svg)',
        );
    });

    it('documenta a URL opaca da badge pessoal', () => {
        const wrapper = mountDocs();

        expect(
            wrapper.find('[data-test="badgeforge-docs-personal-url"]').text(),
        ).toBe('https://devtools.example/b/{username}/{ulid}.svg');
        expect(
            wrapper.find('[data-test="badgeforge-docs-personal"]').text(),
        ).toContain('GITHUB_PAT');
    });

    it('documenta o endpoint JSON com contrato, TTL e limites', () => {
        const wrapper = mountDocs({
            endpointCacheTtl: 450,
            endpointRateLimits: { anonymous: 5, free: 7, paid: 9 },
        });

        const section = wrapper.find('[data-test="badgeforge-docs-json-endpoint"]');

        expect(section.text()).toContain('INTRO_ENDPOINT_JSON');
        expect(
            wrapper.find('[data-test="badgeforge-docs-json-url"]').text(),
        ).toBe('https://devtools.example/api/badges/endpoint/{ulid}.svg');
        expect(
            wrapper.find('[data-test="badgeforge-docs-json-contract"]').text(),
        ).toContain('"schemaVersion": 1');
        expect(
            wrapper.find('[data-test="badgeforge-docs-json-cache"]').text(),
        ).toContain('450');
        expect(
            wrapper.find('[data-test="badgeforge-docs-json-limits"]').text(),
        ).toContain('ANONIMO_5');
        expect(
            wrapper.find('[data-test="badgeforge-docs-json-limits"]').text(),
        ).toContain('FREE_7');
        expect(
            wrapper.find('[data-test="badgeforge-docs-json-limits"]').text(),
        ).toContain('PAGO_9');
        expect(
            wrapper.find('[data-test="badgeforge-docs-json-manage"]').attributes(
                'href',
            ),
        ).toBe('/pt-br/dashboard/badgeforge');
    });

    it('documenta o provider customizado com URL opaca e JSON Pointer', () => {
        const wrapper = mountDocs();

        const section = wrapper.find('[data-test="badgeforge-docs-custom"]');

        expect(section.text()).toContain('INTRO_CUSTOM');
        expect(
            wrapper.find('[data-test="badgeforge-docs-custom-url"]').text(),
        ).toBe('https://devtools.example/api/badges/custom/{id}/{metric}/{path}.svg');
        expect(section.text()).toContain('CORPO_PAGO_CUSTOM');
        expect(section.text()).toContain('CORPO_POINTER_CUSTOM');
        expect(section.text()).toContain('CORPO_SEGURANCA_CUSTOM');
        expect(
            wrapper.find('[data-test="badgeforge-docs-custom-manage"]').attributes(
                'href',
            ),
        ).toBe('/pt-br/dashboard/badgeforge');
    });

    it('documenta a API JSON com base, escopos e links da spec e dos tokens', () => {
        const wrapper = mountDocs();

        const section = wrapper.find('[data-test="badgeforge-docs-api"]');

        expect(section.text()).toContain('INTRO_API');
        expect(section.text()).toContain('ESCOPO_LEITURA');
        expect(section.text()).toContain('ESCOPO_ESCRITA');
        expect(section.text()).toContain('CORPO_TOKEN');
        expect(
            wrapper.find('[data-test="badgeforge-docs-api-base-url"]').text(),
        ).toBe('https://devtools.example/api/v1/badgeforge');
        expect(
            wrapper.find('[data-test="badgeforge-docs-api-tokens"]').attributes('href'),
        ).toBe('/pt-br/settings/api-tokens');
        expect(
            wrapper.find('[data-test="badgeforge-docs-api-spec"]').attributes('href'),
        ).toBe('https://devtools.example/openapi/badges.yaml');
    });

    it('interpola o TTL de cache da configuracao', () => {
        const wrapper = mountDocs({ cacheTtl: 600 });

        expect(
            wrapper.find('[data-test="badgeforge-docs-cache-ttl"]').text(),
        ).toContain('600');
    });

    it('aponta o CTA para o builder do idioma atual', () => {
        const wrapper = mountDocs();

        expect(
            wrapper.find('[data-test="badgeforge-docs-open-tool"]').attributes(
                'href',
            ),
        ).toBe('/pt-br/tools/badgeforge');
        expect(
            wrapper.find('[data-test="badgeforge-docs-manage"]').attributes(
                'href',
            ),
        ).toBe('/pt-br/dashboard/badgeforge');
    });

    it('explica erros e seguranca', () => {
        const wrapper = mountDocs();

        expect(
            wrapper.find('[data-test="badgeforge-docs-errors"]').text(),
        ).toContain('ERRO_FALLBACK');
        expect(
            wrapper.find('[data-test="badgeforge-docs-security"]').text(),
        ).toContain('SEG_CREDENCIAL');
    });
});
