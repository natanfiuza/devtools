import { canonicalKey, formatHex8, parseColor } from '@/lib/color';
import type { Rgba } from '@/lib/color';

/**
 * Biblioteca local do picker (ADR-118).
 *
 * Historico e favoritos vivem so no `localStorage`, com chave versionada e
 * limite de 50 cada. A chave guardada e a cor canonica (`#rrggbbaa`, ou
 * `transparent`), entao `#ff0000` e `#ff0000ff` sao a mesma entrada.
 *
 * Toda leitura valida o formato e todo acesso e protegido: `localStorage`
 * pode estar bloqueado, cheio ou indisponivel no SSR - nada disso pode
 * quebrar a tool.
 */

export const HISTORY_LIMIT = 50;
export const FAVORITES_LIMIT = 50;

export const STORAGE_KEYS = {
    history: 'colors.picker.history.v1',
    favorites: 'colors.picker.favorites.v1',
    last: 'colors.picker.last.v1',
} as const;

/** O minimo de `Storage` que a biblioteca usa (facilita testar). */
export interface ColorStorageLike {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
}

export interface ColorLibrary {
    readHistory(): string[];
    readFavorites(): string[];
    addHistory(color: Rgba): string[];
    addFavorite(color: Rgba): string[];
    removeFavorite(color: Rgba): string[];
    clearHistory(): string[];
    clearFavorites(): string[];
    saveLast(color: Rgba): void;
    readLast(): Rgba | null;
}

function readKeys(
    storage: ColorStorageLike | null,
    key: string,
    limit: number,
): string[] {
    if (storage === null) {
        return [];
    }

    try {
        const raw = storage.getItem(key);

        if (raw === null) {
            return [];
        }

        const parsed: unknown = JSON.parse(raw);

        if (!Array.isArray(parsed)) {
            return [];
        }

        const keys: string[] = [];

        for (const entry of parsed) {
            if (typeof entry !== 'string') {
                continue;
            }

            const result = parseColor(entry);

            if (!result.ok) {
                continue;
            }

            const canonical = canonicalKey(result.color.rgba);

            if (!keys.includes(canonical)) {
                keys.push(canonical);
            }

            if (keys.length >= limit) {
                break;
            }
        }

        return keys;
    } catch {
        return [];
    }
}

function writeKeys(
    storage: ColorStorageLike | null,
    key: string,
    keys: string[],
): void {
    if (storage === null) {
        return;
    }

    try {
        storage.setItem(key, JSON.stringify(keys));
    } catch {
        // Cota estourada ou modo privado: a sessao continua, so nao persiste.
    }
}

export function createColorLibrary(
    storage: ColorStorageLike | null,
): ColorLibrary {
    let history = readKeys(storage, STORAGE_KEYS.history, HISTORY_LIMIT);
    let favorites = readKeys(storage, STORAGE_KEYS.favorites, FAVORITES_LIMIT);

    function remember(keys: string[], color: Rgba, limit: number): string[] {
        const key = canonicalKey(color);

        return [key, ...keys.filter((entry) => entry !== key)].slice(0, limit);
    }

    return {
        readHistory: () => [...history],
        readFavorites: () => [...favorites],

        addHistory(color) {
            history = remember(history, color, HISTORY_LIMIT);
            writeKeys(storage, STORAGE_KEYS.history, history);

            return [...history];
        },

        addFavorite(color) {
            favorites = remember(favorites, color, FAVORITES_LIMIT);
            writeKeys(storage, STORAGE_KEYS.favorites, favorites);

            return [...favorites];
        },

        removeFavorite(color) {
            const key = canonicalKey(color);
            favorites = favorites.filter((entry) => entry !== key);
            writeKeys(storage, STORAGE_KEYS.favorites, favorites);

            return [...favorites];
        },

        clearHistory() {
            history = [];
            writeKeys(storage, STORAGE_KEYS.history, history);

            return [];
        },

        clearFavorites() {
            favorites = [];
            writeKeys(storage, STORAGE_KEYS.favorites, favorites);

            return [];
        },

        saveLast(color) {
            if (storage === null) {
                return;
            }

            try {
                storage.setItem(STORAGE_KEYS.last, formatHex8(color));
            } catch {
                // Idem: sem persistencia, o picker segue funcionando.
            }
        },

        readLast() {
            if (storage === null) {
                return null;
            }

            try {
                const raw = storage.getItem(STORAGE_KEYS.last);

                if (raw === null) {
                    return null;
                }

                const result = parseColor(raw);

                return result.ok ? result.color.rgba : null;
            } catch {
                return null;
            }
        },
    };
}

/** `localStorage` do navegador, ou `null` no SSR / com armazenamento bloqueado. */
export function browserStorage(): ColorStorageLike | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
}
