import { describe, expect, it, vi, afterEach } from 'vitest';
import { effectScope, nextTick, ref, shallowRef } from 'vue';
import { parseColor, type Rgba } from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutStrategy,
} from '@/tools/layout-harmonizer/engine';
import { useLayoutDraft } from '@/tools/layout-harmonizer/composables/useLayoutDraft';
import {
    LAYOUT_DRAFT_KEY,
    clearLayoutDraft,
    markImported,
    markRefused,
    readLayoutDraft,
    writeLayoutDraft,
    type LayoutDraftStorage,
} from '@/tools/layout-harmonizer/lib/draft';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutStateInputsV1,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';

/**
 * Rascunho anonimo do Layout Harmonizer (ADR-161/ADR-168).
 *
 * Sem TTL: o rascunho sobrevive ate importar, recusar ou limpar. Envelope de
 * versao desconhecida fica guardado (so "Descartar" e oferecido), shape
 * invalido e lixo.
 */

class MemoryStorage implements LayoutDraftStorage {
    readonly entries = new Map<string, string>();

    getItem(key: string): string | null {
        return this.entries.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        this.entries.set(key, value);
    }

    removeItem(key: string): void {
        this.entries.delete(key);
    }
}

class BrokenStorage implements LayoutDraftStorage {
    getItem(): string | null {
        throw new Error('bloqueado');
    }

    setItem(): void {
        throw new Error('cheio');
    }

    removeItem(): void {
        throw new Error('bloqueado');
    }
}

function color(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const INPUTS: LayoutStateInputsV1 = {
    seedText: '#3b82f6',
    backgroundText: '',
    secondaryText: '',
    strategy: 'analogous' as LayoutStrategy,
};

function snapshotOf() {
    return buildSnapshot(
        generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
        }),
        emptyEditState(),
        'aa',
    );
}

const SNAPSHOT = snapshotOf();

function stateOf(): LayoutSystemStateV1 {
    return buildLayoutState(INPUTS, null, SNAPSHOT);
}

function write(storage: LayoutDraftStorage, record: unknown): void {
    storage.setItem(LAYOUT_DRAFT_KEY, JSON.stringify(record));
}

describe('writeLayoutDraft / readLayoutDraft', () => {
    it('grava e le o envelope como pending, sem TTL', () => {
        const storage = new MemoryStorage();
        const saved = writeLayoutDraft(
            { state: stateOf(), originPaletteId: '01HZY' },
            storage,
            1_800_000_000_000,
        );

        expect(saved).toMatchObject({
            version: 1,
            status: 'pending',
            decidedAt: null,
            originPaletteId: '01HZY',
            supported: true,
        });
        expect(
            readLayoutDraft(storage)?.state?.snapshot.seed.effective,
        ).toEqual(SNAPSHOT.seed.effective);
    });

    it('sobrevive ao escopo do dia seguinte: nao expira', () => {
        const storage = new MemoryStorage();

        writeLayoutDraft({ state: stateOf() }, storage, 1_700_000_000_000);

        expect(readLayoutDraft(storage)).not.toBeNull();
    });

    it('novo trabalho depois de importar/recusar volta a pending', () => {
        const storage = new MemoryStorage();

        writeLayoutDraft({ state: stateOf() }, storage, 1_000);
        markRefused(storage, 2_000);
        expect(readLayoutDraft(storage)?.status).toBe('refused');

        writeLayoutDraft({ state: stateOf() }, storage, 3_000);
        expect(readLayoutDraft(storage)?.status).toBe('pending');
        expect(readLayoutDraft(storage)?.decidedAt).toBeNull();
    });

    it('markImported/markRefused atualizam o status sem apagar o estado', () => {
        const storage = new MemoryStorage();

        writeLayoutDraft({ state: stateOf() }, storage, 1_000);

        const imported = markImported(storage, 2_000);

        expect(imported?.status).toBe('imported');
        expect(imported?.decidedAt).toBe(2_000);
        expect(imported?.state).not.toBeNull();

        const refused = markRefused(storage, 3_000);

        expect(refused?.status).toBe('refused');
        expect(refused?.state).not.toBeNull();
    });

    it('descarta envelope fora do contrato e apaga o registro', () => {
        const storage = new MemoryStorage();

        storage.setItem(LAYOUT_DRAFT_KEY, '{nao e json');
        expect(readLayoutDraft(storage)).toBeNull();
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        write(storage, { version: 2, savedAt: 1, status: 'pending' });
        expect(readLayoutDraft(storage)).toBeNull();
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        write(storage, {
            version: 1,
            savedAt: 1,
            status: 'pending',
            decidedAt: null,
            originPaletteId: null,
            state: { snapshot: {} },
        });
        expect(readLayoutDraft(storage)).toBeNull();
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();
    });

    it('mantem envelope de versao desconhecida e so o marca como unsupported', () => {
        const storage = new MemoryStorage();

        write(storage, {
            version: 1,
            savedAt: 1_000,
            status: 'pending',
            decidedAt: null,
            originPaletteId: '01HZY',
            state: { stateVersion: 2, inputs: {}, snapshot: {} },
        });

        const draft = readLayoutDraft(storage);

        expect(draft).toMatchObject({
            supported: false,
            state: null,
            originPaletteId: '01HZY',
            savedAt: 1_000,
        });
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).not.toBeNull();
    });

    it('sem storage ou com storage quebrado nao quebra a tela', () => {
        expect(writeLayoutDraft({ state: stateOf() }, null)).toBeNull();
        expect(readLayoutDraft(null)).toBeNull();
        expect(() => clearLayoutDraft(null)).not.toThrow();

        const broken = new BrokenStorage();

        expect(writeLayoutDraft({ state: stateOf() }, broken)).toBeNull();
        expect(readLayoutDraft(broken)).toBeNull();
        expect(() => clearLayoutDraft(broken)).not.toThrow();
        expect(() => markRefused(broken)).not.toThrow();
    });

    it('clearLayoutDraft remove o registro', () => {
        const storage = new MemoryStorage();

        writeLayoutDraft({ state: stateOf() }, storage);
        clearLayoutDraft(storage);

        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();
    });
});

describe('useLayoutDraft', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('grava o envelope depois do debounce, como pending', () => {
        vi.useFakeTimers();

        const storage = new MemoryStorage();
        const snapshot = shallowRef(SNAPSHOT);
        const enabled = ref(true);

        const scope = effectScope();
        const draft = scope.run(() =>
            useLayoutDraft({
                snapshot,
                enabled,
                state: stateOf,
                originPaletteId: () => '01HZY',
                storage,
                debounceMs: 500,
            }),
        )!;

        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        vi.advanceTimersByTime(499);
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        vi.advanceTimersByTime(1);

        const stored = readLayoutDraft(storage);

        expect(stored?.status).toBe('pending');
        expect(stored?.originPaletteId).toBe('01HZY');
        expect(stored?.state?.stateVersion).toBe(1);

        expect(draft.restore()).toEqual(stored);
        scope.stop();
    });

    it('consolida mudancas rapidas em uma unica gravacao', async () => {
        vi.useFakeTimers();

        const storage = new MemoryStorage();
        const snapshot = shallowRef<typeof SNAPSHOT | null>(null);
        const state = stateOf();
        const calls: number[] = [];

        const scope = effectScope();
        scope.run(() =>
            useLayoutDraft({
                snapshot,
                enabled: ref(true),
                state: () => {
                    calls.push(1);

                    return state;
                },
                storage,
                debounceMs: 500,
            }),
        );

        // Sem quadro nao ha rascunho, mesmo com anonimo habilitado.
        vi.advanceTimersByTime(1_000);
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        snapshot.value = SNAPSHOT;
        await nextTick();
        vi.advanceTimersByTime(300);
        snapshot.value = null;
        await nextTick();
        vi.advanceTimersByTime(500);

        expect(calls).toHaveLength(0);
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        // Tres mudancas dentro da janela viram uma gravacao so.
        snapshot.value = SNAPSHOT;
        snapshot.value = null;
        snapshot.value = SNAPSHOT;
        await nextTick();
        vi.advanceTimersByTime(499);

        expect(calls).toHaveLength(0);

        vi.advanceTimersByTime(1);

        expect(calls).toHaveLength(1);
        expect(readLayoutDraft(storage)).not.toBeNull();

        scope.stop();
    });

    it('nao grava desabilitado, com quadro nulo ou estado nulo', async () => {
        vi.useFakeTimers();

        const storage = new MemoryStorage();
        const snapshot = shallowRef(SNAPSHOT);
        const enabled = ref(false);

        const scope = effectScope();
        scope.run(() =>
            useLayoutDraft({
                snapshot,
                enabled,
                state: stateOf,
                storage,
                debounceMs: 10,
            }),
        );

        vi.advanceTimersByTime(50);
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        enabled.value = true;
        await nextTick();
        vi.advanceTimersByTime(10);
        expect(readLayoutDraft(storage)).not.toBeNull();

        clearLayoutDraft(storage);
        snapshot.value = SNAPSHOT;
        // `state` nulo nao grava nada.
        const nullScope = effectScope();
        nullScope.run(() =>
            useLayoutDraft({
                snapshot,
                enabled: ref(true),
                state: () => null,
                storage,
                debounceMs: 10,
            }),
        );
        vi.advanceTimersByTime(20);
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        scope.stop();
        nullScope.stop();
    });

    it('saveNow ignora o debounce e markImported/discard mexem no storage', () => {
        vi.useFakeTimers();

        const storage = new MemoryStorage();
        const snapshot = shallowRef(SNAPSHOT);

        const scope = effectScope();
        const draft = scope.run(() =>
            useLayoutDraft({
                snapshot,
                enabled: ref(true),
                state: stateOf,
                storage,
                debounceMs: 500,
            }),
        )!;

        draft.saveNow();
        expect(readLayoutDraft(storage)).not.toBeNull();

        draft.markImported();
        expect(readLayoutDraft(storage)?.status).toBe('imported');

        draft.discard();
        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();

        scope.stop();
    });

    it('parar o escopo cancela a gravacao pendente', () => {
        vi.useFakeTimers();

        const storage = new MemoryStorage();
        const snapshot = shallowRef(SNAPSHOT);

        const scope = effectScope();
        scope.run(() =>
            useLayoutDraft({
                snapshot,
                enabled: ref(true),
                state: stateOf,
                storage,
                debounceMs: 500,
            }),
        );

        vi.advanceTimersByTime(100);
        scope.stop();
        vi.advanceTimersByTime(1_000);

        expect(storage.getItem(LAYOUT_DRAFT_KEY)).toBeNull();
    });

    it('sem storage o composable continua funcionando', () => {
        const snapshot = shallowRef(SNAPSHOT);

        const scope = effectScope();
        const draft = scope.run(() =>
            useLayoutDraft({
                snapshot,
                enabled: ref(true),
                state: stateOf,
                storage: null,
                debounceMs: 0,
            }),
        )!;

        expect(draft.restore()).toBeNull();
        expect(() => draft.saveNow()).not.toThrow();

        scope.stop();
    });
});
