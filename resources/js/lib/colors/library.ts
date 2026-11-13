import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { canonicalKey, parseColor, type Rgba } from '@/lib/color';
import type { ColorLibraryToolProps } from '@/types/colors';
import {
    browserStorage,
    createColorLibrary,
    FAVORITES_LIMIT,
    HISTORY_LIMIT,
    type ColorLibrary,
    type ColorStorageLike,
} from '@/tools/html-color-picker/lib/storage';
import { colorsApi } from './api';

/**
 * Historico e favoritos compartilhados pelo picker e pelo gerador (F5).
 *
 * Para o anonimo tudo continua no `localStorage` (ADR-118), com o historico
 * reduzido a 20 (ADR-132) e favoritos em 50. Para quem esta logado as listas
 * vem da conta, com escrita otimista e reconciliacao na falha: a UI responde
 * na hora e o servidor continua sendo a fonte da verdade no proximo `load`.
 *
 * O historico local so entra na conta por `importLocalHistory`, chamado a
 * partir de um botao no painel - nunca em segundo plano.
 */

type LibraryPage = {
    data: { id: string; color: string }[];
    next_cursor: string | null;
};

type ImportResult = {
    imported: number;
    total: number;
};

export const DEFAULT_COLOR_LIBRARY_PROPS: ColorLibraryToolProps = {
    authenticated: false,
    anonymousHistoryLimit: HISTORY_LIMIT,
    anonymousFavoritesLimit: FAVORITES_LIMIT,
    historyPerPage: 100,
    favoritesPerPage: 100,
    importBatch: 200,
};

export type UseColorLibraryOptions = {
    /** `Storage` alternativo em teste; `null` desliga a persistencia. */
    storage?: ColorStorageLike | null;
};

function colorOf(key: string): Rgba | null {
    const parsed = parseColor(key);

    return parsed.ok ? parsed.color.rgba : null;
}

function mergeUnique(current: string[], incoming: string[]): string[] {
    const seen = new Set(current);
    const merged = [...current];

    for (const key of incoming) {
        if (!seen.has(key)) {
            seen.add(key);
            merged.push(key);
        }
    }

    return merged;
}

export function useColorLibrary(
    props?: Partial<ColorLibraryToolProps> | null,
    options: UseColorLibraryOptions = {},
) {
    const settings: ColorLibraryToolProps = {
        ...DEFAULT_COLOR_LIBRARY_PROPS,
        ...(props ?? {}),
    };

    const storage =
        options.storage === undefined ? browserStorage() : options.storage;

    const local: ColorLibrary = createColorLibrary(storage, {
        history: settings.anonymousHistoryLimit,
        favorites: settings.anonymousFavoritesLimit,
    });

    const authenticated = settings.authenticated;

    const history = ref<string[]>(
        authenticated ? [] : local.readHistory(),
    );
    const favorites = ref<string[]>(
        authenticated ? [] : local.readFavorites(),
    );

    const ready = ref(!authenticated);
    const loading = ref(false);
    const failed = ref(false);
    const loadingMore = ref(false);

    const nextHistory = ref<string | null>(null);
    const nextFavorites = ref<string | null>(null);

    const importable = ref(
        authenticated ? local.readHistory().length : 0,
    );

    let loaded = false;

    async function load(force = false): Promise<void> {
        if (!authenticated) {
            history.value = local.readHistory();
            favorites.value = local.readFavorites();
            ready.value = true;

            return;
        }

        if (loaded && !force) {
            return;
        }

        loading.value = true;
        failed.value = false;

        try {
            const [historyPage, favoritesPage] = await Promise.all([
                colorsApi<LibraryPage>('/history'),
                colorsApi<LibraryPage>('/favorites'),
            ]);

            history.value = historyPage.data.map((entry) => entry.color);
            favorites.value = favoritesPage.data.map((entry) => entry.color);
            nextHistory.value = historyPage.next_cursor;
            nextFavorites.value = favoritesPage.next_cursor;
            importable.value = local.readHistory().length;
            loaded = true;
        } catch {
            failed.value = true;
        } finally {
            loading.value = false;
            ready.value = true;
        }
    }

    async function loadMore(
        page: 'history' | 'favorites',
    ): Promise<void> {
        if (!authenticated || loadingMore.value) {
            return;
        }

        const cursor =
            page === 'history' ? nextHistory.value : nextFavorites.value;

        if (cursor === null) {
            return;
        }

        loadingMore.value = true;

        try {
            const result = await colorsApi<LibraryPage>(
                `/${page}?cursor=${encodeURIComponent(cursor)}`,
            );
            const incoming = result.data.map((entry) => entry.color);

            if (page === 'history') {
                history.value = mergeUnique(history.value, incoming);
                nextHistory.value = result.next_cursor;
            } else {
                favorites.value = mergeUnique(favorites.value, incoming);
                nextFavorites.value = result.next_cursor;
            }
        } catch {
            failed.value = true;
        } finally {
            loadingMore.value = false;
        }
    }

    const loadMoreHistory = (): Promise<void> => loadMore('history');
    const loadMoreFavorites = (): Promise<void> => loadMore('favorites');

    function addHistory(color: Rgba): void {
        if (!authenticated) {
            history.value = local.addHistory(color);

            return;
        }

        const key = canonicalKey(color);
        const previous = history.value;

        history.value = [key, ...previous.filter((entry) => entry !== key)];

        void colorsApi('/history', {
            method: 'POST',
            body: { color: key },
        }).catch(() => {
            history.value = previous;
            failed.value = true;
        });
    }

    function isFavorite(color: Rgba): boolean {
        return favorites.value.includes(canonicalKey(color));
    }

    function toggleFavorite(color: Rgba): void {
        if (!authenticated) {
            favorites.value = isFavorite(color)
                ? local.removeFavorite(color)
                : local.addFavorite(color);

            return;
        }

        const key = canonicalKey(color);
        const previous = favorites.value;
        const wasFavorite = previous.includes(key);

        favorites.value = wasFavorite
            ? previous.filter((entry) => entry !== key)
            : [key, ...previous];

        const request = wasFavorite
            ? colorsApi('/favorites', {
                method: 'DELETE',
                body: { color: key },
            })
            : colorsApi('/favorites', {
                method: 'POST',
                body: { color: key },
            });

        void request.catch(() => {
            favorites.value = previous;
            failed.value = true;
        });
    }

    function clearHistory(): void {
        if (!authenticated) {
            history.value = local.clearHistory();

            return;
        }

        const previous = history.value;

        history.value = [];
        nextHistory.value = null;

        void colorsApi('/history', { method: 'DELETE' }).catch(() => {
            history.value = previous;
            failed.value = true;
        });
    }

    function clearFavorites(): void {
        if (!authenticated) {
            favorites.value = local.clearFavorites();

            return;
        }

        const previous = favorites.value;

        favorites.value = [];
        nextFavorites.value = null;

        void colorsApi('/favorites', { method: 'DELETE' }).catch(() => {
            favorites.value = previous;
            failed.value = true;
        });
    }

    /**
     * Leva o historico anonimo deste navegador para a conta, em lotes. So
     * limpa o `localStorage` depois de todos os lotes passarem; se um falhar,
     * nada e perdido e o botao continua disponivel.
     */
    async function importLocalHistory(): Promise<number> {
        if (!authenticated) {
            return 0;
        }

        const keys = local.readHistory();

        if (keys.length === 0) {
            return 0;
        }

        const batch = Math.max(1, settings.importBatch);
        let imported = 0;

        for (let index = 0; index < keys.length; index += batch) {
            const result = await colorsApi<ImportResult>('/history/import', {
                method: 'POST',
                body: { colors: keys.slice(index, index + batch) },
            });

            imported += result.imported;
        }

        local.clearHistory();
        importable.value = 0;

        await load(true);

        return imported;
    }

    const historyColors: ComputedRef<Rgba[]> = computed(() =>
        history.value
            .map(colorOf)
            .filter((color): color is Rgba => color !== null),
    );

    const favoriteColors: ComputedRef<Rgba[]> = computed(() =>
        favorites.value
            .map(colorOf)
            .filter((color): color is Rgba => color !== null),
    );

    const canImportLocalHistory: ComputedRef<boolean> = computed(
        () => authenticated && importable.value > 0,
    );

    return {
        authenticated,
        history: history as Ref<string[]>,
        favorites: favorites as Ref<string[]>,
        historyColors,
        favoriteColors,
        ready,
        loading,
        failed,
        loadingMore,
        importable,
        canImportLocalHistory,
        hasMoreHistory: computed(() => nextHistory.value !== null),
        hasMoreFavorites: computed(() => nextFavorites.value !== null),
        load,
        loadMoreHistory,
        loadMoreFavorites,
        addHistory,
        isFavorite,
        toggleFavorite,
        clearHistory,
        clearFavorites,
        importLocalHistory,
        saveLast: (color: Rgba): void => local.saveLast(color),
        readLast: (): Rgba | null => local.readLast(),
    };
}
