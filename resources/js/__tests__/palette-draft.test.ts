import { describe, expect, it } from 'vitest';
import {
    DRAFT_KEY,
    DRAFT_TTL_MS,
    clearPaletteDraft,
    readPaletteDraft,
    savePaletteDraft,
    type DraftExtractedPalette,
    type DraftStorageLike,
    type PaletteDraft,
} from '@/lib/saveDraft';

/**
 * Rascunho do gerador (ADR-096).
 *
 * Serve para o anonimo voltar do login com a mesma base. Guarda so o essencial;
 * qualquer desvio no conteudo, versao ou idade faz a leitura devolver `null` em
 * vez de restaurar uma tela quebrada.
 */

const NOW = 1_800_000_000_000;

class MemoryStorage implements DraftStorageLike {
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

class BrokenStorage implements DraftStorageLike {
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

function write(storage: DraftStorageLike, record: unknown): void {
    storage.setItem(DRAFT_KEY, JSON.stringify(record));
}

const SCALE_DRAFT: Omit<PaletteDraft, 'version' | 'savedAt'> = {
    kind: 'scale',
    base: '#6366f1',
    extracted: null,
    tab: 'scale',
};

/** O suficiente para redesenhar a extracao sem guardar a imagem. */
const EXTRACTED_DRAFT: DraftExtractedPalette = {
    colors: [
        { hex: '#112233', weight: 3, percentage: 75 },
        { hex: '#445566', weight: 1, percentage: 25 },
    ],
    requested: 5,
    algorithmVersion: 1,
    sufficient: true,
    visiblePixels: 4,
    source: { kind: 'file', label: 'foto.png' },
};

describe('savePaletteDraft / readPaletteDraft', () => {
    it('grava e le o rascunho com versao e data', () => {
        const storage = new MemoryStorage();
        const saved = savePaletteDraft(SCALE_DRAFT, storage, NOW);

        expect(saved).toMatchObject({
            version: 1,
            savedAt: NOW,
            kind: 'scale',
            base: '#6366f1',
            extracted: null,
            tab: 'scale',
        });
        expect(readPaletteDraft(storage, NOW + 1000)).toEqual(saved);
    });

    it('mantem a extracao restauravel no rascunho', () => {
        const storage = new MemoryStorage();

        savePaletteDraft(
            {
                kind: 'extracted',
                base: '#112233',
                extracted: EXTRACTED_DRAFT,
                tab: 'extraction',
            },
            storage,
            NOW,
        );

        expect(readPaletteDraft(storage, NOW + 1000)?.extracted).toEqual(
            EXTRACTED_DRAFT,
        );
    });

    it('descarta as extraidas quando o tipo e escala', () => {
        const storage = new MemoryStorage();

        savePaletteDraft(
            {
                kind: 'scale',
                base: '#6366f1',
                extracted: EXTRACTED_DRAFT,
                tab: 'scale',
            },
            storage,
            NOW,
        );

        expect(readPaletteDraft(storage, NOW)?.extracted).toBeNull();
    });

    it('guarda apenas base, tipo, extraidas, aba e data', () => {
        const storage = new MemoryStorage();

        savePaletteDraft(
            {
                kind: 'extracted',
                base: '#112233',
                extracted: EXTRACTED_DRAFT,
                tab: 'extraction',
                // Campos de paleta salva nao pertencem ao rascunho.
                name: 'Oceano',
                roles: { light: {} },
                image: 'data:image/png;base64,AAAA',
            } as never,
            storage,
            NOW,
        );

        expect(
            Object.keys(readPaletteDraft(storage, NOW) ?? {}).sort(),
        ).toEqual(['base', 'extracted', 'kind', 'savedAt', 'tab', 'version']);
    });

    it('expira depois de 24 horas', () => {
        const storage = new MemoryStorage();

        savePaletteDraft(SCALE_DRAFT, storage, NOW);

        expect(readPaletteDraft(storage, NOW + DRAFT_TTL_MS)).not.toBeNull();
        expect(readPaletteDraft(storage, NOW + DRAFT_TTL_MS + 1)).toBeNull();
    });

    it('tolera relogio para tras', () => {
        const storage = new MemoryStorage();

        savePaletteDraft(SCALE_DRAFT, storage, NOW);

        expect(readPaletteDraft(storage, NOW - 60 * 60 * 1000)).not.toBeNull();
    });

    it('descarta JSON invalido, versao errada e campos fora do contrato', () => {
        const storage = new MemoryStorage();

        storage.setItem(DRAFT_KEY, '{nao e json');
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        write(storage, { ...SCALE_DRAFT, version: 2, savedAt: NOW });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        write(storage, {
            ...SCALE_DRAFT,
            version: 1,
            savedAt: NOW,
            base: 'azul',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        write(storage, {
            ...SCALE_DRAFT,
            version: 1,
            savedAt: NOW,
            tab: 'prototype',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        write(storage, {
            ...SCALE_DRAFT,
            version: 1,
            savedAt: NOW,
            kind: 'other',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();
    });

    it('descarta extraidas invalidas', () => {
        const storage = new MemoryStorage();

        // Cor invalida dentro da lista.
        write(storage, {
            version: 1,
            savedAt: NOW,
            kind: 'extracted',
            base: '#112233',
            extracted: {
                ...EXTRACTED_DRAFT,
                colors: [{ hex: 'rosa', weight: 1, percentage: 100 }],
            },
            tab: 'extraction',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        // Lista vazia ou acima do teto da extracao.
        write(storage, {
            version: 1,
            savedAt: NOW,
            kind: 'extracted',
            base: '#112233',
            extracted: { ...EXTRACTED_DRAFT, colors: [] },
            tab: 'extraction',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        write(storage, {
            version: 1,
            savedAt: NOW,
            kind: 'extracted',
            base: '#112233',
            extracted: {
                ...EXTRACTED_DRAFT,
                colors: Array.from({ length: 12 }, () => ({
                    hex: '#112233',
                    weight: 1,
                    percentage: 1,
                })),
            },
            tab: 'extraction',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        // Quantidade pedida fora de 5/7/9/11.
        write(storage, {
            version: 1,
            savedAt: NOW,
            kind: 'extracted',
            base: '#112233',
            extracted: { ...EXTRACTED_DRAFT, requested: 6 },
            tab: 'extraction',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        // Origem com shape fora do contrato.
        write(storage, {
            version: 1,
            savedAt: NOW,
            kind: 'extracted',
            base: '#112233',
            extracted: { ...EXTRACTED_DRAFT, source: { kind: 'url' } },
            tab: 'extraction',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();

        // Rascunho de escala nao aceita extracao malformada.
        write(storage, {
            version: 1,
            savedAt: NOW,
            kind: 'scale',
            base: '#112233',
            extracted: { colors: [] },
            tab: 'scale',
        });
        expect(readPaletteDraft(storage, NOW)).toBeNull();
    });

    it('sem storage ou com storage quebrado nao quebra a tela', () => {
        expect(savePaletteDraft(SCALE_DRAFT, null, NOW)).toBeNull();
        expect(readPaletteDraft(null, NOW)).toBeNull();
        expect(() => clearPaletteDraft(null)).not.toThrow();

        const broken = new BrokenStorage();

        expect(savePaletteDraft(SCALE_DRAFT, broken, NOW)).toBeNull();
        expect(readPaletteDraft(broken, NOW)).toBeNull();
        expect(() => clearPaletteDraft(broken)).not.toThrow();
    });
});

describe('clearPaletteDraft', () => {
    it('remove o rascunho', () => {
        const storage = new MemoryStorage();

        savePaletteDraft(SCALE_DRAFT, storage, NOW);
        clearPaletteDraft(storage);

        expect(readPaletteDraft(storage, NOW)).toBeNull();
        expect(storage.getItem(DRAFT_KEY)).toBeNull();
    });
});
