import { md5Bytes, toHex } from './md5';

/**
 * Os quatro digests da tool, de uma vez.
 *
 * A aba Hash mostra os quatro juntos porque a pergunta que traz alguem aqui
 * ("qual e o hash disto?") quase nunca diz **qual** algoritmo - quem cola um
 * texto num conferidor de checksum quer conferir, e nao escolher. Uma lista de
 * quatro linhas responde as quatro perguntas de uma vez.
 *
 * O MD5 vem do modulo da casa, porque a Web Crypto nao o tem; os outros tres sao
 * `crypto.subtle.digest`, que e a implementacao do navegador. O `subtle` so
 * existe em **contexto seguro**: fora dele (http numa rede interna) as tres
 * linhas ficam sem valor (`hex: null`) e a tela avisa, em vez de mostrar um
 * espaco em branco sem explicacao.
 */

export const HASH_ALGORITHMS = ['md5', 'sha1', 'sha256', 'sha512'] as const;

export type HashAlgorithm = (typeof HASH_ALGORITHMS)[number];

export interface DigestLine {
    algorithm: HashAlgorithm;
    /** O digest em hexadecimal minusculo, ou `null` quando o navegador nao ajuda. */
    hex: string | null;
}

/** Os nomes que a Web Crypto usa para os tres que ela tem. */
const SUBTLE_NAMES: Record<Exclude<HashAlgorithm, 'md5'>, string> = {
    sha1: 'SHA-1',
    sha256: 'SHA-256',
    sha512: 'SHA-512',
};

/** Ha Web Crypto nesta pagina? A pergunta e pela funcao, e nao pelo objeto. */
export function hasWebCrypto(): boolean {
    return (
        typeof crypto !== 'undefined' &&
        typeof crypto.subtle?.digest === 'function'
    );
}

export async function hashAll(text: string): Promise<DigestLine[]> {
    const bytes = new TextEncoder().encode(text);

    // `Promise.all` guarda a ordem da lista, e e ela que a tela desenha: MD5,
    // SHA-1, SHA-256, SHA-512, sempre nessa ordem.
    return Promise.all(
        HASH_ALGORITHMS.map(async (algorithm) => ({
            algorithm,
            hex: await digestOf(algorithm, bytes),
        })),
    );
}

/**
 * O `Uint8Array<ArrayBuffer>` do parametro nao e preciosismo: o `digest` exige
 * um `BufferSource`, e o `Uint8Array` sem parametro e um `ArrayBufferLike`, que
 * pode ser um `SharedArrayBuffer` - e esse a Web Crypto nao aceita.
 */
async function digestOf(
    algorithm: HashAlgorithm,
    bytes: Uint8Array<ArrayBuffer>,
): Promise<string | null> {
    if (algorithm === 'md5') {
        return toHex(md5Bytes(bytes));
    }

    if (!hasWebCrypto()) {
        return null;
    }

    try {
        return toHex(
            new Uint8Array(
                await crypto.subtle.digest(SUBTLE_NAMES[algorithm], bytes),
            ),
        );
    } catch {
        // Algoritmo recusado pela plataforma: a linha fica sem valor, e as
        // outras tres continuam de pe.
        return null;
    }
}
