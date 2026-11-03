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

/** Fonte empacotada (ADR-077); `stack` e o `font-family` do SVG. */
export interface BadgeFont {
    slug: string;
    name: string;
    stack: string;
}

export interface BadgeForgeLimits {
    maxTextLength: number;
    logoSize: { min: number; max: number };
    cacheSeconds: { default: number; min: number; max: number };
    linkMaxLength: number;
    png: { maxWidth: number };
    customLogos: BadgeForgeCustomLogoLimits;
    presets: BadgeForgePresetLimits;
}

/** `limit: null` = ilimitado; `0` = gestao indisponivel (convidado). */
export interface BadgeForgeCustomLogoLimits {
    limit: number | null;
    used: number;
    maxBytes: number;
    maxPaths: number;
    maxPathLength: number;
    maxTotalPathLength: number;
    maxCacheSeconds: number;
    nameMaxLength: number;
}

export interface BadgeForgePresetLimits {
    limit: number | null;
    used: number;
    nameMaxLength: number;
}

export interface BadgeForgeManagement {
    canManage: boolean;
    canSavePresets: boolean;
    isPaid: boolean;
}

/** Metadados de um logo personalizado; os paths vem sob demanda (ADR-078). */
export interface BadgeForgeCustomLogoSummary {
    id: string;
    slug: string;
    name: string;
    viewBox: string;
    pathCount: number;
    sizeBytes: number;
}

/** Snapshot normalizado do formulario que o preset guarda. */
export interface BadgeForgePresetForm {
    label: string;
    message: string;
    style: string;
    font: string | null;
    color: string;
    labelColor: string;
    logo: string;
    logoColor: string;
    logoSize: number;
    logoPosition: 'left' | 'right';
    link: string;
    cacheSeconds: number;
}

export interface BadgeForgePreset {
    id: string;
    name: string;
    form: BadgeForgePresetForm;
    logoOrphaned: boolean;
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
    fonts: BadgeFont[];
    limits: BadgeForgeLimits;
    defaults: BadgeForgeDefaults;
    management: BadgeForgeManagement;
    customLogos: BadgeForgeCustomLogoSummary[];
    presets: BadgeForgePreset[];
}

/** Cor resolvida: hexadecimal canonico (#rrggbb) e alfa entre 0 e 1. */
export interface BadgeColor {
    hex: string;
    alpha: number;
}

/** Um path ja validado (espelho de `App\Support\BadgeForge\LogoPath`). */
export interface BadgeLogoPath {
    d: string;
    fillRule: 'nonzero' | 'evenodd' | null;
    clipRule: 'nonzero' | 'evenodd' | null;
}

/**
 * Logo pronto para o renderer: embutido (`custom: false`, um path) ou
 * personalizado (`custom: true`, varios paths). Ver ADR-078.
 */
export interface BadgeLogo {
    slug: string;
    viewBox: string;
    paths: BadgeLogoPath[];
    custom?: boolean;
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
    font: BadgeFont | null;
    labelColor: BadgeColor;
    messageColor: BadgeColor;
    logo: BadgeLogo | null;
    logoColor: BadgeColor;
    logoSize: number;
    logoPosition: 'left' | 'right';
    link: string | null;
    cacheSeconds: number;
}
