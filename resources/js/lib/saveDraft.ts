import { parseColor } from '@/lib/color';
import type { PaletteKind, PaletteSource } from '@/types/colors';

/**
 * Rascunho do gerador para quem ainda nao esta logado (F4, ADR-096).
 *
 * O anonimo que clica em "Salvar" guarda aqui a base, o tipo, as cores
 * extraidas (com peso e origem) e a aba aberta, vai para o login e, ao voltar,
 * recupera a tela. Nada mais entra: nem imagem, nem URL completa (so o host),
 * nem papeis, nem nome, descricao ou tags - o rascunho nao e uma paleta salva.
 *
 * O `sessionStorage` e protegido como no picker (ADR-118): bloqueado, cheio ou
 * ausente no SSR, a tool segue funcionando sem rascunho.
 */

export const DRAFT_KEY = 'colors.palette.draft.v1';

/** Depois de 24 horas o rascunho nao representa mais a sessao de quem voltou. */
export const DRAFT_TTL_MS = 24 * 60 * 60 * 1000;

export type PaletteDraftTab = 'scale' | 'harmonies' | 'extraction';

/** O que e preciso para redesenhar a extracao sem guardar a imagem. */
export interface DraftExtractedPalette {
    colors: { hex: string; weight: number; percentage: number }[];
    requested: number;
    algorithmVersion: number;
    sufficient: boolean;
    visiblePixels: number;
    source: PaletteSource;
}

export interface PaletteDraft {
    version: 1;
    savedAt: number;
    kind: PaletteKind;
    base: string;
    extracted: DraftExtractedPalette | null;
    tab: PaletteDraftTab;
}

/** O minimo de `Storage` que o rascunho usa (facilita testar). */
export interface DraftStorageLike {
    getItem(key: string): string | null;
    setItem(key: string, value: string): void;
    removeItem(key: string): void;
}

const TABS: readonly PaletteDraftTab[] = ['scale', 'harmonies', 'extraction'];

const EXTRACTION_COUNTS = [5, 7, 9, 11];

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isTab(value: unknown): value is PaletteDraftTab {
    return typeof value === 'string' && TABS.includes(value as PaletteDraftTab);
}

function isHex(value: unknown): value is string {
    return typeof value === 'string' && parseColor(value).ok;
}

function isCount(value: unknown): value is number {
    return typeof value === 'number' && Number.isFinite(value) && value >= 0;
}

function validSource(value: unknown): value is PaletteSource {
    return (
        isRecord(value) &&
        ((value.kind === 'file' && typeof value.label === 'string') ||
            (value.kind === 'url' && typeof value.host === 'string'))
    );
}

function validExtracted(value: unknown): value is DraftExtractedPalette {
    if (!isRecord(value) || !validSource(value.source)) {
        return false;
    }

    if (
        !Array.isArray(value.colors) ||
        value.colors.length === 0 ||
        value.colors.length > 11 ||
        !value.colors.every(
            (color) =>
                isRecord(color) &&
                isHex(color.hex) &&
                isCount(color.weight) &&
                isCount(color.percentage),
        )
    ) {
        return false;
    }

    return (
        typeof value.requested === 'number' &&
        EXTRACTION_COUNTS.includes(value.requested) &&
        isCount(value.algorithmVersion) &&
        typeof value.sufficient === 'boolean' &&
        isCount(value.visiblePixels)
    );
}

function draftFrom(parsed: unknown, now: number): PaletteDraft | null {
    if (!isRecord(parsed)) {
        return null;
    }

    if (parsed.version !== 1 || typeof parsed.savedAt !== 'number') {
        return null;
    }

    // Um salto de relogio para tras nao invalida o rascunho; para frente, o
    // TTL continua valendo.
    if (now - parsed.savedAt > DRAFT_TTL_MS) {
        return null;
    }

    if (parsed.kind !== 'scale' && parsed.kind !== 'extracted') {
        return null;
    }

    if (!isHex(parsed.base) || !isTab(parsed.tab)) {
        return null;
    }

    if (parsed.extracted !== null && !validExtracted(parsed.extracted)) {
        return null;
    }

    return {
        version: 1,
        savedAt: parsed.savedAt,
        kind: parsed.kind,
        base: parsed.base,
        extracted: parsed.kind === 'extracted' ? parsed.extracted : null,
        tab: parsed.tab,
    };
}

/** Grava o rascunho; devolve o que foi gravado, ou `null` sem storage. */
export function savePaletteDraft(
    draft: Omit<PaletteDraft, 'version' | 'savedAt'>,
    storage: DraftStorageLike | null = browserSessionStorage(),
    now = Date.now(),
): PaletteDraft | null {
    if (storage === null) {
        return null;
    }

    const record: PaletteDraft = {
        ...draft,
        version: 1,
        savedAt: now,
        extracted: draft.kind === 'extracted' ? draft.extracted : null,
    };

    try {
        storage.setItem(DRAFT_KEY, JSON.stringify(record));
    } catch {
        return null;
    }

    return record;
}

/** Le o rascunho valido e nao expirado; qualquer desvio e descartado. */
export function readPaletteDraft(
    storage: DraftStorageLike | null = browserSessionStorage(),
    now = Date.now(),
): PaletteDraft | null {
    if (storage === null) {
        return null;
    }

    try {
        const raw = storage.getItem(DRAFT_KEY);

        if (raw === null) {
            return null;
        }

        return draftFrom(JSON.parse(raw), now);
    } catch {
        return null;
    }
}

export function clearPaletteDraft(
    storage: DraftStorageLike | null = browserSessionStorage(),
): void {
    if (storage === null) {
        return;
    }

    try {
        storage.removeItem(DRAFT_KEY);
    } catch {
        // Sem storage nao ha o que limpar.
    }
}

/** `sessionStorage` do navegador, ou `null` no SSR / bloqueado. */
export function browserSessionStorage(): DraftStorageLike | null {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
}
