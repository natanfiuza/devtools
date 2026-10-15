/**
 * Os identificadores: UUID (v4 e v7) e ULID.
 *
 * Os tres sao bytes aleatorios vestidos de texto, e a diferenca esta no que vem
 * antes deles: o v4 nao guarda nada (e so aleatorio), o v7 e o ULID comecam com
 * o instante em que nasceram - por isso dois ids gerados em sequencia saem
 * **em ordem** se ordenados como texto.
 *
 * O v4 usa a primitiva do navegador quando ela existe (`crypto.randomUUID`).
 * O v7 e o ULID sao contas proprias: a biblioteca nao teria o que este arquivo
 * tem de mais util - a volta, `instantOfId`, que le o instante de um id colado.
 * E os tres passam pelo mesmo `crypto.getRandomValues`, que e a unica fonte de
 * aleatoriedade que existe em qualquer contexto (o `randomUUID` so aparece em
 * pagina segura, e por isso tem um caminho de reserva logo abaixo).
 *
 * Referencias: RFC 9562 (UUID) e a especificacao do ULID.
 */

/** Os tres tipos que a tela oferece, na ordem em que aparecem. */
export type IdKind = 'uuid_v4' | 'uuid_v7' | 'ulid';

export const ID_KINDS: readonly IdKind[] = ['uuid_v4', 'uuid_v7', 'ulid'];

/** Quanto a tela gera de uma vez. */
export const ID_QUANTITIES: readonly number[] = [1, 5, 10];

/**
 * O tipo e a quantidade com que a lista abre.
 *
 * Cinco, e nao um: quem chega aqui quer uma lista para colar numa fixture, e
 * um id so e o caso de quem clica no botao de novo.
 */
export const DEFAULT_ID_KIND: IdKind = 'uuid_v4';
export const DEFAULT_ID_QUANTITY = 5;

/** O alfabeto do ULID: Crockford base32, sem as letras que se confundem. */
const ULID_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/** Um instante lido de volta de um id. */
export interface IdInstant {
    kind: 'uuid_v7' | 'ulid';
    ms: number;
}

const UUID_V7 = /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

/**
 * O ULID em maiusculas.
 *
 * A checagem e feita no texto ja em caixa alta, e nao com a bandeira `i`: em
 * regex, `i` tambem aceita caracteres que so *parecem* ASCII (o Kelvin, o "s"
 * longo), e um deles passaria por id valido.
 */
const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/;

function randomBytes(length: number): Uint8Array {
    return crypto.getRandomValues(new Uint8Array(length));
}

function toHex(bytes: Uint8Array): string {
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/**
 * Os 16 bytes na forma canonica: 8-4-4-4-12, em minusculas.
 *
 * So a forma: quem poe a versao e a variante no lugar e o `stamp`. A separacao
 * existe porque o teste dos bytes fixos so consegue prever o texto se a
 * formatacao nao mexer no que recebe.
 */
export function formatUuid(bytes: Uint8Array): string {
    const hex = toHex(bytes);

    return [
        hex.slice(0, 8),
        hex.slice(8, 12),
        hex.slice(12, 16),
        hex.slice(16, 20),
        hex.slice(20),
    ].join('-');
}

/** Poe a versao e a variante nos bits que a RFC reserva para elas. */
function stamp(bytes: Uint8Array, version: number): Uint8Array {
    const copy = Uint8Array.from(bytes);

    // Byte 6, nibble de cima: a versao. Byte 8, os dois bits de cima: a
    // variante da RFC - "10", que e o que faz o hex começar em 8, 9, a ou b.
    copy[6] = ((copy[6] ?? 0) & 0x0f) | (version << 4);
    copy[8] = ((copy[8] ?? 0) & 0x3f) | 0x80;

    return copy;
}

/** Os primeiros 48 bits de um UUID v7 ou de um ULID: os milissegundos. */
function writeMilliseconds(bytes: Uint8Array, ms: number): void {
    // Sem `BigInt`: o instante cabe exato num `number` (2^53 > 2^48), e a
    // conta quebra nos mesmos pedacos que os bytes.
    const high = Math.floor(ms / 2 ** 32);
    const low = ms % 2 ** 32;

    bytes[0] = (high >> 8) & 0xff;
    bytes[1] = high & 0xff;
    bytes[2] = (low >>> 24) & 0xff;
    bytes[3] = (low >>> 16) & 0xff;
    bytes[4] = (low >>> 8) & 0xff;
    bytes[5] = low & 0xff;
}

export function uuidV4(): string {
    const primitive = typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : null;

    return primitive ?? formatUuid(stamp(randomBytes(16), 4));
}

/**
 * O UUID v7: o instante nos primeiros 48 bits e o resto aleatorio (RFC 9562).
 *
 * Os 16 bytes nascem aleatorios e so os seis primeiros sao sobrescritos: e
 * exatamente o desenho da RFC - o `rand_a` e o `rand_b` ficam com os bits que
 * sobraram.
 */
export function uuidV7(now: number): string {
    const bytes = randomBytes(16);

    writeMilliseconds(bytes, Math.floor(now));

    return formatUuid(stamp(bytes, 7));
}

/** O instante em 10 caracteres de Crockford base32 (48 bits em 50). */
function encodeTime(ms: number): string {
    const chars: string[] = [];
    let rest = Math.floor(ms);

    for (let index = 0; index < 10; index += 1) {
        chars.unshift(ULID_ALPHABET[rest % 32] ?? '0');
        rest = Math.floor(rest / 32);
    }

    return chars.join('');
}

/** A parte aleatoria: 10 bytes (80 bits) em 16 caracteres de 5 bits. */
function encodeRandom(bytes: Uint8Array): string {
    let buffer = 0;
    let bits = 0;
    let out = '';

    for (const byte of bytes) {
        buffer = (buffer << 8) | byte;
        bits += 8;

        while (bits >= 5) {
            bits -= 5;
            out += ULID_ALPHABET[(buffer >> bits) & 31] ?? '0';
        }
    }

    return out;
}

export function ulid(now: number): string {
    return encodeTime(Math.floor(now)) + encodeRandom(randomBytes(10));
}

/** Gera a lista que a tela mostra: mesma conta, `quantity` vezes. */
export function generateIds(kind: IdKind, quantity: number, now: number): string[] {
    return Array.from({ length: quantity }, () => {
        if (kind === 'uuid_v4') {
            return uuidV4();
        }

        return kind === 'uuid_v7' ? uuidV7(now) : ulid(now);
    });
}

/** Os 10 caracteres de tempo de um ULID ja em caixa alta, em milissegundos. */
function decodeTime(value: string): number {
    let ms = 0;

    for (const char of value.slice(0, 10)) {
        ms = ms * 32 + ULID_ALPHABET.indexOf(char);
    }

    return ms;
}

/**
 * O instante que um id colado guarda, ou `null` quando ele nao guarda nenhum.
 *
 * Vale para os dois que carregam tempo. O v4 nao esta aqui de proposito: ele e
 * aleatorio inteiro, e nao ha instante para ler.
 *
 * O ULID guarda 48 bits em 10 caracteres (50 bits), entao um id cujo primeiro
 * caractere passe de `7` esta fora da faixa que a especificacao permite - e
 * devolver a data que aquilo daria seria inventar.
 */
export function instantOfId(value: string): IdInstant | null {
    const text = value.trim();
    const lower = text.toLowerCase();
    const upper = text.toUpperCase();

    if (UUID_V7.test(lower)) {
        // Os 12 primeiros digitos hex, sem o hifen do meio: os 48 bits do tempo.
        const time = lower.slice(0, 8) + lower.slice(9, 13);

        return { kind: 'uuid_v7', ms: parseInt(time, 16) };
    }

    if (ULID.test(upper)) {
        const ms = decodeTime(upper);

        return ms < 2 ** 48 ? { kind: 'ulid', ms } : null;
    }

    return null;
}
