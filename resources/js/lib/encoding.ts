/**
 * Codificacao de texto: Base64 (RFC 4648, nas duas variantes) e URL (RFC 3986,
 * componente e formulario).
 *
 * Mora em `lib/` e nao dentro do diretorio de uma tool porque **duas** usam a
 * mesma conta: o Base64, URL e Hash mostra as duas variantes na tela, e o JWT
 * Decoder le os segmentos de um token, que sao base64url. O que fica no
 * diretorio da tool e o que e so dela - o MD5, os SHA e a composicao da tela.
 *
 * O `btoa` do navegador **nao serve** para nada disto: ele trata a string como
 * Latin-1, entao "ação" viraria lixo (e `btoa` nem existe fora do navegador). O
 * caminho aqui e sempre o mesmo - `TextEncoder` para bytes, a tabela para os
 * digitos, e `TextDecoder` na volta.
 */

/**
 * O que uma conversao devolve.
 *
 * Vale para todas - base64, URL, hash -: ou sai o valor, ou nao sai nada. Nao
 * ha **motivo** na recusa de proposito: quem le a tela quer saber que aquilo
 * nao decodifica, e nao qual caractere da string estava fora do alfabeto. Se um
 * dia um motivo virar util, ele entra aqui.
 */
export type ValueResult = { ok: true; value: string } | { ok: false };

/** A recusa, escrita uma vez para nao repetir o objeto em cada `return`. */
export const REFUSED: ValueResult = { ok: false };

/*
| Base64
*/

const STANDARD =
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
const URL_SAFE =
    'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

/**
 * As duas variantes sao as da norma: a **padrao** (secao 4, `+` e `/`, com
 * padding) e a **URL-safe** (secao 5, `-` e `_`, sem padding) - a que sobrevive
 * dentro de uma query string, de um cookie, de um nome de arquivo ou de um
 * segmento de JWT. A codificacao e feita byte a byte, e nao por `replace` no
 * resultado, porque a troca de alfabeto depois de pronta seria mais uma conta
 * para conferir.
 */
export function encodeBase64(text: string): string {
    return toBase64(new TextEncoder().encode(text), STANDARD, true);
}

export function encodeBase64Url(text: string): string {
    return toBase64(new TextEncoder().encode(text), URL_SAFE, false);
}

/**
 * O texto que a string base64 guarda.
 *
 * Na **decodificacao** as duas variantes entram no mesmo alfabeto: a string
 * colada e aceita com `+`/`/` ou com `-`/`_`, e o padding e opcional. Quem cola
 * base64 no meio de um programa raramente sabe de qual das duas veio.
 *
 * A recusa e uma so - alfabeto, comprimento ou bytes que nao sao UTF-8 -,
 * porque as tres querem dizer a mesma coisa para quem esta na tela: isto nao e
 * o base64 de um texto.
 */
export function decodeBase64(value: string): ValueResult {
    const digits = decodeBase64Bytes(value);

    if (digits === null) {
        return REFUSED;
    }

    try {
        // `fatal` e o ponto: bytes que nao formam UTF-8 **nao** viram U+FFFD em
        // silencio, viram recusa.
        return {
            ok: true,
            value: new TextDecoder('utf-8', { fatal: true }).decode(digits),
        };
    } catch {
        return REFUSED;
    }
}

/**
 * Os bytes que a string base64 guarda, ou `null` quando ela nao e base64.
 *
 * A porta dos fundos do `decodeBase64` para quem quer os bytes **sem** virar
 * texto: a assinatura de um JWT, por exemplo, e binaria e nao tem por que
 * passar pelo `TextDecoder` - nem sobreviveria a ele.
 */
export function decodeBase64Bytes(value: string): Uint8Array | null {
    // O base64 do mundo real chega quebrado em linhas (e-mail, chave PEM), e o
    // espaco em branco nao faz parte do alfabeto: tirar e o que a pessoa espera.
    return decodeDigits(value.replace(/\s+/g, ''));
}

/** Bytes para base64, tres bytes por quatro caracteres. */
function toBase64(
    bytes: Uint8Array,
    alphabet: string,
    padding: boolean,
): string {
    // O `??` e so para o compilador: o indice e mascarado em 6 bits e as duas
    // tabelas tem 64 caracteres, entao nunca falta.
    const digit = (index: number): string => alphabet[index] ?? '';
    let out = '';

    for (let index = 0; index < bytes.length; index += 3) {
        const remaining = bytes.length - index;
        const first = bytes[index] ?? 0;
        const second = bytes[index + 1] ?? 0;
        const third = bytes[index + 2] ?? 0;
        const chunk = (first << 16) | (second << 8) | third;

        out += digit((chunk >> 18) & 63);
        out += digit((chunk >> 12) & 63);
        out += remaining > 1 ? digit((chunk >> 6) & 63) : padding ? '=' : '';
        out += remaining > 2 ? digit(chunk & 63) : padding ? '=' : '';
    }

    return out;
}

/**
 * Base64 para bytes, ou `null` quando a string nao e base64.
 *
 * O tamanho do destino sai do tamanho da entrada (cada 4 caracteres valem 3
 * bytes), e nao de um `push` por byte: com o teto de entrada da tool sao alguns
 * milhoes de bytes, e um array que cresce um a um custa caro a toa.
 */
function decodeDigits(clean: string): Uint8Array | null {
    const out = new Uint8Array(Math.ceil((clean.length * 3) / 4));
    let written = 0;
    let buffer = 0;
    let bits = 0;
    let padded = false;

    for (const char of clean) {
        if (char === '=') {
            padded = true;

            continue;
        }

        // Os 62 primeiros digitos sao os mesmos nas duas variantes, entao a
        // tabela padrao responde por eles; `-` e `_` so existem na outra.
        const standard = STANDARD.indexOf(char);
        const digit = standard >= 0 ? standard : URL_SAFE.indexOf(char);

        if (digit < 0 || padded) {
            // Fora do alfabeto das duas variantes, ou dado depois do `=`.
            return null;
        }

        buffer = (buffer << 6) | digit;
        bits += 6;

        if (bits >= 8) {
            bits -= 8;
            out[written] = (buffer >> bits) & 0xff;
            written += 1;
        }
    }

    // Sobraram 6 bits: e o comprimento impossivel de base64 (um caractere
    // sozinho no fim, que nao fecha nem um byte).
    if (bits === 6) {
        return null;
    }

    return out.subarray(0, written);
}

/*
| URL
*/

/**
 * As duas variantes que a web usa.
 *
 * Sao coisas diferentes, e a confusao entre elas e o motivo de a tool existir:
 *
 * - **Componente** (`encodeURIComponent`): o que se coloca dentro de um pedaco
 *   de URL - valor de query, segmento de caminho. Codifica tudo o que nao e
 *   alfanumerico nem `-_.!~*'()`, inclusive `/`, `?`, `&` e `=`.
 * - **Formulario** (`application/x-www-form-urlencoded`): o que um `<form>` com
 *   `GET` manda. Igual ao componente, exceto que o espaco vira `+` - e o `+`,
 *   na volta, e espaco de novo.
 */

export type UrlVariant = 'component' | 'form';

export const URL_VARIANTS: readonly UrlVariant[] = ['component', 'form'];

/** O que o componente deixa passar, alem de `A-Za-z0-9`. */
const COMPONENT_SAFE = "-_.!~*'()";

/**
 * O que o formulario deixa passar.
 *
 * `*`, `!`, `'`, `(` e `)` sao seguros pela RFC 3986, mas a lista do
 * `x-www-form-urlencoded` e mais estreita e nao os inclui: um `<form>` manda
 * `*` como `%2A`. Manter as duas listas separadas e o que faz a saida bater com
 * o que o servidor recebe.
 */
const FORM_SAFE = '-_.~';

export function encodeUrl(text: string, variant: UrlVariant): string {
    return percentEncode(text, variant === 'form');
}

/**
 * O texto que a string codificada guarda.
 *
 * Na volta as duas variantes se juntam de novo: `%20` e `+` os dois viram
 * espaco, que e o que o formulario faz. A recusa e o `%` que nao abre um byte
 * valido (`%zz`, `%A`) ou um byte que nao e UTF-8.
 */
export function decodeUrl(value: string, variant: UrlVariant): ValueResult {
    // No formulario o `+` e espaco. No componente ele **nao** e - la o `+` e o
    // `+` -, e trocar nos dois estragaria um `C++` codificado como componente.
    const source = variant === 'form' ? value.replace(/\+/g, ' ') : value;

    try {
        return { ok: true, value: decodeURIComponent(source) };
    } catch {
        // `URIError`: sequencia de escape malformada ou byte fora do UTF-8.
        return REFUSED;
    }
}

/**
 * A codificacao e feita **byte a byte**, e nao por `encodeURIComponent` sobre a
 * string: a funcao do navegador lanca `URIError` num par solto (metade de um
 * emoji) e a tool nao teria o que mostrar. Passando por `TextEncoder`, um par
 * solto vira o U+FFFD que o proprio padrao manda - e nao um erro na tela.
 */
function percentEncode(text: string, form: boolean): string {
    const safe = form ? FORM_SAFE : COMPONENT_SAFE;
    let out = '';

    for (const byte of new TextEncoder().encode(text)) {
        if (isUnreserved(byte) || safe.includes(String.fromCharCode(byte))) {
            out += String.fromCharCode(byte);

            continue;
        }

        // O `+` e o unico caso em que a variante muda o caractere, e nao so a
        // decisao: no formulario ele e o espaco.
        out +=
            form && byte === 0x20
                ? '+'
                : `%${byte.toString(16).toUpperCase().padStart(2, '0')}`;
    }

    return out;
}

function isUnreserved(byte: number): boolean {
    return (
        (byte >= 0x41 && byte <= 0x5a) || // A-Z
        (byte >= 0x61 && byte <= 0x7a) || // a-z
        (byte >= 0x30 && byte <= 0x39) // 0-9
    );
}
