import { canonicalKey, type Rgb, type Rgba } from '@/lib/color';
import {
    LAYOUT_THEMES,
    type LayoutOverrides,
    type LayoutStrategy,
    type LayoutTheme,
    type LayoutTokenId,
} from '../engine';

/**
 * Estado de edicao do quadro (F3): overrides, bloqueios e ajustes aceitos.
 *
 * Fica fora de `LayoutSystemResult`: o motor e substituido inteiro a cada
 * geracao, mas um valor bloqueado precisa sobreviver a troca de seed. Tudo
 * aqui e puro e devolve um estado novo; nada olha o navegador.
 */

/** De onde veio o valor que esta congelando o token. */
export type OverrideOrigin = 'edit' | 'accept' | 'lock';

export interface LayoutOverride {
    /** sRGB opaco: os tokens do quadro nunca tem alpha. */
    rgb: Rgb;
    origin: OverrideOrigin;
}

export type LayoutOverrideMap = Partial<
    Record<LayoutTheme, Partial<Record<LayoutTokenId, LayoutOverride>>>
>;

export type LayoutLockMap = Partial<
    Record<LayoutTheme, Partial<Record<LayoutTokenId, true>>>
>;

export interface LayoutAcceptedAdjustment {
    theme: LayoutTheme;
    token: LayoutTokenId;
    target: 'aa' | 'aaa';
    from: Rgb;
    to: Rgb;
}

export interface LayoutEditState {
    overrides: LayoutOverrideMap;
    locks: LayoutLockMap;
    accepted: LayoutAcceptedAdjustment[];
}

export type LayoutEditTarget = 'aa' | 'aaa';

export function emptyEditState(): LayoutEditState {
    return { overrides: {}, locks: {}, accepted: [] };
}

/** sRGB opaco de uma cor; o alpha da seed ja foi composto antes. */
export function opaqueRgb(color: Rgba): Rgb {
    return { r: color.r, g: color.g, b: color.b };
}

/**
 * Assinatura das entradas que regeneram o quadro: seed efetiva, estrategia e
 * secundaria manual. O fundo de composicao ja esta dentro da seed efetiva, e
 * repetir o mesmo texto nao pode derrubar edicoes.
 */
export function signatureOf(
    effectiveSeed: Rgba,
    strategy: LayoutStrategy,
    manualSecondary: Rgb | null,
): string {
    const secondary = manualSecondary
        ? canonicalKey({ ...manualSecondary, a: 1 })
        : 'auto';

    return `${canonicalKey(effectiveSeed)}|${strategy}|${secondary}`;
}

export function isLocked(
    state: LayoutEditState,
    theme: LayoutTheme,
    token: LayoutTokenId,
): boolean {
    return state.locks[theme]?.[token] === true;
}

export function overridesForTheme(
    state: LayoutEditState,
    theme: LayoutTheme,
): Partial<Record<LayoutTokenId, Rgb>> | undefined {
    const themeOverrides = state.overrides[theme];

    if (!themeOverrides) {
        return undefined;
    }

    const values: Partial<Record<LayoutTokenId, Rgb>> = {};

    for (const [token, override] of Object.entries(themeOverrides) as [
        LayoutTokenId,
        LayoutOverride | undefined,
    ][]) {
        if (override) {
            values[token] = { ...override.rgb };
        }
    }

    return values;
}

/** Mapa pronto para `generateLayoutSystem`, sem temas vazios. */
export function effectiveOverrides(
    state: LayoutEditState,
): LayoutOverrides | undefined {
    const values: LayoutOverrides = {};

    for (const theme of LAYOUT_THEMES) {
        const overrides = overridesForTheme(state, theme);

        if (overrides && Object.keys(overrides).length > 0) {
            values[theme] = overrides;
        }
    }

    return Object.keys(values).length > 0 ? values : undefined;
}

function withoutOverride(
    state: LayoutEditState,
    theme: LayoutTheme,
    token: LayoutTokenId,
): LayoutOverrideMap {
    const themeOverrides = state.overrides[theme];

    if (!themeOverrides?.[token]) {
        return state.overrides;
    }

    const nextTheme = { ...themeOverrides };
    delete nextTheme[token];

    return Object.keys(nextTheme).length > 0
        ? { ...state.overrides, [theme]: nextTheme }
        : { ...state.overrides, [theme]: undefined };
}

function withoutLock(
    state: LayoutEditState,
    theme: LayoutTheme,
    token: LayoutTokenId,
): LayoutLockMap {
    const themeLocks = state.locks[theme];

    if (!themeLocks?.[token]) {
        return state.locks;
    }

    const nextTheme = { ...themeLocks };
    delete nextTheme[token];

    return Object.keys(nextTheme).length > 0
        ? { ...state.locks, [theme]: nextTheme }
        : { ...state.locks, [theme]: undefined };
}

export function setOverride(
    state: LayoutEditState,
    theme: LayoutTheme,
    token: LayoutTokenId,
    color: Rgba,
): LayoutEditState {
    return {
        ...state,
        overrides: {
            ...state.overrides,
            [theme]: {
                ...state.overrides[theme],
                [token]: { rgb: opaqueRgb(color), origin: 'edit' },
            },
        },
    };
}

/**
 * Aceite de uma sugestao: grava o valor proposto. O bloqueio, se houver,
 * continua; sem bloqueio o override cai na proxima regeneracao (ADR-151).
 */
export function acceptSuggestion(
    state: LayoutEditState,
    theme: LayoutTheme,
    token: LayoutTokenId,
    from: Rgba,
    to: Rgba,
    target: LayoutEditTarget,
): LayoutEditState {
    return {
        overrides: {
            ...state.overrides,
            [theme]: {
                ...state.overrides[theme],
                [token]: { rgb: opaqueRgb(to), origin: 'accept' },
            },
        },
        locks: state.locks,
        accepted: [
            ...state.accepted,
            {
                theme,
                token,
                target,
                from: opaqueRgb(from),
                to: opaqueRgb(to),
            },
        ],
    };
}

/**
 * Liga e desliga o bloqueio. Ligar congela o valor atual como override de
 * origem `lock`; desligar remove so esse congelamento, preservando valores
 * manuais e aceitos.
 */
export function toggleLock(
    state: LayoutEditState,
    theme: LayoutTheme,
    token: LayoutTokenId,
    current: Rgba,
): LayoutEditState {
    if (isLocked(state, theme, token)) {
        const override = state.overrides[theme]?.[token];
        const overrides =
            override?.origin === 'lock'
                ? withoutOverride(state, theme, token)
                : state.overrides;

        return {
            overrides,
            locks: withoutLock(state, theme, token),
            accepted: state.accepted,
        };
    }

    const override = state.overrides[theme]?.[token];

    return {
        overrides: override
            ? state.overrides
            : {
                  ...state.overrides,
                  [theme]: {
                      ...state.overrides[theme],
                      [token]: { rgb: opaqueRgb(current), origin: 'lock' },
                  },
              },
        locks: {
            ...state.locks,
            [theme]: { ...state.locks[theme], [token]: true },
        },
        accepted: state.accepted,
    };
}

function acceptedInTheme(
    accepted: LayoutAcceptedAdjustment[],
    theme: LayoutTheme,
): LayoutAcceptedAdjustment[] {
    return accepted.filter((adjustment) => adjustment.theme !== theme);
}

/** Restaurar o token remove o override e o bloqueio dele. */
export function restoreToken(
    state: LayoutEditState,
    theme: LayoutTheme,
    token: LayoutTokenId,
): LayoutEditState {
    return {
        overrides: withoutOverride(state, theme, token),
        locks: withoutLock(state, theme, token),
        accepted: state.accepted.filter(
            (adjustment) =>
                !(adjustment.theme === theme && adjustment.token === token),
        ),
    };
}

/** Restaurar o tema limpa overrides, bloqueios e aceites so daquele tema. */
export function restoreTheme(
    state: LayoutEditState,
    theme: LayoutTheme,
): LayoutEditState {
    return {
        overrides: { ...state.overrides, [theme]: undefined },
        locks: { ...state.locks, [theme]: undefined },
        accepted: acceptedInTheme(state.accepted, theme),
    };
}

/**
 * Restaurar o sistema limpa os dois temas. A secundaria manual tem o proprio
 * botao de voltar ao automatico e nao e tocada aqui.
 */
export function restoreAll(_state: LayoutEditState): LayoutEditState {
    return { overrides: {}, locks: {}, accepted: [] };
}

/**
 * Descartar overrides sem bloqueio, mantendo o que o usuario congelou. Os
 * aceites cujo valor aplicado saiu tambem somem, para o snapshot nao listar o
 * que nao esta mais na tela.
 */
export function dropUnlocked(state: LayoutEditState): LayoutEditState {
    const overrides: LayoutOverrideMap = {};
    const locks: LayoutLockMap = {};
    const dropped = new Set<string>();

    for (const theme of LAYOUT_THEMES) {
        const themeOverrides = state.overrides[theme];

        if (!themeOverrides) {
            continue;
        }

        for (const [token, override] of Object.entries(themeOverrides) as [
            LayoutTokenId,
            LayoutOverride | undefined,
        ][]) {
            if (!override) {
                continue;
            }

            if (isLocked(state, theme, token)) {
                overrides[theme] = { ...overrides[theme], [token]: override };
                locks[theme] = { ...locks[theme], [token]: true };
            } else {
                dropped.add(`${theme}:${token}`);
            }
        }
    }

    return {
        overrides,
        locks,
        accepted: state.accepted.filter(
            (adjustment) =>
                !dropped.has(`${adjustment.theme}:${adjustment.token}`),
        ),
    };
}

export function countEdits(
    state: LayoutEditState,
    theme?: LayoutTheme,
): number {
    let count = 0;

    for (const current of LAYOUT_THEMES) {
        if (theme && current !== theme) {
            continue;
        }

        const overrides = state.overrides[current] ?? {};
        const locks = state.locks[current] ?? {};

        count += new Set([...Object.keys(overrides), ...Object.keys(locks)])
            .size;
    }

    return count;
}

export function hasAnyEdits(state: LayoutEditState): boolean {
    return countEdits(state) > 0;
}

export function hasUnlockedOverrides(state: LayoutEditState): boolean {
    for (const theme of LAYOUT_THEMES) {
        for (const [token, override] of Object.entries(
            state.overrides[theme] ?? {},
        ) as [LayoutTokenId, LayoutOverride | undefined][]) {
            if (override && !isLocked(state, theme, token)) {
                return true;
            }
        }
    }

    return false;
}
