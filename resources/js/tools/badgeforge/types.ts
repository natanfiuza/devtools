/**
 * Contratos do BadgeForge no cliente (ADR-075).
 *
 * `toolProps` carrega so o que o navegador nao consegue importar: endpoint,
 * registro de logos e limites/defaults. Geometria, metricas e catalogo de
 * cores vem dos JSONs de `resources/badgeforge`, os mesmos lidos pelo PHP.
 */

export interface BadgeForgeLogo {
    slug: string;
    name: string;
}

export interface BadgeForgeLimits {
    maxTextLength: number;
    logoSize: { min: number; max: number };
    cacheSeconds: { default: number; min: number; max: number };
    linkMaxLength: number;
}

export interface BadgeForgeDefaults {
    style: string;
    color: string;
    labelColor: string;
    logoColor: string;
    logoPosition: 'left' | 'right';
}

export interface BadgeForgeToolProps {
    endpoint: string;
    styles: string[];
    logos: BadgeForgeLogo[];
    limits: BadgeForgeLimits;
    defaults: BadgeForgeDefaults;
}

/** Cor resolvida: hexadecimal canonico (#rrggbb) e alfa entre 0 e 1. */
export interface BadgeColor {
    hex: string;
    alpha: number;
}

/** Logo embutido ja extraido do SVG de `resources/badgeforge/logos`. */
export interface BadgeLogo {
    slug: string;
    viewBox: string;
    pathData: string;
}

/** Geometria resolvida de um estilo (espelho de `App\Support\BadgeForge\Style`). */
export interface BadgeStyle {
    id: string;
    height: number;
    radius: number;
    padding: number;
    gap: number;
    fontSize: number;
    fontWeight: number;
    uppercase: boolean;
    letterSpacing: number;
    baseline: number;
    logoSize: number;
    fontFamily: string;
    metricsFamily: string;
    gradient: string | null;
    textColor: BadgeColor;
    labelColor: BadgeColor | null;
    messageColor: BadgeColor | null;
    bottomShadow: { height: number; color: string; opacity: string } | null;
    gradientStops: Array<{ offset: string; color: string; opacity: string }>;
}

/** Pedido normalizado, pronto para o renderer (espelho de `BadgeSpec`). */
export interface BadgeSpec {
    label: string;
    message: string;
    style: BadgeStyle;
    labelColor: BadgeColor;
    messageColor: BadgeColor;
    logo: BadgeLogo | null;
    logoColor: BadgeColor;
    logoSize: number;
    logoPosition: 'left' | 'right';
    link: string | null;
    cacheSeconds: number;
}
