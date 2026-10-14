import { describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import PageHead from '@/components/PageHead.vue';

/**
 * Cabecalho das paginas indexaveis: o titulo e a descricao vao para a aba, para
 * a busca e para o card de compartilhamento.
 *
 * O `Head` do Inertia entra como stub que devolve o slot: o que se verifica e o
 * que o componente escreve, nao o gerenciador do <head> do Inertia.
 */

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render() {
            return h('div', [
                h('span', { 'data-test': 'title-prop' }, this.title),
                this.$slots.default?.(),
            ]);
        },
    }),
}));

function montar(title = 'TITULO', description = 'RESUMO'): VueWrapper {
    return mount(PageHead, { props: { title, description } });
}

describe('PageHead', () => {
    it('da o titulo a aba e ao card', () => {
        const wrapper = montar();

        expect(wrapper.get('[data-test="title-prop"]').text()).toBe('TITULO');
        expect(
            wrapper.get('meta[property="og:title"]').attributes('content'),
        ).toBe('TITULO');
    });

    it('leva a descricao para a busca e para o card', () => {
        const wrapper = montar();

        expect(
            wrapper.get('meta[name="description"]').attributes('content'),
        ).toBe('RESUMO');
        expect(
            wrapper.get('meta[property="og:description"]').attributes('content'),
        ).toBe('RESUMO');
    });

    it('nao escreve mais nada no head', () => {
        // As metas do site inteiro (og:type, og:url, og:image) sao da view: se
        // aparecerem aqui, viram duas fontes do mesmo valor.
        expect(montar().findAll('meta')).toHaveLength(3);
    });
});
