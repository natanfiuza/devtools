import {
    clamp,
    contrastRatio,
    formatHex,
    mapOklchToSrgb,
    normalizeHue,
    rgbToOklch,
    type Oklch,
    type Rgb,
    type Rgba,
} from '@/lib/color';
import { resolveSecondary, resolveSeed } from './alpha';
import { evaluateContrast } from './contrast';
import {
    ACHROMATIC_CHROMA,
    CONTENT_CANDIDATES,
    FEEDBACK_HUES,
    FEEDBACK_PARAMS,
    FOCUS_RING_PARAMS,
    LINK_STATE_DELTA,
    SECONDARY_CHROMA_FACTOR,
    STATE_DELTA,
    STRATEGY_HUES,
    SURFACE_PARAMS,
    SURFACE_TEXT_PARAMS,
    VARIANT_CHROMA_FACTOR,
    VARIANT_DELTA,
    VARIANT_LIMITS,
    type FeedbackTokenId,
    type SurfaceTextTokenId,
    type SurfaceTokenId,
    type ToneParams,
} from './params';
import { secondaryHue } from './strategies';
import { LAYOUT_TOKEN_FAMILY } from './tokens';
import {
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_SCHEMA_VERSION,
    LayoutEngineError,
    type GenerateLayoutInput,
    type LayoutStrategy,
    type LayoutSystemResult,
    type LayoutTheme,
    type LayoutThemeBoard,
    type LayoutToken,
    type LayoutTokenId,
    type LayoutTokenSource,
} from './types';

/**
 * Geracao deterministica do quadro semantico (ADR-147, ADR-166).
 *
 * Entram a seed efetiva, a estrategia e a secundaria manual opcional; saem os
 * dois temas completos, com contraste medido. Nada aqui consulta o navegador,
 * o servidor ou o estado da interface.
 */

interface BuiltColor {
    rgba: Rgba;
    oklch: Oklch;
    clipped: boolean;
}

const SURFACE_IDS: readonly SurfaceTokenId[] = [
    'background',
    'surface',
    'surface-variant',
    'hairline',
];

const SURFACE_TEXT_IDS: readonly SurfaceTextTokenId[] = [
    'on-background',
    'on-surface',
    'on-surface-variant',
];

const FEEDBACK_IDS: readonly FeedbackTokenId[] = [
    'error',
    'success',
    'warning',
    'info',
];

function buildColor(oklch: Oklch): BuiltColor {
    const mapped = mapOklchToSrgb(oklch);

    return {
        rgba: { ...mapped.rgb, a: 1 },
        oklch: mapped.oklch,
        clipped: mapped.clipped,
    };
}

/** Cor exata (seed ou secundaria manual): nao passa pelo gamut mapping. */
function exactColor(rgba: Rgba): BuiltColor {
    const value: Rgba = { r: rgba.r, g: rgba.g, b: rgba.b, a: 1 };

    return { rgba: value, oklch: rgbToOklch(value), clipped: false };
}

function makeToken(
    id: LayoutTokenId,
    theme: LayoutTheme,
    color: BuiltColor,
    source: LayoutTokenSource,
): LayoutToken {
    return {
        id,
        family: LAYOUT_TOKEN_FAMILY[id],
        theme,
        rgba: color.rgba,
        oklch: color.oklch,
        hex: formatHex(color.rgba),
        clipped: color.clipped,
        source,
    };
}

function stateColor(base: Oklch, delta: number): BuiltColor {
    return buildColor({
        l: clamp(base.l + delta, 0, 1),
        c: base.c,
        h: base.h,
    });
}

function variantColor(base: Oklch, theme: LayoutTheme): BuiltColor {
    const limits = VARIANT_LIMITS[theme];

    return buildColor({
        l: clamp(base.l + VARIANT_DELTA[theme], limits.floor, limits.ceiling),
        c: base.c * VARIANT_CHROMA_FACTOR,
        h: base.h,
    });
}

/**
 * Conteudo sobre cor solida: branco (L 1.0) ou escuro (L 0.18), o de maior
 * razao. Empate fica com o branco, por ordem da tabela.
 */
function contentOver(background: Rgba): BuiltColor {
    const candidates = CONTENT_CANDIDATES.map((candidate: ToneParams) =>
        buildColor({ l: candidate.l, c: candidate.c, h: 0 }),
    );

    return candidates.reduce((best, candidate) =>
        contrastRatio(candidate.rgba, background) >
        contrastRatio(best.rgba, background)
            ? candidate
            : best,
    );
}

interface BoardInput {
    theme: LayoutTheme;
    strategy: LayoutStrategy;
    seed: Rgba;
    seedOklch: Oklch;
    achromatic: boolean;
    manualSecondary: Rgba | null;
    overrides?: Partial<Record<LayoutTokenId, Rgb>>;
}

function buildBoard(input: BoardInput): LayoutThemeBoard {
    const { theme, strategy, seed, seedOklch, achromatic } = input;
    const tokens = {} as Record<LayoutTokenId, LayoutToken>;
    const hueWith = (offset: number): number =>
        achromatic ? 0 : normalizeHue(seedOklch.h + offset);
    const surfaceHue = hueWith(0);

    // Superficies e textos de superficie: L/C literais; matiz da seed (ou 0).
    for (const id of SURFACE_IDS) {
        const params = SURFACE_PARAMS[id][theme];
        const token = makeToken(
            id,
            theme,
            buildColor({
                l: params.l,
                c: achromatic ? 0 : params.c,
                h: surfaceHue,
            }),
            'rule',
        );
        tokens[id] = token;
    }

    for (const id of SURFACE_TEXT_IDS) {
        const params = SURFACE_TEXT_PARAMS[id][theme];
        const token = makeToken(
            id,
            theme,
            buildColor({
                l: params.l,
                c: achromatic ? 0 : params.c,
                h: surfaceHue,
            }),
            'rule',
        );
        tokens[id] = token;
    }

    // Feedback: matizes fixas, independentes da seed.
    for (const id of FEEDBACK_IDS) {
        const params = FEEDBACK_PARAMS[id][theme];
        tokens[id] = makeToken(
            id,
            theme,
            buildColor({ l: params.l, c: params.c, h: FEEDBACK_HUES[id] }),
            'feedback',
        );
    }

    // Marca: Primary e exatamente a seed efetiva; variante e estados derivam
    // dela com passos e croma da tabela.
    tokens.primary = makeToken('primary', theme, exactColor(seed), 'seed');
    tokens['primary-variant'] = makeToken(
        'primary-variant',
        theme,
        variantColor(seedOklch, theme),
        'rule',
    );
    tokens['primary-hover'] = makeToken(
        'primary-hover',
        theme,
        stateColor(seedOklch, STATE_DELTA.hover[theme]),
        'rule',
    );
    tokens['primary-active'] = makeToken(
        'primary-active',
        theme,
        stateColor(seedOklch, STATE_DELTA.active[theme]),
        'rule',
    );

    const secondaryBase = input.manualSecondary
        ? exactColor(input.manualSecondary)
        : buildColor({
              l: seedOklch.l,
              c: achromatic ? 0 : seedOklch.c * SECONDARY_CHROMA_FACTOR,
              h: achromatic ? 0 : secondaryHue(seedOklch.h, strategy),
          });
    const secondarySource: LayoutTokenSource = input.manualSecondary
        ? 'manual'
        : 'rule';

    tokens.secondary = makeToken(
        'secondary',
        theme,
        secondaryBase,
        secondarySource,
    );
    tokens['secondary-variant'] = makeToken(
        'secondary-variant',
        theme,
        variantColor(secondaryBase.oklch, theme),
        'rule',
    );
    tokens['secondary-hover'] = makeToken(
        'secondary-hover',
        theme,
        stateColor(secondaryBase.oklch, STATE_DELTA.hover[theme]),
        'rule',
    );
    tokens['secondary-active'] = makeToken(
        'secondary-active',
        theme,
        stateColor(secondaryBase.oklch, STATE_DELTA.active[theme]),
        'rule',
    );

    // Accent: mesmo +120 em toda estrategia (ADR-163); tem estados proprios.
    const accentBase = buildColor({
        l: seedOklch.l,
        c: achromatic ? 0 : seedOklch.c,
        h: hueWith(STRATEGY_HUES[strategy].accent),
    });

    tokens.accent = makeToken('accent', theme, accentBase, 'rule');
    tokens['accent-hover'] = makeToken(
        'accent-hover',
        theme,
        stateColor(accentBase.oklch, STATE_DELTA.hover[theme]),
        'rule',
    );
    tokens['accent-active'] = makeToken(
        'accent-active',
        theme,
        stateColor(accentBase.oklch, STATE_DELTA.active[theme]),
        'rule',
    );

    // Link usa a seed exata; hover/active seguem os passos de link.
    tokens.link = makeToken('link', theme, exactColor(seed), 'rule');
    tokens['link-hover'] = makeToken(
        'link-hover',
        theme,
        stateColor(seedOklch, LINK_STATE_DELTA.hover[theme]),
        'rule',
    );
    tokens['link-active'] = makeToken(
        'link-active',
        theme,
        stateColor(seedOklch, LINK_STATE_DELTA.active[theme]),
        'rule',
    );

    const focus = FOCUS_RING_PARAMS[theme];
    tokens['focus-ring'] = makeToken(
        'focus-ring',
        theme,
        buildColor({
            l: focus.l,
            c: achromatic ? 0 : focus.c,
            h: surfaceHue,
        }),
        'rule',
    );

    // Conteudo sobre marca e feedback, medido sobre a cor base (nao sobre a
    // variante): o mesmo tom e reutilizado em hover/active e os pares com os
    // estados entram nos checks.
    tokens['on-primary'] = makeToken(
        'on-primary',
        theme,
        contentOver(tokens.primary.rgba),
        'rule',
    );
    tokens['on-secondary'] = makeToken(
        'on-secondary',
        theme,
        contentOver(tokens.secondary.rgba),
        'rule',
    );
    tokens['on-accent'] = makeToken(
        'on-accent',
        theme,
        contentOver(tokens.accent.rgba),
        'rule',
    );
    tokens['on-error'] = makeToken(
        'on-error',
        theme,
        contentOver(tokens.error.rgba),
        'rule',
    );
    tokens['on-success'] = makeToken(
        'on-success',
        theme,
        contentOver(tokens.success.rgba),
        'rule',
    );
    tokens['on-warning'] = makeToken(
        'on-warning',
        theme,
        contentOver(tokens.warning.rgba),
        'rule',
    );
    tokens['on-info'] = makeToken(
        'on-info',
        theme,
        contentOver(tokens.info.rgba),
        'rule',
    );

    // Valores manuais (F3): entram depois da derivacao e antes do contraste,
    // sem recalcular dependentes, para que os checks meçam o que a tela mostra.
    if (input.overrides) {
        for (const [id, rgb] of Object.entries(input.overrides) as [
            LayoutTokenId,
            Rgb | undefined,
        ][]) {
            if (!rgb || !(id in tokens)) {
                continue;
            }

            tokens[id] = makeToken(
                id,
                theme,
                exactColor({ ...rgb, a: 1 }),
                'manual',
            );
        }
    }

    return { theme, tokens, checks: evaluateContrast(tokens) };
}

export function generateLayoutSystem(
    input: GenerateLayoutInput,
): LayoutSystemResult {
    if (!(input.strategy in STRATEGY_HUES)) {
        throw new LayoutEngineError(
            'invalid-strategy',
            `estrategia desconhecida: ${String(input.strategy)}`,
        );
    }

    const resolved = resolveSeed(input.seed, input.background ?? null);
    const resolvedSecondary = input.secondary
        ? resolveSecondary(input.secondary, input.secondaryBackground ?? null)
        : null;
    const seedOklch = rgbToOklch(resolved.effective);
    const achromatic = seedOklch.c < ACHROMATIC_CHROMA;
    const manualSecondary: Rgba | null = resolvedSecondary
        ? resolvedSecondary.effective
        : null;

    const boardInput = {
        strategy: input.strategy,
        seed: resolved.effective,
        seedOklch,
        achromatic,
        manualSecondary,
    };

    return {
        schemaVersion: LAYOUT_SCHEMA_VERSION,
        algorithmVersion: LAYOUT_ALGORITHM_VERSION,
        seed: {
            original: resolved.original,
            effective: resolved.effective,
            background: resolved.background,
            achromatic,
        },
        strategy: input.strategy,
        secondarySource: manualSecondary ? 'manual' : 'auto',
        secondary: resolvedSecondary,
        themes: {
            light: buildBoard({
                ...boardInput,
                theme: 'light',
                overrides: input.overrides?.light,
            }),
            dark: buildBoard({
                ...boardInput,
                theme: 'dark',
                overrides: input.overrides?.dark,
            }),
        },
    };
}
