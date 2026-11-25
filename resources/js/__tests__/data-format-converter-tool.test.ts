import {
    flushPromises,
    mount,
    type DOMWrapper,
    type VueWrapper,
} from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Select } from '@/components/ui/select';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/data-format-converter/Tool.vue';

/**
 * A tela do conversor: o texto a esquerda, o convertido a direita e as duas
 * escolhas (formato de entrada e de saida) nos cabecalhos.
 *
 * O editor de verdade fica de fora pelo mesmo motivo das outras tools: o
 * CodeMirror precisa do DOM real. A conversao tem testes proprios
 * (data-format-converter-convert/xml/lossy) - aqui o que se cobra e o que a
 * **tela** faz com ela: o que aparece, o que fica desabilitado, o aviso de
 * perda e o que sobra na tela quando a entrada deixa de valer.
 *
 * No jsdom nao existe `Worker`, entao a conversao roda na thread principal: e o
 * mesmo caminho da queda no navegador, e o unico tempo a esperar e o intervalo
 * entre a ultima tecla e a conta.
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
        'data-format-converter': {
            swap: 'TROCAR',
            from: 'DE',
            to: 'PARA',
            download_name: 'convertido',
            file_types: 'DADOS_DE_CONFIGURACAO',
            formats: {
                json: 'JSON',
                yaml: 'YAML',
                toml: 'TOML',
                xml: 'XML',
            },
            losses: {
                title: 'PERDAS',
                'null-dropped': 'NULO_OMITIDO:{count}',
                'comments-dropped': 'COMENTARIO_PERDIDO:{count}',
            },
            errors: {
                'toml-root': 'RAIZ_TOML',
                'toml-mixed-array': 'MISTO:{path}',
                'toml-invalid': 'TOML_INVALIDO',
                'xml-name': 'NOME_XML:{key}',
                'xml-attribute': 'ATRIBUTO_XML:{key}',
                'xml-invalid': 'XML_INVALIDO',
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
            type: Object as PropType<{
                position: number;
                message: string;
            } | null>,
            default: null,
        },
    },
    emits: ['update:modelValue'],
    setup(props) {
        return () =>
            h(
                'div',
                {
                    'data-test': 'editor',
                    'data-placeholder': props.placeholder,
                    'data-error': props.error?.message ?? '',
                    'data-error-position': String(props.error?.position ?? ''),
                },
                props.modelValue,
            );
    },
});

/** Limite de folga: o dos testes de limite e passado na mao. */
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

function saida(wrapper: VueWrapper): string {
    return (
        wrapper.get('[data-test="data-format-converter-output"]').element
            .textContent ?? ''
    );
}

/** O recado do painel de saida, ou string vazia quando nao ha nenhum. */
function estado(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="data-format-converter-state"]');

    return node.exists() ? node.text() : '';
}

/** O rodape, com os espacos normalizados (o `Intl` usa o nao-quebravel). */
function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="data-format-converter-status"]');

    return node.exists() ? node.text().replace(/\s+/g, ' ') : '';
}

function perdas(wrapper: VueWrapper): string[] {
    return wrapper
        .findAll('[data-test="data-format-converter-loss"]')
        .map((node) => node.text());
}

async function esperarConversao(): Promise<void> {
    await new Promise((resolve) => window.setTimeout(resolve, ESPERA));
    await flushPromises();
}

/**
 * O texto entra pelo editor. O `v-model` nao distingue tecla de colagem, entao
 * os dois caminhos esperam o intervalo - quem entra sem espera e so o que vem de
 * botao (exemplo, arquivo), que ja chega pronto.
 */
async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper
        .findComponent({ name: 'CodeEditor' })
        .vm.$emit('update:modelValue', value);

    await esperarConversao();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

/** O `Select` que tem o gatilho `testid` - sao dois, e o gatilho os separa. */
function seletor(wrapper: VueWrapper, testid: string) {
    const found = wrapper
        .findAllComponents(Select)
        .find((component) =>
            component.find(`[data-test="${testid}"]`).exists(),
        );

    if (found === undefined) {
        throw new Error(`o seletor ${testid} nao esta na tela`);
    }

    return found;
}

/** Troca a escolha pelo caminho do `v-model`: o mesmo que o menu dispara. */
async function escolher(
    wrapper: VueWrapper,
    testid: string,
    valor: string,
): Promise<void> {
    seletor(wrapper, testid).vm.$emit('update:modelValue', valor);
    await flushPromises();
}

describe('Conversor de formatos', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    describe('estado inicial', () => {
        it('abre sem documento, com o convite no editor e sem saida', () => {
            const wrapper = montar();

            expect(
                wrapper
                    .get('[data-test="editor"]')
                    .attributes('data-placeholder'),
            ).toBe('VAZIO');
            expect(
                wrapper
                    .find('[data-test="data-format-converter-output"]')
                    .exists(),
            ).toBe(false);
            expect(
                wrapper
                    .find('[data-test="data-format-converter-state"]')
                    .exists(),
            ).toBe(false);
            expect(
                wrapper
                    .find('[data-test="data-format-converter-status"]')
                    .exists(),
            ).toBe(false);
        });

        it('desabilita o que precisa de documento', () => {
            const wrapper = montar();

            for (const rotulo of ['LIMPAR', 'TROCAR', 'COPIAR', 'BAIXAR']) {
                expect(desabilitado(wrapper, rotulo), rotulo).toBe(true);
            }
            expect(desabilitado(wrapper, 'ABRIR')).toBe(false);
            expect(desabilitado(wrapper, 'EXEMPLO')).toBe(false);
        });

        it('abre em JSON para YAML, sem lembrar escolha nenhuma', () => {
            const wrapper = montar();

            expect(
                seletor(wrapper, 'data-format-converter-from').props(
                    'modelValue',
                ),
            ).toBe('json');
            expect(
                seletor(wrapper, 'data-format-converter-to').props(
                    'modelValue',
                ),
            ).toBe('yaml');
            expect(
                wrapper
                    .get('[data-test="data-format-converter-from"]')
                    .attributes('aria-label'),
            ).toBe('DE');
            expect(
                wrapper
                    .get('[data-test="data-format-converter-to"]')
                    .attributes('aria-label'),
            ).toBe('PARA');
        });
    });

    describe('conversao', () => {
        it('escreve o YAML do JSON colado', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1,"b":[true,null]}');

            expect(saida(wrapper)).toContain('a: 1');
            expect(saida(wrapper)).toContain('- true');
            expect(saida(wrapper)).toContain('- null');
        });

        it('converte na hora quando o formato de saida muda', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1,"b":2}');

            await escolher(wrapper, 'data-format-converter-to', 'toml');

            // Sem `esperarConversao`: a escolha e uma acao pronta.
            expect(saida(wrapper)).toContain('a = 1');
            expect(saida(wrapper)).toContain('b = 2');
        });

        it('converte na hora quando o formato de entrada muda', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'name: devtools\n');

            // O texto era YAML; lido como JSON responderia erro. O seletor
            // manda na leitura.
            await escolher(wrapper, 'data-format-converter-from', 'yaml');

            expect(saida(wrapper)).toContain('name: devtools');
        });

        it('troca os dois formatos sem perder o documento', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1}');
            await clicar(wrapper, 'TROCAR');

            expect(
                seletor(wrapper, 'data-format-converter-from').props(
                    'modelValue',
                ),
            ).toBe('yaml');
            expect(
                seletor(wrapper, 'data-format-converter-to').props(
                    'modelValue',
                ),
            ).toBe('json');
            expect(saida(wrapper)).toContain('"a": 1');
        });

        it('carrega o exemplo em JSON e converte sem esperar o intervalo', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(
                seletor(wrapper, 'data-format-converter-from').props(
                    'modelValue',
                ),
            ).toBe('json');
            expect(saida(wrapper)).toContain('name: devtools');
        });

        it('conta o tamanho no rodape', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1}');

            expect(rodape(wrapper)).toContain('7 bytes');
            expect(rodape(wrapper)).toContain('de ');
        });

        it('nao converte o que passa do limite', async () => {
            const wrapper = montar(4);

            await digitar(wrapper, '{"a":1}');

            expect(estado(wrapper)).toContain('GRANDE:');
            expect(
                wrapper
                    .find('[data-test="data-format-converter-output"]')
                    .exists(),
            ).toBe(false);
        });

        it('limpa a tela e esquece a ultima saida valida', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1}');
            await clicar(wrapper, 'LIMPAR');

            expect(
                wrapper
                    .find('[data-test="data-format-converter-output"]')
                    .exists(),
            ).toBe(false);
            expect(
                wrapper
                    .find('[data-test="data-format-converter-status"]')
                    .exists(),
            ).toBe(false);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });
    });

    describe('entrada que nao converte', () => {
        // Falta a virgula entre `"a"` e `"b"`: o motor informa onde parou, e o
        // parser traduz para linha e coluna.
        const INVALIDO = '{\n  "a": 1\n  "b": 2\n}';

        it('mantem a ultima saida valida, marcada como defasada', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1}');

            const valido = saida(wrapper);

            await digitar(wrapper, INVALIDO);

            expect(
                wrapper
                    .find('[data-test="data-format-converter-stale"]')
                    .exists(),
            ).toBe(true);
            expect(
                wrapper.get('[data-test="data-format-converter-stale"]').text(),
            ).toBe('SAIDA_DEFASADA');
            // O documento antigo continua na tela, e o erro aparece no rodape.
            expect(saida(wrapper)).toBe(valido);
            expect(rodape(wrapper)).toBe('INVALIDO:3:3');
        });

        it('marca o erro na posicao que o editor entende', async () => {
            const wrapper = montar();

            await digitar(wrapper, INVALIDO);

            // Terceira linha, terceira coluna: onze code units antes dela (`{`,
            // quebra, `  "a": 1`, quebra) mais duas da coluna.
            expect(
                wrapper
                    .get('[data-test="editor"]')
                    .attributes('data-error-position'),
            ).toBe('13');
            expect(
                wrapper.get('[data-test="editor"]').attributes('data-error'),
            ).toBe('INVALIDO:3:3');
        });

        it('nao tem posicao para o erro que o motor nao localiza', async () => {
            const wrapper = montar();

            // "Unexpected end of JSON input" nao traz posicao: a tela diz que
            // nao ha posicao em vez de inventar uma.
            await digitar(wrapper, '{"a":');

            expect(rodape(wrapper)).toBe('INVALIDO_SEM_POSICAO');
            expect(
                wrapper.get('[data-test="editor"]').attributes('data-error'),
            ).toBe('');
        });

        it('nao deixa a saida defasada depois de corrigir o documento', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1}');
            await digitar(wrapper, INVALIDO);
            await digitar(wrapper, '{"a":2}');

            expect(
                wrapper
                    .find('[data-test="data-format-converter-stale"]')
                    .exists(),
            ).toBe(false);
            expect(saida(wrapper)).toContain('a: 2');
        });

        it('explica o que o TOML nao aceita, com o caminho', async () => {
            const wrapper = montar();

            // O destino antes do texto: sem conversao anterior, o painel de
            // saida e o do recado (nao ha documento defasado para mostrar).
            await escolher(wrapper, 'data-format-converter-to', 'toml');
            await digitar(wrapper, '{"a":[1,"b"]}');

            expect(estado(wrapper)).toBe('MISTO:a');
        });

        it('explica o TOML que nao tem tabela na raiz', async () => {
            const wrapper = montar();

            await escolher(wrapper, 'data-format-converter-to', 'toml');
            await digitar(wrapper, '[1,2]');

            expect(estado(wrapper)).toBe('RAIZ_TOML');
        });
    });

    describe('perdas', () => {
        it('mostra o aviso do null omitido e esconde na saida defasada', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1,"b":null}');
            await escolher(wrapper, 'data-format-converter-to', 'toml');

            expect(
                wrapper
                    .find('[data-test="data-format-converter-losses"]')
                    .exists(),
            ).toBe(true);
            expect(
                wrapper
                    .get('[data-test="data-format-converter-losses"]')
                    .text(),
            ).toContain('PERDAS');
            expect(perdas(wrapper)).toEqual(['NULO_OMITIDO:1']);

            await digitar(wrapper, '{"a":');

            // A saida de agora e a anterior: o aviso daquela conversao nao vale
            // para o texto na tela.
            expect(
                wrapper
                    .find('[data-test="data-format-converter-losses"]')
                    .exists(),
            ).toBe(false);
            expect(
                wrapper
                    .find('[data-test="data-format-converter-stale"]')
                    .exists(),
            ).toBe(true);
        });

        it('nao avisa quando o destino representa tudo', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":null,"b":1}');

            // YAML tem nulo: nada a avisar.
            expect(
                wrapper
                    .find('[data-test="data-format-converter-losses"]')
                    .exists(),
            ).toBe(false);
        });
    });

    describe('arquivo', () => {
        /**
         * Solta o arquivo e espera a tela responder.
         *
         * A leitura do arquivo passa pelo `FileReader`, que entrega o texto num
         * task do navegador: o tempo varia com a carga da suite, entao o teste
         * espera o **efeito** (o painel de saida ou o recado), e nao um numero
         * fixo de milissegundos.
         */
        async function soltar(wrapper: VueWrapper, file: File): Promise<void> {
            await wrapper
                .get('[data-test="data-format-converter-dropzone"]')
                .trigger('drop', { dataTransfer: { files: [file] } });

            const inicio = Date.now();

            while (Date.now() - inicio < 2000) {
                await flushPromises();

                const pronto =
                    wrapper
                        .find('[data-test="data-format-converter-status"]')
                        .exists() ||
                    wrapper
                        .find('[data-test="data-format-converter-output"]')
                        .exists();

                if (pronto) {
                    return;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 20));
            }
        }

        it('converte o arquivo solto na area do editor', async () => {
            const wrapper = montar();

            await soltar(
                wrapper,
                new File(['{"a":1}'], 'dados.json', {
                    type: 'application/json',
                }),
            );

            expect(saida(wrapper)).toContain('a: 1');
        });

        it('nao troca o formato de entrada pela extensao do arquivo', async () => {
            const wrapper = montar();

            await soltar(
                wrapper,
                new File(['a: 1\n'], 'dados.yaml', { type: 'text/yaml' }),
            );

            // A escolha de entrada e de quem usa (ADR-180): o texto entra, a
            // leitura continua a do seletor, e o erro do JSON aparece.
            expect(
                seletor(wrapper, 'data-format-converter-from').props(
                    'modelValue',
                ),
            ).toBe('json');
            expect(rodape(wrapper)).toMatch(/INVALIDO|INVALIDO_SEM_POSICAO/);
        });

        it('recusa arquivo de outro tipo', async () => {
            const wrapper = montar();

            await soltar(
                wrapper,
                new File(['oi'], 'nota.csv', { type: 'text/csv' }),
            );

            expect(rodape(wrapper)).toBe('TIPO_NAO_ACEITO');
            expect(
                wrapper
                    .find('[data-test="data-format-converter-output"]')
                    .exists(),
            ).toBe(false);
        });

        it('recusa arquivo maior que o limite', async () => {
            const wrapper = montar(4);

            await soltar(wrapper, new File(['{"a":1}'], 'dados.json'));

            expect(rodape(wrapper)).toContain('ARQUIVO_GRANDE:');
        });
    });

    describe('copiar e baixar', () => {
        it('copia o documento convertido e confirma no rodape', async () => {
            const wrapper = montar();

            await digitar(wrapper, '{"a":1}');
            await clicar(wrapper, 'COPIAR');

            expect(clipboard.writeText).toHaveBeenCalledWith(saida(wrapper));
            expect(rodape(wrapper)).toBe('COPIADO');
        });

        it('manda copiar na mao quando o navegador nao expoe a area de transferencia', async () => {
            Object.defineProperty(navigator, 'clipboard', {
                value: undefined,
                configurable: true,
            });

            const wrapper = montar();

            await digitar(wrapper, '{"a":1}');
            await clicar(wrapper, 'COPIAR');

            expect(rodape(wrapper)).toBe('COPIE_NA_MAO');
        });

        it('baixa com o nome e a extensao do formato de saida', async () => {
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

            await digitar(wrapper, '{"a":1}');
            await clicar(wrapper, 'BAIXAR');

            await escolher(wrapper, 'data-format-converter-to', 'toml');
            await clicar(wrapper, 'BAIXAR');

            expect(baixados).toEqual(['convertido.yaml', 'convertido.toml']);
        });
    });
});
