import type { LayoutStrategy, LayoutTheme } from './types';

/**
 * Tabela numerica aprovada na F1 (2026-11-16), emendada na F6 (2026-11-22).
 *
 * Fonte: docs/colors/layout-harmonizer/PARAMETROS.md, derivada da tabela
 * candidata da F0. Mudar qualquer numero daqui muda o resultado do motor e
 * exige subir `LAYOUT_ALGORITHM_VERSION` e atualizar as fixtures.
 *
 * Emenda da F6: o teto de croma do F0 (superficies/textos: C <= 0.012 no
 * claro e <= 0.020 no escuro) passa a valer para os quatro tokens que o
 * excediam (`on-background`, `on-surface` e `on-surface-variant` no claro;
 * `hairline` no escuro). Matte/clareza nao mudam.
 */

export const LAYOUT_PARAMS_VERSION = 2;

export interface ToneParams {
    l: number;
    c: number;
}

/** Abaixo deste croma a seed e acromatica: sem matiz real (ADR-149). */
export const ACHROMATIC_CHROMA = 0.02;

export type SurfaceTokenId =
    'background' | 'surface' | 'surface-variant' | 'hairline';

/**
 * Superficies: L e C literais por tema; a matiz vem da seed (0 quando
 * acromatica). O croma e constante, nunca herdado da seed.
 */
export const SURFACE_PARAMS: Record<
    SurfaceTokenId,
    Record<LayoutTheme, ToneParams>
> = {
    background: {
        light: { l: 0.985, c: 0.006 },
        dark: { l: 0.16, c: 0.015 },
    },
    surface: {
        light: { l: 1, c: 0.003 },
        dark: { l: 0.2, c: 0.018 },
    },
    'surface-variant': {
        light: { l: 0.955, c: 0.01 },
        dark: { l: 0.245, c: 0.02 },
    },
    hairline: {
        light: { l: 0.9, c: 0.012 },
        dark: { l: 0.33, c: 0.02 },
    },
};

export type SurfaceTextTokenId =
    'on-background' | 'on-surface' | 'on-surface-variant';

/**
 * Textos sobre superficie: tons proprios, listados na tabela (nao usam a
 * regra de "branco ou escuro" dos on-* de marca e de feedback).
 */
export const SURFACE_TEXT_PARAMS: Record<
    SurfaceTextTokenId,
    Record<LayoutTheme, ToneParams>
> = {
    'on-background': {
        light: { l: 0.22, c: 0.012 },
        dark: { l: 0.955, c: 0.006 },
    },
    'on-surface': {
        light: { l: 0.26, c: 0.012 },
        dark: { l: 0.93, c: 0.008 },
    },
    'on-surface-variant': {
        light: { l: 0.48, c: 0.012 },
        dark: { l: 0.74, c: 0.018 },
    },
};

export type FeedbackTokenId = 'error' | 'success' | 'warning' | 'info';

/** Matizes fixas, independentes da seed (ADR-147). */
export const FEEDBACK_HUES: Record<FeedbackTokenId, number> = {
    error: 25,
    success: 145,
    warning: 75,
    info: 245,
};

export const FEEDBACK_PARAMS: Record<
    FeedbackTokenId,
    Record<LayoutTheme, ToneParams>
> = {
    error: {
        light: { l: 0.55, c: 0.19 },
        dark: { l: 0.68, c: 0.18 },
    },
    success: {
        light: { l: 0.55, c: 0.15 },
        dark: { l: 0.72, c: 0.15 },
    },
    warning: {
        light: { l: 0.75, c: 0.16 },
        dark: { l: 0.8, c: 0.15 },
    },
    info: {
        light: { l: 0.55, c: 0.14 },
        dark: { l: 0.72, c: 0.14 },
    },
};

/**
 * Variante de marca: passo maior que hover/active e croma reduzido, para a
 * variante nao se confundir com estado (ADR-157).
 *
 * Emenda da F6: o passo era 0.10, igual ao de `active`; com o croma fora do
 * gamut, os dois tokens chegavam ao mesmo HEX em varias seeds/temas (o corte
 * igualava o croma). O passo 0.12 mantem a direcao e os limites aprovados e
 * garante L proprio em relacao aos estados.
 */
export const VARIANT_DELTA: Record<LayoutTheme, number> = {
    light: -0.12,
    dark: 0.12,
};

export const VARIANT_LIMITS: Record<
    LayoutTheme,
    { floor: number; ceiling: number }
> = {
    light: { floor: 0.3, ceiling: 1 },
    dark: { floor: 0, ceiling: 0.85 },
};

export const VARIANT_CHROMA_FACTOR = 0.9;

/** Hover e active: passos menores que a variante, sem mexer no croma. */
export const STATE_DELTA: Record<
    'hover' | 'active',
    Record<LayoutTheme, number>
> = {
    hover: { light: -0.05, dark: 0.05 },
    active: { light: -0.1, dark: 0.1 },
};

/** Secundaria derivada: mesmo L da seed, croma reduzido. */
export const SECONDARY_CHROMA_FACTOR = 0.85;

/** Link usa L/C/H da seed; hover e active seguem os mesmos passos da marca. */
export const LINK_STATE_DELTA = STATE_DELTA;

export const FOCUS_RING_PARAMS: Record<LayoutTheme, ToneParams> = {
    light: { l: 0.55, c: 0.15 },
    dark: { l: 0.75, c: 0.15 },
};

/**
 * Conteudo sobre marca/feedback/fundo colorido: um dos dois neutros, o de
 * maior razao. Sem matiz; a escolha e medida, nao arbitraria.
 */
export const CONTENT_CANDIDATES: readonly ToneParams[] = [
    { l: 1, c: 0 },
    { l: 0.18, c: 0 },
];

export interface StrategyHues {
    /** Deslocamento da secundaria em relacao a seed, em graus. */
    secondary: number;
    /** Deslocamento do Accent; +120 em todas as estrategias. */
    accent: number;
}

/**
 * Triadica: secundaria +240 e Accent +120. Nas demais estrategias o Accent
 * mantem a mesma derivacao (theta + 120) para o catalogo nao mudar de forma
 * entre estrategias (ADR-163); nao e uma cor da harmonia textual.
 */
export const STRATEGY_HUES: Record<LayoutStrategy, StrategyHues> = {
    analogous: { secondary: 30, accent: 120 },
    complementary: { secondary: 180, accent: 120 },
    triadic: { secondary: 240, accent: 120 },
    monochromatic: { secondary: 0, accent: 120 },
};
