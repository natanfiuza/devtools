import { formatHex, type Rgb, type Rgba } from '@/lib/color';
import {
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_SCHEMA_VERSION,
    LAYOUT_THEMES,
    LAYOUT_TOKEN_SPECS,
    type LayoutPairCheck,
    type LayoutSecondaryInfo,
    type LayoutStrategy,
    type LayoutSystemResult,
    type LayoutTheme,
    type LayoutToken,
    type LayoutTokenFamily,
    type LayoutTokenId,
    type LayoutTokenSource,
} from '../engine';
import {
    isLocked,
    type LayoutAcceptedAdjustment,
    type LayoutEditState,
    type LayoutEditTarget,
    type OverrideOrigin,
} from './editState';
import { LAYOUT_SUGGESTIONS_VERSION } from './suggestions';

/**
 * Snapshot do estado efetivo (F3).
 *
 * Congela o que a tela mostra: tokens e checks ja com overrides aplicados,
 * metas de sugestao, bloqueios e aceites. E uma copia profunda; nada aqui
 * regenera o quadro nem depende de Vue. Sem UI e sem exportacao nesta fase.
 */

export const LAYOUT_SNAPSHOT_VERSION = 1;

export interface LayoutSnapshotToken {
    id: LayoutTokenId;
    family: LayoutTokenFamily;
    theme: LayoutTheme;
    rgba: Rgba;
    oklch: { l: number; c: number; h: number };
    hex: string;
    clipped: boolean;
    source: LayoutTokenSource;
}

export interface LayoutSnapshotCheck {
    foreground: LayoutTokenId;
    background: LayoutTokenId;
    verdict: {
        ratio: number;
        aaNormal: boolean;
        aaaNormal: boolean;
        aaLarge: boolean;
        aaaLarge: boolean;
    };
}

export interface LayoutSnapshotTheme {
    theme: LayoutTheme;
    tokens: LayoutSnapshotToken[];
    checks: LayoutSnapshotCheck[];
}

export interface LayoutSnapshotOverride {
    theme: LayoutTheme;
    token: LayoutTokenId;
    origin: OverrideOrigin;
    rgba: Rgba;
    hex: string;
}

export interface LayoutSnapshotLock {
    theme: LayoutTheme;
    token: LayoutTokenId;
}

export interface LayoutSnapshotDiagnostic {
    theme: LayoutTheme;
    foreground: LayoutTokenId;
    background: LayoutTokenId;
    ratio: number;
    aa: boolean;
    aaa: boolean;
}

export interface LayoutSnapshot {
    snapshotVersion: typeof LAYOUT_SNAPSHOT_VERSION;
    schemaVersion: number;
    algorithmVersion: number;
    suggestionsVersion: number;
    target: LayoutEditTarget;
    seed: {
        original: Rgba;
        effective: Rgba;
        background: Rgb | null;
        achromatic: boolean;
    };
    strategy: LayoutStrategy;
    secondarySource: 'auto' | 'manual';
    /**
     * Secundaria manual resolvida (emenda da F6). Campo aditivo e opcional:
     * snapshots gravados antes da emenda nao o possuem.
     */
    secondary?: LayoutSecondaryInfo | null;
    themes: LayoutSnapshotTheme[];
    overrides: LayoutSnapshotOverride[];
    locks: LayoutSnapshotLock[];
    acceptedAdjustments: LayoutAcceptedAdjustment[];
    diagnostics: LayoutSnapshotDiagnostic[];
}

function copyToken(token: LayoutToken): LayoutSnapshotToken {
    return {
        id: token.id,
        family: token.family,
        theme: token.theme,
        rgba: { ...token.rgba },
        oklch: { ...token.oklch },
        hex: token.hex,
        clipped: token.clipped,
        source: token.source,
    };
}

function copyCheck(check: LayoutPairCheck): LayoutSnapshotCheck {
    return {
        foreground: check.foreground,
        background: check.background,
        verdict: { ...check.verdict },
    };
}

function copyRgba(color: Rgba): Rgba {
    return { r: color.r, g: color.g, b: color.b, a: color.a };
}

export function buildSnapshot(
    result: LayoutSystemResult,
    editState: LayoutEditState,
    target: LayoutEditTarget,
): LayoutSnapshot {
    const themes = LAYOUT_THEMES.map((theme) => ({
        theme,
        tokens: LAYOUT_TOKEN_SPECS.map((spec) =>
            copyToken(result.themes[theme].tokens[spec.id]),
        ),
        checks: result.themes[theme].checks.map(copyCheck),
    }));

    const overrides: LayoutSnapshotOverride[] = [];
    const locks: LayoutSnapshotLock[] = [];

    for (const theme of LAYOUT_THEMES) {
        for (const spec of LAYOUT_TOKEN_SPECS) {
            const override = editState.overrides[theme]?.[spec.id];

            if (override) {
                const rgba: Rgba = { ...override.rgb, a: 1 };

                overrides.push({
                    theme,
                    token: spec.id,
                    origin: override.origin,
                    rgba,
                    hex: formatHex(rgba),
                });
            }

            if (isLocked(editState, theme, spec.id)) {
                locks.push({ theme, token: spec.id });
            }
        }
    }

    const diagnostics: LayoutSnapshotDiagnostic[] = [];

    for (const board of themes) {
        for (const check of board.checks) {
            if (check.verdict.aaNormal) {
                continue;
            }

            diagnostics.push({
                theme: board.theme,
                foreground: check.foreground,
                background: check.background,
                ratio: check.verdict.ratio,
                aa: check.verdict.aaNormal,
                aaa: check.verdict.aaaNormal,
            });
        }
    }

    return {
        snapshotVersion: LAYOUT_SNAPSHOT_VERSION,
        schemaVersion: LAYOUT_SCHEMA_VERSION,
        algorithmVersion: LAYOUT_ALGORITHM_VERSION,
        suggestionsVersion: LAYOUT_SUGGESTIONS_VERSION,
        target,
        seed: {
            original: copyRgba(result.seed.original),
            effective: copyRgba(result.seed.effective),
            background: result.seed.background
                ? { ...result.seed.background }
                : null,
            achromatic: result.seed.achromatic,
        },
        strategy: result.strategy,
        secondarySource: result.secondarySource,
        secondary: result.secondary
            ? {
                  original: copyRgba(result.secondary.original),
                  effective: copyRgba(result.secondary.effective),
                  background: result.secondary.background
                      ? { ...result.secondary.background }
                      : null,
              }
            : null,
        themes,
        overrides,
        locks,
        acceptedAdjustments: editState.accepted.map((adjustment) => ({
            ...adjustment,
            from: { ...adjustment.from },
            to: { ...adjustment.to },
        })),
        diagnostics,
    };
}
