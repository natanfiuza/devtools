import { isLayoutSystemState, type LayoutSystemStateV1 } from './state';

/**
 * Rascunho anonimo do Layout Harmonizer (F4, ADR-161/ADR-168).
 *
 * Quem ainda nao tem conta e clica em "Salvar" guarda aqui o envelope inteiro
 * para atravessar o login sem perder a tela. Diferente do rascunho de paleta,
 * nao ha TTL: o rascunho vive ate importar, recusar ou limpar, porque o
 * trabalho do Layout Harmonizer pode ser retomado dias depois.
 *
 * Dois descartes sao diferentes:
 * - envelope que nao e o contrato v1: e lixo, apagado na leitura;
 * - envelope valido de versao que este editor nao abre: fica guardado, e o
 *   dialogo oferece apenas "Descartar" (nada e hidratado).
 */

export const LAYOUT_DRAFT_KEY = 'devtools.layout-harmonizer.draft.v1';

export const LAYOUT_DRAFT_VERSION = 1;

export type LayoutDraftStatus = 'pending' | 'imported' | 'refused';

export interface LayoutDraft {
    version: 1;
    savedAt: number;
    status: LayoutDraftStatus;
    decidedAt: number | null;
    /** CTA de uma paleta: preserva o id para reconstruir a origem. */
    originPaletteId: string | null;
    /** `false` quando o envelope e valido, mas de uma versao desconhecida. */
    supported: boolean;
    /** Envelope openable; `null` quando `supported` e `false`. */
    state: LayoutSystemStateV1 | null;
}

export interface LayoutDraftInput {
    state: LayoutSystemStateV1;
    originPaletteId?: string | null;
}

/** O minimo de `Storage` que o rascunho usa (facilita testar). */
export interface LayoutDraftStorage {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
}

const STATUSES: readonly LayoutDraftStatus[] = [
    'pending',
    'imported',
    'refused',
];

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isStatus(value: unknown): value is LayoutDraftStatus {
    return (
        typeof value === 'string' &&
        STATUSES.includes(value as LayoutDraftStatus)
    );
}

function isNullableNumber(value: unknown): value is number | null {
    return value === null || typeof value === 'number';
}

function isNullableString(value: unknown): value is string | null {
    return value === null || typeof value === 'string';
}

/** `localStorage` do navegador, ou `null` no SSR / bloqueado. */
export function browserLocalStorage(): LayoutDraftStorage | null {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
}

/**
 * Traduz o JSON lido para o contrato. `null` significa "apagar": ou nao e o
 * envelope v1, ou o `state` nem e uma versao numerada de nada.
 */
function draftFrom(parsed: unknown): LayoutDraft | null {
    if (!isRecord(parsed)) {
        return null;
    }

    if (
        parsed.version !== LAYOUT_DRAFT_VERSION ||
        typeof parsed.savedAt !== 'number' ||
        !isStatus(parsed.status) ||
        !isNullableNumber(parsed.decidedAt) ||
        !isNullableString(parsed.originPaletteId)
    ) {
        return null;
    }

    if (isLayoutSystemState(parsed.state)) {
        return {
            version: 1,
            savedAt: parsed.savedAt,
            status: parsed.status,
            decidedAt: parsed.decidedAt,
            originPaletteId: parsed.originPaletteId,
            supported: true,
            state: parsed.state,
        };
    }

    const version = isRecord(parsed.state) ? parsed.state.stateVersion : null;

    // Sem `stateVersion` numerico o registro nao diz de que versao e: nao ha
    // como explicar ao usuario, entao e lixo.
    if (typeof version !== 'number') {
        return null;
    }

    return {
        version: 1,
        savedAt: parsed.savedAt,
        status: parsed.status,
        decidedAt: parsed.decidedAt,
        originPaletteId: parsed.originPaletteId,
        supported: false,
        state: null,
    };
}

function readRaw(storage: LayoutDraftStorage): unknown {
    const raw = storage.getItem(LAYOUT_DRAFT_KEY);

    if (raw === null) {
        return null;
    }

    return JSON.parse(raw);
}

/**
 * Le o rascunho. Envelope de versao desconhecida vira `supported: false` e
 * continua no storage; shape invalido e apagado na hora.
 */
export function readLayoutDraft(
    storage: LayoutDraftStorage | null = browserLocalStorage(),
): LayoutDraft | null {
    if (storage === null) {
        return null;
    }

    try {
        const draft = draftFrom(readRaw(storage));

        if (draft === null) {
            storage.removeItem(LAYOUT_DRAFT_KEY);
        }

        return draft;
    } catch {
        try {
            storage.removeItem(LAYOUT_DRAFT_KEY);
        } catch {
            // Storage bloqueado: nao ha o que limpar.
        }

        return null;
    }
}

/**
 * Grava um rascunho novo. Qualquer trabalho anonimo depois de importar ou
 * recusar volta a `pending`: o status descreve o rascunho atual, nao o passado.
 */
export function writeLayoutDraft(
    input: LayoutDraftInput,
    storage: LayoutDraftStorage | null = browserLocalStorage(),
    now = Date.now(),
): LayoutDraft | null {
    if (storage === null) {
        return null;
    }

    const draft: LayoutDraft = {
        version: 1,
        savedAt: now,
        status: 'pending',
        decidedAt: null,
        originPaletteId: input.originPaletteId ?? null,
        supported: true,
        state: input.state,
    };

    try {
        storage.setItem(LAYOUT_DRAFT_KEY, JSON.stringify(draft));
    } catch {
        return null;
    }

    return draft;
}

function decide(
    status: Exclude<LayoutDraftStatus, 'pending'>,
    storage: LayoutDraftStorage | null,
    now: number,
): LayoutDraft | null {
    const draft = readLayoutDraft(storage);

    if (draft === null || !draft.supported || storage === null) {
        return null;
    }

    const decided: LayoutDraft = {
        ...draft,
        status,
        decidedAt: now,
    };

    try {
        storage.setItem(LAYOUT_DRAFT_KEY, JSON.stringify(decided));
    } catch {
        return null;
    }

    return decided;
}

/** O usuario confirmou "Importar": o rascunho continua guardado. */
export function markImported(
    storage: LayoutDraftStorage | null = browserLocalStorage(),
    now = Date.now(),
): LayoutDraft | null {
    return decide('imported', storage, now);
}

/** O usuario confirmou "Descartar": nao ha mais o que reabrir. */
export function markRefused(
    storage: LayoutDraftStorage | null = browserLocalStorage(),
    now = Date.now(),
): LayoutDraft | null {
    return decide('refused', storage, now);
}

export function clearLayoutDraft(
    storage: LayoutDraftStorage | null = browserLocalStorage(),
): void {
    if (storage === null) {
        return;
    }

    try {
        storage.removeItem(LAYOUT_DRAFT_KEY);
    } catch {
        // Sem storage nao ha o que limpar.
    }
}
