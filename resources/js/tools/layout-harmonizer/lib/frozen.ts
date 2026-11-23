import { formatHex, rgbToOklch, type Rgba } from '@/lib/color';
import {
    evaluateContrast,
    type LayoutTheme,
    type LayoutToken,
    type LayoutTokenId,
} from '../engine';
import type { LayoutSnapshot, LayoutSnapshotOverride } from './snapshot';
import { boardsFromSnapshot, type LayoutViewResult } from './state';

/**
 * Operacoes do modo congelado (F4, ADR-169).
 *
 * O snapshot gravado e a fonte de verdade: editar um token atualiza o valor,
 * o OKLCH e TODOS os checks daquele tema, sem gerar tokens e sem trocar as
 * versoes. Nada aqui chama `generateLayoutSystem`.
 */

/** Resultado estrutural do snapshot, com as versoes gravadas. */
export function frozenResult(snapshot: LayoutSnapshot): LayoutViewResult {
    return {
        schemaVersion: snapshot.schemaVersion,
        algorithmVersion: snapshot.algorithmVersion,
        seed: {
            original: { ...snapshot.seed.original },
            effective: { ...snapshot.seed.effective },
            background: snapshot.seed.background
                ? { ...snapshot.seed.background }
                : null,
            achromatic: snapshot.seed.achromatic,
        },
        strategy: snapshot.strategy,
        secondarySource: snapshot.secondarySource,
        secondary: snapshot.secondary
            ? {
                  original: { ...snapshot.secondary.original },
                  effective: { ...snapshot.secondary.effective },
                  background: snapshot.secondary.background
                      ? { ...snapshot.secondary.background }
                      : null,
              }
            : null,
        themes: boardsFromSnapshot(snapshot),
    };
}

/**
 * Aplica a cor manual em um token do snapshot: atualiza o token e remede os
 * 16 pares do tema com `evaluateContrast`. O outro tema nao e tocado.
 */
export function applyFrozenColor(
    snapshot: LayoutSnapshot,
    theme: LayoutTheme,
    token: LayoutTokenId,
    color: Rgba,
): LayoutSnapshot {
    const rgba: Rgba = {
        r: color.r,
        g: color.g,
        b: color.b,
        a: 1,
    };
    const hex = formatHex(rgba);
    const oklch = rgbToOklch(rgba);

    const themes = snapshot.themes.map((board) => {
        if (board.theme !== theme) {
            return board;
        }

        const tokens = board.tokens.map((entry) =>
            entry.id === token
                ? {
                      ...entry,
                      rgba,
                      oklch,
                      hex,
                      clipped: false,
                      source: 'manual' as const,
                  }
                : entry,
        );

        const record = {} as Record<LayoutTokenId, LayoutToken>;

        for (const entry of tokens) {
            record[entry.id] = entry;
        }

        return {
            ...board,
            tokens,
            checks: evaluateContrast(record),
        };
    });

    const overrides: LayoutSnapshotOverride[] = snapshot.overrides.filter(
        (override) => !(override.theme === theme && override.token === token),
    );

    overrides.push({
        theme,
        token,
        origin: 'edit',
        rgba,
        hex,
    });

    return { ...snapshot, themes, overrides };
}

/** Liga/desliga o bloqueio; o valor exibido continua o do snapshot. */
export function toggleFrozenLock(
    snapshot: LayoutSnapshot,
    theme: LayoutTheme,
    token: LayoutTokenId,
): LayoutSnapshot {
    const exists = snapshot.locks.some(
        (lock) => lock.theme === theme && lock.token === token,
    );

    return {
        ...snapshot,
        locks: exists
            ? snapshot.locks.filter(
                  (lock) => !(lock.theme === theme && lock.token === token),
              )
            : [...snapshot.locks, { theme, token }],
    };
}

export function isFrozenLocked(
    snapshot: LayoutSnapshot,
    theme: LayoutTheme,
    token: LayoutTokenId,
): boolean {
    return snapshot.locks.some(
        (lock) => lock.theme === theme && lock.token === token,
    );
}
