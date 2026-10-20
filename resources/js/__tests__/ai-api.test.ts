import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    AiApiError,
    getQuery,
    postQuery,
    queryUrl,
    toAiApiError,
} from '@/tools/ai-sql-builder/api';
import type {
    AiQueryInput,
    AiQueryResponse,
} from '@/tools/ai-sql-builder/types';

/**
 * O cliente HTTP da IA.
 *
 * O que se cobra aqui e o contrato do navegador com a API: o CSRF sai do
 * cookie e vai uma unica vez decodificado, a sessao e `same-origin`, e o
 * envelope de erro vira um `code` que a tela sabe traduzir. O corpo do
 * provedor nunca passa por aqui - o que o servidor manda e o envelope
 * `{error:{code,message,details}}`.
 */

const ENTRADA: AiQueryInput = {
    schema_source: 'ddl',
    dialect: 'mysql',
    output_target: 'sql',
    schema: 'CREATE TABLE users (id bigint primary key)',
    description: 'Liste os usuarios',
};

const RESPOSTA: AiQueryResponse = {
    id: '01HX0000000000000000000000',
    status: 'queued',
    cache_hit: false,
    result: null,
    tokens: { in: null, out: null },
    poll_after_ms: 1500,
    quota: null,
    error_code: null,
};

function json(
    body: unknown,
    status = 200,
    headers: Record<string, string> = {},
) {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json', ...headers },
    });
}

function ok(body: unknown = { data: RESPOSTA }): Response {
    return json(body);
}

let request: ReturnType<typeof vi.fn>;

beforeEach(() => {
    document.cookie = `XSRF-TOKEN=${encodeURIComponent('token+value')};path=/`;
    request = vi.fn();
    vi.stubGlobal('fetch', request);
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.cookie = 'XSRF-TOKEN=;path=/;max-age=0';
});

describe('postQuery', () => {
    it('envia o pedido como JSON, com sessao e CSRF do site', async () => {
        request.mockResolvedValue(ok());

        await expect(postQuery('/api/ai/queries', ENTRADA)).resolves.toEqual(
            RESPOSTA,
        );

        const [url, init] = request.mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Headers;

        expect(url).toBe('/api/ai/queries');
        expect(init.method).toBe('POST');
        expect(init.credentials).toBe('same-origin');
        expect(init.body).toBe(JSON.stringify(ENTRADA));
        expect(headers.get('Accept')).toBe('application/json');
        expect(headers.get('X-Requested-With')).toBe('XMLHttpRequest');
        expect(headers.get('Content-Type')).toBe('application/json');
        // O cookie guarda o valor codificado e o `getCookie` decodifica uma vez:
        // o cabecalho leva o token cru, nao `token%2Bvalue`.
        expect(headers.get('X-XSRF-TOKEN')).toBe('token+value');
    });

    it('nao inventa CSRF quando o cookie ainda nao existe', async () => {
        document.cookie = 'XSRF-TOKEN=;path=/;max-age=0';
        request.mockResolvedValue(ok());

        await postQuery('/api/ai/queries', ENTRADA);

        const [, init] = request.mock.calls[0] as [string, RequestInit];
        const headers = init.headers as Headers;

        expect(headers.has('X-XSRF-TOKEN')).toBe(false);
    });
});

describe('getQuery', () => {
    it('troca o marcador do endpoint pelo id codificado', async () => {
        request.mockResolvedValue(ok());

        await getQuery('/api/ai/queries/__ID__', 'a/b');

        expect(queryUrl('/api/ai/queries/__ID__', 'a/b')).toBe(
            '/api/ai/queries/a%2Fb',
        );
        expect(request.mock.calls[0]?.[0]).toBe('/api/ai/queries/a%2Fb');
    });

    it('usa o metodo GET', async () => {
        request.mockResolvedValue(ok());

        await getQuery('/api/ai/queries/__ID__', '01HX');

        const [, init] = request.mock.calls[0] as [string, RequestInit];

        expect(init.method).toBe('GET');
        expect(init.body).toBeUndefined();
    });
});

describe('erros', () => {
    it('traduz o codigo do servidor de cota para o vocabulario da tela', async () => {
        request.mockResolvedValue(
            json(
                {
                    error: {
                        code: 'quota_exceeded',
                        message: 'Quota exceeded',
                        details: { unit: 'uses' },
                    },
                },
                402,
            ),
        );

        const error = await postQuery('/api/ai/queries', ENTRADA).catch(
            (cause: unknown) => cause,
        );

        expect(error).toBeInstanceOf(AiApiError);
        expect(error).toMatchObject({
            code: 'quota_exhausted',
            status: 402,
            details: { unit: 'uses' },
        });
    });

    it('normaliza o erro de validacao do servidor', async () => {
        request.mockResolvedValue(
            json(
                { error: { code: 'validation_error', message: 'Invalid' } },
                422,
            ),
        );

        await expect(
            postQuery('/api/ai/queries', ENTRADA),
        ).rejects.toMatchObject({ code: 'validation', status: 422 });
    });

    it('lê o Retry-After do rate limit', async () => {
        request.mockResolvedValue(
            json(
                { error: { code: 'rate_limited', message: 'Too many' } },
                429,
                { 'Retry-After': '17' },
            ),
        );

        await expect(
            postQuery('/api/ai/queries', ENTRADA),
        ).rejects.toMatchObject({
            code: 'rate_limited',
            retryAfterSeconds: 17,
        });
    });

    it('cai no status quando o corpo nao e o envelope', async () => {
        request.mockResolvedValue(
            new Response('<html>CSRF</html>', { status: 419 }),
        );

        await expect(
            postQuery('/api/ai/queries', ENTRADA),
        ).rejects.toMatchObject({ code: 'csrf', status: 419 });
    });

    it('mapeia 401 sem envelope para sessao expirada', async () => {
        request.mockResolvedValue(new Response('', { status: 401 }));

        await expect(
            postQuery('/api/ai/queries', ENTRADA),
        ).rejects.toMatchObject({ code: 'unauthenticated', status: 401 });
    });

    it('distingue falha de rede de resposta invalida', async () => {
        request.mockRejectedValueOnce(new TypeError('offline'));

        await expect(
            postQuery('/api/ai/queries', ENTRADA),
        ).rejects.toMatchObject({ code: 'network', status: 0 });

        request.mockResolvedValueOnce(
            new Response('not json', { status: 200 }),
        );

        await expect(
            postQuery('/api/ai/queries', ENTRADA),
        ).rejects.toMatchObject({ code: 'invalid_response', status: 0 });
    });

    it('uniformiza um erro que nao veio do cliente', () => {
        expect(toAiApiError(new Error('boom'))).toMatchObject({
            code: 'generic',
            status: 0,
        });

        const known = new AiApiError('unsafe_sql', 'blocked', 422);

        expect(toAiApiError(known)).toBe(known);
    });
});
