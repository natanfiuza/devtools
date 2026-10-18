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
import Tool from '@/tools/timestamp-uuid/Tool.vue';
import { instantOfId, ulid, uuidV7 } from '@/tools/timestamp-uuid/lib/ids';

/**
 * A tela do Timestamp e UUID: as seis leituras de cima e a lista de baixo.
 *
 * O que se cobra aqui e o que a **tela** faz - a ordem das leituras, o aviso do
 * id colado, o que cada botao de copiar leva, e o que "Limpar" limpa. As contas
 * (a unidade pelo tamanho do numero, os bits do ULID) tem arquivo proprio:
 * `time.test.ts` e `ids.test.ts`.
 *
 * O "tempo relativo" e a unica linha que depende do relogio de verdade - a tela
 * le o `Date.now()` -, entao aqui ele so e conferido como "saiu alguma coisa";
 * o valor esta preso em `time.test.ts`, onde o agora entra por parametro.
 */

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
        'timestamp-uuid': {
            now: 'AGORA_BOTAO',
            kind: 'TIPO',
            quantity: 'QUANTIDADE',
            generate: 'GERAR',
            ids_title: 'IDS:{count}',
            copy_ids: 'COPIAR_TODOS',
            ids_copied: '{count} ids copiados',
            id_copied: 'ID_COPIADO',
            row_copied: '{label} copiado',
            recognised: 'RECONHECIDO:{kind}',
            kinds: { uuid_v4: 'V4', uuid_v7: 'V7', ulid: 'ULID' },
            readings: {
                unix_seconds: 'UNIX_S',
                unix_ms: 'UNIX_MS',
                iso_utc: 'ISO_UTC',
                iso_local: 'ISO_LOCAL',
                long_date: 'LONGA',
                relative: 'RELATIVO',
            },
            states: { unreadable: 'ILEGIVEL_INSTANTE' },
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

/** O exemplo que a tela carrega no botao Exemplo: 2026-10-01 12:00 UTC. */
const EXEMPLO = '1790856000';

/** O mesmo instante em ISO, que e o que a linha do UTC mostra. */
const EXEMPLO_UTC = '2026-10-01T12:00:00.000Z';

/** Um instante fixo para os ids: 2026-10-14 12:00 UTC. */
const AGORA = Date.UTC(2026, 9, 14, 12, 0, 0);

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

function botao(wrapper: VueWrapper, rotulo: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('button')
        .find((node) => node.text() === rotulo);

    if (found === undefined) {
        throw new Error(`o botao ${rotulo} nao esta na tela`);
    }

    return found;
}

function desabilitado(wrapper: VueWrapper, rotulo: string): boolean {
    return botao(wrapper, rotulo).attributes('disabled') !== undefined;
}

async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper
        .findComponent({ name: 'CodeEditor' })
        .vm.$emit('update:modelValue', value);

    await flushPromises();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

async function gerar(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('[data-test="timestamp-uuid-generate"]').trigger('click');
    await flushPromises();
}

/**
 * O `Select` que tem o gatilho `testid`.
 *
 * Sao dois no cabecalho da saida (o tipo e a quantidade) e os dois sao o mesmo
 * componente: o que os separa e o gatilho, e nao a ordem.
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
    valor: string | number,
): Promise<void> {
    seletor(wrapper, testid).vm.$emit('update:modelValue', valor);

    await flushPromises();
}

function linha(wrapper: VueWrapper, label: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('[data-test="timestamp-uuid-reading"]')
        .find((node) => node.attributes('data-label') === label);

    if (found === undefined) {
        throw new Error(`a linha ${label} nao esta na tela`);
    }

    return found;
}

/** O rotulo de uma leitura, em caixa alta, como o teste o escreve. */
function etiqueta(wrapper: VueWrapper, label: string): string {
    return linha(wrapper, label)
        .get('[data-test="timestamp-uuid-reading-label"]')
        .text();
}

function valor(wrapper: VueWrapper, label: string): string {
    return linha(wrapper, label)
        .get('[data-test="timestamp-uuid-reading-value"]')
        .text();
}

/** As leituras na tela, na ordem em que aparecem, pelos `data-label`. */
function leituras(wrapper: VueWrapper): string[] {
    return wrapper
        .findAll('[data-test="timestamp-uuid-reading"]')
        .map((node) => node.attributes('data-label') ?? '');
}

/** O botao de copiar de uma leitura, pelo `data-label` dela. */
async function copiarLeitura(
    wrapper: VueWrapper,
    label: string,
): Promise<void> {
    await linha(wrapper, label)
        .get('[data-test="timestamp-uuid-reading-copy"]')
        .trigger('click');
    await flushPromises();
}

function idsNaTela(wrapper: VueWrapper): string[] {
    return wrapper
        .findAll('[data-test="timestamp-uuid-id"]')
        .map((node) => node.get('p').text());
}

function estado(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="timestamp-uuid-state"]');

    return node.exists() ? node.text() : '';
}

function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="timestamp-uuid-status"]');

    return node.exists() ? node.text().replace(/\s+/g, ' ') : '';
}

describe('Timestamp e UUID', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    describe('estado inicial', () => {
        it('abre sem leitura nenhuma, com os geradores no cabecalho da saida', () => {
            const wrapper = montar();

            expect(leituras(wrapper)).toEqual([]);
            expect(
                wrapper.find('[data-test="timestamp-uuid-ids"]').exists(),
            ).toBe(false);
            expect(
                wrapper.find('[data-test="timestamp-uuid-state"]').exists(),
            ).toBe(false);

            expect(
                seletor(wrapper, 'timestamp-uuid-kind').props('modelValue'),
            ).toBe('uuid_v4');
            expect(
                seletor(wrapper, 'timestamp-uuid-quantity').props('modelValue'),
            ).toBe(5);
            expect(
                wrapper
                    .get('[data-test="timestamp-uuid-kind"]')
                    .attributes('aria-label'),
            ).toBe('TIPO');
            expect(
                wrapper
                    .get('[data-test="timestamp-uuid-quantity"]')
                    .attributes('aria-label'),
            ).toBe('QUANTIDADE');
        });

        it('convida a colar e nao oferece copiar', () => {
            const wrapper = montar();

            expect(
                wrapper
                    .get('[data-test="editor"]')
                    .attributes('data-placeholder'),
            ).toBe('VAZIO');
            expect(rodape(wrapper)).toBe('VAZIO');
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
            expect(desabilitado(wrapper, 'LIMPAR')).toBe(true);
        });
    });

    describe('as seis leituras', () => {
        it('saem na ordem, com o rotulo de cada uma', async () => {
            const wrapper = montar();

            await digitar(wrapper, EXEMPLO);

            expect(leituras(wrapper)).toEqual([
                'unix_seconds',
                'unix_ms',
                'iso_utc',
                'iso_local',
                'long_date',
                'relative',
            ]);
            expect(etiqueta(wrapper, 'unix_seconds')).toBe('UNIX_S');
            expect(etiqueta(wrapper, 'iso_utc')).toBe('ISO_UTC');
        });

        it('mostra o mesmo instante em cada forma', async () => {
            const wrapper = montar();

            await digitar(wrapper, EXEMPLO);

            expect(valor(wrapper, 'unix_seconds')).toBe(EXEMPLO);
            expect(valor(wrapper, 'unix_ms')).toBe('1790856000000');
            expect(valor(wrapper, 'iso_utc')).toBe(EXEMPLO_UTC);
            expect(valor(wrapper, 'iso_local')).toMatch(
                /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/,
            );
            expect(valor(wrapper, 'long_date')).toContain('2026');
            expect(valor(wrapper, 'relative')).not.toBe('');
        });

        it('le a data escrita', async () => {
            const wrapper = montar();

            await digitar(wrapper, '2026-10-01T12:00:00Z');

            expect(valor(wrapper, 'unix_seconds')).toBe(EXEMPLO);
            expect(valor(wrapper, 'iso_utc')).toBe(EXEMPLO_UTC);
        });

        it('le o instante que um id colado guarda, e diz de onde ele veio', async () => {
            const wrapper = montar();

            await digitar(wrapper, uuidV7(AGORA));

            expect(
                wrapper.get('[data-test="timestamp-uuid-recognised"]').text(),
            ).toContain('RECONHECIDO:V7');
            expect(valor(wrapper, 'unix_ms')).toBe(String(AGORA));
            expect(valor(wrapper, 'iso_utc')).toBe('2026-10-14T12:00:00.000Z');

            await digitar(wrapper, ulid(AGORA));

            expect(
                wrapper.get('[data-test="timestamp-uuid-recognised"]').text(),
            ).toContain('RECONHECIDO:ULID');
            expect(valor(wrapper, 'unix_ms')).toBe(String(AGORA));
        });

        it('nao avisa nada quando o instante foi escrito, e nao lido de um id', async () => {
            const wrapper = montar();

            await digitar(wrapper, EXEMPLO);

            expect(
                wrapper
                    .find('[data-test="timestamp-uuid-recognised"]')
                    .exists(),
            ).toBe(false);
        });

        it('recusa o que nao e um instante', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'nada');

            expect(estado(wrapper)).toBe('ILEGIVEL_INSTANTE');
            expect(leituras(wrapper)).toEqual([]);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);

            // E volta a ler quando o texto vira um instante.
            await digitar(wrapper, EXEMPLO);

            expect(estado(wrapper)).toBe('');
            expect(valor(wrapper, 'iso_utc')).toBe(EXEMPLO_UTC);
        });

        it('recusa o texto acima do teto', async () => {
            const wrapper = montar(10);

            await digitar(wrapper, '17908560000');

            expect(estado(wrapper)).toBe('GRANDE:11 bytes:10 bytes');
            expect(leituras(wrapper)).toEqual([]);
        });
    });

    describe('os botoes do documento', () => {
        it('Agora escreve o Unix em segundos', async () => {
            const wrapper = montar();
            const antes = Math.floor(Date.now() / 1000);

            await clicar(wrapper, 'AGORA_BOTAO');

            const escrito = wrapper.get('[data-test="editor"]').text();

            expect(escrito).toMatch(/^\d{10}$/);
            expect(Number(escrito)).toBeGreaterThanOrEqual(antes);
            expect(valor(wrapper, 'unix_seconds')).toBe(escrito);
        });

        it('Exemplo escreve o instante do sample', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(wrapper.get('[data-test="editor"]').text()).toBe(EXEMPLO);
            expect(valor(wrapper, 'iso_utc')).toBe(EXEMPLO_UTC);
        });

        it('Limpar esvazia o documento', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'LIMPAR');

            expect(wrapper.get('[data-test="editor"]').text()).toBe('');
            expect(leituras(wrapper)).toEqual([]);
            expect(rodape(wrapper)).toBe('VAZIO');
        });

        it('Limpar nao apaga os ids gerados', async () => {
            const wrapper = montar();

            await digitar(wrapper, EXEMPLO);
            await gerar(wrapper);
            await clicar(wrapper, 'LIMPAR');

            // Os ids nao saem da entrada: apagar o que alguem acabou de gerar
            // porque limpou a caixa de cima seria perder trabalho.
            expect(wrapper.get('[data-test="editor"]').text()).toBe('');
            expect(idsNaTela(wrapper)).toHaveLength(5);
            expect(rodape(wrapper)).toBe('IDS:5');
        });
    });

    describe('copiar as leituras', () => {
        it('leva as seis linhas, com o rotulo na frente de cada uma', async () => {
            const wrapper = montar();

            await digitar(wrapper, EXEMPLO);
            await clicar(wrapper, 'COPIAR');

            // A conferencia e da **forma** (rotulo: valor, uma por linha) e nao
            // dos valores: o local e o relativo mudam com o fuso e o relogio de
            // quem roda a suite, e o que eles valem esta nos outros casos.
            const esperado = leituras(wrapper)
                .map(
                    (label) =>
                        `${etiqueta(wrapper, label)}: ${valor(wrapper, label)}`,
                )
                .join('\n');

            expect(clipboard.writeText).toHaveBeenCalledWith(esperado);
            expect(rodape(wrapper)).toBe('COPIADO');
        });

        it('leva uma linha so, pelo botao dela', async () => {
            const wrapper = montar();

            await digitar(wrapper, EXEMPLO);
            await copiarLeitura(wrapper, 'iso_utc');

            expect(clipboard.writeText).toHaveBeenCalledWith(EXEMPLO_UTC);
            expect(rodape(wrapper)).toBe('ISO_UTC copiado');
        });
    });

    describe('gerar ids', () => {
        it('gera a quantidade que o seletor diz, do tipo que ele diz', async () => {
            const wrapper = montar();

            await gerar(wrapper);

            expect(idsNaTela(wrapper)).toHaveLength(5);
            for (const id of idsNaTela(wrapper)) {
                expect(id).toMatch(
                    /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/,
                );
            }

            await escolher(wrapper, 'timestamp-uuid-kind', 'ulid');
            await escolher(wrapper, 'timestamp-uuid-quantity', 10);
            await gerar(wrapper);

            expect(idsNaTela(wrapper)).toHaveLength(10);
            for (const id of idsNaTela(wrapper)) {
                expect(id).toMatch(/^[0-9A-HJKMNP-TV-Z]{26}$/);
            }
        });

        it('o v7 nasce com o instante de agora', async () => {
            const wrapper = montar();
            const antes = Date.now();

            await escolher(wrapper, 'timestamp-uuid-kind', 'uuid_v7');
            await gerar(wrapper);

            const lido = instantOfId(idsNaTela(wrapper)[0] ?? '');

            expect(lido?.kind).toBe('uuid_v7');
            expect(lido?.ms).toBeGreaterThanOrEqual(antes);
        });

        it('conta os ids na tela e no rodape', async () => {
            const wrapper = montar();

            await gerar(wrapper);

            expect(
                wrapper.get('[data-test="timestamp-uuid-ids-title"]').text(),
            ).toBe('IDS:5');
            expect(rodape(wrapper)).toBe('IDS:5');
        });

        it('copiar todos leva a lista inteira, uma por linha', async () => {
            const wrapper = montar();

            await escolher(wrapper, 'timestamp-uuid-quantity', 1);
            await gerar(wrapper);
            await wrapper
                .get('[data-test="timestamp-uuid-ids-copy"]')
                .trigger('click');
            await flushPromises();

            expect(clipboard.writeText).toHaveBeenCalledWith(
                idsNaTela(wrapper).join('\n'),
            );
            expect(rodape(wrapper)).toBe('1 ids copiados');
        });

        it('cada id tem o proprio botao de copiar', async () => {
            const wrapper = montar();

            await escolher(wrapper, 'timestamp-uuid-quantity', 1);
            await gerar(wrapper);
            await wrapper
                .get('[data-test="timestamp-uuid-id-copy"]')
                .trigger('click');
            await flushPromises();

            expect(clipboard.writeText).toHaveBeenCalledWith(
                idsNaTela(wrapper)[0],
            );
            expect(rodape(wrapper)).toBe('ID_COPIADO');
        });

        it('gerar de novo troca a lista, e nao acrescenta', async () => {
            const wrapper = montar();

            await escolher(wrapper, 'timestamp-uuid-quantity', 1);
            await gerar(wrapper);

            const primeiro = idsNaTela(wrapper)[0];

            await gerar(wrapper);

            expect(idsNaTela(wrapper)).toHaveLength(1);
            expect(idsNaTela(wrapper)[0]).not.toBe(primeiro);
        });
    });
});
