import {
    decodeBase64,
    decodeUrl,
    encodeBase64,
    encodeBase64Url,
    encodeUrl,
    URL_VARIANTS,
    type ValueResult,
} from '@/lib/encoding';
import type { HashAlgorithm } from './hash';

/**
 * A conta da tela: o texto entra uma vez, e sai o que a aba mostra.
 *
 * As abas nao sao tres tools - sao tres leituras do mesmo texto. Mesmo assim a
 * conta e feita **por aba**, e nao para as tres de uma vez: a conversao de 5 MB
 * custa segundos (medido: ~2 s no Base64, ~5 s na URL), e calcular as duas abas
 * a cada tecla pagaria esse preco por uma saida que ninguem esta vendo.
 *
 * A aba do Hash fica de fora daqui: ela e assincrona e nao tem direcao, entao
 * mora em `hash.ts`, e a tela chama as duas.
 */

export const DIRECTIONS = ['encode', 'decode'] as const;

export type Direction = (typeof DIRECTIONS)[number];

/** As abas do painel de saida, na ordem em que aparecem. */
export const MODES = ['base64', 'url', 'hash'] as const;

export type Mode = (typeof MODES)[number];

/** As duas abas que a direcao governa - a do Hash nao tem volta. */
export const CODED_MODES = ['base64', 'url'] as const;

export type CodedMode = (typeof CODED_MODES)[number];

/** Os rotulos das linhas - os mesmos nomes das chaves em `rows.*`. */
export type RowLabel = 'base64' | 'urlSafe' | 'component' | 'form' | HashAlgorithm;

/**
 * A linha de chave/valor em que a saida e desenhada.
 *
 * As tres abas tem a mesma forma - um rotulo e um valor -, e a tela desenha as
 * tres com o mesmo componente. O que muda e quantas linhas cada uma tem: a
 * codificacao de Base64 rende duas (padrao e URL-safe), a decodificacao uma -
 * o decodificador aceita os dois alfabetos, e duas linhas iguais seriam ruido -,
 * a URL rende duas nos dois sentidos, e o Hash rende quatro sempre.
 */
export interface ValueRow {
    label: RowLabel;
    result: ValueResult;
}

export function rowsFor(text: string, mode: CodedMode, direction: Direction): ValueRow[] {
    if (mode === 'base64') {
        return direction === 'encode' ? base64Rows(text) : decodeBase64Rows(text);
    }

    return direction === 'encode' ? urlRows(text) : decodeUrlRows(text);
}

/** Os quatro digests viram as quatro linhas da aba Hash. */
export function digestRows(digests: { algorithm: HashAlgorithm; hex: string | null }[]): ValueRow[] {
    return digests.map((digest) => ({
        label: digest.algorithm,
        // Sem Web Crypto nao ha valor: a linha fica sem resultado, e a tela
        // explica uma vez em vez de repetir o mesmo aviso quatro vezes.
        result: digest.hex === null ? { ok: false } : { ok: true, value: digest.hex },
    }));
}

function base64Rows(text: string): ValueRow[] {
    return [
        { label: 'base64', result: { ok: true, value: encodeBase64(text) } },
        { label: 'urlSafe', result: { ok: true, value: encodeBase64Url(text) } },
    ];
}

function decodeBase64Rows(text: string): ValueRow[] {
    // Uma linha so: o decodificador aceita `+`/`/` e `-`/`_`, com ou sem
    // padding, entao nao ha "qual variante" a perguntar.
    return [{ label: 'base64', result: decodeBase64(text) }];
}

function urlRows(text: string): ValueRow[] {
    return URL_VARIANTS.map((variant) => ({
        label: variant,
        result: { ok: true, value: encodeUrl(text, variant) },
    }));
}

function decodeUrlRows(text: string): ValueRow[] {
    // Na URL as duas continuam mesmo na volta, porque `%20` e `+` sao coisas
    // diferentes na entrada: o mesmo texto pode dar resultados distintos.
    return URL_VARIANTS.map((variant) => ({
        label: variant,
        result: decodeUrl(text, variant),
    }));
}
