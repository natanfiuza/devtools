import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    fetchImage,
    parseImageUrl,
} from '@/tools/palette-generator/lib/fetchImage';
import type { ImageLimits } from '@/tools/palette-generator/lib/imageLimits';

/**
 * Importacao por URL (ADR-122).
 *
 * Nenhum teste toca a rede: `fetch` e fingido em todos. O que se prova aqui e
 * o contrato de seguranca - apenas HTTPS, sem credenciais, teto de bytes antes
 * e durante a leitura, timeout de verdade e CORS mapeado para uma mensagem.
 */

const LIMITS: ImageLimits = {
    maxBytes: 1024,
    maxSide: 4096,
    maxPixels: 16_000_000,
    analysisSide: 512,
    fetchTimeoutMs: 8000,
};

const URL_IMAGE = 'https://cdn.example.com/pasta/foto.png';

function headers(values: Record<string, string>): Headers {
    return {
        get: (name: string) => values[name.toLowerCase()] ?? null,
    } as unknown as Headers;
}

interface FakeResponse {
    ok: boolean;
    status: number;
    headers: Headers;
    body: { getReader: () => FakeReader } | null;
    arrayBuffer: () => Promise<ArrayBuffer>;
    reader: FakeReader;
}

interface FakeReader {
    read: ReturnType<typeof vi.fn>;
    cancel: ReturnType<typeof vi.fn>;
}

function response(
    chunks: Uint8Array[],
    options: {
        status?: number;
        type?: string;
        length?: number;
        body?: boolean;
    } = {},
): FakeResponse {
    const status = options.status ?? 200;
    const content = options.type ?? 'image/png';
    let index = 0;

    const reader: FakeReader = {
        read: vi.fn(async () =>
            index < chunks.length
                ? { done: false, value: chunks[index++] }
                : { done: true, value: undefined },
        ),
        cancel: vi.fn(async () => undefined),
    };

    const headerValues: Record<string, string> = {
        'content-type': content,
    };

    if (options.length !== undefined) {
        headerValues['content-length'] = String(options.length);
    }

    return {
        ok: status >= 200 && status < 300,
        status,
        headers: headers(headerValues),
        body:
            options.body === false ? null : { getReader: () => reader },
        arrayBuffer: async () => {
            const total = chunks.reduce((sum, chunk) => sum + chunk.byteLength, 0);
            const buffer = new ArrayBuffer(total);
            const view = new Uint8Array(buffer);
            let offset = 0;

            for (const chunk of chunks) {
                view.set(chunk, offset);
                offset += chunk.byteLength;
            }

            return buffer;
        },
        reader,
    };
}

function useFetch(value: FakeResponse | Error): ReturnType<typeof vi.fn> {
    const mock = vi.fn(() =>
        value instanceof Error
            ? Promise.reject(value)
            : Promise.resolve(value as unknown as Response),
    );

    vi.stubGlobal('fetch', mock);

    return mock;
}

afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

describe('parseImageUrl', () => {
    it('aceita HTTPS', () => {
        const parsed = parseImageUrl(URL_IMAGE);

        expect(parsed.ok).toBe(true);

        if (parsed.ok) {
            expect(parsed.url.host).toBe('cdn.example.com');
        }
    });

    it('recusa http, ftp e texto que nao e URL', () => {
        expect(parseImageUrl('http://example.com/a.png')).toEqual({
            ok: false,
            error: 'not_https',
        });
        expect(parseImageUrl('ftp://example.com/a.png')).toEqual({
            ok: false,
            error: 'not_https',
        });
        expect(parseImageUrl('nao e url')).toEqual({
            ok: false,
            error: 'invalid_url',
        });
        expect(parseImageUrl('')).toEqual({
            ok: false,
            error: 'invalid_url',
        });
    });

    it('recusa usuario e senha na URL', () => {
        expect(
            parseImageUrl('https://user:senha@cdn.example.com/a.png'),
        ).toEqual({ ok: false, error: 'invalid_url' });
    });
});

describe('fetchImage', () => {
    it('busca na origem com CORS, sem credenciais e sem referer', async () => {
        const mock = useFetch(response([new Uint8Array([1, 2, 3])]));

        const outcome = await fetchImage(URL_IMAGE, LIMITS);

        expect(outcome.ok).toBe(true);
        expect(mock).toHaveBeenCalledTimes(1);

        const [url, init] = mock.mock.calls[0] as [string, RequestInit];

        expect(url).toBe(URL_IMAGE);
        expect(init).toMatchObject({
            mode: 'cors',
            credentials: 'omit',
            referrerPolicy: 'no-referrer',
        });
        expect(init.headers).toBeUndefined();
    });

    it('devolve um Blob com o MIME da resposta', async () => {
        useFetch(
            response([new Uint8Array([1, 2]), new Uint8Array([3])], {
                type: 'image/webp; charset=binary',
            }),
        );

        const outcome = await fetchImage(URL_IMAGE, LIMITS);

        expect(outcome.ok).toBe(true);

        if (outcome.ok) {
            expect(outcome.mime).toBe('image/webp');
            expect(outcome.blob.type).toBe('image/webp');
            expect(outcome.blob.size).toBe(3);
        }
    });

    it('recusa content-type que nao e raster', async () => {
        useFetch(response([new Uint8Array([1])], { type: 'image/svg+xml' }));

        expect(await fetchImage(URL_IMAGE, LIMITS)).toEqual({
            ok: false,
            error: 'not_image',
        });
    });

    it('recusa pelo Content-Length antes de ler o corpo', async () => {
        const fake = response([new Uint8Array([1])], { length: 2048 });

        useFetch(fake);

        expect(await fetchImage(URL_IMAGE, LIMITS)).toEqual({
            ok: false,
            error: 'too_large',
        });
        expect(fake.reader.read).not.toHaveBeenCalled();
    });

    it('conta os bytes lidos e para ao passar do teto', async () => {
        const fake = response([
            new Uint8Array(600),
            new Uint8Array(600),
        ]);

        useFetch(fake);

        expect(await fetchImage(URL_IMAGE, LIMITS)).toEqual({
            ok: false,
            error: 'too_large',
        });
        expect(fake.reader.cancel).toHaveBeenCalled();
    });

    it('corpo vazio vira not_image', async () => {
        useFetch(response([]));

        expect(await fetchImage(URL_IMAGE, LIMITS)).toEqual({
            ok: false,
            error: 'not_image',
        });
    });

    it('sem stream cai no arrayBuffer e respeita o teto', async () => {
        useFetch(response([new Uint8Array(2000)], { body: false }));

        expect(await fetchImage(URL_IMAGE, LIMITS)).toEqual({
            ok: false,
            error: 'too_large',
        });
    });

    it('resposta sem stream dentro do teto vira Blob', async () => {
        useFetch(response([new Uint8Array(10)], { body: false }));

        const outcome = await fetchImage(URL_IMAGE, LIMITS);

        expect(outcome.ok).toBe(true);

        if (outcome.ok) {
            expect(outcome.blob.size).toBe(10);
        }
    });

    it('status de erro vira network', async () => {
        useFetch(response([], { status: 404 }));

        expect(await fetchImage(URL_IMAGE, LIMITS)).toEqual({
            ok: false,
            error: 'network',
        });
    });

    it('TypeError do fetch vira cors, sem proxy', async () => {
        const mock = useFetch(new TypeError('Failed to fetch'));

        expect(await fetchImage(URL_IMAGE, LIMITS)).toEqual({
            ok: false,
            error: 'cors',
        });

        for (const [url] of mock.mock.calls as [string][]) {
            expect(url).toBe(URL_IMAGE);
            expect(url).not.toMatch(/corsproxy|allorigins|cors-anywhere/i);
        }
    });

    it('estoura o timeout e limpa o timer', async () => {
        vi.useFakeTimers();

        vi.stubGlobal(
            'fetch',
            vi.fn(
                (_url: string, init?: RequestInit) =>
                    new Promise<Response>((_resolve, reject) => {
                        init?.signal?.addEventListener('abort', () =>
                            reject(new DOMException('Aborted', 'AbortError')),
                        );
                    }),
            ),
        );

        const promise = fetchImage(URL_IMAGE, {
            ...LIMITS,
            fetchTimeoutMs: 1000,
        });

        await vi.advanceTimersByTimeAsync(1000);

        expect(await promise).toEqual({ ok: false, error: 'timeout' });
        expect(vi.getTimerCount()).toBe(0);
    });

    it('cancelamento do usuario vira cancelled', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                (_url: string, init?: RequestInit) =>
                    new Promise<Response>((_resolve, reject) => {
                        init?.signal?.addEventListener('abort', () =>
                            reject(new DOMException('Aborted', 'AbortError')),
                        );
                    }),
            ),
        );

        const controller = new AbortController();
        const promise = fetchImage(URL_IMAGE, LIMITS, controller.signal);

        controller.abort();

        expect(await promise).toEqual({ ok: false, error: 'cancelled' });
    });

    it('valida a URL antes de chamar fetch', async () => {
        const mock = useFetch(response([new Uint8Array([1])]));

        expect(await fetchImage('http://example.com/a.png', LIMITS)).toEqual({
            ok: false,
            error: 'not_https',
        });
        expect(mock).not.toHaveBeenCalled();
    });
});
