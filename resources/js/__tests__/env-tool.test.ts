import {
    flushPromises,
    mount,
    type DOMWrapper,
    type VueWrapper,
} from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { acceptsFile } from '@/lib/file';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { ENV_FILE_RULES } from '@/tools/env-example/lib/files';
import Tool from '@/tools/env-example/Tool.vue';

/**
 * A tela do `.env.example`.
 *
 * O parser e a mascara tem testes proprios; aqui se cobra o que a tela faz com
 * eles: a saida preservada, o banner com as chaves e as linhas, o exemplo, a
 * copia e o download. A aceitacao do arquivo tambem e cobrada, porque `.env`
 * nao tem extensao e o risco do formato esta na lista de aceites.
 */

const { downloadText } = vi.hoisted(() => ({ downloadText: vi.fn() }));

vi.mock('@/lib/download', () => ({
    downloadText,
}));

const traducoes = {
    tools: {
        ui: {
            input: 'ENTRADA',
            output: 'SAIDA',
            resize: 'REDIMENSIONAR',
            actions: {
                open: 'ABRIR',
                example: 'EXEMPLO',
                clear: 'LIMPAR',
                copy: 'COPIAR',
                copied: 'COPIADO',
                copy_manual: 'COPIE_NA_MAO',
                download: 'BAIXAR',
            },
            states: {
                empty: 'VAZIO',
                file_type: 'TIPO:{formats}',
                file_too_large: 'GRANDE:{size}:{limit}',
                file_unreadable: 'ILEGIVEL',
            },
        },
        'env-example': {
            panes: { source: 'ORIGEM', example: 'SAIDA_ENV' },
            file_types: 'ENV',
            states: { empty: 'COLE_UM_ENV' },
            notices: {
                masked: 'MASCARADAS',
                masked_key: '{key} NA_LINHA_{line}',
                invalid: 'INVALIDAS',
                invalid_line: 'LINHA_{line}',
            },
            status: { summary: 'MASCARADAS:{masked}:INVALIDAS:{invalid}' },
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

function montar(): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Tool, {
        props: { maxInputBytes: 5_000_000 },
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

function saida(wrapper: VueWrapper): string {
    return wrapper.get('[data-test="env-output"]').text();
}

describe('ENV para exemplo', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });

        downloadText.mockClear();
    });

    it('abre vazio, com as acoes desabilitadas', () => {
        const wrapper = montar();

        expect(wrapper.get('[data-test="env-state"]').text()).toBe(
            'COLE_UM_ENV',
        );
        expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        expect(desabilitado(wrapper, 'BAIXAR')).toBe(true);
        expect(wrapper.get('[data-test="env-status"]').text()).toBe('VAZIO');
        expect(wrapper.find('[data-test="env-notices"]').exists()).toBe(false);
    });

    it('mascara o valor sensivel e preserva o resto', async () => {
        const wrapper = montar();

        await digitar(
            wrapper,
            '# app\nAPP_NAME=Demo\nAPP_KEY=abc\nDB_PASSWORD="p@ss"',
        );

        expect(saida(wrapper)).toBe(
            '# app\nAPP_NAME=Demo\nAPP_KEY=\nDB_PASSWORD=""',
        );
        expect(wrapper.get('[data-test="env-notices"]').text()).toContain(
            'APP_KEY NA_LINHA_3',
        );
        expect(wrapper.get('[data-test="env-notices"]').text()).toContain(
            'DB_PASSWORD NA_LINHA_4',
        );
        expect(wrapper.get('[data-test="env-status"]').text()).toBe(
            'MASCARADAS:2:INVALIDAS:0',
        );
        expect(desabilitado(wrapper, 'COPIAR')).toBe(false);
        expect(desabilitado(wrapper, 'BAIXAR')).toBe(false);
    });

    it('avisa a linha que ficou invalida', async () => {
        const wrapper = montar();

        await digitar(wrapper, 'APP_KEY=abc\nSEM_IGUAL');

        expect(wrapper.get('[data-test="env-invalid-line"]').text()).toBe(
            'LINHA_2',
        );
        expect(saida(wrapper)).toContain('SEM_IGUAL');
        expect(wrapper.get('[data-test="env-status"]').text()).toBe(
            'MASCARADAS:1:INVALIDAS:1',
        );
    });

    it('carrega o exemplo e limpa', async () => {
        const wrapper = montar();

        await clicar(wrapper, 'EXEMPLO');

        expect(wrapper.get('[data-test="editor"]').text()).toContain(
            'APP_NAME=Demo',
        );
        expect(saida(wrapper)).toContain('APP_KEY=\n');
        expect(saida(wrapper)).toContain('DB_PASSWORD= ');
        expect(saida(wrapper)).toContain('DATABASE_URL=');
        expect(wrapper.findAll('[data-test="env-masked-key"]')).toHaveLength(3);

        await clicar(wrapper, 'LIMPAR');

        expect(wrapper.get('[data-test="editor"]').text()).toBe('');
        expect(wrapper.get('[data-test="env-state"]').text()).toBe(
            'COLE_UM_ENV',
        );
    });

    it('copia o exemplo inteiro', async () => {
        const wrapper = montar();

        await digitar(wrapper, 'APP_KEY=abc\nAPP_NAME=Demo');
        await clicar(wrapper, 'COPIAR');

        expect(clipboard.writeText).toHaveBeenCalledWith(
            'APP_KEY=\nAPP_NAME=Demo',
        );
        expect(wrapper.get('[data-test="env-status"]').text()).toBe('COPIADO');
    });

    it('baixa como .env.example', async () => {
        const wrapper = montar();

        await digitar(wrapper, 'APP_KEY=abc\nAPP_NAME=Demo');
        await clicar(wrapper, 'BAIXAR');

        expect(downloadText).toHaveBeenCalledWith(
            '.env.example',
            'APP_KEY=\nAPP_NAME=Demo',
            'text/plain',
        );
    });

    it('rotula os dois paineis', () => {
        const wrapper = montar();

        expect(wrapper.text()).toContain('ORIGEM');
        expect(wrapper.text()).toContain('SAIDA_ENV');
    });
});

describe('arquivos aceitos', () => {
    function file(name: string, type = ''): File {
        return new File(['x'], name, { type });
    }

    it('aceita o .env e as variantes por nome', () => {
        for (const name of [
            '.env',
            '.env.local',
            '.env.production',
            '.env.example',
        ]) {
            expect(
                acceptsFile(file(name), {
                    ...ENV_FILE_RULES,
                    maxBytes: 1024,
                }),
                name,
            ).toBe(true);
        }
    });

    it('aceita text/plain e recusa outro arquivo sem tipo', () => {
        const rules = { ...ENV_FILE_RULES, maxBytes: 1024 };

        expect(acceptsFile(file('notas', 'text/plain'), rules)).toBe(true);
        expect(acceptsFile(file('notas.json'), rules)).toBe(false);
    });
});
