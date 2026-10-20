import { getCookie } from '@/lib/cookies';
import type { AiQueryInput, AiQueryResponse } from './types';

/**
 * Cliente HTTP da API de IA (ADR-042).
 *
 * A sessao e a do site: o POST leva `X-XSRF-TOKEN` do cookie que o Laravel
 * grava (o `getCookie` ja decodifica uma vez) e `credentials: same-origin`.
 * Nao ha token de API nesta fase.
 *
 * O corpo de erro do provedor nunca chega aqui; o que a tela traduz e o `code`
 * do envelope `{error:{code,message,details}}`. `message` e fallback tecnico,
 * nao texto de interface.
 */

/** Codigos que a tela sabe traduzir (`ai.errors.<code>`). */
const SERVER_CODES: Record<string, string> = {
    csrf_token_mismatch: 'csrf',
    quota_exceeded: 'quota_exhausted',
    ai_unavailable: 'unavailable',
    validation_error: 'validation',
};

/** Quando o servidor nao mandou `code`, o status decide o mais provavel. */
const STATUS_CODES: Record<number, string> = {
    401: 'unauthenticated',
    402: 'quota_exhausted',
    403: 'forbidden',
    404: 'not_found',
    419: 'csrf',
    422: 'validation',
    429: 'rate_limited',
    503: 'unavailable',
};

export class AiApiError extends Error {
    constructor(
        public readonly code: string,
        message: string,
        public readonly status: number,
        public readonly details: Record<string, unknown> | null = null,
        public readonly retryAfterSeconds: number | null = null,
    ) {
        super(message);
        this.name = 'AiApiError';
    }
}

/** Erro uniforme para qualquer falha do cliente, inclusive de rede. */
export function toAiApiError(error: unknown): AiApiError {
    return error instanceof AiApiError
        ? error
        : new AiApiError('generic', 'Unexpected error', 0);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function retryAfterSeconds(response: Response): number | null {
    const header = response.headers.get('Retry-After');

    if (header === null) {
        return null;
    }

    const seconds = Number.parseInt(header, 10);

    return Number.isFinite(seconds) ? Math.max(0, seconds) : null;
}

async function readBody(response: Response): Promise<unknown> {
    const text = await response.text();

    if (text === '') {
        return null;
    }

    try {
        return JSON.parse(text) as unknown;
    } catch {
        // Uma pagina HTML de erro (419 do CSRF, proxy fora do ar) nao e o
        // envelope: o status ainda diz o que aconteceu.
        return null;
    }
}

function errorFrom(response: Response, payload: unknown): AiApiError {
    const envelope =
        isRecord(payload) && isRecord(payload.error) ? payload.error : null;
    const serverCode = envelope?.code;
    const code =
        typeof serverCode === 'string' && serverCode !== ''
            ? (SERVER_CODES[serverCode] ?? serverCode)
            : (STATUS_CODES[response.status] ?? 'generic');
    const message =
        typeof envelope?.message === 'string' && envelope.message !== ''
            ? envelope.message
            : code;
    const details = isRecord(envelope?.details) ? envelope.details : null;

    return new AiApiError(
        code,
        message,
        response.status,
        details,
        retryAfterSeconds(response),
    );
}

function dataFrom(payload: unknown): AiQueryResponse {
    const data = isRecord(payload) ? payload.data : null;

    if (
        !isRecord(data) ||
        typeof data.id !== 'string' ||
        typeof data.status !== 'string'
    ) {
        throw new AiApiError('invalid_response', 'Invalid API response', 0);
    }

    return data as unknown as AiQueryResponse;
}

function csrfToken(): string | null {
    return getCookie('XSRF-TOKEN');
}

async function request(
    url: string,
    init: RequestInit = {},
): Promise<AiQueryResponse> {
    const headers = new Headers(init.headers);

    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const token = csrfToken();

    if (token !== null) {
        headers.set('X-XSRF-TOKEN', token);
    }

    let response: Response;

    try {
        response = await fetch(url, {
            ...init,
            headers,
            credentials: 'same-origin',
        });
    } catch {
        throw new AiApiError('network', 'Network error', 0);
    }

    const payload = await readBody(response);

    if (!response.ok) {
        throw errorFrom(response, payload);
    }

    return dataFrom(payload);
}

export async function postQuery(
    endpoint: string,
    input: AiQueryInput,
): Promise<AiQueryResponse> {
    return request(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(input),
    });
}

/** Troca o marcador do endpoint pelo ULID, codificado uma vez. */
export function queryUrl(template: string, id: string): string {
    return template.replace('__ID__', encodeURIComponent(id));
}

export async function getQuery(
    endpoint: string,
    id: string,
): Promise<AiQueryResponse> {
    return request(queryUrl(endpoint, id), { method: 'GET' });
}
