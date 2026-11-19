import { onScopeDispose, watch, type ComputedRef, type Ref } from 'vue';
import type { LayoutSnapshot } from '../lib/snapshot';
import {
    browserLocalStorage,
    clearLayoutDraft,
    markImported as markStoredImported,
    markRefused as markStoredRefused,
    readLayoutDraft,
    writeLayoutDraft,
    type LayoutDraft,
    type LayoutDraftStorage,
} from '../lib/draft';
import type { LayoutSystemStateV1 } from '../lib/state';

/**
 * Gravacao do rascunho anonimo (F4, ADR-161).
 *
 * O composable so escreve: quem decide hidratar/importar/recusar e a pagina.
 * A gravacao e debounced porque cada tecla no picker regenera o quadro e nao
 * faz sentido serializar ~25 KB a cada caractere. Nunca grava com aviso ativo:
 * `enabled` ja carrega "anonimo, quadro valido e sem aviso".
 */

export interface LayoutDraftOptions {
    /** Entrada reativa que dispara a gravacao (snapshot do quadro). */
    snapshot: Ref<LayoutSnapshot | null>;
    /** Anonimo, quadro valido e sem aviso. */
    enabled: Ref<boolean>;
    /** Envelope completo no instante da gravacao. */
    state: () => LayoutSystemStateV1 | null;
    /** CTA da paleta: o id sobrevive ao login. */
    originPaletteId?:
        Ref<string | null> | ComputedRef<string | null> | (() => string | null);
    /** Storage injetavel; `undefined` usa o `localStorage` do navegador. */
    storage?: LayoutDraftStorage | null;
    debounceMs?: number;
}

export interface LayoutDraftWriter {
    /** Le o rascunho atual (para hidratar/importar). */
    restore(): LayoutDraft | null;
    /** Grava agora, sem esperar o debounce. */
    saveNow(): void;
    markImported(): void;
    markRefused(): void;
    discard(): void;
}

export function useLayoutDraft(options: LayoutDraftOptions): LayoutDraftWriter {
    const storage =
        options.storage === undefined ? browserLocalStorage() : options.storage;
    const debounceMs = options.debounceMs ?? 500;

    let timer: ReturnType<typeof setTimeout> | null = null;

    function cancelTimer(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function originId(): string | null {
        const source = options.originPaletteId;

        if (source === undefined) {
            return null;
        }

        return typeof source === 'function' ? source() : source.value;
    }

    function saveNow(): void {
        cancelTimer();

        if (!options.enabled.value) {
            return;
        }

        const state = options.state();

        if (state === null) {
            return;
        }

        writeLayoutDraft({ state, originPaletteId: originId() }, storage);
    }

    function schedule(): void {
        cancelTimer();

        if (!options.enabled.value || options.snapshot.value === null) {
            return;
        }

        timer = setTimeout(() => {
            timer = null;
            saveNow();
        }, debounceMs);
    }

    watch([options.snapshot, options.enabled], schedule, { immediate: true });

    onScopeDispose(cancelTimer);

    return {
        restore: () => readLayoutDraft(storage),
        saveNow,
        markImported: () => {
            markStoredImported(storage);
        },
        markRefused: () => {
            markStoredRefused(storage);
        },
        discard: () => {
            cancelTimer();
            clearLayoutDraft(storage);
        },
    };
}
