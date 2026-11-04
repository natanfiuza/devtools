/**
 * Contratos do BadgeForge no cliente (ADR-075).
 *
 * `toolProps` carrega so o que o navegador nao consegue importar: endpoint,
 * registro de logos e limites/defaults. Geometria, metricas e catalogo de
 * cores vem dos JSONs de `resources/badgeforge`, os mesmos lidos pelo PHP.
 */

/** Modo do construtor: badge estatica local ou badge de provider (F4). */
export type BadgeMode = 'static' | 'provider';

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
    personalBadges: BadgeForgePersonalBadgeLimits;
    providers: { maxParamLength: number };
}

/** `limit: null` = ilimitado (pago); `0` = criacao bloqueada. */
export interface BadgeForgePersonalBadgeLimits {
    limit: number | null;
    used: number;
    nameMaxLength: number;
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
    canCreatePersonalBadges: boolean;
    isPaid: boolean;
}

/**
 * Parametro de path de uma definicao de provider (F4, ADR-080). `pattern` e
 * aplicado ao trecho cru; `allowSlash` libera barra (pacote npm com escopo).
 */
export interface BadgeForgeProviderParam {
    name: string;
    required: boolean;
    pattern: string;
    allowSlash: boolean;
    placeholderKey: string;
}

export interface BadgeForgeProviderDefinition {
    slug: string;
    name: string;
    label: string;
    supportsPrivate: boolean;
    examplePath: string;
    pathPattern: string;
    params: BadgeForgeProviderParam[];
}

export interface BadgeForgeProvider {
    slug: string;
    name: string;
    category: string;
    requiresAuth: boolean;
    /** Basic auth (Docker): o PAT privado exige tambem o username. */
    requiresUsername: boolean;
    documentationUrl: string | null;
    definitions: BadgeForgeProviderDefinition[];
}

/** Badge pessoal do dono; o PAT nunca viaja, so o estado da credencial. */
export interface BadgeForgePersonalBadge {
    id: string;
    name: string;
    type: 'static' | 'provider';
    providerSlug: string | null;
    definitionSlug: string | null;
    params: Record<string, string> | null;
    private: boolean;
    credentialSet: boolean;
    credentialUsername: string | null;
    url: string | null;
    pngUrl: string | null;
    lastGeneratedAt: string | null;
    expiresAt: string | null;
    createdAt: string | null;
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
    providerEndpoint: string;
    personalEndpoint: string;
    styles: string[];
    logos: BadgeForgeLogo[];
    fonts: BadgeFont[];
    providers: BadgeForgeProvider[];
    limits: BadgeForgeLimits;
    defaults: BadgeForgeDefaults;
    management: BadgeForgeManagement;
    customLogos: BadgeForgeCustomLogoSummary[];
    presets: BadgeForgePreset[];
    personalBadges: BadgeForgePersonalBadge[];
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
