import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Show from '@/pages/tools/Show.vue';

/**
 * A pagina generica de uma tool.
 *
 * Ela nao conhece ferramenta nenhuma: le o slug no registro (nome, descricao,
 * icone) e encaixa o componente do diretorio da tool. O que se cobra aqui e
 * esse encaixe - o cabecalho, as metas e o limite de entrada que desce para a
 * ferramenta.
 *
 * O **glob** que acha o `Tool.vue` entra como duble: carregar o JSON Viewer de
 * verdade aqui traria o CodeMirror para dentro deste teste sem dizer nada sobre
 * a pagina. Que todo diretorio de tool `available` tenha componente e cobrado
 * no `tools-registry.test.ts`.
 */

const { pageProps } = vi.hoisted(() => ({ pageProps: { localeSlug: 'pt-br' } }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: pageProps }),
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render() {
            return h('div', [this.$slots.default?.()]);
        },
    }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        render() {
            return h('a', { href: this.href }, this.$slots.default?.());
        },
    }),
}));

vi.mock('@/tools/components', async () => {
    const { defineComponent: define, h: create } = await import('vue');

    const Stub = define({
        name: 'ToolStub',
        props: { maxInputBytes: { type: Number, default: 0 } },
        setup(props) {
            return () =>
                create('div', { 'data-test': 'tool-stub' }, String(props.maxInputBytes));
        },
    });

    return {
        toolComponentLoader: (slug: string) =>
            slug === 'json-viewer' ? async () => Stub : null,
    };
});

const traducoes = {
    app: { nav: { tools: 'FERRAMENTAS' } },
    tools: {
        'json-viewer': {
            name: 'JSON_VIEWER',
            description: 'RESUMO',
            intro: 'INTRO',
        },
        'sql-formatter': {
            name: 'SQL_FORMATTER',
            description: 'RESUMO_SQL',
            intro: 'INTRO_SQL',
        },
        ui: { states: { unavailable: 'INDISPONIVEL' } },
    },
};

function montar(
    slug = 'json-viewer',
    maxInputBytes = 5_000_000,
): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt-br', translations: traducoes });

    return mount(Show, {
        props: { slug, maxInputBytes },
        global: { plugins: [i18n] },
    });
}

describe('Pagina da tool', () => {
    it('apresenta a ferramenta pelo registro', async () => {
        const wrapper = montar();

        await flushPromises();

        expect(wrapper.get('h1').text()).toBe('JSON_VIEWER');
        expect(wrapper.text()).toContain('RESUMO');
        expect(wrapper.text()).toContain('INTRO');
    });

    it('leva nome e descricao para o cabecalho da pagina', async () => {
        const wrapper = montar();

        await flushPromises();

        expect(
            wrapper.get('meta[property="og:title"]').attributes('content'),
        ).toBe('JSON_VIEWER');
        expect(
            wrapper.get('meta[property="og:description"]').attributes('content'),
        ).toBe('RESUMO');
    });

    it('volta para a home pelo caminho do idioma', async () => {
        const wrapper = montar();

        await flushPromises();

        // A rota e a do wayfinder com o idioma das props compartilhadas: a
        // pagina nao monta caminho na mao.
        expect(wrapper.get('a').attributes('href')).toBe('/pt-br');
        expect(wrapper.text()).toContain('FERRAMENTAS');
    });

    it('encaixa o componente da tool com o limite de entrada', async () => {
        const wrapper = montar('json-viewer', 1234);

        await flushPromises();

        // O limite e do servidor (`config/tools.php`) e desce por prop: quem
        // decide o que e grande demais nao e a tela.
        expect(wrapper.get('[data-test="tool-stub"]').text()).toBe('1234');
    });

    it('avisa quando o diretorio da tool nao tem componente', async () => {
        const wrapper = montar('sql-formatter');

        await flushPromises();

        // O registro carrega o nome, mas o diretorio nao traz um `Tool.vue`:
        // erro de registro vira recado na tela, e nao tela em branco.
        expect(wrapper.get('h1').text()).toBe('SQL_FORMATTER');
        expect(wrapper.text()).toContain('INDISPONIVEL');
        expect(wrapper.find('[data-test="tool-stub"]').exists()).toBe(false);
    });

    it('nao quebra com um slug fora do registro', async () => {
        const wrapper = montar('nao-existe');

        await flushPromises();

        // Nao acontece pela rota (o servidor so renderiza tool `available`),
        // mas o componente da pagina nao pode depender disso para desenhar.
        expect(wrapper.get('h1').text()).toBe('');
        expect(wrapper.text()).toContain('INDISPONIVEL');
    });
});
