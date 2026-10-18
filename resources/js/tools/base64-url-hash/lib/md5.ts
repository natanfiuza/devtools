/**
 * MD5, o do RFC 1321.
 *
 * A Web Crypto **nao tem MD5** - ela so expoe as funcoes que ainda servem para
 * alguma coisa, e o MD5 nao serve. Mas ele continua sendo o que aparece em
 * `Content-MD5`, em ETag de bucket S3 e em checksum de CDN antigo, entao a tool
 * precisa dele.
 *
 * MD5 aqui e **checksum, nunca assinatura**: quem precisa de resistencia a
 * adulteracao usa SHA-256. A tela diz isso no rodape da aba Hash.
 *
 * A implementacao segue o RFC 1321 passo a passo, e o teste a confere contra os
 * sete vetores do apendice A.5 - o do RFC - e contra os de um milhao de `a`.
 */

/** O deslocamento de cada uma das 64 voltas, na ordem do RFC. */
const SHIFTS = [
    7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 7, 12, 17, 22, 5, 9, 14, 20, 5,
    9, 14, 20, 5, 9, 14, 20, 5, 9, 14, 20, 4, 11, 16, 23, 4, 11, 16, 23, 4, 11,
    16, 23, 4, 11, 16, 23, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10, 15, 21, 6, 10,
    15, 21,
];

/**
 * A tabela `T`: `floor(abs(sin(i + 1)) * 2^32)`, e nao os 64 numeros digitados.
 *
 * A formula **e** a definicao do RFC (secao 3.4), e escrever os numeros a mao
 * seria escrever o resultado dela - com a chance de um digito trocado que
 * nenhum teste curto pegaria. O `>>> 0` traz cada um para 32 bits sem sinal.
 */
const T = Array.from(
    { length: 64 },
    (_, index) => Math.floor(Math.abs(Math.sin(index + 1)) * 2 ** 32) >>> 0,
);

/** O digest do texto, em hexadecimal minusculo: 32 caracteres. */
export function md5(text: string): string {
    return toHex(md5Bytes(new TextEncoder().encode(text)));
}

/** Os 16 bytes do digest, na ordem em que o RFC os escreve. */
export function md5Bytes(bytes: Uint8Array): Uint8Array {
    const padded = pad(bytes);

    let a = 0x67452301;
    let b = 0xefcdab89;
    let c = 0x98badcfe;
    let d = 0x10325476;

    for (let offset = 0; offset < padded.length; offset += 64) {
        const view = new DataView(
            padded.buffer,
            padded.byteOffset + offset,
            64,
        );
        // As dezesseis palavras do bloco sao lidas **little-endian** - e a
        // diferenca de MD5 para SHA, que le os mesmos bytes ao contrario.
        const words = Array.from({ length: 16 }, (_, index) =>
            view.getUint32(index * 4, true),
        );

        // Os acumuladores do bloco: o estado so muda depois das 64 voltas.
        let [aa, bb, cc, dd] = [a, b, c, d];

        for (let step = 0; step < 64; step += 1) {
            const { f, index } = mix(bb, cc, dd, step);
            const sum = (aa + f + at(T, step) + at(words, index)) >>> 0;

            // A volta do RFC: b recebe b + rotacao, e as outras tres giram.
            [aa, dd, cc, bb] = [
                dd,
                cc,
                bb,
                (bb + rotateLeft(sum, at(SHIFTS, step))) >>> 0,
            ];
        }

        a = (a + aa) >>> 0;
        b = (b + bb) >>> 0;
        c = (c + cc) >>> 0;
        d = (d + dd) >>> 0;
    }

    return wordsToBytes([a, b, c, d]);
}

/**
 * As quatro funcoes auxiliares (`F`, `G`, `H`, `I`) e a palavra que cada uma
 * usa.
 *
 * Elas valem dezesseis voltas cada, e o indice da palavra muda de uma para a
 * outra: direto em `F`, multiplicado em `G` e `H`, e de tras para frente em
 * `I`.
 */
function mix(
    b: number,
    c: number,
    d: number,
    step: number,
): { f: number; index: number } {
    if (step < 16) {
        return { f: (b & c) | (~b & d), index: step };
    }

    if (step < 32) {
        return { f: (d & b) | (~d & c), index: (5 * step + 1) % 16 };
    }

    if (step < 48) {
        return { f: b ^ c ^ d, index: (3 * step + 5) % 16 };
    }

    return { f: c ^ (b | ~d), index: (7 * step) % 16 };
}

function rotateLeft(value: number, shift: number): number {
    return ((value << shift) | (value >>> (32 - shift))) >>> 0;
}

/**
 * `T`, `SHIFTS` e as palavras do bloco, num acesso so.
 *
 * O `?? 0` existe para o `noUncheckedIndexedAccess` do projeto, e nao para o
 * caso real: `step` vai de 0 a 63 e `index` de 0 a 15, os dois dentro das
 * tabelas. Sem o ajudante, o mesmo `??` apareceria tres vezes dentro da volta.
 */
function at(table: readonly number[], index: number): number {
    return table[index] ?? 0;
}

/**
 * O texto com o `1` de fim e o comprimento, ate fechar um multiplo de 64.
 *
 * O comprimento entra em **bits**, little-endian, nos ultimos oito bytes. Com
 * os dois primeiros campos de 32 bits sozinhos isso ja cobre 512 MB - muito
 * acima do teto da tool -, entao o par de cima vai zerado e a conta e exata.
 */
function pad(bytes: Uint8Array): Uint8Array {
    const total = Math.ceil((bytes.length + 9) / 64) * 64;
    const out = new Uint8Array(total);

    out.set(bytes);
    out[bytes.length] = 0x80;

    const bits = bytes.length * 8;
    const view = new DataView(out.buffer);

    view.setUint32(total - 8, bits >>> 0, true);
    view.setUint32(total - 4, Math.floor(bits / 2 ** 32), true);

    return out;
}

function wordsToBytes(words: number[]): Uint8Array {
    const out = new Uint8Array(16);
    const view = new DataView(out.buffer);

    words.forEach((word, index) => view.setUint32(index * 4, word, true));

    return out;
}

/** Bytes em hexadecimal minusculo - o mesmo formato das quatro linhas da aba Hash. */
export function toHex(bytes: Uint8Array): string {
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join(
        '',
    );
}
