import { decodeBase64, decodeBase64Bytes } from '@/lib/encoding';

/**
 * A leitura de um JWT (RFC 7519): as tres partes separadas por ponto, as duas
 * primeiras em base64url guardando JSON, e a terceira sendo a assinatura.
 *
 * **A assinatura nao e verificada aqui** - e nem poderia ser: conferir HS256
 * exige a chave secreta, que so o emissor tem, e conferir RS256 exige a chave
 * publica, que teria de vir de algum lugar. O que este modulo faz e o que a
 * tela promete: mostrar o que esta escrito dentro do token, e dizer quantos
 * bytes a assinatura tem. Um token decodificado **nao** e um token confiavel.
 *
 * Tambem nao ha validacao de `alg`, de `iss` nem de `aud`: `alg: none` decodifica
 * como qualquer outro, e a tela mostra o valor cru. Julgar o token e de quem
 * confia nele, com a chave na mao.
 */

/** Os claims de tempo do RFC 7519, na ordem em que a tela os lista. */
export const TIME_CLAIMS = ['iat', 'nbf', 'exp'] as const;

export type TimeClaimName = (typeof TIME_CLAIMS)[number];

/** O maior instante que um `Date` aceita: 100 milhoes de dias, em milissegundos. */
const MAX_TIME_MS = 8.64e15;

/**
 * Um claim de tempo que o payload traz.
 *
 * `date` e `null` quando o valor nao e um numero que caiba numa data - um
 * `"exp": "amanha"` nao impede a leitura do resto do token, mas tambem nao vira
 * data nenhuma.
 */
export interface TimeClaim {
    name: TimeClaimName;
    /** O valor como veio do JSON, sem conversao. */
    raw: unknown;
    date: Date | null;
}

export interface DecodedToken {
    header: Record<string, unknown>;
    payload: Record<string, unknown>;
    /** Os claims de tempo presentes, na ordem de `TIME_CLAIMS`. */
    claims: TimeClaim[];
    /** Quantos bytes a assinatura ocupa - o unico juizo que a tool faz dela. */
    signatureBytes: number;
    /** `exp` ja passou. */
    expired: boolean;
    /** `nbf` ainda nao chegou. */
    notYetValid: boolean;
}

/**
 * Por que o texto colado nao e um JWT.
 *
 * Sao tres, e nao um so, porque as tres mandam olhar para lugares diferentes:
 * a forma (faltam as partes), o transporte (um dos segmentos nao e base64url) e
 * o conteudo (o segmento nao guarda um objeto JSON).
 */
export type DecodeFailure = 'shape' | 'base64' | 'json';

export type DecodeResult =
    { ok: true; token: DecodedToken } | { ok: false; reason: DecodeFailure };

/**
 * O valor de um cabecalho `Authorization` chega inteiro na area de
 * transferencia - "Bearer eyJ..." -, e quem cola raramente tira o prefixo.
 */
const BEARER = /^bearer\s+/i;

/**
 * Le o token.
 *
 * `now` entra por parametro para o teste poder fixar o instante: o "expirado" e
 * uma comparacao com o relogio de quem esta na tela, e um teste que dependesse
 * do relogio de verdade envelheceria sozinho.
 */
export function decodeJwt(
    input: string,
    now: number = Date.now(),
): DecodeResult {
    const parts = input.trim().replace(BEARER, '').split('.');
    const header = parts[0];
    const payload = parts[1];
    const signature = parts[2];

    // Nao ha `?? ''` aqui de proposito: um token truncado perde a terceira
    // parte e vira duas, e inventar a parte que falta so esconderia o problema.
    if (
        header === undefined ||
        payload === undefined ||
        signature === undefined
    ) {
        return { ok: false, reason: 'shape' };
    }

    if (parts.length !== 3) {
        return { ok: false, reason: 'shape' };
    }

    const readHeader = readSegment(header);

    if (!readHeader.ok) {
        return { ok: false, reason: readHeader.reason };
    }

    const readPayload = readSegment(payload);

    if (!readPayload.ok) {
        return { ok: false, reason: readPayload.reason };
    }

    const signatureBytes = decodeBase64Bytes(signature);

    // A assinatura nao e lida como texto: ela e binaria, e o que a tela precisa
    // dela e so o tamanho.
    if (signatureBytes === null) {
        return { ok: false, reason: 'base64' };
    }

    const claims = TIME_CLAIMS.map((name) =>
        readClaim(readPayload.value, name),
    ).filter((claim): claim is TimeClaim => claim !== null);

    return {
        ok: true,
        token: {
            header: readHeader.value,
            payload: readPayload.value,
            claims,
            signatureBytes: signatureBytes.length,
            expired: isBefore(claimOf(claims, 'exp'), now),
            notYetValid: isAfter(claimOf(claims, 'nbf'), now),
        },
    };
}

/*
| As partes
*/

type SegmentResult =
    | { ok: true; value: Record<string, unknown> }
    | { ok: false; reason: 'base64' | 'json' };

/**
 * Um segmento do token: base64url que guarda um objeto JSON.
 *
 * As listas ficam de fora de proposito. O payload de um JWT e um conjunto de
 * claims, e o cabecalho um conjunto de parametros - os dois sao objetos pela
 * norma -; um `[1,2,3]` que decodifica aqui nao e um token com outra forma, e
 * um texto que por acaso passou pelo base64.
 */
function readSegment(segment: string): SegmentResult {
    const decoded = decodeBase64(segment);

    if (!decoded.ok) {
        return { ok: false, reason: 'base64' };
    }

    let parsed: unknown;

    try {
        parsed = JSON.parse(decoded.value);
    } catch {
        return { ok: false, reason: 'json' };
    }

    if (
        parsed === null ||
        typeof parsed !== 'object' ||
        Array.isArray(parsed)
    ) {
        return { ok: false, reason: 'json' };
    }

    return { ok: true, value: parsed as Record<string, unknown> };
}

/*
| Os claims de tempo
*/

function readClaim(
    payload: Record<string, unknown>,
    name: TimeClaimName,
): TimeClaim | null {
    // `in` e nao `payload[name] === undefined`: um `"exp": null` esta la, e a
    // tela deve mostrar que o valor existe e nao serve - e nao sumir com ele.
    if (!(name in payload)) {
        return null;
    }

    const raw = payload[name];

    return { name, raw, date: asDate(raw) };
}

function asDate(raw: unknown): Date | null {
    if (typeof raw !== 'number' || !Number.isFinite(raw)) {
        return null;
    }

    const milliseconds = raw * 1000;

    // Um numero grande demais para o `Date` daria uma data invalida - e uma data
    // invalida na tela e pior do que dizer que nao deu para ler.
    return Math.abs(milliseconds) > MAX_TIME_MS ? null : new Date(milliseconds);
}

function claimOf(claims: TimeClaim[], name: TimeClaimName): TimeClaim | null {
    return claims.find((claim) => claim.name === name) ?? null;
}

/** O instante do claim ja passou. */
function isBefore(claim: TimeClaim | null, now: number): boolean {
    return claim !== null && claim.date !== null && claim.date.getTime() <= now;
}

/** O instante do claim ainda nao chegou. */
function isAfter(claim: TimeClaim | null, now: number): boolean {
    return claim !== null && claim.date !== null && claim.date.getTime() > now;
}
