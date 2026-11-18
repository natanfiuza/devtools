import type { ContrastVerdict, Oklch, Rgb, Rgba } from '@/lib/color';

/**
 * Contrato do motor do Layout Harmonizer (ADR-146, ADR-147, ADR-166).
 *
 * Puro: sem Vue, DOM, i18n, storage ou aleatoriedade. A mesma entrada gera
 * sempre o mesmo quadro, nos dois temas, e a versao do algoritmo acompanha a
 * tabela de parametros aprovada em docs/colors/layout-harmonizer/PARAMETROS.md.
 */

export const LAYOUT_SCHEMA_VERSION = 1;

/** Sobe quando qualquer numero de `params.ts` mudar o resultado. */
export const LAYOUT_ALGORITHM_VERSION = 1;

export const LAYOUT_THEMES = ['light', 'dark'] as const;

export type LayoutTheme = (typeof LAYOUT_THEMES)[number];

export const LAYOUT_STRATEGIES = [
    'analogous',
    'complementary',
    'triadic',
    'monochromatic',
] as const;

export type LayoutStrategy = (typeof LAYOUT_STRATEGIES)[number];

export type LayoutTokenFamily =
    'brand' | 'surface' | 'feedback' | 'content' | 'interaction';

/**
 * Catalogo de 33 tokens: os 29 do quadro base mais os 4 do Accent triadico.
 * O catalogo e estavel entre estrategias (ADR-163); o que muda e a derivacao.
 */
export type LayoutTokenId =
    | 'primary'
    | 'primary-variant'
    | 'secondary'
    | 'secondary-variant'
    | 'surface'
    | 'surface-variant'
    | 'background'
    | 'hairline'
    | 'error'
    | 'success'
    | 'warning'
    | 'info'
    | 'on-primary'
    | 'on-secondary'
    | 'on-background'
    | 'on-surface'
    | 'on-surface-variant'
    | 'on-error'
    | 'on-success'
    | 'on-warning'
    | 'on-info'
    | 'primary-hover'
    | 'primary-active'
    | 'secondary-hover'
    | 'secondary-active'
    | 'link'
    | 'link-hover'
    | 'link-active'
    | 'focus-ring'
    | 'accent'
    | 'on-accent'
    | 'accent-hover'
    | 'accent-active';

/**
 * Proveniencia do valor: a seed efetiva exata, uma escolha manual, a regra da
 * tabela ou a familia fixa de feedback (independente da seed).
 */
export type LayoutTokenSource = 'seed' | 'manual' | 'rule' | 'feedback';

export interface LayoutToken {
    id: LayoutTokenId;
    family: LayoutTokenFamily;
    theme: LayoutTheme;
    /** sRGB efetivo, sempre opaco: o alpha da seed e composto antes de gerar. */
    rgba: Rgba;
    /** OKLCH efetivo, depois do mapeamento para sRGB. */
    oklch: Oklch;
    /** HEX de 6 digitos. */
    hex: string;
    /** O croma pedido nao coube no sRGB e foi reduzido. */
    clipped: boolean;
    source: LayoutTokenSource;
}

export interface LayoutPairCheck {
    foreground: LayoutTokenId;
    background: LayoutTokenId;
    verdict: ContrastVerdict;
}

export interface LayoutThemeBoard {
    theme: LayoutTheme;
    tokens: Record<LayoutTokenId, LayoutToken>;
    /** Pares de conteudo medidos contra o fundo correspondente. */
    checks: LayoutPairCheck[];
}

export interface LayoutSeedInfo {
    /** Cor informada, com alpha e a sintaxe de origem preservados. */
    original: Rgba;
    /** Cor efetiva usada na geracao; sempre opaca. */
    effective: Rgba;
    /** Fundo escolhido para compor o alpha; `null` quando a seed e opaca. */
    background: Rgb | null;
    /** Croma abaixo de 0.02: sem matiz real, sem matiz aleatorio. */
    achromatic: boolean;
}

/**
 * Valores manuais por tema e token (F3). O override vale exatamente para o
 * token informado: nao recalcula derivados, e o contraste e medido depois dele.
 */
export type LayoutOverrides = Partial<
    Record<LayoutTheme, Partial<Record<LayoutTokenId, Rgb>>>
>;

export interface GenerateLayoutInput {
    seed: Rgba;
    strategy: LayoutStrategy;
    /**
     * Obrigatorio quando a seed tem alpha. Nunca existe fundo padrao: a
     * composicao precisa ser explicita (ADR-149).
     */
    background?: Rgb | null;
    /** Secundaria manual opaca; ausente = derivada da estrategia. */
    secondary?: Rgb | null;
    /**
     * Overrides manuais por tema; ausente ou vazio devolve o quadro automatico.
     * Precedencia: override por token > secundaria manual > derivacao.
     */
    overrides?: LayoutOverrides;
}

export interface LayoutSystemResult {
    schemaVersion: typeof LAYOUT_SCHEMA_VERSION;
    algorithmVersion: typeof LAYOUT_ALGORITHM_VERSION;
    seed: LayoutSeedInfo;
    strategy: LayoutStrategy;
    secondarySource: 'auto' | 'manual';
    themes: Record<LayoutTheme, LayoutThemeBoard>;
}

export type LayoutEngineErrorCode =
    'invalid-strategy' | 'seed-alpha-without-background';

export class LayoutEngineError extends Error {
    constructor(
        readonly code: LayoutEngineErrorCode,
        message: string,
    ) {
        super(message);
        this.name = 'LayoutEngineError';
    }
}
