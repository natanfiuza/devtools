import {
    formatColor,
    formatHex,
    parseColor,
    type Rgb,
    type Rgba,
} from '@/lib/color';
import {
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_SCHEMA_VERSION,
    LAYOUT_STRATEGIES,
    LAYOUT_THEMES,
    LAYOUT_TOKEN_SPECS,
    type LayoutSecondaryInfo,
    type LayoutSeedInfo,
    type LayoutStrategy,
    type LayoutSystemResult,
    type LayoutTheme,
    type LayoutThemeBoard,
    type LayoutToken,
    type LayoutTokenId,
} from '../engine';
import {
    emptyEditState,
    type LayoutAcceptedAdjustment,
    type LayoutEditState,
    type LayoutEditTarget,
    type LayoutLockMap,
    type OverrideOrigin,
} from './editState';
import {
    LAYOUT_SNAPSHOT_VERSION,
    type LayoutSnapshot,
    type LayoutSnapshotCheck,
    type LayoutSnapshotOverride,
    type LayoutSnapshotTheme,
    type LayoutSnapshotToken,
} from './snapshot';
import { LAYOUT_SUGGESTIONS_VERSION } from './suggestions';

/**
 * Estado persistido do sistema de layout (F4, ADR-168).
 *
 * O `snapshot` e o retrato imutavel da F3; `inputs` guarda os textos crus
 * digitados e `secondary` o RGB efetivo da secundaria manual. Reabrir
 * hidrata exatamente o que a tela mostrava, sem chamar o motor.
 *
 * A forma e versionada separadamente do snapshot: `stateVersion` cobre o
 * envelope, `snapshotVersion`/`schemaVersion` cobrem o retrato.
 */

export const LAYOUT_STATE_VERSION = 1;

export interface LayoutStateInputsV1 {
    seedText: string;
    backgroundText: string;
    secondaryText: string;
    /**
     * Fundo de composicao da secundaria com alpha (emenda da F6). Ausente em
     * estados gravados antes da emenda.
     */
    secondaryBackgroundText?: string;
    strategy: LayoutStrategy;
}

/** Secundaria manual; linhas v1 antigas gravavam RGB sem o canal alpha. */
export type LayoutStateSecondary = Rgba | Rgb;

export interface LayoutSystemStateV1 {
    stateVersion: typeof LAYOUT_STATE_VERSION;
    inputs: LayoutStateInputsV1;
    /** Secundaria manual informada; `null` quando automatica. */
    secondary: LayoutStateSecondary | null;
    /**
     * Fundo de composicao da secundaria com alpha; ausente quando a secundaria
     * e opaca ou automatica.
     */
    secondaryBackground?: Rgb | null;
    snapshot: LayoutSnapshot;
}

/** `live` regenera; `compatible` congela valores e edita no lugar. */
export type LayoutHydrateMode = 'live' | 'compatible' | 'readonly';

export interface HydratedLayoutState {
    inputs: LayoutStateInputsV1;
    secondary: Rgba | null;
    secondaryBackground: Rgb | null;
    snapshot: LayoutSnapshot;
    editState: LayoutEditState;
    target: LayoutEditTarget;
    mode: LayoutHydrateMode;
}

/**
 * Resultado estrutural do quadro exibido. O live devolve o tipo do motor
 * (`LayoutSystemResult`, versoes literais); o modo congelado carrega as versoes
 * gravadas no snapshot, por isso a visao usa `number`.
 */
export interface LayoutViewResult {
    schemaVersion: number;
    algorithmVersion: number;
    seed: LayoutSeedInfo;
    strategy: LayoutStrategy;
    secondarySource: 'auto' | 'manual';
    secondary: LayoutSecondaryInfo | null;
    themes: Record<LayoutTheme, LayoutThemeBoard>;
}

const TOKEN_IDS = new Set<string>(LAYOUT_TOKEN_SPECS.map((spec) => spec.id));
const STRATEGIES = new Set<string>(LAYOUT_STRATEGIES);
const THEMES = new Set<string>(LAYOUT_THEMES);
const ORIGINS = new Set<string>(['edit', 'accept', 'lock']);

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isFiniteNumber(value: unknown): value is number {
    return typeof value === 'number' && Number.isFinite(value);
}

function isRgb(value: unknown): value is Rgb {
    return (
        isRecord(value) &&
        isFiniteNumber(value.r) &&
        isFiniteNumber(value.g) &&
        isFiniteNumber(value.b)
    );
}

function isRgba(value: unknown): value is Rgba {
    return (
        isRecord(value) &&
        isFiniteNumber(value.r) &&
        isFiniteNumber(value.g) &&
        isFiniteNumber(value.b) &&
        isFiniteNumber(value.a)
    );
}

function isTheme(value: unknown): value is LayoutTheme {
    return typeof value === 'string' && THEMES.has(value);
}

function isStrategy(value: unknown): value is LayoutStrategy {
    return typeof value === 'string' && STRATEGIES.has(value);
}

function isOrigin(value: unknown): value is OverrideOrigin {
    return typeof value === 'string' && ORIGINS.has(value);
}

function isSnapshotToken(value: unknown): value is LayoutSnapshotToken {
    return (
        isRecord(value) &&
        typeof value.id === 'string' &&
        TOKEN_IDS.has(value.id) &&
        typeof value.family === 'string' &&
        isTheme(value.theme) &&
        isRgba(value.rgba) &&
        isRecord(value.oklch) &&
        isFiniteNumber(value.oklch.l) &&
        isFiniteNumber(value.oklch.c) &&
        isFiniteNumber(value.oklch.h) &&
        typeof value.hex === 'string' &&
        typeof value.clipped === 'boolean' &&
        typeof value.source === 'string'
    );
}

function isSnapshotCheck(value: unknown): value is LayoutSnapshotCheck {
    return (
        isRecord(value) &&
        typeof value.foreground === 'string' &&
        TOKEN_IDS.has(value.foreground) &&
        typeof value.background === 'string' &&
        TOKEN_IDS.has(value.background) &&
        isRecord(value.verdict) &&
        isFiniteNumber(value.verdict.ratio) &&
        typeof value.verdict.aaNormal === 'boolean' &&
        typeof value.verdict.aaaNormal === 'boolean' &&
        typeof value.verdict.aaLarge === 'boolean' &&
        typeof value.verdict.aaaLarge === 'boolean'
    );
}

function isSnapshotTheme(value: unknown): value is LayoutSnapshotTheme {
    return (
        isRecord(value) &&
        isTheme(value.theme) &&
        Array.isArray(value.tokens) &&
        value.tokens.length === LAYOUT_TOKEN_SPECS.length &&
        new Set(
            value.tokens
                .filter(isRecord)
                .map((token) => token.id)
                .filter((id): id is string => typeof id === 'string'),
        ).size === LAYOUT_TOKEN_SPECS.length &&
        value.tokens.every(isSnapshotToken) &&
        Array.isArray(value.checks) &&
        value.checks.every(isSnapshotCheck)
    );
}

function isSnapshotOverride(value: unknown): value is LayoutSnapshotOverride {
    return (
        isRecord(value) &&
        isTheme(value.theme) &&
        typeof value.token === 'string' &&
        TOKEN_IDS.has(value.token) &&
        isOrigin(value.origin) &&
        isRgba(value.rgba) &&
        typeof value.hex === 'string'
    );
}

function isSnapshot(value: unknown): value is LayoutSnapshot {
    return (
        isRecord(value) &&
        isFiniteNumber(value.snapshotVersion) &&
        isFiniteNumber(value.schemaVersion) &&
        isFiniteNumber(value.algorithmVersion) &&
        isFiniteNumber(value.suggestionsVersion) &&
        (value.target === 'aa' || value.target === 'aaa') &&
        isRecord(value.seed) &&
        isRgba(value.seed.original) &&
        isRgba(value.seed.effective) &&
        (value.seed.background === null || isRgb(value.seed.background)) &&
        typeof value.seed.achromatic === 'boolean' &&
        isStrategy(value.strategy) &&
        (value.secondarySource === 'auto' ||
            value.secondarySource === 'manual') &&
        (value.secondary === undefined ||
            value.secondary === null ||
            (isRecord(value.secondary) &&
                isRgba(value.secondary.original) &&
                isRgba(value.secondary.effective) &&
                (value.secondary.background === null ||
                    isRgb(value.secondary.background)))) &&
        Array.isArray(value.themes) &&
        value.themes.length === LAYOUT_THEMES.length &&
        value.themes.every(isSnapshotTheme) &&
        new Set(
            value.themes
                .filter(isRecord)
                .map((theme) => theme.theme)
                .filter((theme) => typeof theme === 'string'),
        ).size === LAYOUT_THEMES.length &&
        Array.isArray(value.overrides) &&
        value.overrides.every(isSnapshotOverride) &&
        Array.isArray(value.locks) &&
        Array.isArray(value.acceptedAdjustments)
    );
}

/** Secundaria com alpha exige o fundo de composicao explicito. */
function hasCompositionBackground(
    secondary: unknown,
    background: unknown,
): boolean {
    return !isRgba(secondary) || secondary.a >= 1 || isRgb(background);
}

/** Shape minimo do envelope persistido; snapshots parciais sao recusados. */
export function isLayoutSystemState(
    value: unknown,
): value is LayoutSystemStateV1 {
    return (
        isRecord(value) &&
        value.stateVersion === LAYOUT_STATE_VERSION &&
        isRecord(value.inputs) &&
        typeof value.inputs.seedText === 'string' &&
        typeof value.inputs.backgroundText === 'string' &&
        typeof value.inputs.secondaryText === 'string' &&
        (value.inputs.secondaryBackgroundText === undefined ||
            typeof value.inputs.secondaryBackgroundText === 'string') &&
        isStrategy(value.inputs.strategy) &&
        (value.secondary === null ||
            isRgb(value.secondary) ||
            isRgba(value.secondary)) &&
        (value.secondaryBackground === undefined ||
            value.secondaryBackground === null ||
            isRgb(value.secondaryBackground)) &&
        hasCompositionBackground(value.secondary, value.secondaryBackground) &&
        isSnapshot(value.snapshot)
    );
}

/** `readonly` para versoes desconhecidas ou mais novas que as atuais. */
export function hydrationMode(state: LayoutSystemStateV1): LayoutHydrateMode {
    const snapshot = state.snapshot;

    if (
        state.stateVersion !== LAYOUT_STATE_VERSION ||
        snapshot.snapshotVersion !== LAYOUT_SNAPSHOT_VERSION ||
        snapshot.schemaVersion !== LAYOUT_SCHEMA_VERSION ||
        snapshot.algorithmVersion > LAYOUT_ALGORITHM_VERSION ||
        snapshot.suggestionsVersion > LAYOUT_SUGGESTIONS_VERSION
    ) {
        return 'readonly';
    }

    return snapshot.algorithmVersion === LAYOUT_ALGORITHM_VERSION &&
        snapshot.suggestionsVersion === LAYOUT_SUGGESTIONS_VERSION
        ? 'live'
        : 'compatible';
}

function sameRgba(left: Rgba, right: Rgba): boolean {
    return (
        left.r === right.r &&
        left.g === right.g &&
        left.b === right.b &&
        left.a === right.a
    );
}

function sameRgb(left: Rgb, right: Rgb): boolean {
    return left.r === right.r && left.g === right.g && left.b === right.b;
}

/**
 * Texto da seed que reabre o mesmo quadro: o texto cru quando ainda produz a
 * cor gravada, senao o HEX da seed efetiva (nunca regenera outro quadro).
 */
function seededText(raw: string, snapshot: LayoutSnapshot): string {
    const parsed = parseColor(raw.trim());

    if (parsed.ok && sameRgba(parsed.color.rgba, snapshot.seed.original)) {
        return raw;
    }

    return formatHex(snapshot.seed.effective);
}

function backgroundInput(raw: string, snapshot: LayoutSnapshot): string {
    const background = snapshot.seed.background;

    if (background === null) {
        return '';
    }

    const parsed = parseBackground(raw);

    return parsed && sameRgb(parsed, background)
        ? raw
        : formatHex({ ...background, a: 1 });
}

function parseBackground(raw: string): Rgb | null {
    const trimmed = raw.trim();

    if (trimmed === '') {
        return null;
    }

    const parsed = parseColor(trimmed);

    return parsed.ok
        ? {
              r: parsed.color.rgba.r,
              g: parsed.color.rgba.g,
              b: parsed.color.rgba.b,
          }
        : null;
}

/** Secundaria informada, normalizada para Rgba (linhas antigas nao tem `a`). */
function secondaryColorOf(state: LayoutSystemStateV1): Rgba | null {
    if (state.snapshot.secondary) {
        return { ...state.snapshot.secondary.original };
    }

    if (state.secondary === null) {
        return null;
    }

    return 'a' in state.secondary
        ? { ...state.secondary }
        : { ...state.secondary, a: 1 };
}

/** Fundo de composicao gravado, com o snapshot como fonte preferida. */
function secondaryBackgroundOf(state: LayoutSystemStateV1): Rgb | null {
    return (
        state.snapshot.secondary?.background ??
        state.secondaryBackground ??
        null
    );
}

function secondaryInput(raw: string, state: LayoutSystemStateV1): string {
    if (state.snapshot.secondarySource !== 'manual') {
        return '';
    }

    const manual = secondaryColorOf(state);

    if (manual === null) {
        return '';
    }

    const parsed = parseColor(raw.trim());

    return parsed.ok && sameRgba(parsed.color.rgba, manual)
        ? raw
        : formatColor(manual, manual.a < 1 ? 'rgba' : 'hex');
}

function secondaryBackgroundInput(
    raw: string,
    state: LayoutSystemStateV1,
): string {
    const background = secondaryBackgroundOf(state);

    if (background === null) {
        return '';
    }

    const parsed = parseBackground(raw);

    return parsed && sameRgb(parsed, background)
        ? raw
        : formatHex({ ...background, a: 1 });
}

/** Locks do snapshot filtrados contra o catalogo atual. */
export function locksOf(snapshot: LayoutSnapshot): LayoutLockMap {
    const locks: LayoutLockMap = {};

    for (const lock of snapshot.locks) {
        if (
            typeof lock === 'object' &&
            lock !== null &&
            isTheme(lock.theme) &&
            typeof lock.token === 'string' &&
            TOKEN_IDS.has(lock.token)
        ) {
            const theme = (locks[lock.theme] ??= {});
            theme[lock.token as LayoutTokenId] = true;
        }
    }

    return locks;
}

/** Overrides, bloqueios e aceites do snapshot, sem reconstruir o quadro. */
export function editStateFromSnapshot(
    snapshot: LayoutSnapshot,
): LayoutEditState {
    const state = emptyEditState();

    for (const override of snapshot.overrides) {
        const theme = (state.overrides[override.theme] ??= {});
        theme[override.token] = {
            rgb: {
                r: override.rgba.r,
                g: override.rgba.g,
                b: override.rgba.b,
            },
            origin: override.origin,
        };
    }

    state.locks = locksOf(snapshot);
    state.accepted = snapshot.acceptedAdjustments.map(
        (adjustment: LayoutAcceptedAdjustment): LayoutAcceptedAdjustment => ({
            theme: adjustment.theme,
            token: adjustment.token,
            target: adjustment.target,
            from: { ...adjustment.from },
            to: { ...adjustment.to },
        }),
    );

    return state;
}

/** Arrays do snapshot viram os Records que o quadro consome. */
export function boardsFromSnapshot(
    snapshot: LayoutSnapshot,
): Record<LayoutTheme, LayoutThemeBoard> {
    const boards = {} as Record<LayoutTheme, LayoutThemeBoard>;

    for (const theme of LAYOUT_THEMES) {
        const board = snapshot.themes.find((entry) => entry.theme === theme);
        const tokens = {} as Record<LayoutTokenId, LayoutToken>;

        for (const spec of LAYOUT_TOKEN_SPECS) {
            const token = board?.tokens.find((entry) => entry.id === spec.id);

            if (token) {
                tokens[spec.id] = {
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
        }

        const checks = (board?.checks ?? []).map((check) => ({
            foreground: check.foreground,
            background: check.background,
            verdict: { ...check.verdict },
        }));

        boards[theme] = { theme, tokens, checks };
    }

    return boards;
}

/** Quadro valido do modo live: mesmas versoes do motor atual. */
export function materializeResult(
    snapshot: LayoutSnapshot,
): LayoutSystemResult {
    return {
        schemaVersion: LAYOUT_SCHEMA_VERSION,
        algorithmVersion: LAYOUT_ALGORITHM_VERSION,
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
 * Hidrata o envelope para a tela. O shape ja foi validado; os textos sao
 * normalizados para nunca reabrirem um quadro diferente do gravado.
 */
export function hydrateState(state: LayoutSystemStateV1): HydratedLayoutState {
    const secondaryBackground = secondaryBackgroundOf(state);

    return {
        inputs: {
            seedText: seededText(state.inputs.seedText, state.snapshot),
            backgroundText: backgroundInput(
                state.inputs.backgroundText,
                state.snapshot,
            ),
            secondaryText: secondaryInput(state.inputs.secondaryText, state),
            secondaryBackgroundText: secondaryBackgroundInput(
                state.inputs.secondaryBackgroundText ?? '',
                state,
            ),
            strategy: state.inputs.strategy,
        },
        secondary: secondaryColorOf(state),
        secondaryBackground,
        snapshot: state.snapshot,
        editState: editStateFromSnapshot(state.snapshot),
        target: state.snapshot.target,
        mode: hydrationMode(state),
    };
}

/** Envelope enviado ao servidor: snapshot, entradas cruas e secundaria. */
export function buildLayoutState(
    inputs: LayoutStateInputsV1,
    secondary: Rgba | null,
    snapshot: LayoutSnapshot,
    secondaryBackground: Rgb | null = null,
): LayoutSystemStateV1 {
    const composed = secondary !== null && secondary.a < 1;

    return {
        stateVersion: LAYOUT_STATE_VERSION,
        inputs: {
            seedText: inputs.seedText,
            backgroundText: inputs.backgroundText,
            secondaryText: inputs.secondaryText,
            secondaryBackgroundText: inputs.secondaryBackgroundText ?? '',
            strategy: inputs.strategy,
        },
        secondary: secondary ? { ...secondary } : null,
        ...(composed && secondaryBackground
            ? { secondaryBackground: { ...secondaryBackground } }
            : {}),
        snapshot,
    };
}
