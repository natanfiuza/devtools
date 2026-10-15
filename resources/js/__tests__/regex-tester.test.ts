import { flushPromises, mount, type DOMWrapper, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import type { WorkerRequest } from '@/lib/worker/client';
import { search, type RegexRequest, type RegexResult } from '@/tools/regex-tester/lib/run';
import Tool from '@/tools/regex-tester/Tool.vue';

/**
 * A tela do Regex Tester: o padrao na barra de cima, o texto de teste a
 * esquerda e a leitura a direita.
 *
 * O motor - os casamentos, os grupos, o corte no teto, os pedacos da previa -
 * tem arquivo proprio (`regex-run.test.ts`), e o worker e o relogio que o mata
 * tambem (`regex-worker.test.ts`). O que se cobra **aqui** e o que a tela faz
 * com eles: o que aparece, o que fica desabilitado, o que "Limpar" limpa, e o
 * que sobra - nada - quando a conta estoura o tempo.
 *
 * Duas coisas so existem neste arquivo:
 *
 * - o **estouro de tempo**, que precisa de um worker fingido e do relogio na
 *   mao: no jsdom nao ha `Worker`, e da thread principal a tool nao espera nada
 *   (e por isso que ela nao pode ser a dona do relogio);
 * - o **intervalo** entre a ultima tecla e a busca, que na maioria dos testes e
 *   espera de verdade e no do estouro e tempo fingido - quem escolhe e o
 *   `relogioFalso`.
 */

/** O intervalo da tela (200 ms) mais a folga do relogio de verdade. */
const ESPERA = 260;

/** O tempo limite de uma busca nos testes; o de verdade vem de `config/tools.php`. */
const LIMITE_MS = 1000;

/** O texto do padrao e o da troca que o botao Exemplo escreve. */
const EXEMPLO_PADRAO = '^(?<quando>\\S+) (?<nivel>[A-Z]+)\\s+pedido (?<id>\\d+) (?<mensagem>.+)$';
const EXEMPLO_TROCA = '$<nivel> #$<id>: $<mensagem>';

/** O que a lista mostra no lugar de um valor que nao existe. */
const AUSENTE = '—';

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
        'regex-tester': {
            pattern: 'PADRAO',
            replacement: 'TROCA',
            replacement_hint: 'TROCA_DICA',
            position: 'POSICAO:{start}',
            matches_count: 'CONTAGEM:{count}',
            matches_none: 'NENHUM',
            truncated: 'CORTADO:{count}',
            replaced_title: 'TITULO_TROCA',
            file_types: 'TXT',
            flags: { i: 'FLAG_I', m: 'FLAG_M', s: 'FLAG_S', u: 'FLAG_U' },
            states: {
                pattern: 'PADRAO_INVALIDO',
                pattern_empty: 'PADRAO_VAZIO',
                timeout: 'TEMPO:{seconds}',
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
            type: Object as PropType<{ position: number; message: string } | null>,
            default: null,
        },
        extensions: { type: Array as PropType<unknown[]>, default: () => [] },
    },
    emits: ['update:modelValue', 'find'],
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

interface Props {
    maxInputBytes: number;
    regexTimeoutMs: number;
    regexMaxMatches: number;
}

/** Folga na entrada e no teto: os testes que querem limite passam o deles. */
const PADRAO: Props = {
    maxInputBytes: 5_000_000,
    regexTimeoutMs: LIMITE_MS,
    regexMaxMatches: 1000,
};

/**
 * O relogio fingido.
 *
 * Com ele na mao os ajudantes **nao** podem passar pelo `flushPromises` do Vue
 * Test Utils: ele agenda um `setImmediate`, que so corre quando o relogio anda -
 * esperar por ele travaria o teste.
 */
let relogioFalso = false;

/**
 * O worker fingido, com a resposta nas maos de quem escreve o teste.
 *
 * Ele nao responde sozinho: quem responde e o teste, com `answer` - e e isso que
 * permite deixar um pedido pendurado e ver o tempo estourar sem esperar um
 * segundo de verdade.
 */
class FakeWorker {
    static readonly instances: FakeWorker[] = [];

    readonly posted: WorkerRequest<RegexRequest>[] = [];

    terminated = false;

    private readonly listeners = new Map<string, ((event: never) => void)[]>();

    constructor() {
        FakeWorker.instances.push(this);
    }

    addEventListener(type: string, handler: (event: never) => void): void {
        const handlers = this.listeners.get(type) ?? [];

        handlers.push(handler);
        this.listeners.set(type, handlers);
    }

    postMessage(request: WorkerRequest<RegexRequest>): void {
        this.posted.push(request);
    }

    terminate(): void {
        this.terminated = true;
    }

    /** Responde o ultimo pedido, como o worker de verdade responderia. */
    answer(): void {
        const id = this.posted.length - 1;
        const request = this.posted[id];

        if (request === undefined) {
            throw new Error('nenhum pedido foi enviado');
        }

        // O `id` e o do pedido que chegou: e por ele que o cliente casa a
        // resposta, e inventa-lo aqui esconderia um erro do protocolo.
        this.emit('message', {
            data: { id: request.id, result: search(request.payload) } satisfies WorkerResponse,
        });
    }

    private emit(type: string, event: unknown): void {
        for (const handler of this.listeners.get(type) ?? []) {
            handler(event as never);
        }
    }
}

type WorkerResponse = { id: number; result: RegexResult };

function montar(overrides: Partial<Props> = {}): VueWrapper {
    const i18n = createI18nInstance();

    applyTranslations(i18n, { locale: 'pt-br', translations: traducoes });

    return mount(Tool, {
        props: { ...PADRAO, ...overrides },
        // O foco do Ctrl+F precisa de um documento de verdade: o jsdom nao foca
        // o que esta fora dele.
        attachTo: document.body,
        global: {
            plugins: [i18n],
            stubs: { CodeEditor: EditorStub },
        },
    });
}

/**
 * Espera o que estiver pendente: o intervalo da tela, a conta, ou os dois.
 *
 * Com o relogio de verdade e o `flushPromises`; com o fingido, so o
 * `advanceTimersByTimeAsync`, que anda o relogio e ainda deixa as promessas
 * assentarem entre um temporizador e o proximo.
 */
async function esperar(ms: number = ESPERA): Promise<void> {
    if (relogioFalso) {
        await vi.advanceTimersByTimeAsync(ms);

        return;
    }

    await new Promise((resolve) => window.setTimeout(resolve, ms));
    await flushPromises();
}

/*
| A tela
*/

function campo(wrapper: VueWrapper, testid: string): HTMLInputElement {
    return wrapper.get(`[data-test="${testid}"]`).element as HTMLInputElement;
}

/** As flags como a barra as escreve no fim do campo: `/g`, `/giu`. */
function flagsNaBarra(wrapper: VueWrapper): string {
    const depois = campo(wrapper, 'regex-tester-pattern').nextElementSibling;

    return (depois?.textContent ?? '').trim();
}

function botao(wrapper: VueWrapper, rotulo: string): DOMWrapper<Element> {
    const found = wrapper.findAll('button').find((node) => node.text() === rotulo);

    if (found === undefined) {
        throw new Error(`o botao ${rotulo} nao esta na tela`);
    }

    return found;
}

/** O alternador de uma flag, pelo nome acessivel dele: na tela so ha a letra. */
function alternador(wrapper: VueWrapper, flag: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('button')
        .find((node) => node.attributes('aria-label') === `FLAG_${flag.toUpperCase()}`);

    if (found === undefined) {
        throw new Error(`o alternador da flag ${flag} nao esta na tela`);
    }

    return found;
}

function desabilitado(wrapper: VueWrapper, rotulo: string): boolean {
    return botao(wrapper, rotulo).attributes('disabled') !== undefined;
}

/**
 * A frase do estado, sem o detalhe.
 *
 * O detalhe do motor mora **dentro** do bloco do estado, logo abaixo da frase -
 * entao o que se le aqui e o `span` da frase, e o `detalhe` le a linha de baixo.
 */
function estado(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="regex-tester-state"]');

    return node.exists() ? node.get('span').text() : '';
}

/** A linha do detalhe: a mensagem do motor, e nao uma frase nossa. */
function detalhe(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="regex-tester-detail"]');

    return node.exists() ? node.text() : '';
}

function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="regex-tester-status"]');

    return node.exists() ? node.text().replace(/\s+/g, ' ') : '';
}

/*
| A leitura
*/

function casamentos(wrapper: VueWrapper): DOMWrapper<Element>[] {
    return wrapper.findAll('[data-test="regex-tester-match"]');
}

function trechos(wrapper: VueWrapper): string[] {
    return wrapper.findAll('[data-test="regex-tester-match-text"]').map((node) => node.text());
}

function posicoes(wrapper: VueWrapper): string[] {
    return wrapper.findAll('[data-test="regex-tester-match-position"]').map((node) => node.text());
}

function grupos(wrapper: VueWrapper): string[] {
    return wrapper.findAll('[data-test="regex-tester-group"]').map((node) => node.text());
}

function nomeados(wrapper: VueWrapper): string[] {
    return wrapper.findAll('[data-test="regex-tester-named-group"]').map((node) => node.text());
}

/** Os pedacos da previa, com a marca de quem foi pintado. */
function pintadas(wrapper: VueWrapper): { text: string; match: boolean }[] {
    return wrapper.findAll('[data-test="regex-tester-preview"] span').map((node) => ({
        text: node.element.textContent ?? '',
        match: (node.attributes('class') ?? '').includes('bg-accent-2/30'),
    }));
}

function textoDaPrevia(wrapper: VueWrapper): string {
    return wrapper.get('[data-test="regex-tester-preview"]').element.textContent ?? '';
}

function textoDaTroca(wrapper: VueWrapper): string {
    return wrapper.get('[data-test="regex-tester-replaced"]').text();
}

/*
| As acoes
*/

/** O texto entra pelo editor: o caminho de quem digita ou cola. */
async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper.findComponent({ name: 'CodeEditor' }).vm.$emit('update:modelValue', value);

    await esperar();
}

/** O padrao entra pela barra de cima. */
async function escrever(wrapper: VueWrapper, value: string): Promise<void> {
    await wrapper.get('[data-test="regex-tester-pattern"]').setValue(value);

    await esperar();
}

async function trocar(wrapper: VueWrapper, value: string): Promise<void> {
    await wrapper.get('[data-test="regex-tester-replacement"]').setValue(value);

    await esperar();
}

async function ligar(wrapper: VueWrapper, flag: string): Promise<void> {
    await alternador(wrapper, flag).trigger('click');

    await esperar();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');

    // `esperar(0)`: o clique de um botao ja traz o estado pronto, e o intervalo
    // da tela nao se aplica a ele.
    await esperar(0);
}

describe('Regex Tester', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    afterEach(() => {
        relogioFalso = false;
        FakeWorker.instances.length = 0;

        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    describe('estado inicial', () => {
        it('abre sem leitura, com o campo do padrao e o `g` fixo na decoracao', () => {
            const wrapper = montar();

            expect(campo(wrapper, 'regex-tester-pattern').value).toBe('');
            expect(campo(wrapper, 'regex-tester-pattern').placeholder).toBe('PADRAO');
            expect(flagsNaBarra(wrapper)).toBe('/g');
            expect(wrapper.get('[data-test="editor"]').attributes('data-placeholder')).toBe('VAZIO');
            expect(estado(wrapper)).toBe('VAZIO');
            expect(wrapper.find('[data-test="regex-tester-match"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="regex-tester-preview"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="regex-tester-replaced"]').exists()).toBe(false);
        });

        it('nao oferece copiar nem limpar sem texto de teste', () => {
            const wrapper = montar();

            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
            expect(desabilitado(wrapper, 'LIMPAR')).toBe(true);
            expect(rodape(wrapper)).toBe('VAZIO');
        });

        it('abre com as quatro flags desligadas, cada uma com o nome acessivel', () => {
            const wrapper = montar();

            for (const flag of ['i', 'm', 's', 'u']) {
                expect(alternador(wrapper, flag).attributes('aria-pressed'), flag).toBe('false');
                expect(alternador(wrapper, flag).attributes('aria-label'), flag).toBe(
                    `FLAG_${flag.toUpperCase()}`,
                );
            }
        });
    });

    describe('a busca', () => {
        it('lista os casamentos com o trecho e a posicao', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'banana');
            await escrever(wrapper, 'an');

            expect(trechos(wrapper)).toEqual(['an', 'an']);
            expect(posicoes(wrapper)).toEqual(['POSICAO:1', 'POSICAO:3']);
            expect(rodape(wrapper)).toBe('CONTAGEM:2');
            expect(wrapper.find('[data-test="regex-tester-truncated"]').exists()).toBe(false);
        });

        it('pede o padrao quando so ha texto de teste', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'banana');

            expect(estado(wrapper)).toBe('PADRAO_VAZIO');
            expect(wrapper.find('[data-test="regex-tester-match"]').exists()).toBe(false);
        });

        it('diz o que o motor reclamou quando a expressao nao compila', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'banana');
            await escrever(wrapper, 'a(');

            expect(estado(wrapper)).toBe('PADRAO_INVALIDO');
            expect(detalhe(wrapper)).toContain('a(');
            expect(wrapper.find('[data-test="regex-tester-match"]').exists()).toBe(false);
            expect(rodape(wrapper)).toBe('PADRAO_INVALIDO');
        });

        it('liga a flag `i` e o casamento aparece', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'BANANA');
            await escrever(wrapper, 'a');

            expect(trechos(wrapper)).toEqual([]);
            expect(wrapper.get('[data-test="regex-tester-none"]').text()).toBe('NENHUM');
            expect(pintadas(wrapper).every((part) => !part.match)).toBe(true);

            await ligar(wrapper, 'i');

            expect(alternador(wrapper, 'i').attributes('aria-pressed')).toBe('true');
            expect(flagsNaBarra(wrapper)).toBe('/gi');
            expect(trechos(wrapper)).toEqual(['A', 'A', 'A']);
        });

        it('escreve as flags na ordem da tela, e nao na ordem do clique', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'a');
            await escrever(wrapper, 'a');

            await ligar(wrapper, 'u');
            await ligar(wrapper, 'i');

            expect(flagsNaBarra(wrapper)).toBe('/giu');
        });

        it('para no teto de casamentos e avisa que cortou', async () => {
            const wrapper = montar({ regexMaxMatches: 2 });

            await digitar(wrapper, 'aaaa');
            await escrever(wrapper, 'a');

            expect(trechos(wrapper)).toEqual(['a', 'a']);
            expect(wrapper.get('[data-test="regex-tester-truncated"]').text()).toBe('CORTADO:2');
            expect(rodape(wrapper)).toBe('CONTAGEM:2');
        });

        it('escreve os grupos numerados e os nomeados, e o traco no que faltou', async () => {
            const wrapper = montar();

            await digitar(wrapper, '2026-10 e 2027');
            await escrever(wrapper, '(?<ano>\\d{4})(?<mes>-\\d{2})?');

            expect(trechos(wrapper)).toEqual(['2026-10', '2027']);
            expect(grupos(wrapper)).toEqual(['$1: 2026', '$2: -10', '$1: 2027', `$2: ${AUSENTE}`]);
            expect(nomeados(wrapper)).toEqual([
                '$<ano>: 2026',
                '$<mes>: -10',
                '$<ano>: 2027',
                `$<mes>: ${AUSENTE}`,
            ]);
        });

        it('mostra o casamento vazio como aspas, e nao como nada', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'b');
            await escrever(wrapper, 'a*');

            expect(trechos(wrapper)).toEqual(["''", "''"]);
            expect(posicoes(wrapper)).toEqual(['POSICAO:0', 'POSICAO:1']);
            // O vazio nao pinta faixa nenhuma: nao ha o que realcar.
            expect(pintadas(wrapper).every((part) => !part.match)).toBe(true);
        });
    });

    describe('a previa realcada', () => {
        it('pinta so o casamento, e guarda o texto inteiro', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'banana');
            await escrever(wrapper, 'an');

            expect(pintadas(wrapper)).toEqual([
                { text: 'b', match: false },
                { text: 'an', match: true },
                { text: 'an', match: true },
                { text: 'a', match: false },
            ]);
            expect(textoDaPrevia(wrapper)).toBe('banana');
        });
    });

    describe('a substituicao', () => {
        it('mostra o texto trocado, com os grupos numerados e nomeados', async () => {
            const wrapper = montar();

            await digitar(wrapper, '2026-10 e 2027-03');
            await escrever(wrapper, '(?<ano>\\d{4})-(?<mes>\\d{2})');
            await trocar(wrapper, '$<mes>/$<ano>');

            expect(textoDaTroca(wrapper)).toBe('10/2026 e 03/2027');
            expect(wrapper.text()).toContain('TITULO_TROCA');
        });

        it('copia o texto trocado', async () => {
            const wrapper = montar();

            await digitar(wrapper, '2026-10 e 2027-03');
            await escrever(wrapper, '(?<ano>\\d{4})-(?<mes>\\d{2})');
            await trocar(wrapper, '$<mes>/$<ano>');
            await clicar(wrapper, 'COPIAR');

            expect(clipboard.writeText).toHaveBeenCalledWith('10/2026 e 03/2027');
            expect(rodape(wrapper)).toBe('COPIADO');
        });

        it('campo de troca vazio nao e uma troca', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'banana');
            await escrever(wrapper, 'a');
            await trocar(wrapper, 'X');

            expect(textoDaTroca(wrapper)).toBe('bXnXnX');
            expect(desabilitado(wrapper, 'COPIAR')).toBe(false);

            await trocar(wrapper, '');

            expect(wrapper.find('[data-test="regex-tester-replaced"]').exists()).toBe(false);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });
    });

    describe('as acoes', () => {
        it('o exemplo escreve texto, padrao, troca e flags de uma vez', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(campo(wrapper, 'regex-tester-pattern').value).toBe(EXEMPLO_PADRAO);
            expect(campo(wrapper, 'regex-tester-replacement').value).toBe(EXEMPLO_TROCA);
            expect(flagsNaBarra(wrapper)).toBe('/gm');
            expect(alternador(wrapper, 'm').attributes('aria-pressed')).toBe('true');

            // Sem esperar o intervalo: o exemplo aplica os quatro de uma vez e a
            // busca sai com o conjunto novo. Se ela tivesse saido so com o texto,
            // o padrao ainda estaria vazio e nao haveria casamento nenhum.
            expect(casamentos(wrapper)).toHaveLength(3);
            expect(textoDaTroca(wrapper)).toContain('INFO #4417:');
        });

        it('leva o foco ao padrao quando o editor pede a busca', async () => {
            const wrapper = montar();

            wrapper.findComponent({ name: 'CodeEditor' }).vm.$emit('find');

            await flushPromises();

            expect(document.activeElement).toBe(campo(wrapper, 'regex-tester-pattern'));
        });

        it('limpa o texto de teste e deixa os controles da busca em pe', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'LIMPAR');

            expect(wrapper.get('[data-test="editor"]').element.textContent).toBe('');
            expect(estado(wrapper)).toBe('VAZIO');
            expect(campo(wrapper, 'regex-tester-pattern').value).toBe(EXEMPLO_PADRAO);
            expect(campo(wrapper, 'regex-tester-replacement').value).toBe(EXEMPLO_TROCA);
            expect(alternador(wrapper, 'm').attributes('aria-pressed')).toBe('true');
            expect(desabilitado(wrapper, 'LIMPAR')).toBe(true);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });

        it('nao busca o que passa do limite de entrada', async () => {
            const wrapper = montar({ maxInputBytes: 3 });

            await digitar(wrapper, 'banana');
            await escrever(wrapper, 'a');

            expect(estado(wrapper)).toContain('GRANDE:6');
            expect(wrapper.find('[data-test="regex-tester-match"]').exists()).toBe(false);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });
    });

    describe('arquivo', () => {
        function escolher(elemento: Element, file: File): void {
            Object.defineProperty(elemento, 'files', {
                value: [file],
                configurable: true,
            });
        }

        it('abre o arquivo de texto escolhido e ja busca com ele', async () => {
            const wrapper = montar();

            await escrever(wrapper, 'a');

            const arquivo = new File(['banana e ananas'], 'notas.txt', { type: 'text/plain' });
            const entrada = wrapper.get('[data-test="regex-tester-file"]');

            escolher(entrada.element, arquivo);
            await entrada.trigger('change');
            await esperar();

            expect(wrapper.get('[data-test="editor"]').element.textContent).toBe('banana e ananas');
            expect(trechos(wrapper)).toEqual(['a', 'a', 'a', 'a', 'a', 'a']);
        });

        it('recusa arquivo de outro formato', async () => {
            const wrapper = montar();
            const entrada = wrapper.get('[data-test="regex-tester-file"]');

            escolher(entrada.element, new File(['a;b'], 'notas.csv', { type: 'text/csv' }));
            await entrada.trigger('change');
            await flushPromises();

            expect(rodape(wrapper)).toBe('TIPO_NAO_ACEITO');
            expect(wrapper.get('[data-test="editor"]').element.textContent).toBe('');
        });
    });

    describe('o estouro de tempo', () => {
        it('nao deixa resultado nenhum na tela', async () => {
            vi.stubGlobal('Worker', FakeWorker);
            vi.useFakeTimers();

            relogioFalso = true;

            const wrapper = montar();

            // A busca fica esperando o worker: sem resposta nao ha o que pintar.
            await clicar(wrapper, 'EXEMPLO');

            expect(estado(wrapper)).toBe('ANALISANDO');

            const worker = FakeWorker.instances[0] as FakeWorker;

            worker.answer();
            await esperar(0);

            expect(casamentos(wrapper)).toHaveLength(3);

            // O texto muda e o pedido novo **nunca** volta: o relogio mata a
            // conta, e a tela nao pode mostrar nem o resultado de antes nem um
            // resultado pela metade.
            await digitar(wrapper, 'nada aqui');

            expect(worker.posted).toHaveLength(2);

            await esperar(LIMITE_MS);

            expect(estado(wrapper)).toBe('TEMPO:1');
            expect(rodape(wrapper)).toBe('TEMPO:1');
            expect(wrapper.find('[data-test="regex-tester-match"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="regex-tester-preview"]').exists()).toBe(false);
            expect(wrapper.find('[data-test="regex-tester-replaced"]').exists()).toBe(false);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
            expect(worker.terminated).toBe(true);
        });
    });
});
