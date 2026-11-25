import { beforeEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import ToolShell from '@/components/tool/ToolShell.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';

/**
 * Os rotulos dos dois paineis da casca.
 *
 * O padrao - Entrada/Saida - e o contrato de quase toda tool. O diff compara
 * duas entradas e nomeia os lados de outro jeito, entao a casca aceita o par
 * pronto, ja traduzido. Os dois ramos (desktop e mobile) e o padrao antigo
 * ficam cobrados aqui: sem isso, uma tool que nao passa rotulo poderia perder
 * "Entrada"/"Saida" sem ninguem notar.
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

function montar(paneLabels?: { input?: string; output?: string }): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt-br', translations: traducoes });

    const Host = defineComponent({
        name: 'Host',
        render() {
            return h(
                ToolShell,
                { splitKey: 'diff', ...(paneLabels ? { paneLabels } : {}) },
                {
                    input: () => h('p', 'ORIGINAL'),
                    output: () => h('p', 'MODIFICADO'),
                },
            );
        },
    });

    return mount(Host, { global: { plugins: [i18n] } });
}

/** O texto do cabecalho dos dois paineis, no desktop. */
function cabecalhos(wrapper: VueWrapper): string[] {
    return wrapper.findAll('section h2').map((header) => header.text());
}

beforeEach(() => {
    localStorage.clear();
});

describe('ToolShell com rotulos de painel', () => {
    it('usa os rotulos no desktop quando a tool os passa', () => {
        stubViewport(true);

        const wrapper = montar({
            input: 'ORIGINAL',
            output: 'MODIFICADO',
        });

        expect(cabecalhos(wrapper)).toEqual(['ORIGINAL', 'MODIFICADO']);
    });

    it('cai no par padrao quando a tool nao passa rotulo', () => {
        stubViewport(true);

        expect(cabecalhos(montar())).toEqual(['ENTRADA', 'SAIDA']);
    });

    it('cai no par padrao no lado que ficou sem rotulo', () => {
        stubViewport(true);

        expect(cabecalhos(montar({ input: 'ORIGINAL' }))).toEqual([
            'ORIGINAL',
            'SAIDA',
        ]);
    });

    it('usa os rotulos nas abas do mobile', () => {
        stubViewport(false);

        const wrapper = montar({
            input: 'ORIGINAL',
            output: 'MODIFICADO',
        });

        expect(
            wrapper.findAll('[role="tab"]').map((tab) => tab.text()),
        ).toEqual(['ORIGINAL', 'MODIFICADO']);
    });

    it('mantem Entrada/Saida nas abas quando nao ha rotulo', () => {
        stubViewport(false);

        const wrapper = montar();

        expect(
            wrapper.findAll('[role="tab"]').map((tab) => tab.text()),
        ).toEqual(['ENTRADA', 'SAIDA']);
    });
});
