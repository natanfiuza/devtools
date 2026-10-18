import { describe, expect, it } from 'vitest';
import {
    HASH_ALGORITHMS,
    hashAll,
    hasWebCrypto,
} from '@/tools/base64-url-hash/lib/hash';

/**
 * Os quatro digests da aba Hash.
 *
 * Tres deles sao o `crypto.subtle` do navegador - testar a implementacao do
 * navegador nao e trabalho -, e o que se cobra aqui e o **contrato**: a ordem
 * fixa, o hex minusculo, o formato do resultado, e o que acontece quando a
 * pagina nao esta em contexto seguro e o `subtle` nao existe.
 *
 * Os valores sao os de fora - FIPS 180-4 e o RFC 1321 -, escritos por inteiro:
 * um digest trocado e exatamente o que um teste de ida e volta nao pega.
 */

const ESPERADO: Record<string, string> = {
    md5: '900150983cd24fb0d6963f7d28e17f72',
    sha1: 'a9993e364706816aba3e25717850c26c9cd0d89d',
    sha256: 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
    sha512:
        'ddaf35a193617abacc417349ae20413112e6fa4e89a97ea20a9eeee64b55d39a' +
        '2192992a274fc1a836ba3c23a3feebbd454d4423643ce80e2a9ac94fa54ca49f',
};

/** Os comprimentos em hexadecimal de cada algoritmo. */
const TAMANHO: Record<string, number> = {
    md5: 32,
    sha1: 40,
    sha256: 64,
    sha512: 128,
};

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

describe('hashAll', () => {
    it('devolve os quatro na ordem da tela', async () => {
        expect([...HASH_ALGORITHMS]).toEqual([
            'md5',
            'sha1',
            'sha256',
            'sha512',
        ]);

        const linhas = await hashAll('abc');

        expect(linhas.map((linha) => linha.algorithm)).toEqual([
            'md5',
            'sha1',
            'sha256',
            'sha512',
        ]);
    });

    it('acerta os quatro valores de `abc`', async () => {
        for (const linha of await hashAll('abc')) {
            expect(linha.hex, linha.algorithm).toBe(ESPERADO[linha.algorithm]);
        }
    });

    it('acerta o texto vazio', async () => {
        // O caso em que o padding do MD5 e do SHA se encontram: nenhum byte de
        // entrada, e mesmo assim o bloco inteiro.
        const linhas = await hashAll('');

        expect(linhas.find((linha) => linha.algorithm === 'md5')?.hex).toBe(
            'd41d8cd98f00b204e9800998ecf8427e',
        );
        expect(linhas.find((linha) => linha.algorithm === 'sha256')?.hex).toBe(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        );
    });

    it('escreve tudo em hex minusculo, no comprimento de cada algoritmo', async () => {
        for (const linha of await hashAll('ação 🚀')) {
            expect(linha.hex, linha.algorithm).toMatch(/^[0-9a-f]+$/);
            expect(linha.hex, linha.algorithm).toHaveLength(
                TAMANHO[linha.algorithm] ?? 0,
            );
        }
    });

    it('conta o texto acentuado pelos bytes UTF-8', async () => {
        const linhas = await hashAll('ação');

        expect(linhas.find((linha) => linha.algorithm === 'md5')?.hex).toBe(
            'ce762a2c8073e0b6ad5d9bc99283af67',
        );
        expect(linhas.find((linha) => linha.algorithm === 'sha256')?.hex).toBe(
            '0664077f33cc3ebbaa4bbdacac0eb70e740983080f01dce29929e73b7785a7ad',
        );
    });
});

describe('sem Web Crypto', () => {
    it('reconhece a pagina sem `subtle`', () => {
        expect(hasWebCrypto()).toBe(true);

        const restaurar = semWebCrypto();

        try {
            expect(hasWebCrypto()).toBe(false);
        } finally {
            restaurar();
        }

        expect(hasWebCrypto()).toBe(true);
    });

    it('deixa as tres linhas sem valor e o MD5 de pe', async () => {
        // Fora de contexto seguro (http numa rede interna) o `subtle` nao
        // existe. O MD5 e a conta da casa e continua saindo; as outras tres
        // ficam sem valor, e a tela avisa - em vez de um espaco em branco sem
        // explicacao.
        const restaurar = semWebCrypto();

        try {
            const linhas = await hashAll('abc');

            expect(linhas.map((linha) => linha.hex)).toEqual([
                ESPERADO.md5,
                null,
                null,
                null,
            ]);
        } finally {
            restaurar();
        }
    });
});
