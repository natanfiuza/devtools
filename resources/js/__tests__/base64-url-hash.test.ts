import {
    flushPromises,
    mount,
    type DOMWrapper,
    type VueWrapper,
} from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { Select } from '@/components/ui/select';
import { Tabs } from '@/components/ui/tabs';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/base64-url-hash/Tool.vue';

/**
 * A tela do Base64, URL e Hash: uma entrada, tres abas e uma direcao.
 *
 * O que se cobra aqui e o que a **tela** faz, e nao a conta - essa tem os tres
 * arquivos proprios (`encoding-base64`, `encoding-url`, `md5`/`hash`). E o que a
 * tela faz e: qual aba mostra quantas linhas, o que a direcao muda, o que sobra
 * quando a entrada nao decodifica, e o que o botao Copiar leva.
 *
 * O editor de verdade fica de fora pelo mesmo motivo das outras tools - o
 * CodeMirror precisa do DOM real -, e o `crypto.subtle` do jsdom **serve**: os
 * quatro hashes sao calculados de verdade aqui.
 */

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
        'base64-url-hash': {
            direction: 'DIRECAO',
            file_types: 'TXT',
            row_copied: '{label} copiado',
            directions: { encode: 'CODIFICAR', decode: 'DECODIFICAR' },
            modes: { base64: 'ABA_BASE64', url: 'ABA_URL', hash: 'ABA_HASH' },
            rows: {
                base64: 'LINHA_BASE64',
                urlSafe: 'LINHA_URLSAFE',
                component: 'LINHA_COMPONENTE',
                form: 'LINHA_FORMULARIO',
                md5: 'LINHA_MD5',
                sha1: 'LINHA_SHA1',
                sha256: 'LINHA_SHA256',
                sha512: 'LINHA_SHA512',
            },
            states: {
                invalid_base64: 'INVALIDO_BASE64',
                invalid_url: 'INVALIDO_URL',
                no_crypto: 'SEM_CRYPTO',
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

/** O rotulo do botao, sem as teclas que moram dentro dele. */
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

async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper
        .findComponent({ name: 'CodeEditor' })
        .vm.$emit('update:modelValue', value);

    // A conta das abas de codificacao e sincrona, mas a dos hashes nao: o
    // `flush` e o que espera os dois caminhos.
    await flushPromises();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

/** O `Select` que tem o gatilho `testid`. */
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

async function escolher(
    wrapper: VueWrapper,
    testid: string,
    valor: string,
): Promise<void> {
    seletor(wrapper, testid).vm.$emit('update:modelValue', valor);
    await flushPromises();
}

/**
 * O `Tabs` do painel de saida.
 *
 * Sao dois na tela - o da casca, que empilha entrada e saida no celular, e o
 * das abas de modo -, e o que os separa e o `data-test` da casca.
 */
function painel(wrapper: VueWrapper) {
    const found = wrapper
        .findAllComponents(Tabs)
        .find(
            (component) =>
                component.attributes('data-test') !== 'tool-shell-tabs',
        );

    if (found === undefined) {
        throw new Error('as abas do painel nao estao na tela');
    }

    return found;
}

async function abrir(wrapper: VueWrapper, mode: string): Promise<void> {
    painel(wrapper).vm.$emit('update:modelValue', mode);
    await flushPromises();
}

/** As linhas da aba aberta, na ordem em que a tela as desenha. */
function rotulos(wrapper: VueWrapper): string[] {
    return wrapper
        .findAll('[data-test="base64-url-hash-row"]')
        .map((node) => node.attributes('data-label') ?? '');
}

function linha(wrapper: VueWrapper, label: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('[data-test="base64-url-hash-row"]')
        .find((node) => node.attributes('data-label') === label);

    if (found === undefined) {
        throw new Error(`a linha ${label} nao esta na tela`);
    }

    return found;
}

/**
 * O valor de uma linha.
 *
 * `textContent`, e nao `text()`: o `text()` do Vue Test Utils apara as pontas, e
 * o valor de um hash ou de um texto decodificado pode comecar ou terminar com
 * espaco.
 */
function valor(wrapper: VueWrapper, label: string): string {
    return (
        linha(wrapper, label).get('[data-test="base64-url-hash-value"]').element
            .textContent ?? ''
    );
}

/** A linha ficou sem valor? */
function recusada(wrapper: VueWrapper, label: string): boolean {
    return linha(wrapper, label)
        .find('[data-test="base64-url-hash-refused"]')
        .exists();
}

function estado(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="base64-url-hash-state"]');

    return node.exists() ? node.text() : '';
}

function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="base64-url-hash-status"]');

    return node.exists() ? node.text().replace(/\s+/g, ' ') : '';
}

/** Tira o `subtle` do caminho e devolve o que o poe de volta. */
function semWebCrypto(): () => void {
    const original = Object.getOwnPropertyDescriptor(crypto, 'subtle');

    Object.defineProperty(crypto, 'subtle', {
        value: undefined,
        configurable: true,
    });

    return () => {
        if (original === undefined) {
            Reflect.deleteProperty(crypto, 'subtle');

            return;
        }

        Object.defineProperty(crypto, 'subtle', original);
    };
}

describe('Base64, URL e Hash', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    describe('estado inicial', () => {
        it('abre sem texto, na aba Base64 e codificando', () => {
            const wrapper = montar();

            expect(
                wrapper
                    .get('[data-test="editor"]')
                    .attributes('data-placeholder'),
            ).toBe('VAZIO');
            expect(painel(wrapper).props('modelValue')).toBe('base64');
            expect(
                seletor(wrapper, 'base64-url-hash-direction').props(
                    'modelValue',
                ),
            ).toBe('encode');
            expect(rotulos(wrapper)).toEqual([]);
            expect(estado(wrapper)).toBe('');
        });

        it('desabilita o que precisa de texto', () => {
            const wrapper = montar();

            expect(desabilitado(wrapper, 'LIMPAR')).toBe(true);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });

        it('nao oferece download: a saida e para copiar, nao para guardar', () => {
            const wrapper = montar();

            expect(
                wrapper
                    .findAll('button')
                    .some((node) => rotuloDoBotao(node) === 'BAIXAR'),
            ).toBe(false);
        });

        it('nomeia as tres abas e a direcao', () => {
            const wrapper = montar();

            for (const [mode, rotulo] of [
                ['base64', 'ABA_BASE64'],
                ['url', 'ABA_URL'],
                ['hash', 'ABA_HASH'],
            ]) {
                expect(
                    wrapper
                        .get(`[data-test="base64-url-hash-mode-${mode}"]`)
                        .text(),
                ).toBe(rotulo);
            }

            expect(
                wrapper
                    .get('[data-test="base64-url-hash-direction"]')
                    .attributes('aria-label'),
            ).toBe('DIRECAO');
        });
    });

    describe('aba Base64', () => {
        it('mostra as duas variantes do RFC 4648 lado a lado', async () => {
            const wrapper = montar();

            await digitar(wrapper, '???');

            expect(rotulos(wrapper)).toEqual(['base64', 'urlSafe']);
            // A diferenca das duas e exatamente o que se quer ver: `+` e `/` do
            // alfabeto padrao, `-` e `_` do URL-safe.
            expect(valor(wrapper, 'base64')).toBe('Pz8/');
            expect(valor(wrapper, 'urlSafe')).toBe('Pz8_');
        });

        it('codifica o texto acentuado em UTF-8, e nao em Latin-1', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'ação');

            expect(valor(wrapper, 'base64')).toBe('YcOnw6Nv');
        });

        it('deixa uma linha so para decodificar', async () => {
            const wrapper = montar();

            await escolher(wrapper, 'base64-url-hash-direction', 'decode');
            await digitar(wrapper, 'YQ');

            // O decodificador aceita os dois alfabetos, com ou sem padding:
            // duas linhas iguais seriam ruido.
            expect(rotulos(wrapper)).toEqual(['base64']);
            expect(valor(wrapper, 'base64')).toBe('a');
        });
    });

    describe('aba URL', () => {
        it('mostra o componente e o formulario lado a lado', async () => {
            const wrapper = montar();

            await abrir(wrapper, 'url');
            await digitar(wrapper, 'a b');

            expect(rotulos(wrapper)).toEqual(['component', 'form']);
            // O espaco e a diferenca das duas: `%20` num, `+` no outro.
            expect(valor(wrapper, 'component')).toBe('a%20b');
            expect(valor(wrapper, 'form')).toBe('a+b');
        });

        it('mantem as duas linhas na volta, porque a leitura difere', async () => {
            const wrapper = montar();

            await abrir(wrapper, 'url');
            await escolher(wrapper, 'base64-url-hash-direction', 'decode');
            await digitar(wrapper, 'a+b');

            expect(rotulos(wrapper)).toEqual(['component', 'form']);
            // No formulario o `+` e o espaco; no componente ele e o `+`.
            expect(valor(wrapper, 'component')).toBe('a+b');
            expect(valor(wrapper, 'form')).toBe('a b');
        });

        it('recusa a sequencia de escape quebrada', async () => {
            const wrapper = montar();

            await abrir(wrapper, 'url');
            await escolher(wrapper, 'base64-url-hash-direction', 'decode');
            await digitar(wrapper, '%E0%A4');

            // A recusa e da entrada inteira - as duas variantes recusam juntas
            // -, entao ela ocupa o lugar das linhas.
            expect(estado(wrapper)).toBe('INVALIDO_URL');
            expect(rotulos(wrapper)).toEqual([]);
        });
    });

    describe('aba Hash', () => {
        it('mostra os quatro na mesma tela', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'abc');
            await abrir(wrapper, 'hash');

            expect(rotulos(wrapper)).toEqual([
                'md5',
                'sha1',
                'sha256',
                'sha512',
            ]);
            expect(valor(wrapper, 'md5')).toBe(
                '900150983cd24fb0d6963f7d28e17f72',
            );
            expect(valor(wrapper, 'sha1')).toBe(
                'a9993e364706816aba3e25717850c26c9cd0d89d',
            );
            expect(valor(wrapper, 'sha256')).toBe(
                'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
            );
            expect(valor(wrapper, 'sha512')).toHaveLength(128);
        });

        it('nao oferece direcao: hash nao tem volta', async () => {
            const wrapper = montar();

            expect(
                wrapper
                    .find('[data-test="base64-url-hash-direction"]')
                    .exists(),
            ).toBe(true);

            await abrir(wrapper, 'hash');

            expect(
                wrapper
                    .find('[data-test="base64-url-hash-direction"]')
                    .exists(),
            ).toBe(false);

            await abrir(wrapper, 'base64');

            expect(
                wrapper
                    .find('[data-test="base64-url-hash-direction"]')
                    .exists(),
            ).toBe(true);
        });

        it('recalcula quando o texto muda com a aba aberta', async () => {
            const wrapper = montar();

            await abrir(wrapper, 'hash');
            await digitar(wrapper, 'abc');

            expect(valor(wrapper, 'md5')).toBe(
                '900150983cd24fb0d6963f7d28e17f72',
            );

            await digitar(wrapper, 'a');

            expect(valor(wrapper, 'md5')).toBe(
                '0cc175b9c0f1b6a831c399e269772661',
            );
        });

        it('nao deixa digest antigo na tela depois de limpar', async () => {
            const wrapper = montar();

            await abrir(wrapper, 'hash');
            await digitar(wrapper, 'abc');
            await clicar(wrapper, 'LIMPAR');

            expect(rotulos(wrapper)).toEqual([]);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });

        it('deixa as tres linhas de SHA sem valor quando nao ha Web Crypto', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'abc');

            const restaurar = semWebCrypto();

            try {
                await abrir(wrapper, 'hash');

                // O MD5 e a conta da casa: ele sai de qualquer jeito.
                expect(recusada(wrapper, 'md5')).toBe(false);
                expect(valor(wrapper, 'md5')).toBe(
                    '900150983cd24fb0d6963f7d28e17f72',
                );
                expect(recusada(wrapper, 'sha256')).toBe(true);
                // O aviso aparece uma vez, e nao tres.
                expect(
                    wrapper.findAll('[data-test="base64-url-hash-no-crypto"]'),
                ).toHaveLength(1);
                expect(
                    wrapper
                        .get('[data-test="base64-url-hash-no-crypto"]')
                        .text(),
                ).toBe('SEM_CRYPTO');
            } finally {
                restaurar();
            }
        });
    });

    describe('limite de entrada', () => {
        it('nao converte o que passa do teto', async () => {
            const wrapper = montar(3);

            await digitar(wrapper, 'abcd');

            expect(estado(wrapper)).toContain('GRANDE:');
            expect(rotulos(wrapper)).toEqual([]);
        });

        it('conta o tamanho no rodape', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'abc');

            expect(rodape(wrapper)).toBe('3 bytes de 4,8 MB');
        });
    });

    describe('copiar', () => {
        it('copia a aba aberta, uma linha por valor', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'abc');
            await clicar(wrapper, 'COPIAR');

            // O rotulo vai junto: quem cola num arquivo de configuracao precisa
            // saber qual linha e qual.
            expect(clipboard.writeText).toHaveBeenCalledWith(
                'LINHA_BASE64: YWJj\nLINHA_URLSAFE: YWJj',
            );
            expect(rodape(wrapper)).toBe('COPIADO');
        });

        it('copia a aba que estiver aberta, e nao sempre a primeira', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'a b');
            await abrir(wrapper, 'url');
            await clicar(wrapper, 'COPIAR');

            expect(clipboard.writeText).toHaveBeenCalledWith(
                'LINHA_COMPONENTE: a%20b\nLINHA_FORMULARIO: a+b',
            );
        });

        it('copia os quatro hashes quando a aba do Hash esta aberta', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'abc');
            await abrir(wrapper, 'hash');
            await clicar(wrapper, 'COPIAR');

            const copiado = clipboard.writeText.mock.calls[0]?.[0] as string;

            expect(copiado.split('\n')).toHaveLength(4);
            expect(
                copiado.startsWith(
                    'LINHA_MD5: 900150983cd24fb0d6963f7d28e17f72',
                ),
            ).toBe(true);
        });

        it('copia so o valor pelo botao da linha, e diz qual foi', async () => {
            const wrapper = montar();

            await digitar(wrapper, '???');
            await linha(wrapper, 'urlSafe')
                .get('[data-test="base64-url-hash-row-copy"]')
                .trigger('click');
            await flushPromises();

            expect(clipboard.writeText).toHaveBeenCalledWith('Pz8_');
            expect(rodape(wrapper)).toBe('LINHA_URLSAFE copiado');
        });

        it('nao oferece copiar quando nao ha valor nenhum na aba', async () => {
            const wrapper = montar();

            await escolher(wrapper, 'base64-url-hash-direction', 'decode');
            await digitar(wrapper, '!!!!');

            // A recusa e da entrada inteira: o recado ocupa o lugar das linhas,
            // e nao ha o que copiar.
            expect(estado(wrapper)).toBe('INVALIDO_BASE64');
            expect(rotulos(wrapper)).toEqual([]);
            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });

        it('nao oferece copiar na linha que ficou sem valor', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'abc');

            const restaurar = semWebCrypto();

            try {
                await abrir(wrapper, 'hash');

                // A linha sem valor nao tem o que copiar - e nao adianta o botao
                // ao lado das outras, que tem.
                expect(
                    linha(wrapper, 'sha256')
                        .find('[data-test="base64-url-hash-row-copy"]')
                        .exists(),
                ).toBe(false);
                expect(
                    linha(wrapper, 'md5')
                        .find('[data-test="base64-url-hash-row-copy"]')
                        .exists(),
                ).toBe(true);
            } finally {
                restaurar();
            }
        });

        it('manda copiar na mao quando o navegador nao expoe a area de transferencia', async () => {
            Object.defineProperty(navigator, 'clipboard', {
                value: undefined,
                configurable: true,
            });

            const wrapper = montar();

            await digitar(wrapper, 'abc');
            await clicar(wrapper, 'COPIAR');

            expect(rodape(wrapper)).toBe('COPIE_NA_MAO');
        });
    });

    describe('exemplo', () => {
        it('carrega o pedido de exemplo e ja mostra as tres abas', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(valor(wrapper, 'base64')).toContain(
                'UGFkYXJpYSBkbyBCYWlycm8gLSBwZWRpZG8gIzQy',
            );

            // O exemplo tem o que as tres abas querem mostrar: acento (UTF-8 de
            // verdade), espaco, e os caracteres que a URL escapa.
            await abrir(wrapper, 'url');

            expect(valor(wrapper, 'component')).toContain('%20');

            await abrir(wrapper, 'hash');

            expect(valor(wrapper, 'md5')).toHaveLength(32);
        });

        it('para de mostrar o valor quando o exemplo e limpo', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'LIMPAR');

            expect(rotulos(wrapper)).toEqual([]);
            expect(
                wrapper.get('[data-test="editor"]').element.textContent,
            ).toBe('');
        });
    });
});
