import { flushPromises, mount, type DOMWrapper, type VueWrapper } from '@vue/test-utils';
import { defineComponent, h, type PropType } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/jwt-decoder/Tool.vue';

/**
 * A tela do JWT Decoder: uma entrada, dois blocos e as datas.
 *
 * O que se cobra aqui e o que a **tela** faz - quantos blocos aparecem, o que a
 * cor de cada pedaco do JSON diz, o selo do claim vencido, e o que o botao
 * Copiar leva. A leitura do token em si tem o arquivo proprio
 * (`jwt-decode.test.ts`).
 *
 * O editor de verdade fica de fora, como nas outras tools: o CodeMirror precisa
 * do DOM real.
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
        'jwt-decoder': {
            header: 'CABECALHO',
            payload: 'PAYLOAD',
            signature: 'ASSINATURA',
            signature_bytes: 'BYTES:{count}',
            not_verified: 'NAO_VERIFICADA',
            block_copied: '{label} copiado',
            warning: 'AVISO',
            claims_title: 'DATAS',
            claims: { expired: 'EXPIRADO', not_yet: 'AINDA_NAO_VALE' },
            states: { shape: 'ESTADO_FORMA', base64: 'ESTADO_BASE64', json: 'ESTADO_JSON' },
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

/*
| Os tokens dos casos.
|
| Montados aqui com `btoa`, e nao com o `decodeJwt` da propria tool: um caso
| escrito com o codigo que ele exercita nao prova nada. Os instantes saem do
| relogio de agora **de proposito** nos casos de selo - um token fixo no
| calendario envelheceria e a suite passaria a mentir.
*/

const CABECALHO = { alg: 'HS256', typ: 'JWT' };

function segment(value: unknown): string {
    const bytes = new TextEncoder().encode(JSON.stringify(value));
    const binary = String.fromCharCode(...bytes);

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function token(payload: unknown, signature: string = 'YXNzaW5hdHVyYQ'): string {
    return `${segment(CABECALHO)}.${segment(payload)}.${signature}`;
}

/** O token que a tela carrega no botao Exemplo. */
const EXEMPLO =
    'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI0MiIsIm5hbWUiOiJKb8OjbyBkYSBTaWx2YSIsInJvbGUiOiJhZG1pbiIsImlhdCI6MTc5MDg1NjAwMCwiZXhwIjoxNzkwODU5NjAwfQ.BCP_2bylQ6tinXehvIuJek2eA0lOvAss9vDRFAr4thw';

const agoraEmSegundos = (): number => Math.floor(Date.now() / 1000);

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

function botao(wrapper: VueWrapper, rotulo: string): DOMWrapper<Element> {
    const found = wrapper.findAll('button').find((node) => node.text() === rotulo);

    if (found === undefined) {
        throw new Error(`o botao ${rotulo} nao esta na tela`);
    }

    return found;
}

function desabilitado(wrapper: VueWrapper, rotulo: string): boolean {
    return botao(wrapper, rotulo).attributes('disabled') !== undefined;
}

async function digitar(wrapper: VueWrapper, value: string): Promise<void> {
    wrapper.findComponent({ name: 'CodeEditor' }).vm.$emit('update:modelValue', value);

    await flushPromises();
}

async function clicar(wrapper: VueWrapper, rotulo: string): Promise<void> {
    await botao(wrapper, rotulo).trigger('click');
    await flushPromises();
}

function bloco(wrapper: VueWrapper, name: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('[data-test="jwt-decoder-block"]')
        .find((node) => node.attributes('data-block') === name);

    if (found === undefined) {
        throw new Error(`o bloco ${name} nao esta na tela`);
    }

    return found;
}

function blocos(wrapper: VueWrapper): string[] {
    return wrapper
        .findAll('[data-test="jwt-decoder-block"]')
        .map((node) => node.attributes('data-block') ?? '');
}

/** O JSON do bloco como a tela o desenha, sem espaco entre os pedacos. */
function texto(wrapper: VueWrapper, name: string): string {
    return (
        bloco(wrapper, name).get('[data-test="jwt-decoder-json"]').element.textContent ?? ''
    );
}

/** Os pedacos coloridos do bloco: o texto e as classes de cada um. */
function pedacos(wrapper: VueWrapper, name: string): { text: string; classes: string[] }[] {
    return bloco(wrapper, name)
        .get('[data-test="jwt-decoder-json"]')
        .findAll('span')
        .map((node) => ({
            text: node.element.textContent ?? '',
            classes: Array.from(node.element.classList),
        }));
}

/** A cor de um pedaco do bloco, pelo texto dele. */
function cor(wrapper: VueWrapper, name: string, texto: string): string[] {
    return pedacos(wrapper, name).find((piece) => piece.text === texto)?.classes ?? [];
}

function claim(wrapper: VueWrapper, name: string): DOMWrapper<Element> {
    const found = wrapper
        .findAll('[data-test="jwt-decoder-claim"]')
        .find((node) => node.attributes('data-claim') === name);

    if (found === undefined) {
        throw new Error(`a linha do claim ${name} nao esta na tela`);
    }

    return found;
}

function valorDoClaim(wrapper: VueWrapper, name: string): string {
    return claim(wrapper, name).get('[data-test="jwt-decoder-claim-value"]').text();
}

function selo(wrapper: VueWrapper, name: string): string {
    const node = claim(wrapper, name).find('[data-test="jwt-decoder-claim-badge"]');

    return node.exists() ? node.text() : '';
}

function classeDoSelo(wrapper: VueWrapper, name: string): string {
    return claim(wrapper, name).get('[data-test="jwt-decoder-claim-badge"]').attributes('class') ?? '';
}

function estado(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="jwt-decoder-state"]');

    return node.exists() ? node.text() : '';
}

function rodape(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="jwt-decoder-status"]');

    return node.exists() ? node.text().replace(/\s+/g, ' ') : '';
}

/**
 * A linha da assinatura como o olho a le.
 *
 * Os pedacos ficam lado a lado por `gap`, e nao por espaco no HTML - o texto
 * cru viria colado -, entao a juncao com espaco e feita aqui.
 */
function assinatura(wrapper: VueWrapper): string {
    const node = wrapper.find('[data-test="jwt-decoder-signature"]');

    if (!node.exists()) {
        return '';
    }

    return Array.from(node.element.children)
        .map((child) => child.textContent ?? '')
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim();
}

describe('JWT Decoder', () => {
    let clipboard: { writeText: ReturnType<typeof vi.fn> };

    beforeEach(() => {
        clipboard = { writeText: vi.fn(() => Promise.resolve()) };

        Object.defineProperty(navigator, 'clipboard', {
            value: clipboard,
            configurable: true,
        });
    });

    describe('estado inicial', () => {
        it('abre com a faixa de aviso e sem bloco nenhum', () => {
            const wrapper = montar();

            expect(wrapper.find('[data-test="jwt-decoder-warning"]').text()).toBe('AVISO');
            expect(blocos(wrapper)).toEqual([]);
            expect(wrapper.find('[data-test="jwt-decoder-claims"]').exists()).toBe(false);
            expect(assinatura(wrapper)).toBe('');
        });

        it('nao oferece copiar antes de haver token', () => {
            const wrapper = montar();

            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
            expect(rodape(wrapper)).toBe('VAZIO');
        });
    });

    describe('o token lido', () => {
        it('mostra o cabecalho e o payload, nesta ordem', async () => {
            const wrapper = montar();

            await digitar(wrapper, token({ sub: '42' }));

            expect(blocos(wrapper)).toEqual(['header', 'payload']);
            expect(texto(wrapper, 'header')).toBe('{\n  "alg": "HS256",\n  "typ": "JWT"\n}');
            expect(texto(wrapper, 'payload')).toBe('{\n  "sub": "42"\n}');
        });

        it('pinta cada pedaco do JSON com a cor do DESIGN', async () => {
            const wrapper = montar();

            await digitar(wrapper, token({ texto: 'a', numero: 7, sim: true, nada: null }));

            expect(cor(wrapper, 'payload', '"texto"')).toContain('text-json-key');
            expect(cor(wrapper, 'payload', '"a"')).toContain('text-json-string');
            expect(cor(wrapper, 'payload', '7')).toContain('text-json-number');
            expect(cor(wrapper, 'payload', 'true')).toContain('text-json-literal');
            expect(cor(wrapper, 'payload', 'null')).toContain('text-json-literal');
            expect(cor(wrapper, 'payload', '{')).toContain('text-json-punct');
        });

        it('tolera o prefixo Bearer colado', async () => {
            const wrapper = montar();

            await digitar(wrapper, `Bearer ${token({ sub: '42' })}`);

            expect(estado(wrapper)).toBe('');
            expect(blocos(wrapper)).toEqual(['header', 'payload']);
        });

        it('mostra o exemplo da tela', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(texto(wrapper, 'payload')).toContain('"name": "João da Silva"');
            expect(assinatura(wrapper)).toBe('ASSINATURA BYTES:32 NAO_VERIFICADA');
        });

        it('mostra a assinatura so como comprimento em bytes', async () => {
            const wrapper = montar();

            await digitar(wrapper, token({ sub: '42' }, 'YWJjZGVm'));

            expect(assinatura(wrapper)).toBe('ASSINATURA BYTES:6 NAO_VERIFICADA');
        });
    });

    describe('as datas dos claims', () => {
        it('troca o numero cru pela data no idioma', async () => {
            const wrapper = montar();
            const iat = agoraEmSegundos() - 60;

            await digitar(wrapper, token({ iat, exp: iat + 3600 }));

            // O ano e o que a data tem de estavel: a hora muda com o fuso de
            // quem roda a suite, e o numero cru nao pode sobrar na tela.
            expect(valorDoClaim(wrapper, 'iat')).toContain('2026');
            expect(valorDoClaim(wrapper, 'iat')).not.toContain(String(iat));
        });

        it('marca o exp que ja passou, so ele', async () => {
            const wrapper = montar();
            const agora = agoraEmSegundos();

            await digitar(wrapper, token({ iat: agora - 60, exp: agora - 1 }));

            expect(selo(wrapper, 'exp')).toBe('EXPIRADO');
            expect(classeDoSelo(wrapper, 'exp')).toContain('text-destructive');
            expect(selo(wrapper, 'iat')).toBe('');
        });

        it('nao marca o exp que ainda vale', async () => {
            const wrapper = montar();
            const agora = agoraEmSegundos();

            await digitar(wrapper, token({ iat: agora - 60, exp: agora + 3600 }));

            expect(selo(wrapper, 'exp')).toBe('');
        });

        it('marca o nbf que ainda nao chegou, como aviso e nao como erro', async () => {
            const wrapper = montar();
            const agora = agoraEmSegundos();

            await digitar(wrapper, token({ nbf: agora + 3600, exp: agora + 7200 }));

            expect(selo(wrapper, 'nbf')).toBe('AINDA_NAO_VALE');
            expect(classeDoSelo(wrapper, 'nbf')).not.toContain('text-destructive');
            expect(selo(wrapper, 'exp')).toBe('');
        });

        it('mostra o valor cru quando ele nao vira data', async () => {
            const wrapper = montar();

            await digitar(wrapper, token({ exp: 'amanha' }));

            expect(valorDoClaim(wrapper, 'exp')).toBe('"amanha"');
            expect(selo(wrapper, 'exp')).toBe('');
        });

        it('nao lista data nenhuma quando o payload nao tem claims de tempo', async () => {
            const wrapper = montar();

            await digitar(wrapper, token({ sub: '42' }));

            expect(wrapper.find('[data-test="jwt-decoder-claims"]').exists()).toBe(false);
        });
    });

    describe('o que nao e um JWT', () => {
        it('diz o que faltou, no lugar dos blocos', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'nada');
            expect(estado(wrapper)).toBe('ESTADO_FORMA');
            expect(blocos(wrapper)).toEqual([]);

            await digitar(wrapper, 'eyJhbGciOiJIUzI1NiJ9.***.YWJj');
            expect(estado(wrapper)).toBe('ESTADO_BASE64');

            await digitar(wrapper, `${segment(CABECALHO)}.b25kYQ.YWJj`);
            expect(estado(wrapper)).toBe('ESTADO_JSON');

            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
        });

        it('volta a mostrar os blocos quando o token fica valido', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'nada');
            expect(estado(wrapper)).toBe('ESTADO_FORMA');

            await digitar(wrapper, token({ sub: '42' }));

            expect(estado(wrapper)).toBe('');
            expect(blocos(wrapper)).toEqual(['header', 'payload']);
        });
    });

    describe('o limite de entrada', () => {
        it('recusa o texto acima do teto', async () => {
            const wrapper = montar(10);
            const linha = token({ sub: '42' });

            await digitar(wrapper, linha);

            expect(estado(wrapper)).toBe(`GRANDE:${linha.length} bytes:10 bytes`);
            expect(blocos(wrapper)).toEqual([]);
        });
    });

    describe('copiar', () => {
        it('leva os dois blocos e a assinatura', async () => {
            const wrapper = montar();

            await digitar(wrapper, token({ sub: '42' }));
            await clicar(wrapper, 'COPIAR');

            expect(clipboard.writeText).toHaveBeenCalledWith(
                [
                    'CABECALHO:',
                    '{\n  "alg": "HS256",\n  "typ": "JWT"\n}',
                    'PAYLOAD:',
                    '{\n  "sub": "42"\n}',
                    'ASSINATURA: BYTES:10 (NAO_VERIFICADA)',
                ].join('\n'),
            );
            expect(rodape(wrapper)).toBe('COPIADO');
        });

        it('leva um bloco so, pelo botao do bloco', async () => {
            const wrapper = montar();

            await digitar(wrapper, token({ sub: '42' }));
            await bloco(wrapper, 'payload').get('[data-test="jwt-decoder-block-copy"]').trigger('click');
            await flushPromises();

            expect(clipboard.writeText).toHaveBeenCalledWith('{\n  "sub": "42"\n}');
            expect(rodape(wrapper)).toBe('PAYLOAD copiado');
        });

        it('nao oferece copiar quando o token nao decodifica', async () => {
            const wrapper = montar();

            await digitar(wrapper, 'nada');

            expect(desabilitado(wrapper, 'COPIAR')).toBe(true);
            expect(wrapper.find('[data-test="jwt-decoder-block-copy"]').exists()).toBe(false);
        });
    });

    describe('exemplo e limpar', () => {
        it('o exemplo da tela e o token do arquivo', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');

            expect(wrapper.find('[data-test="editor"]').text()).toBe(EXEMPLO);
            expect(texto(wrapper, 'header')).toBe('{\n  "alg": "HS256",\n  "typ": "JWT"\n}');
        });

        it('limpar volta ao vazio', async () => {
            const wrapper = montar();

            await clicar(wrapper, 'EXEMPLO');
            await clicar(wrapper, 'LIMPAR');

            expect(wrapper.find('[data-test="editor"]').text()).toBe('');
            expect(blocos(wrapper)).toEqual([]);
            expect(rodape(wrapper)).toBe('VAZIO');
        });
    });
});
