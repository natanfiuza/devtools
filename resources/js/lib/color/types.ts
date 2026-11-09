/**
 * Tipos do nucleo de cor (ADR-117).
 *
 * O estado autoritativo e sRGB + alpha (`Rgba`): todo o resto - HSL, HSV,
 * OKLab, OKLCH - e conversao. Alpha fica sempre em 0..1, mesmo quando a
 * entrada usa 0..255 ou porcentagem.
 */

/** Canal sRGB em 0..255, sem arredondar enquanto o valor circula. */
export interface Rgb {
    r: number;
    g: number;
    b: number;
}

/** sRGB com alpha em 0..1. */
export interface Rgba extends Rgb {
    a: number;
}

/** Canal linear (0..1), o espaco em que OKLab e WCAG trabalham. */
export interface LinearRgb {
    r: number;
    g: number;
    b: number;
}

/** HSL: h 0..360, s e l 0..100. */
export interface Hsl {
    h: number;
    s: number;
    l: number;
}

/** HSV: h 0..360, s e v 0..100. */
export interface Hsv {
    h: number;
    s: number;
    v: number;
}

/** OKLab: L 0..1 e a/b aproximadamente -0.4..0.4. */
export interface Oklab {
    l: number;
    a: number;
    b: number;
}

/** OKLCH: L 0..1, C >= 0 e h 0..360. */
export interface Oklch {
    l: number;
    c: number;
    h: number;
}

/** Sintaxe de origem, para a interface dizer de onde a cor veio. */
export type ColorSyntax = 'hex' | 'rgb' | 'hsl' | 'oklch' | 'oklab' | 'name';

export interface ParsedColor {
    rgba: Rgba;
    syntax: ColorSyntax;
    /**
     * Texto original quando a entrada era OKLCH/OKLab fora do gamut sRGB -
     * fica so como metadado e nunca volta ao estado mutavel. `null` quando a
     * entrada ja estava no gamut.
     */
    sourceValue: string | null;
    /** A entrada foi reduzida de croma para caber em sRGB (ADR-117). */
    outOfGamut: boolean;
}

export type ParseErrorCode = 'empty' | 'syntax' | 'range' | 'unknown_name';

export type ParseResult =
    { ok: true; color: ParsedColor } | { ok: false; code: ParseErrorCode };

/** Resultado do mapeamento de gamut. */
export interface GamutResult {
    /** Cor sRGB resultante. */
    rgb: Rgb;
    /** OKLCH efetivo, com o croma reduzido quando houve corte. */
    oklch: Oklch;
    /** `true` quando o croma foi reduzido. */
    clipped: boolean;
}

/** Classificacao WCAG, sempre decidida no valor cru da razao. */
export interface ContrastVerdict {
    ratio: number;
    aaNormal: boolean;
    aaaNormal: boolean;
    aaLarge: boolean;
    aaaLarge: boolean;
}

export interface ContrastReport {
    background: Rgb;
    black: ContrastVerdict;
    white: ContrastVerdict;
}

/** Formatos de exibicao/copia; o rotulo e do i18n, nunca daqui. */
export type ColorFormat =
    | 'hex'
    | 'hex8'
    | 'rgb'
    | 'rgba'
    | 'hsl'
    | 'hsla'
    | 'oklch'
    | 'oklab'
    | 'name';
