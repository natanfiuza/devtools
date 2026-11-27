import {
    flushPromises,
    mount,
    type DOMWrapper,
    type VueWrapper,
} from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Tabs } from '@/components/ui/tabs';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/curl-to-code/Tool.vue';

/**
 * A tela do cURL.
 *
 * O motor tem testes proprios (`curl-tokenize`, `curl-parse`, `curl-emit`);
 * aqui se cobra o que a tela faz com ele: as tres abas, o banner do que ficou
 * de fora, o aviso de credencial, os estados de erro, o exemplo e a copia da
 * aba aberta.
 *
 * O editor de verdade fica de fora: o CodeMirror precisa do DOM real. O duble
 * tem o mesmo contrato de texto e emite a mudanca como o de verdade.
 */

const traducoes = {
    tools: {
        ui: {
            input: 'ENTRADA',
            output: 'SAIDA',
            resize: 'REDIMENSIONAR',
            actions: {
                example: 'EXEMPLO',
                clear: 'LIMPAR',
                copy: 'COPIAR',
                copied: 'COPIADO',
                copy_manual: 'COPIE_NA_MAO',
            },
            states: {
                empty: 'VAZIO',
            },
        },
        'curl-to-code': {
            panes: { command: 'COMANDO', code: 'CODIGO' },
            tabs: { laravel: 'LARAVEL', fetch: 'FETCH', axios: 'AXIOS' },
            unsupported: 'FORA:{options}',
            warnings: {
                credential_in_output: 'CREDENCIAL:{user}',
                extra_url: 'URL_EXTRA:{url}',
                header_without_colon: 'SEM_DOIS_PONTOS:{token}',
                data_from_file: 'ARQUIVO:{token}',
            },
            errors: {
                empty: 'VAZIO_CMD',
                not_curl: 'NAO_CURL:{token}',
                shell: 'SHELL:{detail}',
                missing_value: 'SEM_VALOR:{option}',
                missing_url: 'SEM_URL',
            },
            shell_details: {
                unterminated_single: 'ASPAS_SIMPLES',
                unterminated_double: 'ASPAS_DUPLAS',
                dangling_escape: 'BARRA_FINAL',
            },
            states: { empty: 'COLE_UM_COMANDO' },
            status: { ready: 'PRONTO:{method}:{url}' },
        },
    },
};

/** O duble do editor: mesmo contrato de texto, sem CodeMirror. */
const EditorStub = defineComponent({
    name: 'CodeEditor',
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
        error: {
            type: Object as PropType<{
                position: number;
                message: string;
            } | null>,
            default: null,
        },
        extensions: { type: Array as PropType<unknown[]>, default: () => [] },
    },
    emits: ['update:modelValue'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    'data-test': 'editor',
                    'data-placeholder': props.placeholder,
                },
                props.modelValue,
            );
    },
});

const EXEMPLO =
    'curl -X POST https://api.example.com/users -H \'Content-Type: application/json\' -d \'{"name":"Ana"}\'';

function montar(): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Tool, {
        global: {
            plugins: [i18n],
            stubs: { CodeEditor: EditorStub },
        },
    });
}

function rotuloDoBotao(node: DOMWrapper<Element>): string {
    const copia = node.element.cloneNode(true) as Element;

    copia.querySelectorAll('kbd').forEach((tecla) => tecla.remove());

    return (copia.textContent ?? '').trim();
}

function botao(wrapper: VueWrapper, rotulo: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('button')
        .find((node) => rotuloDoBotao(node) === rotulo);

    if (found === undefined) {
        throw new Error(`o botao ${rotulo} nao esta na tela`);
    }

    return found;
}

function desabilitado(wrapper: VueWrapper, rotulo: string): boolean {
    return botao(wrapper, rotulo).attributes('disabled') !== undefined;
}

async function digitar(wrapper: VueWrapper, valor: string): Promise<void> {
    wrapper
        .findComponent({ name: 'CodeEditor' })
        .vm.$emit('update:modelValue', valor);

    await flushPromises();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

/**
 * Abre uma aba da saida.
 *
 * A casca tambem usa `Tabs` (para empilhar os paineis no mobile), entao o
 * seletor certo e o que contem os gatilhos desta tool - e nao o primeiro
 * `Tabs` da arvore, que e o da casca.
 */
async function abrir(wrapper: VueWrapper, target: string): Promise<void> {
    // A casca embrulha o conteudo inteiro no `Tabs` dela, entao o `Tabs` da
    // saida e o ultimo da arvore - e nao o primeiro que "ve" os gatilhos.
    const abas = wrapper
        .findAllComponents(Tabs)
        .filter(
            (component) =>
                component.element.querySelector(
                    '[data-test="curl-tab-laravel"]',
                ) !== null,
        )
        .at(-1);

    if (abas === undefined) {
        throw new Error('as abas da saida nao estao na tela');
    }

    abas.vm.$emit('update:modelValue', target);
    await flushPromises();
}

function saida(wrapper: VueWrapper): string {
    return wrapper.get('[data-test="curl-output"]').text();
}

function avisos(wrapper: VueWrapper): string {
    return wrapper.get('[data-test="curl-notices"]').text();
}

describe('cURL para codigo', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    it('abre vazio, com o convite e a copia desabilitada', () => {
        const wrapper = montar();

        expect(wrapper.get('[data-test="curl-state"]').text()).toBe(
            'COLE_UM_COMANDO',
        );
        expect(
            wrapper.get('[data-test="curl-state"]').attributes('data-state'),
        ).toBe('empty');
        expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        expect(wrapper.get('[data-test="curl-status"]').text()).toBe('VAZIO');
        expect(wrapper.find('[data-test="curl-notices"]').exists()).toBe(false);
    });

    it('gera as tres saidas e abre na aba do Laravel', async () => {
        const wrapper = montar();

        await digitar(wrapper, EXEMPLO);

        expect(wrapper.findAll('[data-test^="curl-tab-"]')).toHaveLength(3);
        expect(saida(wrapper)).toContain('Http::withHeaders([');
        expect(saida(wrapper)).toContain(
            '->withBody(\'{"name":"Ana"}\', \'application/json\')',
        );
        expect(saida(wrapper)).toContain(
            "->post('https://api.example.com/users')",
        );
        expect(wrapper.get('[data-test="curl-status"]').text()).toBe(
            'PRONTO:POST:https://api.example.com/users',
        );
    });

    it('troca de aba sem perder o comando', async () => {
        const wrapper = montar();

        await digitar(wrapper, EXEMPLO);
        await abrir(wrapper, 'fetch');

        expect(saida(wrapper)).toContain(
            "await fetch('https://api.example.com/users'",
        );
        expect(saida(wrapper)).toContain("method: 'POST'");

        await abrir(wrapper, 'axios');

        expect(saida(wrapper)).toContain('await axios.request({');
        expect(saida(wrapper)).toContain('data: \'{"name":"Ana"}\'');
    });

    it('lista o que ficou de fora e avisa da credencial', async () => {
        const wrapper = montar();

        await digitar(wrapper, 'curl -k -u ana:segredo https://x.test');

        expect(avisos(wrapper)).toContain('FORA:-k');
        expect(avisos(wrapper)).toContain('CREDENCIAL:ana');
        expect(avisos(wrapper)).not.toContain('segredo');
        expect(saida(wrapper)).toContain(
            "Http::withBasicAuth('ana', 'segredo')",
        );
    });

    it('recusa comando que nao e curl', async () => {
        const wrapper = montar();

        await digitar(wrapper, 'wget https://x.test');

        expect(wrapper.get('[data-test="curl-state"]').text()).toBe(
            'NAO_CURL:wget',
        );
        expect(
            wrapper.get('[data-test="curl-state"]').attributes('data-state'),
        ).toBe('invalid');
        expect(wrapper.findAll('[data-test^="curl-tab-"]')).toHaveLength(0);
        expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        expect(wrapper.get('[data-test="curl-status"]').text()).toBe(
            'NAO_CURL:wget',
        );
    });

    it('traduz o problema do shell', async () => {
        const wrapper = montar();

        await digitar(wrapper, "curl 'https://x.test");

        expect(wrapper.get('[data-test="curl-state"]').text()).toBe(
            'SHELL:ASPAS_SIMPLES',
        );
    });

    it('carrega o exemplo e limpa', async () => {
        const wrapper = montar();

        await clicar(wrapper, 'EXEMPLO');

        expect(wrapper.get('[data-test="editor"]').text()).toContain(
            'curl -X POST https://api.example.com/users',
        );
        expect(saida(wrapper)).toContain('Http::withHeaders([');

        await clicar(wrapper, 'LIMPAR');

        expect(wrapper.get('[data-test="editor"]').text()).toBe('');
        expect(wrapper.get('[data-test="curl-state"]').text()).toBe(
            'COLE_UM_COMANDO',
        );
    });

    it('copia a aba aberta', async () => {
        const wrapper = montar();

        await digitar(wrapper, EXEMPLO);
        await abrir(wrapper, 'fetch');
        await clicar(wrapper, 'COPIAR');

        expect(clipboard.writeText).toHaveBeenCalledTimes(1);

        const text = clipboard.writeText.mock.calls[0]?.[0] as string;

        expect(text.startsWith('const response = await fetch(')).toBe(true);
        expect(text).toContain("method: 'POST'");
        expect(wrapper.get('[data-test="curl-status"]').text()).toBe('COPIADO');
    });

    it('rotula os dois paineis', () => {
        const wrapper = montar();

        expect(wrapper.text()).toContain('COMANDO');
        expect(wrapper.text()).toContain('CODIGO');
    });
});
