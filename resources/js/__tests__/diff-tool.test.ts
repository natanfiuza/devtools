import {
    flushPromises,
    mount,
    type DOMWrapper,
    type VueWrapper,
} from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/diff/Tool.vue';

/**
 * A tela do diff: dois paineis de entrada, o modo texto/JSON, a pintura, a
 * navegacao e o relatorio.
 *
 * O motor tem testes proprios (diff-lines/diff-json/diff-report) - aqui o que
 * se cobra e o que a **tela** faz com ele: os rotulos que trocam Entrada/Saida
 * por Original/Modificado, o que fica desabilitado, o realce que chega em cada
 * editor, a lista de caminhos do modo JSON e o que sobra enquanto o texto
 * espera a proxima conta.
 *
 * No jsdom nao existe `Worker`, entao o diff roda na thread principal: e o
 * mesmo caminho da queda no navegador, e o unico tempo a esperar e o intervalo
 * entre a ultima tecla e a conta.
 */

const ESPERA = 260;

const traducoes = {
    tools: {
        ui: {
            input: 'ENTRADA',
            output: 'SAIDA',
            resize: 'REDIMENSIONAR',
            drop: 'SOLTE_AQUI',
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
                parsing: 'ANALISANDO',
                invalid: 'INVALIDO:{line}:{column}',
                invalid_no_position: 'SEM_POSICAO',
                too_large: 'GRANDE:{size}:{limit}',
                file_too_large: 'ARQUIVO_GRANDE:{size}:{limit}',
                file_type: 'TIPO_NAO_ACEITO',
                file_unreadable: 'ILEGIVEL',
                drop_hint: 'SOLTE_PARA_ABRIR',
            },
        },
        diff: {
            left: 'ORIGINAL',
            right: 'MODIFICADO',
            modes: { text: 'TEXTO', json: 'JSON' },
            open_left: 'ABRIR_ORIGINAL',
            open_right: 'ABRIR_MODIFICADO',
            previous: 'ANTERIOR',
            next: 'PROXIMA',
            no_changes: 'SEM_DIFERENCA',
            truncated: 'GROSSEIRO',
            changes_title: 'DIFERENCAS',
            file_types: 'TEXTOS',
            download_name: 'diff',
            summary: {
                text: 'TEXTO:+{added}:−{removed}',
                json: 'JSON:+{added}:−{removed}:~{changed}',
            },
            invalid_side: '{side}:{message}',
            report: {
                title: 'RELATORIO',
                text: 'TEXTO',
                json: 'JSON',
                original: 'ORIGINAL',
                modified: 'MODIFICADO',
                added: 'ADICIONADAS',
                removed: 'REMOVIDAS',
                changed: 'ALTERADAS',
                no_changes: 'SEM_DIFERENCA',
                truncated: 'GROSSEIRO',
                invalid: 'JSON_INVALIDO',
                line: 'linha',
                column: 'coluna',
            },
        },
    },
};

/** O duble do editor: mesmo contrato de texto, sem CodeMirror. */
const EditorStub = defineComponent({
    name: 'CodeEditor',
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
        testid: { type: String, default: 'code-editor' },
        error: {
            type: Object as PropType<{
                position: number;
                message: string;
            } | null>,
            default: null,
        },
        lineHighlights: {
            type: Array as PropType<{ line: number; tone: string }[]>,
            default: () => [],
        },
    },
    emits: ['update:modelValue'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    'data-test': 'editor',
                    'data-editor': props.testid,
                    'data-placeholder': props.placeholder,
                    'data-error': props.error?.message ?? '',
                    'data-error-position': String(props.error?.position ?? ''),
                    'data-highlights': JSON.stringify(props.lineHighlights),
                },
                props.modelValue,
            );
    },
});

const LIMITE_FOLGADO = 5_000_000;

function montar(maxInputBytes: number = LIMITE_FOLGADO): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt_BR', translations: traducoes });

    return mount(Tool, {
        props: { maxInputBytes },
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

function editor(wrapper: VueWrapper, lado: 'esquerda' | 'direita') {
    const testid =
        lado === 'esquerda' ? 'diff-left-editor' : 'diff-right-editor';
    const found = wrapper
        .findAllComponents({ name: 'CodeEditor' })
        .find((component) => component.props('testid') === testid);

    if (found === undefined) {
        throw new Error(`o editor da ${lado} nao esta na tela`);
    }

    return found;
}

function textoDoEditor(
    wrapper: VueWrapper,
    lado: 'esquerda' | 'direita',
): string {
    return editor(wrapper, lado).text();
}

function highlights(
    wrapper: VueWrapper,
    lado: 'esquerda' | 'direita',
): { line: number; tone: string }[] {
    const raw = editor(wrapper, lado).attributes('data-highlights') ?? '[]';

    return JSON.parse(raw) as { line: number; tone: string }[];
}

function erroDoEditor(
    wrapper: VueWrapper,
    lado: 'esquerda' | 'direita',
): string {
    return editor(wrapper, lado).attributes('data-error') ?? '';
}

function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="diff-status"]');

    return node.exists() ? node.text() : '';
}

function mudancas(wrapper: VueWrapper): { kind: string; path: string }[] {
    return wrapper.findAll('[data-test="diff-change"]').map((node) => ({
        kind: node.attributes('data-kind') ?? '',
        path: node.find('code').text(),
    }));
}

async function esperarDiff(): Promise<void> {
    await new Promise((resolve) => window.setTimeout(resolve, ESPERA));
    await flushPromises();
}

async function digitar(
    wrapper: VueWrapper,
    lado: 'esquerda' | 'direita',
    valor: string,
): Promise<void> {
    editor(wrapper, lado).vm.$emit('update:modelValue', valor);

    await esperarDiff();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

async function modo(wrapper: VueWrapper, qual: 'text' | 'json'): Promise<void> {
    await wrapper.get(`[data-test="diff-mode-${qual}"]`).trigger('click');
    await flushPromises();
}

describe('Diff', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    describe('estado inicial', () => {
        it('abre com os dois paineis vazios e sem diff', () => {
            const wrapper = montar();

            expect(
                wrapper
                    .get('[data-editor="diff-left-editor"]')
                    .attributes('data-placeholder'),
            ).toBe('VAZIO');
            expect(
                wrapper
                    .get('[data-editor="diff-right-editor"]')
                    .attributes('data-placeholder'),
            ).toBe('VAZIO');
            expect(wrapper.find('[data-test="diff-status"]').exists()).toBe(
                false,
            );
            expect(wrapper.find('[data-test="diff-changes"]').exists()).toBe(
                false,
            );
        });

        it('rotula os paineis como Original e Modificado', () => {
            const wrapper = montar();

            expect(wrapper.text()).toContain('ORIGINAL');
            expect(wrapper.text()).toContain('MODIFICADO');
        });

        it('abre no modo texto, com o que precisa de resultado desabilitado', () => {
            const wrapper = montar();

            expect(
                wrapper
                    .get('[data-test="diff-mode-text"]')
                    .attributes('aria-pressed'),
            ).toBe('true');
            expect(
                wrapper
                    .get('[data-test="diff-mode-json"]')
                    .attributes('aria-pressed'),
            ).toBe('false');

            for (const rotulo of [
                'ANTERIOR',
                'PROXIMA',
                'COPIAR',
                'BAIXAR',
                'LIMPAR',
            ]) {
                expect(desabilitado(wrapper, rotulo), rotulo).toBe(true);
            }
            expect(desabilitado(wrapper, 'EXEMPLO')).toBe(false);
        });
    });

    describe('modo texto', () => {
        it('resume as linhas e pinta o par alterado dos dois lados', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'esquerda', 'a\nb\nc');
            await digitar(wrapper, 'direita', 'a\nB\nc');

            expect(rodape(wrapper)).toBe('TEXTO:+1:−1');
            expect(highlights(wrapper, 'esquerda')).toEqual([
                { line: 1, tone: 'changed' },
            ]);
            expect(highlights(wrapper, 'direita')).toEqual([
                { line: 1, tone: 'changed' },
            ]);
        });

        it('pinta adicao e remocao sem par', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'esquerda', 'a\nb');
            await digitar(wrapper, 'direita', 'a\nb\nc');

            expect(rodape(wrapper)).toBe('TEXTO:+1:−0');
            expect(highlights(wrapper, 'esquerda')).toEqual([]);
            expect(highlights(wrapper, 'direita')).toEqual([
                { line: 2, tone: 'added' },
            ]);
        });

        it('diz que nao ha diferenca em textos iguais', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'esquerda', 'a\nb');
            await digitar(wrapper, 'direita', 'a\nb');

            expect(rodape(wrapper)).toBe('SEM_DIFERENCA');
        });

        it('limpa o realce enquanto o texto espera a proxima conta', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'esquerda', 'a\nb');
            await digitar(wrapper, 'direita', 'a\nB');

            expect(highlights(wrapper, 'esquerda')).toHaveLength(1);

            // O resultado anterior nao vale para o texto novo: some a pintura
            // (e o resumo) ate a conta do intervalo chegar.
            editor(wrapper, 'esquerda').vm.$emit(
                'update:modelValue',
                'a\nx\nb',
            );
            await flushPromises();

            expect(highlights(wrapper, 'esquerda')).toEqual([]);
            expect(rodape(wrapper)).toBe('');
        });

        it('carrega o exemplo e calcula sem esperar o intervalo', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(textoDoEditor(wrapper, 'esquerda')).toContain('workers = 2');
            expect(textoDoEditor(wrapper, 'direita')).toContain('workers = 4');
            expect(rodape(wrapper)).toBe('TEXTO:+3:−2');
            expect(highlights(wrapper, 'esquerda')).toEqual([
                { line: 2, tone: 'changed' },
                { line: 3, tone: 'changed' },
            ]);
        });

        it('habilita a navegacao e percorre as regioes sem estourar', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(desabilitado(wrapper, 'PROXIMA')).toBe(false);
            expect(desabilitado(wrapper, 'ANTERIOR')).toBe(false);

            await clicar(wrapper, 'PROXIMA');
            await clicar(wrapper, 'ANTERIOR');

            // Os dois editores sao dublês (sem `EditorView`): a navegacao some
            // no guarda, e o que sobra e o resumo intacto.
            expect(rodape(wrapper)).toBe('TEXTO:+3:−2');
        });
    });

    describe('modo JSON', () => {
        it('recusa o texto que nao e JSON e aponta o lado', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await modo(wrapper, 'json');

            expect(rodape(wrapper)).toMatch(/^ORIGINAL:/);
            expect(mudancas(wrapper)).toEqual([]);
        });

        it('lista os caminhos que mudaram, ignorando a ordem das chaves', async () => {
            const wrapper = montar();

            await modo(wrapper, 'json');
            await clicar(wrapper, 'EXEMPLO');

            expect(rodape(wrapper)).toBe('JSON:+1:−0:~2');
            expect(mudancas(wrapper)).toEqual([
                { kind: 'changed', path: '$.flags.debug' },
                { kind: 'changed', path: '$.tags[1]' },
                { kind: 'added', path: '$.owner' },
            ]);
        });

        it('nao mostra lista quando o documento e o mesmo', async () => {
            const wrapper = montar();

            await modo(wrapper, 'json');
            await digitar(wrapper, 'esquerda', '{"a":1,"b":2}');
            await digitar(wrapper, 'direita', '{"b":2,"a":1}');

            expect(rodape(wrapper)).toBe('SEM_DIFERENCA');
            expect(wrapper.find('[data-test="diff-changes"]').exists()).toBe(
                false,
            );
        });

        it('marca o erro na posicao do lado invalido', async () => {
            const wrapper = montar();

            await modo(wrapper, 'json');
            await digitar(wrapper, 'esquerda', '{"a":1}');
            await digitar(wrapper, 'direita', '{\n  "a": 1\n  "b": 2\n}');

            expect(rodape(wrapper)).toMatch(/^MODIFICADO:INVALIDO:3:/);
            expect(erroDoEditor(wrapper, 'direita')).toMatch(/^INVALIDO:3:/);
            expect(erroDoEditor(wrapper, 'esquerda')).toBe('');
        });

        it('desabilita a navegacao, que e do modo texto', async () => {
            const wrapper = montar();

            await modo(wrapper, 'json');
            await clicar(wrapper, 'EXEMPLO');

            expect(desabilitado(wrapper, 'PROXIMA')).toBe(true);
            expect(desabilitado(wrapper, 'ANTERIOR')).toBe(true);
        });
    });

    describe('limite e limpeza', () => {
        it('nao compara o lado que passa do limite', async () => {
            const wrapper = montar(4);

            await digitar(wrapper, 'esquerda', '{"a":1}');

            expect(rodape(wrapper)).toContain('GRANDE:');
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });

        it('limpa os dois paineis e esquece o resultado', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'LIMPAR');

            expect(textoDoEditor(wrapper, 'esquerda')).toBe('');
            expect(textoDoEditor(wrapper, 'direita')).toBe('');
            expect(rodape(wrapper)).toBe('');
            expect(desabilitado(wrapper, 'BAIXAR')).toBe(true);
        });
    });

    describe('arquivo', () => {
        /** Solta o arquivo e espera a tela responder (o `FileReader` e async). */
        async function soltar(
            wrapper: VueWrapper,
            lado: 'esquerda' | 'direita',
            file: File,
            esperado: string,
        ): Promise<void> {
            const testid = lado === 'esquerda' ? 'diff-left' : 'diff-right';

            await wrapper
                .get(`[data-test="${testid}-dropzone"]`)
                .trigger('drop', { dataTransfer: { files: [file] } });

            const inicio = Date.now();

            // O sinal de que o arquivo entrou e o proprio editor: o rodape
            // pode ser o da solta anterior.
            while (Date.now() - inicio < 2000) {
                await flushPromises();

                if (textoDoEditor(wrapper, lado) === esperado) {
                    await flushPromises();

                    return;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 20));
            }
        }

        it('compara os dois arquivos soltos nos paineis', async () => {
            const wrapper = montar();

            // So o lado esquerdo tem texto: as tres linhas saem do original.
            await soltar(
                wrapper,
                'esquerda',
                new File(['a\nb\nc'], 'antes.txt'),
                'a\nb\nc',
            );
            expect(rodape(wrapper)).toBe('TEXTO:+0:−3');

            await soltar(
                wrapper,
                'direita',
                new File(['a\nB\nc'], 'depois.txt'),
                'a\nB\nc',
            );
            expect(rodape(wrapper)).toBe('TEXTO:+1:−1');
        });

        it('recusa arquivo de outro tipo', async () => {
            const wrapper = montar();

            await wrapper
                .get('[data-test="diff-left-dropzone"]')
                .trigger('drop', {
                    dataTransfer: { files: [new File(['oi'], 'foto.png')] },
                });
            await flushPromises();

            expect(rodape(wrapper)).toBe('TIPO_NAO_ACEITO');
        });
    });

    describe('copiar e baixar', () => {
        it('copia o relatorio do diff e confirma no rodape', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'COPIAR');

            const copiado = clipboard.writeText.mock.calls[0]?.[0] as string;

            expect(copiado).toContain('RELATORIO');
            expect(copiado).toContain('TEXTO: ORIGINAL → MODIFICADO');
            expect(copiado).toContain('@@ -3 +3 @@');
            expect(rodape(wrapper)).toBe('COPIADO');
        });

        it('manda copiar na mao quando o navegador nao expoe a area de transferencia', async () => {
            Object.defineProperty(navigator, 'clipboard', {
                value: undefined,
                configurable: true,
            });

            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'COPIAR');

            expect(rodape(wrapper)).toBe('COPIE_NA_MAO');
        });

        it('baixa o relatorio com o nome da tool', async () => {
            const baixados: string[] = [];

            Object.defineProperty(URL, 'createObjectURL', {
                value: vi.fn((_blob: Blob) => 'blob:teste'),
                configurable: true,
            });
            Object.defineProperty(URL, 'revokeObjectURL', {
                value: vi.fn(),
                configurable: true,
            });
            vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(
                function (this: HTMLAnchorElement) {
                    baixados.push(this.download);
                },
            );

            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'BAIXAR');

            expect(baixados).toEqual(['diff.txt']);
        });
    });
});
