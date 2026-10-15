import { flushPromises, mount, type DOMWrapper, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Select } from '@/components/ui/select';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/json-to-code/Tool.vue';

/**
 * A tela do JSON to Code: o texto a esquerda, o codigo a direita e as duas
 * escolhas que mudam o que sai (alvo e nome da raiz).
 *
 * O editor de verdade fica de fora pelo mesmo motivo do JSON Viewer: o
 * CodeMirror precisa do DOM real. A inferencia e os emissores tem teste proprio
 * (json-to-code-infer.test.ts e json-to-code-emit.test.ts) - aqui o que se cobra
 * e o que a **tela** faz com eles: o que aparece, o que fica desabilitado e o
 * que sobra na tela quando a entrada deixa de valer.
 *
 * A analise aqui e sincrona de proposito (nao ha worker nesta tool), entao o
 * unico tempo a esperar e o intervalo entre a ultima tecla e a conta.
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
        'json-to-code': {
            target: 'ALVO',
            root_name: 'NOME_DA_RAIZ',
            download_name: 'tipos',
            file_types: 'JSON_OU_TXT',
            targets: {
                typescript: 'ALVO_TS',
                php: 'ALVO_PHP',
                pydantic: 'ALVO_PY',
            },
            states: { root_object: 'RAIZ_PRECISA_SER_OBJETO' },
            stats: { types: 'TIPOS:{count}' },
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
                    'data-test': 'editor',
                    'data-placeholder': props.placeholder,
                    'data-error': props.error?.message ?? '',
                },
                props.modelValue,
            );
    },
});

/** Limite de folga: o dos testes de limite e passado na mao. */
const LIMITE_FOLGADO = 5_000_000;

function montar(maxInputBytes: number = LIMITE_FOLGADO): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt-br', translations: traducoes });

    return mount(Tool, {
        props: { maxInputBytes },
        global: {
            plugins: [i18n],
            stubs: { CodeEditor: EditorStub },
        },
    });
}

/**
 * O rotulo do botao, sem as teclas.
 *
 * Nesta tool nao ha atalho nenhum, mas a limpeza fica: o `kbd` mora dentro do
 * botao quando existe, e sem tirar as teclas o texto deixa de casar com o
 * rotulo no dia em que um atalho entrar.
 */
function rotuloDoBotao(node: DOMWrapper<Element>): string {
    const copia = node.element.cloneNode(true) as Element;

    copia.querySelectorAll('kbd').forEach((tecla) => tecla.remove());

    return (copia.textContent ?? '').trim();
}

function botao(wrapper: VueWrapper, rotulo: string): DOMWrapper<Element> {
    const found = wrapper.findAll('button').find((node) => rotuloDoBotao(node) === rotulo);

    if (found === undefined) {
        throw new Error(`o botao ${rotulo} nao esta na tela`);
    }

    return found;
}

function desabilitado(wrapper: VueWrapper, rotulo: string): boolean {
    return botao(wrapper, rotulo).attributes('disabled') !== undefined;
}

function saida(wrapper: VueWrapper): string {
    // `textContent`, e nao `text()`: o codigo termina em quebra de linha, e o
    // arquivo gerado e o texto inteiro - o `text()` do Vue Test Utils apara.
    return wrapper.get('[data-test="json-to-code-output"]').element.textContent ?? '';
}

/** O aviso da tela, ou string vazia quando nao ha nenhum. */
function estado(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="json-to-code-state"]');

    return node.exists() ? node.text() : '';
}

function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="json-to-code-status"]');

    return node.exists() ? node.text() : '';
}

async function esperarAnalise(): Promise<void> {
    await new Promise((resolve) => window.setTimeout(resolve, ESPERA));
    await flushPromises();
}

/**
 * O texto entra pelo editor. O `v-model` nao distingue tecla de colagem, entao
 * os dois caminhos esperam o intervalo - quem entra sem espera e so o que vem de
 * botao (exemplo, arquivo), que ja chega analisado.
 */
async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper.findComponent({ name: 'CodeEditor' }).vm.$emit('update:modelValue', value);

    await esperarAnalise();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

/** Troca o alvo pelo caminho do `v-model`: o mesmo que o menu dispara. */
async function escolherAlvo(wrapper: VueWrapper, alvo: string): Promise<void> {
    wrapper.findComponent(Select).vm.$emit('update:modelValue', alvo);

    await flushPromises();
}

async function escreverNomeDaRaiz(wrapper: VueWrapper, nome: string): Promise<void> {
    await wrapper.get('[data-test="json-to-code-root-name"]').setValue(nome);
    await flushPromises();
}

/** Um documento com objeto aninhado e um array de itens que nao se parecem. */
const DOCUMENTO = [
    '{',
    '  "cliente": { "nome": "Ana" },',
    '  "itens": [',
    '    { "sku": "A", "qtd": 2 },',
    '    { "sku": "B", "qtd": 1, "desconto": 0.5 }',
    '  ]',
    '}',
].join('\n');

describe('JSON to Code', () => {
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

            expect(wrapper.get('[data-test="editor"]').attributes('data-placeholder')).toBe('VAZIO');
            expect(wrapper.find('[data-test="json-to-code-output"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="json-to-code-state"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="json-to-code-status"]').exists()).toBe(false);
        });

        it('desabilita o que precisa de codigo', () => {
            const wrapper = montar();

            for (const rotulo of ['LIMPAR', 'COPIAR', 'BAIXAR']) {
                expect(desabilitado(wrapper, rotulo), rotulo).toBe(true);
            }
        });

        it('abre no TypeScript, com a raiz chamada Root', () => {
            const wrapper = montar();

            expect(wrapper.get('[data-test="json-to-code-root-name"]').attributes('placeholder')).toBe(
                'Root',
            );
            // O alvo padrao: o valor do `Select` e a opcao escolhida nele. O
            // texto da opcao so existe com o menu aberto, entao o que se le aqui
            // e o rotulo do gatilho.
            expect(wrapper.findComponent(Select).props('modelValue')).toBe('typescript');
            expect(wrapper.get('[data-test="json-to-code-target"]').attributes('aria-label')).toBe(
                'ALVO',
            );
        });
    });

    describe('geracao', () => {
        it('escreve o codigo do documento colado', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);

            expect(saida(wrapper)).toBe(`export interface Root {
    cliente: Cliente;
    itens: Itens[];
}

export interface Cliente {
    nome: string;
}

export interface Itens {
    sku: string;
    qtd: number;
    desconto?: number;
}
`);
        });

        it('conta os tipos e o tamanho no rodape', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);

            expect(rodape(wrapper)).toContain('TIPOS:3');
        });

        it('troca o alvo sem perder o documento', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await escolherAlvo(wrapper, 'php');

            expect(saida(wrapper)).toContain('declare(strict_types=1);');
            expect(saida(wrapper)).toContain('final class Root');
            // O conteudo e o mesmo: so a linguagem mudou.
            expect(saida(wrapper)).toContain('?float $desconto = null');
            expect(rodape(wrapper)).toContain('TIPOS:3');

            await escolherAlvo(wrapper, 'pydantic');

            expect(saida(wrapper)).toContain('class Itens(BaseModel):');
            expect(saida(wrapper)).toContain('desconto: float | None = None');
        });

        it('usa o nome de raiz digitado, corrigido', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await escreverNomeDaRaiz(wrapper, 'meu pedido');

            expect(saida(wrapper)).toContain('export interface meu_pedido {');
        });

        it('volta ao Root quando o campo do nome fica vazio', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await escreverNomeDaRaiz(wrapper, 'Pedido');
            await escreverNomeDaRaiz(wrapper, '');

            expect(saida(wrapper)).toContain('export interface Root {');
        });

        it('carrega o exemplo ja analisado, sem esperar o intervalo', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            // Sem `esperarAnalise`: o botao entrega o texto pronto, e a analise
            // dele e imediata.
            expect(saida(wrapper)).toContain('export interface Root {');
            expect(saida(wrapper)).toContain('export interface Enderecos {');
            expect(rodape(wrapper)).toContain('TIPOS:4');
        });
    });

    describe('entrada que nao vira codigo', () => {
        it('mantem a ultima saida valida, marcada como defasada', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);

            const valido = saida(wrapper);

            // Virgula sobrando antes do fecho: o motor informa onde.
            await digitar(wrapper, '{\n  "cliente": 1,\n}');

            expect(wrapper.find('[data-test="json-to-code-stale"]').exists()).toBe(true);
            expect(wrapper.get('[data-test="json-to-code-stale"]').text()).toBe('SAIDA_DEFASADA');
            // O codigo antigo continua na tela, e o erro aparece no rodape.
            expect(saida(wrapper)).toBe(valido);
            expect(rodape(wrapper)).toBe('INVALIDO:3:1');
        });

        it('nao deixa a saida defasada depois de corrigir o texto', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await digitar(wrapper, '{\n  "cliente": 1,\n}');
            await digitar(wrapper, '{ "cliente": { "nome": "Ana" } }');

            expect(wrapper.find('[data-test="json-to-code-stale"]').exists()).toBe(false);
            expect(saida(wrapper)).toContain('export interface Cliente {');
        });

        it('pede um objeto na raiz em vez de escrever vazio', async () => {
            const wrapper = montar();

            await digitar(wrapper, '42');

            expect(estado(wrapper)).toBe('RAIZ_PRECISA_SER_OBJETO');
            expect(wrapper.find('[data-test="json-to-code-output"]').exists()).toBe(false);
        });

        it('nao analisa o que passa do limite', async () => {
            const wrapper = montar(10);

            await digitar(wrapper, '{"cliente":"Ana"}');

            expect(estado(wrapper)).toContain('GRANDE:');
            expect(wrapper.find('[data-test="json-to-code-output"]').exists()).toBe(false);
        });

        it('limpa a tela e esquece a ultima saida valida', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'LIMPAR');

            expect(wrapper.find('[data-test="json-to-code-output"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="json-to-code-status"]').exists()).toBe(false);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });
    });

    describe('copiar e baixar', () => {
        it('copia o codigo e confirma no rodape', async () => {
            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'COPIAR');

            expect(clipboard.writeText).toHaveBeenCalledWith(saida(wrapper));
            expect(rodape(wrapper)).toBe('COPIADO');
        });

        it('manda copiar na mao quando o navegador nao expoe a area de transferencia', async () => {
            Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true });

            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'COPIAR');

            expect(rodape(wrapper)).toBe('COPIE_NA_MAO');
        });

        it('baixa com o nome e a extensao do alvo', async () => {
            const baixados: string[] = [];

            Object.defineProperty(URL, 'createObjectURL', {
                value: vi.fn((_blob: Blob) => 'blob:teste'),
                configurable: true,
            });
            Object.defineProperty(URL, 'revokeObjectURL', { value: vi.fn(), configurable: true });
            vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
                this: HTMLAnchorElement,
            ) {
                baixados.push(this.download);
            });

            const wrapper = montar();

            await digitar(wrapper, DOCUMENTO);
            await clicar(wrapper, 'BAIXAR');
            await escolherAlvo(wrapper, 'php');
            await clicar(wrapper, 'BAIXAR');
            await escolherAlvo(wrapper, 'pydantic');
            await clicar(wrapper, 'BAIXAR');

            expect(baixados).toEqual(['tipos.ts', 'tipos.php', 'tipos.py']);
        });
    });
});
