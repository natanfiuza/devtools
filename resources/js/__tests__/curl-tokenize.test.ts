import { describe, expect, it } from 'vitest';
import { tokenizeShell } from '@/tools/curl-to-code/lib/tokenize';

/**
 * O tokenizador do subconjunto de shell.
 *
 * O que se cobra e o contrato que o parser usa: aspas simples literais, aspas
 * duplas com os escapes do shell, barra fora de aspas, continuacao de linha e
 * os tres problemas que recusam o comando (aspas sem par e barra no fim).
 */

describe('tokenizeShell', () => {
    it('separa por espacos, tabulacoes e quebras de linha', () => {
        expect(tokenizeShell('curl\t-X  POST\nhttps://x.test').tokens).toEqual([
            'curl',
            '-X',
            'POST',
            'https://x.test',
        ]);
    });

    it('mantem aspas simples literais', () => {
        expect(tokenizeShell("curl 'https://x.test/a b?x=$1'").tokens).toEqual([
            'curl',
            'https://x.test/a b?x=$1',
        ]);
    });

    it('interpreta os escapes das aspas duplas', () => {
        expect(
            tokenizeShell('curl "a\\"b" "c\\$d" "e\\\\f" "g\\`h"').tokens,
        ).toEqual(['curl', 'a"b', 'c$d', 'e\\f', 'g`h']);
    });

    it('deixa a barra literal quando o escape nao e do shell', () => {
        expect(tokenizeShell('curl "g\\nh"').tokens).toEqual(['curl', 'g\\nh']);
    });

    it('escapa o proximo caractere fora de aspas', () => {
        expect(tokenizeShell('curl https://x.test/a\\ b').tokens).toEqual([
            'curl',
            'https://x.test/a b',
        ]);
    });

    it('junta a linha continuada com barra', () => {
        expect(
            tokenizeShell(
                'curl -X POST \\\n  -H "Accept: a"' + ' \\\r\n  https://x.test',
            ).tokens,
        ).toEqual(['curl', '-X', 'POST', '-H', 'Accept: a', 'https://x.test']);
    });

    it('mantem o valor colado a uma opcao num token so', () => {
        expect(
            tokenizeShell("curl -XPOST -H'Accept: a b' -d'{\"a\":1}'").tokens,
        ).toEqual(['curl', '-XPOST', '-HAccept: a b', '-d{"a":1}']);
    });

    it('devolve lista vazia para entrada vazia', () => {
        expect(tokenizeShell('   ').tokens).toEqual([]);
    });

    it('aponta aspas simples sem par', () => {
        const result = tokenizeShell("curl 'https://x.test");

        expect(result.issues).toEqual([
            { code: 'unterminated_single', position: 5 },
        ]);
    });

    it('aponta aspas duplas sem par', () => {
        const result = tokenizeShell('curl "https://x.test');

        expect(result.issues).toEqual([
            { code: 'unterminated_double', position: 5 },
        ]);
    });

    it('aponta barra no fim da entrada', () => {
        const result = tokenizeShell('curl https://x.test\\');

        expect(result.issues).toEqual([
            { code: 'dangling_escape', position: 19 },
        ]);
    });
});
