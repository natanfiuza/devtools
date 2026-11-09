import { describe, expect, it } from 'vitest';
import {
    createColorLibrary,
    FAVORITES_LIMIT,
    HISTORY_LIMIT,
    STORAGE_KEYS,
} from '@/tools/html-color-picker/lib/storage';
import type { ColorStorageLike } from '@/tools/html-color-picker/lib/storage';
import type { Rgba } from '@/lib/color';

/**
 * Biblioteca local (ADR-118): chave canonica, caps 50/50, leitura tolerante.
 */

const RED: Rgba = { r: 255, g: 0, b: 0, a: 1 };
const BLUE: Rgba = { r: 0, g: 0, b: 255, a: 1 };
const CLEAR: Rgba = { r: 12, g: 34, b: 56, a: 0 };

class FakeStorage implements ColorStorageLike {
    readonly data = new Map<string, string>();
    failReads = false;
    failWrites = false;

    getItem(key: string): string | null {
        if (this.failReads) {
            throw new Error('blocked');
        }

        return this.data.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        if (this.failWrites) {
            throw new Error('quota');
        }

        this.data.set(key, value);
    }
}

function seed(storage: FakeStorage, key: string, value: unknown): void {
    storage.data.set(key, JSON.stringify(value));
}

describe('createColorLibrary', () => {
    it('guarda a cor canonica e deduplica HEX com e sem alpha', () => {
        const storage = new FakeStorage();
        const library = createColorLibrary(storage);

        library.addHistory(RED);
        const keys = library.addHistory({ ...RED, a: 1 });

        expect(keys).toEqual(['#ff0000ff']);
        expect(storage.data.get(STORAGE_KEYS.history)).toBe('["#ff0000ff"]');
    });

    it('normaliza alpha zero para transparent', () => {
        const library = createColorLibrary(new FakeStorage());

        expect(library.addHistory(CLEAR)).toEqual(['transparent']);
    });

    it('coloca a cor mais recente primeiro e respeita o cap', () => {
        const library = createColorLibrary(new FakeStorage());

        for (let index = 0; index < HISTORY_LIMIT + 5; index += 1) {
            library.addHistory({
                r: index,
                g: 0,
                b: 0,
                a: 1,
            });
        }

        const keys = library.readHistory();

        expect(keys).toHaveLength(HISTORY_LIMIT);
        expect(keys[0]).toBe('#360000ff');
        expect(keys).not.toContain('#000000ff');
    });

    it('remove favorito pela cor canonica e limpa as listas', () => {
        const library = createColorLibrary(new FakeStorage());

        library.addFavorite(RED);
        library.addFavorite(BLUE);
        expect(library.removeFavorite({ ...RED, a: 1 })).toEqual(['#0000ffff']);

        expect(library.clearFavorites()).toEqual([]);
        expect(library.clearHistory()).toEqual([]);
    });

    it('le apenas entradas validas e sem repeticao', () => {
        const storage = new FakeStorage();

        seed(storage, STORAGE_KEYS.history, [
            '#ff0000',
            '#ff0000ff',
            'not-a-color',
            42,
            'transparent',
            '#00ff00',
        ]);

        expect(createColorLibrary(storage).readHistory()).toEqual([
            '#ff0000ff',
            'transparent',
            '#00ff00ff',
        ]);
    });

    it('descarta JSON corrompido ou que nao seja lista', () => {
        const storage = new FakeStorage();

        storage.data.set(STORAGE_KEYS.history, '{oops');
        storage.data.set(STORAGE_KEYS.favorites, '{"a":1}');

        const library = createColorLibrary(storage);

        expect(library.readHistory()).toEqual([]);
        expect(library.readFavorites()).toEqual([]);
    });

    it('sobrevive a cota estourada e a armazenamento bloqueado', () => {
        const storage = new FakeStorage();

        storage.failReads = true;
        storage.failWrites = true;

        const library = createColorLibrary(storage);

        expect(library.readHistory()).toEqual([]);
        expect(library.addHistory(RED)).toEqual(['#ff0000ff']);
        expect(library.readFavorites()).toEqual([]);
    });

    it('funciona sem storage (SSR)', () => {
        const library = createColorLibrary(null);

        expect(library.addHistory(RED)).toEqual(['#ff0000ff']);
        expect(library.readHistory()).toEqual(['#ff0000ff']);
        expect(library.saveLast(RED)).toBeUndefined();
        expect(library.readLast()).toBeNull();
    });

    it('salva e le a ultima cor', () => {
        const library = createColorLibrary(new FakeStorage());

        library.saveLast({ ...BLUE, a: 0.5 });

        const last = library.readLast();

        expect(last).not.toBeNull();
        expect(last?.r).toBe(0);
        expect(last?.b).toBe(255);
        expect(last?.a).toBeCloseTo(0.5, 2);
    });

    it('ignora ultima cor invalida', () => {
        const storage = new FakeStorage();

        storage.data.set(STORAGE_KEYS.last, 'nope');

        expect(createColorLibrary(storage).readLast()).toBeNull();
    });

    it('mantem as chaves versionadas', () => {
        expect(STORAGE_KEYS).toEqual({
            history: 'colors.picker.history.v1',
            favorites: 'colors.picker.favorites.v1',
            last: 'colors.picker.last.v1',
        });
        expect(HISTORY_LIMIT).toBe(50);
        expect(FAVORITES_LIMIT).toBe(50);
    });
});
