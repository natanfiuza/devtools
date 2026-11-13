import { flushPromises } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { Rgba } from '@/lib/color';
import { useColorLibrary } from '@/lib/colors/library';
import {
    STORAGE_KEYS,
    type ColorStorageLike,
} from '@/tools/html-color-picker/lib/storage';

/**
 * Biblioteca compartilhada do picker e do gerador (F5, ADR-127).
 *
 * O anonimo nao faz requisicao; o logado le, grava e importa pela API JSON,
 * com escrita otimista e reconciliacao na falha.
 */

const RED: Rgba = { r: 255, g: 0, b: 0, a: 1 };
const BLUE: Rgba = { r: 0, g: 0, b: 255, a: 1 };
const GREEN: Rgba = { r: 0, g: 255, b: 0, a: 1 };

class FakeStorage implements ColorStorageLike {
    readonly data = new Map<string, string>();

    getItem(key: string): string | null {
        return this.data.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        this.data.set(key, value);
    }
}

type Reply = { status?: number; body?: unknown };

type Call = { url: string; init?: RequestInit };

function stubFetch(
    handler: (url: string, init?: RequestInit) => Reply,
): Call[] {
    const calls: Call[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const url = input.toString();

            calls.push({ url, init });

            const reply = handler(url, init);
            const status = reply.status ?? 200;

            return {
                ok: status >= 200 && status < 300,
                status,
                json: async () => reply.body ?? {},
            } as Response;
        }),
    );

    return calls;
}

function postOf(calls: Call[], path: string): Call | undefined {
    return calls.find(
        (call) =>
            call.init?.method === 'POST' && call.url === `/api/tools/colors${path}`,
    );
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('useColorLibrary anonimo', () => {
    it('usa so o storage local e respeita os limites do servidor', () => {
        const storage = new FakeStorage();
        const calls = stubFetch(() => ({ body: {} }));

        const library = useColorLibrary(
            {
                authenticated: false,
                anonymousHistoryLimit: 2,
                anonymousFavoritesLimit: 1,
            },
            { storage },
        );

        library.addHistory(RED);
        library.addHistory(BLUE);
        library.addHistory(GREEN);
        library.toggleFavorite(RED);
        library.toggleFavorite(BLUE);

        expect(library.history.value).toHaveLength(2);
        expect(library.favorites.value).toEqual(['#0000ffff']);
        expect(library.isFavorite(BLUE)).toBe(true);

        library.toggleFavorite(BLUE);

        expect(library.isFavorite(BLUE)).toBe(false);
        expect(library.importable.value).toBe(0);
        expect(calls).toHaveLength(0);

        library.load();

        expect(calls).toHaveLength(0);
        expect(library.ready.value).toBe(true);
    });

    it('le o historico local existente ao iniciar', () => {
        const storage = new FakeStorage();

        storage.data.set(
            STORAGE_KEYS.history,
            JSON.stringify(['#ff0000ff']),
        );
        storage.data.set(
            STORAGE_KEYS.favorites,
            JSON.stringify(['#0000ffff']),
        );

        const library = useColorLibrary(
            { authenticated: false },
            { storage },
        );

        expect(library.history.value).toEqual(['#ff0000ff']);
        expect(library.favoriteColors.value).toHaveLength(1);
        expect(library.favoriteColors.value[0]?.b).toBe(255);
    });
});

describe('useColorLibrary autenticada', () => {
    it('carrega historico e favoritos da conta com cursor', async () => {
        stubFetch((url) => {
            if (url.endsWith('/history')) {
                return {
                    body: {
                        data: [{ id: 'h1', color: '#ff0000ff' }],
                        next_cursor: 'cursor-1',
                    },
                };
            }

            return { body: { data: [], next_cursor: null } };
        });

        const library = useColorLibrary(
            { authenticated: true },
            { storage: new FakeStorage() },
        );

        expect(library.ready.value).toBe(false);

        await library.load();

        expect(library.ready.value).toBe(true);
        expect(library.loading.value).toBe(false);
        expect(library.history.value).toEqual(['#ff0000ff']);
        expect(library.historyColors.value[0]?.r).toBe(255);
        expect(library.hasMoreHistory.value).toBe(true);
        expect(library.hasMoreFavorites.value).toBe(false);
    });

    it('grava o historico de forma otimista e desfaz na falha', async () => {
        const calls = stubFetch(() => ({ body: { entry: { id: 'h1' } } }));

        const library = useColorLibrary(
            { authenticated: true },
            { storage: new FakeStorage() },
        );

        library.addHistory(RED);

        expect(library.history.value).toEqual(['#ff0000ff']);

        await flushPromises();

        const post = postOf(calls, '/history');

        expect(post).toBeDefined();
        expect(JSON.parse(String(post?.init?.body))).toEqual({
            color: '#ff0000ff',
        });
        expect(
            (post?.init?.headers as Headers).get('X-Requested-With'),
        ).toBe('XMLHttpRequest');

        stubFetch(() => ({ status: 500 }));

        const failing = useColorLibrary(
            { authenticated: true },
            { storage: new FakeStorage() },
        );

        failing.addHistory(BLUE);
        expect(failing.history.value).toEqual(['#0000ffff']);

        await flushPromises();

        expect(failing.history.value).toEqual([]);
        expect(failing.failed.value).toBe(true);
    });

    it('favorita e desfavorita na conta, desfazendo na falha', async () => {
        const calls = stubFetch(() => ({ body: { favorited: true } }));

        const library = useColorLibrary(
            { authenticated: true },
            { storage: new FakeStorage() },
        );

        library.toggleFavorite(RED);

        expect(library.isFavorite(RED)).toBe(true);
        expect(postOf(calls, '/favorites')).toBeDefined();

        await flushPromises();

        library.toggleFavorite(RED);

        expect(library.isFavorite(RED)).toBe(false);

        await flushPromises();

        const call = calls.find(
            (entry) =>
                entry.init?.method === 'DELETE' &&
                entry.url === '/api/tools/colors/favorites',
        );

        expect(call).toBeDefined();

        stubFetch(() => ({ status: 429 }));

        const failing = useColorLibrary(
            { authenticated: true },
            { storage: new FakeStorage() },
        );

        failing.toggleFavorite(BLUE);
        expect(failing.isFavorite(BLUE)).toBe(true);

        await flushPromises();

        expect(failing.isFavorite(BLUE)).toBe(false);
        expect(failing.failed.value).toBe(true);
    });

    it('importa o historico local em lotes e so entao limpa o storage', async () => {
        const storage = new FakeStorage();

        storage.data.set(
            STORAGE_KEYS.history,
            JSON.stringify(['#ff0000ff', '#00ff00ff', '#0000ffff']),
        );

        const calls = stubFetch((url, init) => {
            if (url.endsWith('/history/import')) {
                const body = JSON.parse(String(init?.body)) as {
                    colors: string[];
                };

                return {
                    body: {
                        imported: body.colors.length,
                        total: body.colors.length,
                    },
                };
            }

            if (url.endsWith('/history')) {
                return {
                    body: {
                        data: [{ id: 'h1', color: '#ff0000ff' }],
                        next_cursor: null,
                    },
                };
            }

            return { body: { data: [], next_cursor: null } };
        });

        const library = useColorLibrary(
            { authenticated: true, importBatch: 2 },
            { storage },
        );

        expect(library.importable.value).toBe(3);
        expect(library.canImportLocalHistory.value).toBe(true);

        const imported = await library.importLocalHistory();

        expect(imported).toBe(3);

        const imports = calls.filter((call) =>
            call.url.endsWith('/history/import'),
        );

        expect(imports).toHaveLength(2);
        expect(JSON.parse(String(imports[0]?.init?.body))).toEqual({
            colors: ['#ff0000ff', '#00ff00ff'],
        });
        expect(JSON.parse(String(imports[1]?.init?.body))).toEqual({
            colors: ['#0000ffff'],
        });
        expect(storage.data.get(STORAGE_KEYS.history)).toBe('[]');
        expect(library.importable.value).toBe(0);
        expect(library.history.value).toEqual(['#ff0000ff']);
    });

    it('mantem o historico local quando a importacao falha', async () => {
        const storage = new FakeStorage();

        storage.data.set(
            STORAGE_KEYS.history,
            JSON.stringify(['#ff0000ff']),
        );

        stubFetch(() => ({ status: 500 }));

        const library = useColorLibrary(
            { authenticated: true },
            { storage },
        );

        await expect(library.importLocalHistory()).rejects.toThrow();

        expect(library.importable.value).toBe(1);
        expect(storage.data.get(STORAGE_KEYS.history)).toBe(
            '["#ff0000ff"]',
        );
    });
});
