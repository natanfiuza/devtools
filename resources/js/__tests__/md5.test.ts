import { describe, expect, it } from 'vitest';
import { md5, md5Bytes, toHex } from '@/tools/base64-url-hash/lib/md5';

/**
 * O MD5 de casa, contra os vetores do RFC 1321.
 *
 * A Web Crypto nao tem MD5, entao ele e o unico dos quatro hashes que nao vem
 * pronto do navegador - e o unico que precisa de prova. A prova e a do RFC: os
 * sete vetores do apendice A.5, mais o do milhao de `a` que todo mundo cita.
 *
 * Um MD5 que erra por um bit passa em qualquer teste de ida e volta, e falha
 * exatamente aqui. E por isso que este arquivo compara com numeros de fora, e
 * nao com o resultado de uma chamada anterior.
 */

/** Os sete vetores do apendice A.5 do RFC 1321, na ordem em que estao la. */
const VETORES_RFC: readonly [string, string][] = [
    ['', 'd41d8cd98f00b204e9800998ecf8427e'],
    ['a', '0cc175b9c0f1b6a831c399e269772661'],
    ['abc', '900150983cd24fb0d6963f7d28e17f72'],
    ['message digest', 'f96b697d7cb7938d525a2f31aaf161d0'],
    ['abcdefghijklmnopqrstuvwxyz', 'c3fcd3d76192e4007dfb496cca67e13b'],
    [
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
        'd174ab98d277d9f5a5611c2c9f419d9f',
    ],
    [
        '12345678901234567890123456789012345678901234567890123456789012345678901234567890',
        '57edf4a22be3c955ac49da2e2107b67a',
    ],
];

describe('md5', () => {
    it('acerta os sete vetores do RFC 1321', () => {
        for (const [texto, esperado] of VETORES_RFC) {
            expect(md5(texto), JSON.stringify(texto)).toBe(esperado);
        }
    });

    it('atravessa os limites de bloco', () => {
        // Cinquenta e cinco bytes ainda cabem no bloco com o `1` e o
        // comprimento; cinquenta e seis nao, e o texto ganha um bloco a mais. O
        // padding e o lugar classico do erro de um byte.
        expect(md5('a'.repeat(55))).toBe('ef1772b6dff9a122358552954ad0df65');
        expect(md5('a'.repeat(56))).toBe('3b0c8ac703f828b04c6c197006d17218');
        expect(md5('a'.repeat(64))).toBe('014842d480b571495a4a0363793f7367');
    });

    it('acerta o milhao de `a`', () => {
        expect(md5('a'.repeat(1_000_000))).toBe('7707d6ae4e027c70eea2a935c2296f21');
    });

    it('conta o texto acentuado pelos bytes UTF-8', () => {
        // "ação" sao seis bytes, e nao quatro: um MD5 que passasse a string
        // direto para a conta daria outro numero.
        expect(md5('ação')).toBe('ce762a2c8073e0b6ad5d9bc99283af67');
        expect(md5('ação')).not.toBe(md5('acao'));
    });

    it('escreve sempre em minusculo e com os zeros a esquerda', () => {
        const digest = md5('abc');

        expect(digest).toHaveLength(32);
        expect(digest).toBe(digest.toLowerCase());
        // O MD5 de `a` comeca com o byte `0c`: sem o `padStart` ele sairia como
        // um digito so, o digest encolheria para 31 caracteres, e a comparacao
        // com o de fora falharia por um byte.
        expect(md5('a')).toBe('0cc175b9c0f1b6a831c399e269772661');
    });
});

describe('md5Bytes', () => {
    it('devolve os dezesseis bytes do digest', () => {
        const bytes = md5Bytes(new TextEncoder().encode('abc'));

        expect(bytes).toHaveLength(16);
        expect(toHex(bytes)).toBe(md5('abc'));
    });

    it('da o mesmo digest do caminho de texto', () => {
        const texto = 'ação 🚀';

        expect(toHex(md5Bytes(new TextEncoder().encode(texto)))).toBe(md5(texto));
    });
});
