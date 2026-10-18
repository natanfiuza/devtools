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
import Tool from '@/tools/sql-formatter/Tool.vue';

/**
 * A tela do SQL Formatter: a consulta a esquerda, o SQL indentado a direita e as
 * duas escolhas (dialeto e caixa das palavras-chave) no cabecalho da saida.
 *
 * O editor de verdade fica de fora pelo mesmo motivo das outras tools: o
 * CodeMirror precisa do DOM real. A formatacao tem teste proprio
 * (sql-format.test.ts) - aqui o que se cobra e o que a **tela** faz com ela: o
 * que aparece, o que fica desabilitado, e o que sobra na tela quando a entrada
 * deixa de valer.
 *
 * No jsdom nao existe `Worker`, entao a formatacao roda na thread principal: e o
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
        'sql-formatter': {
            dialect: 'DIALETO',
            keyword_case: 'CAIXA',
            download_name: 'consulta',
            file_types: 'SQL_OU_TXT',
            dialects: {
                mysql: 'DIALETO_MYSQL',
                pgsql: 'DIALETO_PGSQL',
                sqlsrv: 'DIALETO_SQLSRV',
                sqlite: 'DIALETO_SQLITE',
            },
            cases: {
                upper: 'CAIXA_ALTA',
                lower: 'CAIXA_BAIXA',
                preserve: 'CAIXA_DO_AUTOR',
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
                    'data-error': props.error?.message ?? '',
                    // A posicao em code units: e o que o editor de verdade usa
                    // para marcar a linha do erro.
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
 * botao quando existe, e sem tirar as teclas o texto deixaria de casar com o
 * rotulo no dia em que um atalho entrar.
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

function saida(wrapper: VueWrapper): string {
    // `textContent`, e nao `text()`: quem le a saida aqui e o rodape da tela, e
    // o `text()` do Vue Test Utils apara o comeco e o fim do SQL.
    return (
        wrapper.get('[data-test="sql-formatter-output"]').element.textContent ??
        ''
    );
}

/** O aviso da tela, ou string vazia quando nao ha nenhum. */
function estado(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="sql-formatter-state"]');

    return node.exists() ? node.text() : '';
}

/**
 * O rodape, com os espacos normalizados.
 *
 * O espaco entre o numero e a unidade vem do `Intl` - as vezes e o
 * nao-quebravel -, e sem normalizar a asercao leria um texto que nao e o que a
 * pessoa ve.
 */
function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="sql-formatter-status"]');

    return node.exists() ? node.text().replace(/\s+/g, ' ') : '';
}

async function esperarFormatacao(): Promise<void> {
    await new Promise((resolve) => window.setTimeout(resolve, ESPERA));
    await flushPromises();
}

/**
 * O texto entra pelo editor. O `v-model` nao distingue tecla de colagem, entao
 * os dois caminhos esperam o intervalo - quem entra sem espera e so o que vem de
 * botao (exemplo, arquivo), que ja chega formatado.
 */
async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper
        .findComponent({ name: 'CodeEditor' })
        .vm.$emit('update:modelValue', value);

    await esperarFormatacao();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

/**
 * O `Select` que tem o gatilho `testid`.
 *
 * Sao dois na tela (o dialeto e a caixa), e os dois sao o mesmo componente: o
 * que os separa e o gatilho. Pelo indice, o teste quebraria no dia em que a
 * ordem do cabecalho mudasse.
 */
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

    // A troca dispara formatacao nova, e ela e assincrona mesmo na thread
    // principal: sem o `flush` a tela mostraria o SQL de antes.
    await flushPromises();
}

const CONSULTA = 'select top 10 id from clientes';

describe('SQL Formatter', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    describe('estado inicial', () => {
        it('abre sem consulta, com o convite no editor e sem saida', () => {
            const wrapper = montar();

            expect(
                wrapper
                    .get('[data-test="editor"]')
                    .attributes('data-placeholder'),
            ).toBe('VAZIO');
            expect(
                wrapper.find('[data-test="sql-formatter-output"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="sql-formatter-state"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="sql-formatter-status"]').exists(),
            ).toBe(false);
        });

        it('desabilita o que precisa de SQL', () => {
            const wrapper = montar();

            for (const rotulo of ['LIMPAR', 'COPIAR', 'BAIXAR']) {
                expect(desabilitado(wrapper, rotulo), rotulo).toBe(true);
            }
        });

        it('abre no MySQL e em maiusculas, sem lembrar escolha nenhuma', () => {
            const wrapper = montar();

            // O valor do `Select` e a opcao escolhida nele. O texto da opcao so
            // existe com o menu aberto, entao o que se le aqui e o valor e o
            // rotulo do gatilho.
            expect(
                seletor(wrapper, 'sql-formatter-dialect').props('modelValue'),
            ).toBe('mysql');
            expect(
                seletor(wrapper, 'sql-formatter-case').props('modelValue'),
            ).toBe('upper');
            expect(
                wrapper
                    .get('[data-test="sql-formatter-dialect"]')
                    .attributes('aria-label'),
            ).toBe('DIALETO');
            expect(
                wrapper
                    .get('[data-test="sql-formatter-case"]')
                    .attributes('aria-label'),
            ).toBe('CAIXA');
        });

        it('leva cada um dos quatro dialetos ate a formatacao', async () => {
            const wrapper = montar();

            const consulta = 'select top 10 id from clientes';

            await digitar(wrapper, consulta);

            // O SQL Server e o unico em que `top` e palavra-chave: passar pelos
            // quatro e o que prova que o slug chega inteiro na biblioteca - os
            // itens do menu so existem com o menu aberto, entao nao da para
            // contar as opcoes da tela daqui.
            for (const dialect of [
                'mysql',
                'pgsql',
                'sqlite',
                'sqlsrv',
                'mysql',
            ]) {
                await escolher(wrapper, 'sql-formatter-dialect', dialect);

                expect(
                    seletor(wrapper, 'sql-formatter-dialect').props(
                        'modelValue',
                    ),
                ).toBe(dialect);
                expect(saida(wrapper), dialect).toContain(
                    dialect === 'sqlsrv' ? 'TOP 10' : 'top 10',
                );
            }
        });
    });

    describe('formatacao', () => {
        it('escreve o SQL indentado da consulta colada', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'select id from clientes');

            expect(saida(wrapper)).toBe('SELECT\n  id\nFROM\n  clientes');
        });

        it('conta o tamanho no rodape', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'select id from clientes');

            expect(rodape(wrapper)).toBe('23 bytes de 4,8 MB');
        });

        it('troca o dialeto sem perder a consulta', async () => {
            const wrapper = montar();

            await digitar(wrapper, CONSULTA);
            // No MySQL `top` nao e palavra-chave: fica como esta.
            expect(saida(wrapper)).toContain('top 10');

            await escolher(wrapper, 'sql-formatter-dialect', 'sqlsrv');

            // No SQL Server ela e - e o dialeto chega na biblioteca.
            expect(saida(wrapper)).toContain('TOP 10');
        });

        it('troca a caixa das palavras-chave sem perder a consulta', async () => {
            const wrapper = montar();

            // Caixa misturada de proposito: e a unica entrada em que as tres
            // opcoes dao saidas diferentes.
            await digitar(wrapper, 'SeLeCt id FROM clientes');

            expect(saida(wrapper)).toBe('SELECT\n  id\nFROM\n  clientes');

            await escolher(wrapper, 'sql-formatter-case', 'lower');

            expect(saida(wrapper)).toBe('select\n  id\nfrom\n  clientes');

            await escolher(wrapper, 'sql-formatter-case', 'preserve');

            expect(saida(wrapper)).toBe('SeLeCt\n  id\nFROM\n  clientes');
        });

        it('carrega o exemplo ja formatado, sem esperar o intervalo', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            // Sem `esperarFormatacao`: o botao entrega o texto pronto, e a
            // formatacao dele e imediata.
            expect(saida(wrapper)).toContain(
                '-- Faturamento por cliente no ultimo trimestre',
            );
            // As palavras-chave sobem; o nome da funcao e do autor, e fica.
            expect(saida(wrapper)).toContain('count(p.id) AS pedidos');
            expect(saida(wrapper)).toContain('-- Os que nunca compraram');
        });
    });

    describe('entrada que nao vira SQL', () => {
        it('mantem a ultima saida valida, marcada como defasada', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'select id from clientes');

            const valido = saida(wrapper);

            // Aspa aberta: o motor informa onde.
            await digitar(wrapper, "select 'abc");

            expect(
                wrapper.find('[data-test="sql-formatter-stale"]').exists(),
            ).toBe(true);
            expect(
                wrapper.get('[data-test="sql-formatter-stale"]').text(),
            ).toBe('SAIDA_DEFASADA');
            // O SQL antigo continua na tela, e o erro aparece no rodape.
            expect(saida(wrapper)).toBe(valido);
            expect(rodape(wrapper)).toBe('INVALIDO:1:8');
        });

        it('marca o erro na posicao que o editor entende', async () => {
            const wrapper = montar();

            await digitar(wrapper, "select 'abc");

            // Oitava coluna da primeira linha: a posicao 7 do texto.
            expect(
                wrapper
                    .get('[data-test="editor"]')
                    .attributes('data-error-position'),
            ).toBe('7');
            expect(
                wrapper.get('[data-test="editor"]').attributes('data-error'),
            ).toBe('INVALIDO:1:8');
        });

        it('nao deixa a saida defasada depois de corrigir a consulta', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'select id from clientes');
            await digitar(wrapper, "select 'abc");
            await digitar(wrapper, "select 'abc' from clientes");

            expect(
                wrapper.find('[data-test="sql-formatter-stale"]').exists(),
            ).toBe(false);
            expect(saida(wrapper)).toContain("'abc'");
        });

        it('nao formata o que passa do limite', async () => {
            const wrapper = montar(10);

            await digitar(wrapper, 'select id from clientes');

            expect(estado(wrapper)).toContain('GRANDE:');
            expect(
                wrapper.find('[data-test="sql-formatter-output"]').exists(),
            ).toBe(false);
        });

        it('limpa a tela e esquece a ultima saida valida', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'select id from clientes');
            await clicar(wrapper, 'LIMPAR');

            expect(
                wrapper.find('[data-test="sql-formatter-output"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="sql-formatter-status"]').exists(),
            ).toBe(false);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });
    });

    describe('copiar e baixar', () => {
        it('copia o SQL e confirma no rodape', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'select id from clientes');
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

            await digitar(wrapper, 'select id from clientes');
            await clicar(wrapper, 'COPIAR');

            expect(rodape(wrapper)).toBe('COPIE_NA_MAO');
        });

        it('baixa com o nome e a extensao do arquivo', async () => {
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

            await digitar(wrapper, 'select id from clientes');
            await clicar(wrapper, 'BAIXAR');

            expect(baixados).toEqual(['consulta.sql']);
        });
    });
});
