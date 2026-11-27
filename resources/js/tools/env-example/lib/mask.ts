/**
 * A mascara do `.env`.
 *
 * Uma chave e sensivel quando algum **segmento** do nome esta na lista
 * aprovada - `APP_KEY`, `DB_PASSWORD` e `api_key` entram; `AUTHOR` nao (o
 * segmento e `AUTHOR`, e nao `AUTH`) e `APIKEY` tambem nao (sem separador, o
 * nome e um segmento so). O falso negativo e sabido e preferivel ao falso
 * positivo: esvaziar `AUTHOR` estragaria o exemplo.
 *
 * Um valor tambem e sensivel quando e uma URL com usuario e senha
 * (`mysql://root:segredo@db/app`): ali a credencial esta no valor, inclusive
 * numa chave de nome inocente como `DATABASE_URL`.
 *
 * A troca e cirurgica: sai so o conteudo do valor. Ficam a indentacao, o
 * `export `, a chave, o `=`, as aspas e o comentario em linha. O resto do
 * arquivo sai como veio.
 */

import type {
    EnvDocument,
    EnvPairLine,
    InvalidEnvLine,
    MaskResult,
    MaskedKey,
} from '../types';

/** Segmentos de nome que indicam segredo. Comparacao sem caixa. */
const SENSITIVE_SEGMENTS = new Set([
    'KEY',
    'SECRET',
    'PASSWORD',
    'PASS',
    'TOKEN',
    'AUTH',
    'CREDENTIAL',
    'DSN',
    'PRIVATE',
    'PASSWD',
    'PWD',
]);

/** Esquema `://` seguido do userinfo e do `@`. */
const URL_USERINFO = /^[a-z][a-z0-9+.-]*:\/\/([^/\s@]+)@/i;

/**
 * O nome tem algum segmento sensivel?
 *
 * O nome e partido em tudo que nao e letra ou numero: `APP_KEY`, `app-key` e
 * `app.key` viram `[APP, KEY]`. CamelCase nao e desmembrado (`apiKey` e
 * `APIKEY`, um segmento so).
 */
export function isSensitiveKey(key: string): boolean {
    return key
        .split(/[^A-Za-z0-9]+/)
        .filter((segment) => segment !== '')
        .some((segment) => SENSITIVE_SEGMENTS.has(segment.toUpperCase()));
}

/** O valor e uma URL com `usuario:senha@`? */
export function hasUrlCredentials(value: string): boolean {
    const userinfo = URL_USERINFO.exec(value.trim())?.[1];

    return userinfo !== undefined && userinfo.includes(':');
}

function isSensitive(pair: EnvPairLine): boolean {
    return isSensitiveKey(pair.key) || hasUrlCredentials(pair.value);
}

/** O par com o valor esvaziado, mantendo aspas e comentario. */
function maskedText(pair: EnvPairLine): string {
    return `${pair.lead.trimEnd()}${pair.quote}${pair.quote}${pair.comment}`;
}

/**
 * Devolve o `.env.example` e as duas listas do aviso.
 *
 * Valor sensivel que ja estava vazio nao entra na lista: nao havia segredo a
 * remover, e apontar a chave so encheria o aviso.
 */
export function maskEnv(doc: EnvDocument): MaskResult {
    const masked: MaskedKey[] = [];
    const invalid: InvalidEnvLine[] = [];

    const output = doc.lines
        .map((line) => {
            if (line.kind === 'invalid') {
                invalid.push({ line: line.line, raw: line.raw });

                return line.raw;
            }

            if (line.kind !== 'pair') {
                return line.raw;
            }

            if (line.value === '' || !isSensitive(line)) {
                return line.raw;
            }

            masked.push({ key: line.key, line: line.line });

            return maskedText(line);
        })
        .join('\n');

    return { output, masked, invalid };
}
