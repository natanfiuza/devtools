import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import PageHead from '@/components/PageHead.vue';

/**
 * Cabecalho das paginas indexaveis: so o titulo da aba.
 *
 * O titulo e a descricao sao resolvidos no servidor e escritos pelo
 * app.blade.php; aqui chega so o titulo, pelas props compartilhadas, para a
 * navegacao do cliente e o SSR. O `Head` do Inertia entra como stub que mostra
 * a prop: o que se verifica e o que o componente entrega a ele.
 */

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            seo: { meta: { title: 'TITULO', description: 'RESUMO' } },
        },
    }),
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

describe('PageHead', () => {
    it('da o titulo da pagina a aba', () => {
        const wrapper = mount(PageHead);

        expect(wrapper.get('[data-test="title-prop"]').text()).toBe('TITULO');
    });

    it('nao escreve meta nenhuma', () => {
        // As metas (description, og:title, og:description) sao da view: se
        // aparecessem aqui, viram duas fontes do mesmo valor.
        expect(mount(PageHead).findAll('meta')).toHaveLength(0);
    });
});
