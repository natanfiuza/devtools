import { flushPromises, mount, type DOMWrapper, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/json-viewer/Tool.vue';

/**
 * O workspace do JSON Viewer: os dois paineis, a arvore, a busca e as acoes.
 *
 * O editor de verdade fica de fora: o CodeMirror precisa do DOM real para
 * medir linha e coluna, e o que este teste cobra e o que a tela faz com o
 * **texto**. O duble no lugar dele recebe e devolve o mesmo `v-model`. O tema
 * do editor tem teste proprio (json-editor-theme.test.ts) e o layout do editor
 * entra na conferencia manual da fase.
 *
 * A analise roda na thread principal: o jsdom nao tem `Worker`, que e
 * justamente o caminho de queda do cliente - o caminho do worker esta em
 * json-worker.test.ts.
 */

const ESPERA = 260;

const traducoes = {
    tools: {
        status: { soon: 'SELO_EM_BREVE' },
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
                unavailable: 'INDISPONIVEL',
                invalid: 'INVALIDO:{line}:{column}',
                invalid_no_position: 'INVALIDO_SEM_POSICAO',
                stale_output: 'SAIDA_DEFASADA',
                too_large: 'GRANDE:{size}:{limit}',
                file_too_large: 'ARQUIVO_GRANDE:{size}:{limit}',
                file_type: 'TIPO_NAO_ACEITO',
                file_unreadable: 'ILEGIVEL',
                drop_hint: 'SOLTE_PARA_ABRIR',
            },
            limits: { of: 'de {limit}' },
            shortcuts: { title: 'ATALHOS' },
        },
        'json-viewer': {
            format: 'FORMATAR',
            minify: 'MINIFICAR',
            sort_keys: 'ORDENAR',
            expand_all: 'EXPANDIR',
            collapse_all: 'RECOLHER',
            download_name: 'documento',
            file_types: 'JSON_OU_TXT',
            tree: {
                toggle: 'ALTERNAR',
                copy_path: 'COPIAR_CAMINHO',
                copied: 'CAMINHO:{path}',
            },
            search: {
                placeholder: 'BUSCAR',
                label: 'BUSCAR_ARVORE',
                clear: 'LIMPAR_BUSCA',
                results: 'RESULTADOS:{count}',
                truncated: 'TRUNCADO:{count}',
                none: 'SEM_RESULTADO',
            },
            stats: { keys: 'CHAVES:{count}', items: 'ITENS:{count}' },
            shortcuts: {
                format: 'ATALHO_FORMATAR',
                search: 'ATALHO_BUSCA',
                clear: 'ATALHO_LIMPAR',
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
        error: {
            type: Object as PropType<{ message: string } | null>,
            default: null,
        },
    },
    emits: ['update:modelValue'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    'data-test': 'json-editor',
                    'data-placeholder': props.placeholder,
                    'data-error': props.error?.message ?? '',
                },
                props.modelValue,
            );
    },
});

/** Limite de folga: o dos testes de limite e passado na mao. */
const LIMITE_FOLGADO = 5_000_000;

/** O limiar da virtualizacao, como o controller o entrega (config/tools.php). */
const VIRTUALIZA_DE = 2000;

function montar(
    maxInputBytes: number = LIMITE_FOLGADO,
    virtualizeFrom: number = VIRTUALIZA_DE,
): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt-br', translations: traducoes });

    return mount(Tool, {
        props: { maxInputBytes, virtualizeFrom },
        global: {
            plugins: [i18n],
            stubs: { CodeEditor: EditorStub },
        },
    });
}

/**
 * O rotulo do botao, sem as teclas.
 *
 * O `kbd` do atalho mora dentro do botao (DESIGN: "atalhos visiveis"), e o
 * `text()` do Vue Test Utils devolve o texto todo: sem tirar as teclas, o
 * "Formatar" deixaria de casar com "FORMATAR Ctrl Enter".
 */
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

function textoDoEditor(wrapper: VueWrapper): string {
    return wrapper.get('[data-test="json-editor"]').text();
}

function linhas(wrapper: VueWrapper): DOMWrapper<Element>[] {
    return wrapper.findAll('[data-test="json-tree-row"]');
}

/**
 * Espera a analise do texto que acabou de entrar.
 *
 * Sao dois tempos: o intervalo entre a ultima tecla e a conta (o `debounce` da
 * tela), e a conta em si - que aqui roda na thread principal e resolve num
 * microtask. O `flushPromises` no fim entrega o DOM ja pintado.
 */
async function esperarAnalise(): Promise<void> {
    await new Promise((resolve) => window.setTimeout(resolve, ESPERA));
    await flushPromises();
}

/**
 * O texto entra pelo editor - tecla, colagem ou o que o navegador mandar.
 *
 * O `v-model` nao distingue um do outro, entao a tela analisa depois do
 * intervalo de espera em todos os casos: quem entra sem espera e so o que vem
 * de um botao (exemplo, arquivo), que ja passou pela analise antes de escrever.
 */
async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper.findComponent({ name: 'CodeEditor' }).vm.$emit('update:modelValue', value);

    await esperarAnalise();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

/** Texto valido e pequeno, com uma chave aninhada e um item de array. */
const DOCUMENTO = '{\n  "autor": {\n    "nome": "Nataniel",\n    "site": "exemplo.dev"\n  },\n  "tags": ["json", "arvore"]\n}';

describe('JSON Viewer', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    describe('estado inicial', () => {
        it('abre sem documento, com o convite no editor e sem arvore', () => {
            const wrapper = montar();

            expect(wrapper.get('[data-test="json-editor"]').attributes('data-placeholder')).toBe(
                'VAZIO',
            );
            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="json-viewer-state"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="json-viewer-status"]').exists()).toBe(false);
        });

        it('desabilita o que precisa de documento', () => {
            const wrapper = montar();

            for (const rotulo of ['LIMPAR', 'COPIAR', 'BAIXAR', 'FORMATAR', 'MINIFICAR', 'ORDENAR']) {
                expect(desabilitado(wrapper, rotulo), rotulo).toBe(true);
            }
        });

        it('mostra os atalhos do teclado, com a tecla ao lado de cada um', () => {
            const wrapper = montar();
            const faixa = wrapper.get('[data-test="tool-shell-shortcuts"]');
            const atalhos = faixa.text();

            expect(atalhos).toContain('ATALHO_FORMATAR');
            expect(atalhos).toContain('ATALHO_BUSCA');
            expect(atalhos).toContain('ATALHO_LIMPAR');
            expect(faixa.findAll('kbd').map((tecla) => tecla.text())).toEqual([
                'Ctrl Enter',
                'Ctrl F',
                'Esc',
            ]);
        });

        it('anuncia o atalho no proprio botao de formatar', () => {
            const wrapper = montar();

            expect(botao(wrapper, 'FORMATAR').get('kbd').text()).toBe('Ctrl Enter');
        });
    });

    describe('arvore', () => {
        it('desenha a arvore do documento colado', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);

            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(true);

            // Raiz mais os dois filhos do primeiro nivel: o resto nasce fechado.
            expect(linhas(wrapper)).toHaveLength(3);
            expect(linhas(wrapper)[0]?.text()).toBe('{2}');
        });

        it('abre e fecha um no pelo botao da linha', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);

            const autor = linhas(wrapper)[1];

            await autor?.get('[data-test="json-tree-toggle"]').trigger('click');
            await flushPromises();

            // Os dois filhos do `autor` entraram na tela.
            expect(linhas(wrapper)).toHaveLength(5);
            expect(wrapper.get('[data-test="json-tree"]').text()).toContain('exemplo.dev');

            await autor?.get('[data-test="json-tree-toggle"]').trigger('click');
            await flushPromises();

            expect(linhas(wrapper)).toHaveLength(3);
        });

        it('expande e recolhe tudo', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'EXPANDIR');

            // A arvore inteira: raiz, 2 chaves e 4 folhas.
            expect(linhas(wrapper)).toHaveLength(7);

            await clicar(wrapper, 'RECOLHER');

            // Recolher tudo mantem a raiz aberta - fechada, nao sobraria nada.
            expect(linhas(wrapper)).toHaveLength(3);
        });

        it('renderiza so a janela quando a lista passa do limiar da configuracao', async () => {
            // O limiar vem de `config/tools.php` e desce por prop, como o teto
            // de entrada - com um limiar baixo, uma lista pequena ja exercita
            // a janela, que e o que o jsdom alcanca (sem layout nao ha altura
            // de janela, so a folga).
            const grande = `{"itens": [${Array.from({ length: 30 }, (_, i) => i).join(', ')}]}`;

            const semJanela = montar(LIMITE_FOLGADO, 1000);

            await digitar(semJanela, grande);
            await clicar(semJanela, 'EXPANDIR');

            // A lista inteira: raiz, a chave e os 30 itens.
            expect(linhas(semJanela)).toHaveLength(32);

            const comJanela = montar(LIMITE_FOLGADO, 3);

            await digitar(comJanela, grande);
            await clicar(comJanela, 'EXPANDIR');

            expect(linhas(comJanela).length).toBeLessThan(32);
            expect(linhas(comJanela).length).toBeGreaterThan(0);
        });
    });

    describe('busca', () => {
        it('acha pelo valor, abre o caminho ate o resultado e conta', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            expect(wrapper.get('[data-test="json-tree"]').text()).not.toContain('Nataniel');

            await wrapper.get('[data-test="json-viewer-search"]').setValue('Nataniel');
            await flushPromises();

            expect(wrapper.get('[data-test="json-viewer-search-count"]').text()).toBe(
                'RESULTADOS:1',
            );
            // O `autor` estava fechado: a busca abriu o caminho ate a folha.
            expect(wrapper.get('[data-test="json-tree"]').text()).toContain('Nataniel');
        });

        it('avisa quando nao ha resultado', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await wrapper.get('[data-test="json-viewer-search"]').setValue('nao-existe');
            await flushPromises();

            expect(wrapper.get('[data-test="json-viewer-search-count"]').text()).toBe(
                'SEM_RESULTADO',
            );
        });

        it('limpa a busca', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await wrapper.get('[data-test="json-viewer-search"]').setValue('Nataniel');
            await flushPromises();

            await clicar(wrapper, 'LIMPAR_BUSCA');

            expect(wrapper.find('[data-test="json-viewer-search-count"]').exists()).toBe(false);
        });

        it('mostra o Esc junto do campo de busca', () => {
            const wrapper = montar();

            expect(wrapper.get('[data-test="json-viewer-search-shortcut"]').text()).toBe('Esc');
        });

        it('limpa a busca no Esc', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await wrapper.get('[data-test="json-viewer-search"]').setValue('Nataniel');
            await flushPromises();

            // O Esc vale com o foco onde estiver - quem digitou no editor logo
            // depois de um `Mod-f` tambem espera que ele desfaca a busca.
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
            await flushPromises();

            const campo = wrapper.get('[data-test="json-viewer-search"]')
                .element as HTMLInputElement;

            expect(campo.value).toBe('');
            expect(wrapper.find('[data-test="json-viewer-search-count"]').exists()).toBe(false);
        });
    });

    describe('caminho', () => {
        it('copia o caminho JSONPath da linha', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'EXPANDIR');

            const linha = linhas(wrapper).find((node) => node.text().includes('exemplo.dev'));

            await linha?.get('[data-test="json-tree-copy"]').trigger('click');
            await flushPromises();

            // `site` esta dentro de `autor`: o caminho sobe ate a raiz.
            expect(clipboard.writeText).toHaveBeenCalledWith('$.autor.site');
            expect(wrapper.get('[data-test="json-viewer-status"]').text()).toBe(
                'CAMINHO:$.autor.site',
            );
        });

        it('manda copiar na mao quando o navegador nao expoe a area de transferencia', async () => {
            Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });

            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'COPIAR');

            expect(wrapper.get('[data-test="json-viewer-status"]').text()).toBe('COPIE_NA_MAO');
        });
    });

    describe('documento', () => {
        it('conta chaves, itens e o tamanho contra o limite', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":[1,true,null]}');

            const rodape = wrapper.get('[data-test="json-viewer-status"]').text();

            expect(rodape).toContain('CHAVES:1');
            expect(rodape).toContain('ITENS:3');
            // O rotulo da unidade e do `Intl`: o que e nosso e o numero e a
            // unidade escolhida (bytes, e nao kB).
            expect(rodape).toMatch(/19\s*bytes/);
            expect(rodape).toMatch(/de\s*4,8/);
        });

        it('formata o documento', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1,"b":[2,3]}');
            await clicar(wrapper, 'FORMATAR');

            expect(textoDoEditor(wrapper)).toBe('{\n  "a": 1,\n  "b": [\n    2,\n    3\n  ]\n}');
        });

        it('formata o documento pelo atalho do editor', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1,"b":[2,3]}');

            // O `Mod-Enter` de verdade esta em json-editor-keys.test.ts, que
            // monta o CodeMirror; aqui o que se cobra e a ligacao entre o
            // recado do editor e a acao da tela.
            wrapper.findComponent({ name: 'CodeEditor' }).vm.$emit('format');
            await flushPromises();

            expect(textoDoEditor(wrapper)).toBe('{\n  "a": 1,\n  "b": [\n    2,\n    3\n  ]\n}');
        });

        it('minifica o documento', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{\n  "a": 1,\n  "b": 2\n}');
            await clicar(wrapper, 'MINIFICAR');

            expect(textoDoEditor(wrapper)).toBe('{"a":1,"b":2}');
        });

        it('ordena as chaves e leva a arvore junto', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"b":2,"a":1}');
            await clicar(wrapper, 'ORDENAR');

            expect(textoDoEditor(wrapper)).toBe('{\n  "a": 1,\n  "b": 2\n}');
            expect(linhas(wrapper)[1]?.text()).toContain('a: 1');
        });

        it('mostra o erro com linha e coluna', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{\n  "a": 1\n  "b": 2\n}');

            // Sem nenhuma versao valida antes, nao ha arvore nenhuma para
            // mostrar: a faixa de estado ocupa o lugar dela.
            expect(wrapper.get('[data-test="json-viewer-state"]').text()).toContain(
                'INVALIDO:3:3',
            );
            expect(wrapper.get('[data-test="json-editor"]').attributes('data-error')).toBe(
                'INVALIDO:3:3',
            );
            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(false);
        });

        it('mantem a ultima arvore valida na tela, marcada como defasada', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'EXPANDIR');

            // A arvore do documento que valia.
            expect(linhas(wrapper)).toHaveLength(7);

            await digitar(wrapper, '{\n  "a": 1\n  "b": 2\n}');

            // O texto quebrou: a arvore **continua** na tela, com o aviso, e o
            // erro com linha e coluna segue visivel no rodape (SPEC secao 5).
            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(true);
            expect(linhas(wrapper)).toHaveLength(7);
            expect(wrapper.get('[data-test="json-viewer-stale"]').text()).toBe(
                'SAIDA_DEFASADA',
            );
            expect(wrapper.get('[data-test="json-viewer-status"]').text()).toContain(
                'INVALIDO:3:3',
            );
        });

        it('larga a arvore defasada quando o documento e limpo', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await digitar(wrapper, '{\n  "a": 1\n  "b": 2\n}');

            expect(wrapper.find('[data-test="json-viewer-stale"]').exists()).toBe(true);

            await digitar(wrapper, '');

            // Documento vazio nao tem "ultima versao valida".
            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="json-viewer-stale"]').exists()).toBe(false);
        });

        it('desliga a analise quando o texto passa do limite', async () => {
            const wrapper = montar(10);

            await digitar(wrapper, '{"a": 12345}');

            const estado = wrapper.get('[data-test="json-viewer-state"]').text();

            expect(estado).toContain('GRANDE:');
            expect(estado).toMatch(/12\s*bytes/);
            expect(estado).toMatch(/10\s*bytes/);
            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(false);
        });

        it('carrega o exemplo', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(textoDoEditor(wrapper)).toContain('JSON Viewer');
            expect(linhas(wrapper).length).toBeGreaterThan(5);
        });

        it('limpa o documento e volta ao estado inicial', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'LIMPAR');

            expect(textoDoEditor(wrapper)).toBe('');
            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="json-viewer-status"]').exists()).toBe(false);
            expect(desabilitado(wrapper, 'FORMATAR')).toBe(true);
        });

        it('analisa o que foi digitado depois do intervalo de espera', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);

            expect(wrapper.find('[data-test="json-tree"]').exists()).toBe(true);
            expect(linhas(wrapper)).toHaveLength(3);
        });
    });

    describe('arquivo', () => {
        function escolher(elemento: Element, file: File): void {
            Object.defineProperty(elemento, 'files', {
                value: [file],
                configurable: true,
            });
        }

        it('abre o arquivo escolhido', async () => {
            const wrapper = montar();
            const campo = wrapper.get('[data-test="json-viewer-file"]');
            const arquivo = new File([DOCUMENTO], 'dados.json', {
                type: 'application/json',
            });

            escolher(campo.element, arquivo);
            await campo.trigger('change');
            await esperarAnalise();

            expect(textoDoEditor(wrapper)).toBe(DOCUMENTO);
            expect(linhas(wrapper)).toHaveLength(3);
        });

        it('recusa arquivo de outro formato', async () => {
            const wrapper = montar();
            const campo = wrapper.get('[data-test="json-viewer-file"]');

            escolher(campo.element, new File(['oi'], 'nota.csv', { type: 'text/csv' }));
            await campo.trigger('change');
            await flushPromises();

            expect(wrapper.get('[data-test="json-viewer-status"]').text()).toBe('TIPO_NAO_ACEITO');
            expect(textoDoEditor(wrapper)).toBe('');
        });

        it('recusa arquivo maior que o limite', async () => {
            const wrapper = montar(10);
            const campo = wrapper.get('[data-test="json-viewer-file"]');

            escolher(campo.element, new File([DOCUMENTO], 'dados.json'));
            await campo.trigger('change');
            await flushPromises();

            const rodape = wrapper.get('[data-test="json-viewer-status"]').text();

            expect(rodape).toContain('ARQUIVO_GRANDE:');
            expect(textoDoEditor(wrapper)).toBe('');
        });

        it('abre o arquivo solto na area do editor', async () => {
            const wrapper = montar();
            const arquivo = new File([DOCUMENTO], 'dados.json');

            await wrapper.get('[data-test="json-viewer-dropzone"]').trigger('drop', {
                dataTransfer: { files: [arquivo] },
            });
            await esperarAnalise();

            expect(textoDoEditor(wrapper)).toBe(DOCUMENTO);
        });
    });

    describe('baixar', () => {
        it('entrega o documento com o nome traduzido', async () => {
            const url = vi.fn((_blob: Blob) => 'blob:teste');
            const baixados: string[] = [];

            Object.defineProperty(URL, 'createObjectURL', { value: url, configurable: true });
            Object.defineProperty(URL, 'revokeObjectURL', {
                value: vi.fn(),
                configurable: true,
            });
            vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
                this: HTMLAnchorElement,
            ) {
                baixados.push(this.download);
            });

            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'BAIXAR');

            expect(baixados).toEqual(['documento.json']);
            expect(await (url.mock.calls[0]?.[0] as Blob).text()).toBe(DOCUMENTO);
        });
    });
});
