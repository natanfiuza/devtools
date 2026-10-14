import { beforeEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, onMounted } from 'vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';

/**
 * A casca de qualquer tool: os dois paineis, o divisor e as abas do mobile.
 *
 * O que se cobra aqui e o **contrato da casca**, e nao o desenho: quanto cada
 * painel ocupa sai do `useSplitPane` (que tem teste proprio de conta), o
 * arrasto passa pelo ponteiro da janela e o resto e o que fica no DOM.
 *
 * O jsdom nao tem layout: o retangulo do container e um retangulo de mentira, e
 * o que importa e a conta que sai dele. Tambem nao ha captura de ponteiro - o
 * `setPointerCapture` fica de fora, como fica de fora o `matchMedia` do tema.
 */

const traducoes = {
    tools: {
        ui: {
            input: 'ENTRADA',
            output: 'SAIDA',
            resize: 'REDIMENSIONAR',
        },
    },
};

/** jsdom nao implementa matchMedia: quem decide o ramo e este duble. */
function stubViewport(desktop: boolean): void {
    window.matchMedia = ((query: string) => ({
        matches: query.includes('min-width') ? desktop : false,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        onchange: null,
        dispatchEvent: () => false,
    })) as unknown as typeof window.matchMedia;
}

/** Quantas vezes o conteudo do painel de entrada foi montado. */
const montagens = { total: 0 };

const Probe = defineComponent({
    name: 'Probe',
    setup() {
        onMounted(() => {
            montagens.total += 1;
        });

        return () => h('p', 'CONTEUDO_DA_ENTRADA');
    },
});

function montar(slots = true): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt-br', translations: traducoes });

    const Host = defineComponent({
        name: 'Host',
        render() {
            return h(ToolShell, { splitKey: 'json-viewer' }, {
                actions: slots ? () => h('button', 'ACAO') : undefined,
                'input-header': slots ? () => h('button', 'FORMATAR') : undefined,
                input: () => h(Probe),
                'output-header': slots ? () => h('span', 'BUSCA') : undefined,
                output: () => h('p', 'ARVORE'),
                status: slots ? () => h('span', 'RODAPE') : undefined,
                shortcuts: slots ? () => h('span', 'ATALHO') : undefined,
            });
        },
    });

    return mount(Host, { global: { plugins: [i18n] } });
}

/** O painel de entrada e o de saida, na ordem em que aparecem. */
function paineis(wrapper: VueWrapper): { entrada: HTMLElement; saida: HTMLElement } {
    const sections = wrapper.findAll('section');

    return {
        entrada: sections[0]?.element as HTMLElement,
        saida: sections[1]?.element as HTMLElement,
    };
}

/** Um retangulo de mentira: o divisor precisa de um para virar percentual. */
function darRetangulo(wrapper: VueWrapper, left: number, width: number): void {
    const panes = wrapper.get('[data-test="tool-shell-panes"]').element;

    panes.getBoundingClientRect = () =>
        ({ left, width, top: 0, height: 400, right: left + width, bottom: 400, x: left, y: 0 }) as DOMRect;
}

function moverPonteiro(clientX: number): void {
    window.dispatchEvent(Object.assign(new Event('pointermove'), { clientX }));
}

/**
 * O `trigger` do Vue Test Utils monta um `MouseEvent`, e nele o `clientX` e
 * somente leitura - o evento sai daqui na mao.
 */
function pressionarPonteiro(wrapper: VueWrapper, clientX: number): void {
    wrapper
        .get('[data-test="tool-shell-splitter"]')
        .element.dispatchEvent(
            Object.assign(new Event('pointerdown'), { clientX, pointerId: 1 }),
        );
}

function largura(wrapper: VueWrapper): string {
    return paineis(wrapper).entrada.style.width;
}

beforeEach(() => {
    localStorage.clear();
    montagens.total = 0;
    stubViewport(true);

    // jsdom nao traz a captura de ponteiro (nem eventos de ponteiro de verdade).
    if (typeof HTMLElement.prototype.setPointerCapture !== 'function') {
        Object.defineProperty(HTMLElement.prototype, 'setPointerCapture', {
            value: () => {},
            configurable: true,
        });
    }
});

describe('ToolShell no desktop', () => {
    it('divide os dois paineis ao meio e descreve o divisor', () => {
        const wrapper = montar();
        const divisor = wrapper.get('[data-test="tool-shell-splitter"]');

        expect(wrapper.find('[data-test="tool-shell-panes"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="tool-shell-tabs"]').exists()).toBe(false);
        expect(largura(wrapper)).toBe('50%');

        // O divisor e um controle de verdade: quem usa leitor de tela sabe onde
        // esta e o que as setas fazem.
        expect(divisor.attributes('role')).toBe('separator');
        expect(divisor.attributes('aria-valuenow')).toBe('50');
        expect(divisor.attributes('aria-valuemin')).toBe('20');
        expect(divisor.attributes('aria-valuemax')).toBe('80');
        expect(divisor.attributes('aria-label')).toBe('REDIMENSIONAR');
    });

    it('arrasta o divisor com o ponteiro', async () => {
        const wrapper = montar();

        darRetangulo(wrapper, 100, 800);

        pressionarPonteiro(wrapper, 500);
        await wrapper.vm.$nextTick();

        moverPonteiro(300);
        await wrapper.vm.$nextTick();

        // 300 esta a 200 pixels da esquerda de um container de 800: um quarto.
        expect(largura(wrapper)).toBe('25%');

        moverPonteiro(1000);
        await wrapper.vm.$nextTick();

        // Fora do container o percentual encosta no limite - nenhum painel
        // desaparece por um arrasto descuidado.
        expect(largura(wrapper)).toBe('80%');

        window.dispatchEvent(new Event('pointerup'));
        await wrapper.vm.$nextTick();

        // Solto o ponteiro, o movimento nao mexe mais em nada.
        moverPonteiro(300);
        await wrapper.vm.$nextTick();

        expect(largura(wrapper)).toBe('80%');
    });

    it('move o divisor pelo teclado', async () => {
        const wrapper = montar();
        const divisor = wrapper.get('[data-test="tool-shell-splitter"]');

        await divisor.trigger('keydown', { key: 'ArrowRight' });
        expect(largura(wrapper)).toBe('52%');

        await divisor.trigger('keydown', { key: 'ArrowLeft' });
        await divisor.trigger('keydown', { key: 'ArrowLeft' });
        expect(largura(wrapper)).toBe('48%');

        await divisor.trigger('keydown', { key: 'Home' });
        expect(largura(wrapper)).toBe('20%');

        await divisor.trigger('keydown', { key: 'End' });
        expect(largura(wrapper)).toBe('80%');

        // Tecla que nao e do divisor nao muda nada.
        await divisor.trigger('keydown', { key: 'a' });
        expect(largura(wrapper)).toBe('80%');
    });

    it('volta ao meio com o duplo clique', async () => {
        const wrapper = montar();
        const divisor = wrapper.get('[data-test="tool-shell-splitter"]');

        await divisor.trigger('keydown', { key: 'End' });
        await divisor.trigger('dblclick');

        expect(largura(wrapper)).toBe('50%');
    });

    it('lembra a divisao de cada tool', async () => {
        const wrapper = montar();

        await wrapper.get('[data-test="tool-shell-splitter"]').trigger('keydown', {
            key: 'End',
        });

        expect(localStorage.getItem('tool-split:json-viewer')).toBe('80');

        // A proxima visita comeca onde a anterior parou.
        expect(largura(montar())).toBe('80%');
    });
});

describe('ToolShell no mobile', () => {
    beforeEach(() => {
        stubViewport(false);
    });

    it('empilha os paineis em abas', async () => {
        const wrapper = montar();
        const abas = wrapper.findAll('[role="tab"]');
        const conteudos = wrapper.findAll('[role="tabpanel"]');

        expect(wrapper.find('[data-test="tool-shell-tabs"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="tool-shell-panes"]').exists()).toBe(false);
        expect(abas.map((aba) => aba.text())).toEqual(['ENTRADA', 'SAIDA']);

        // Entrada aberta, saida montada e fora da tela: `force-mount` mantem os
        // dois paineis vivos, e quem esconde o inativo e a classe de `display`
        // - sem ela o reka-ui deixaria os dois empilhados.
        expect(conteudos[0]?.classes()).toContain('flex');
        expect(conteudos[1]?.classes()).toContain('hidden');

        // A aba troca no `mousedown` (como no Radix): quem clica aperta o botao
        // antes de soltar, entao e ele que a tela escuta.
        await abas[1]?.trigger('mousedown');

        expect(conteudos[0]?.classes()).toContain('hidden');
        expect(conteudos[1]?.classes()).toContain('flex');
    });

    it('nao desmonta o painel de entrada ao trocar de aba', async () => {
        const wrapper = montar();

        await wrapper.findAll('[role="tab"]')[1]?.trigger('mousedown');
        await wrapper.findAll('[role="tab"]')[0]?.trigger('mousedown');

        // Sem `force-mount` o editor renasceria a cada troca de aba, e o
        // historico de edicao morreria junto.
        expect(montagens.total).toBe(1);
    });

    it('leva as acoes de cada painel para dentro da aba', async () => {
        const wrapper = montar();

        // No celular nao ha cabecalho de painel com titulo: o rotulo da aba ja
        // diz qual e qual, e os botoes vao logo abaixo dele.
        expect(
            wrapper.get('[data-test="tool-shell-input-header"]').text(),
        ).toBe('FORMATAR');
        expect(
            wrapper.get('[data-test="tool-shell-output-header"]').text(),
        ).toBe('BUSCA');
    });

    it('nao abre a faixa de acoes quando a tool nao tem nenhuma', () => {
        const wrapper = montar(false);

        expect(wrapper.find('[data-test="tool-shell-input-header"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="tool-shell-output-header"]').exists()).toBe(false);
    });
});

describe('ToolShell sem conteudo', () => {
    it('esconde as faixas que ninguem preencheu', () => {
        const wrapper = montar(false);

        expect(wrapper.find('[data-test="tool-shell-status"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="tool-shell-shortcuts"]').exists()).toBe(false);
    });

    it('mostra as faixas preenchidas', () => {
        const wrapper = montar();

        expect(wrapper.get('[data-test="tool-shell-status"]').text()).toBe('RODAPE');
        expect(wrapper.get('[data-test="tool-shell-shortcuts"]').text()).toBe('ATALHO');
    });
});
