import { describe, expect, it } from 'vitest';
import { decodeBase64, encodeBase64, encodeBase64Url } from '@/lib/encoding';

/**
 * O Base64 do RFC 4648, nas duas variantes.
 *
 * O que se cobra aqui e o que a norma promete e o `btoa` **nao** entrega: UTF-8
 * de verdade (o `btoa` trata a string como Latin-1, e "ação" viraria lixo) e o
 * alfabeto URL-safe sem padding.
 *
 * Os valores curtos estao escritos por inteiro - `YQ==`, `Pz8_` -, e sao
 * conferiveis a mao. Os longos passam pela ida e volta.
 */

/** O texto de um resultado que deu certo; o teste que espera recusa usa `recusa`. */
function texto(result: ReturnType<typeof decodeBase64>): string {
    if (!result.ok) {
        throw new Error('esperava um texto decodificado, veio recusa');
    }

    return result.value;
}

describe('encodeBase64', () => {
    it('codifica o ASCII sem sobra', () => {
        expect(encodeBase64('abc')).toBe('YWJj');
        expect(encodeBase64('')).toBe('');
    });

    it('fecha o grupo que falta com o padding da norma', () => {
        // Um byte sobra dois `=`; dois bytes, um.
        expect(encodeBase64('a')).toBe('YQ==');
        expect(encodeBase64('ab')).toBe('YWI=');
    });

    it('codifica o texto acentuado em UTF-8', () => {
        // "ação" em UTF-8 sao seis bytes (61 C3 A7 C3 A3 6F), e nao quatro: e
        // exatamente aqui que o `btoa` cru erraria.
        expect(encodeBase64('ação')).toBe('YcOnw6Nv');
        expect(encodeBase64('ç')).toBe('w6c=');
    });

    it('usa o `+` e a `/` do alfabeto padrao', () => {
        expect(encodeBase64('zz>')).toBe('eno+');
        expect(encodeBase64('???')).toBe('Pz8/');
    });
});

describe('encodeBase64Url', () => {
    it('troca `+` e `/` por `-` e `_`', () => {
        expect(encodeBase64Url('zz>')).toBe('eno-');
        expect(encodeBase64Url('???')).toBe('Pz8_');
    });

    it('nao escreve padding nenhum', () => {
        // A variante da secao 5 da norma e a que sobrevive dentro de uma query
        // string, de um cookie ou de um nome de arquivo - e ali o `=` atrapalha.
        expect(encodeBase64Url('a')).toBe('YQ');
        expect(encodeBase64Url('ab')).toBe('YWI');
        expect(encodeBase64Url('abc')).toBe('YWJj');
    });
});

describe('decodeBase64', () => {
    it('volta ao texto de origem', () => {
        expect(texto(decodeBase64('YWJj'))).toBe('abc');
        expect(texto(decodeBase64('YcOnw6Nv'))).toBe('ação');
        expect(texto(decodeBase64(''))).toBe('');
    });

    it('aceita o padding e tambem a falta dele', () => {
        expect(texto(decodeBase64('YQ=='))).toBe('a');
        expect(texto(decodeBase64('YQ'))).toBe('a');
        expect(texto(decodeBase64('YWI='))).toBe('ab');
        expect(texto(decodeBase64('YWI'))).toBe('ab');
    });

    it('le as duas variantes sem perguntar qual', () => {
        // Quem cola base64 no meio de um programa raramente sabe de qual das
        // duas veio, entao as duas entram no mesmo alfabeto.
        expect(texto(decodeBase64('Pz8/'))).toBe('???');
        expect(texto(decodeBase64('Pz8_'))).toBe('???');
        expect(texto(decodeBase64('eno+'))).toBe('zz>');
        expect(texto(decodeBase64('eno-'))).toBe('zz>');
    });

    it('ignora o espaco em branco de quem chega quebrado em linhas', () => {
        // E-mail e chave PEM quebram base64 a cada 76 colunas, e o espaco nao
        // faz parte do alfabeto: tirar e o que a pessoa espera.
        expect(texto(decodeBase64('YWJj\n'))).toBe('abc');
        expect(texto(decodeBase64('YW\nJj'))).toBe('abc');
        expect(texto(decodeBase64('  Y W\tJ j  '))).toBe('abc');
    });

    it('recusa a string com um caractere solto no fim', () => {
        // Seis bits sobrando nao fecham um byte, e nao existe texto para eles:
        // e o comprimento impossivel de base64.
        expect(decodeBase64('a')).toEqual({ ok: false });
        expect(decodeBase64('YWJjY')).toEqual({ ok: false });
    });

    it('recusa o que esta fora do alfabeto', () => {
        expect(decodeBase64('!!!!')).toEqual({ ok: false });
        expect(decodeBase64('YWJj-')).toEqual({ ok: false });
        expect(decodeBase64('YWJ$')).toEqual({ ok: false });
    });

    it('recusa o dado que vem depois do padding', () => {
        expect(decodeBase64('YQ==YQ==')).toEqual({ ok: false });
    });

    it('recusa os bytes que nao sao UTF-8', () => {
        // FF FF F0 e base64 legitimo - os tres bytes sao validos como base64 -,
        // mas nao formam texto nenhum. Sem o `fatal` do `TextDecoder` isto
        // viraria U+FFFD em silencio, e a tela mentiria.
        expect(decodeBase64('//8=')).toEqual({ ok: false });
        expect(decodeBase64('/w==')).toEqual({ ok: false });
    });

    it('faz a ida e a volta com o que o `btoa` nao aguenta', () => {
        const original = 'olá, 🚀 e ação\nsegunda linha\ttab';

        expect(texto(decodeBase64(encodeBase64(original)))).toBe(original);
        expect(texto(decodeBase64(encodeBase64Url(original)))).toBe(original);
    });
});
