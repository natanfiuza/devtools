import data from '../../../../badgeforge/styles.json';
import { parseColor, parseColorOr } from './color';
import type { BadgeColor, BadgeStyle } from '../types';

/**
 * Espelho de `App\Support\BadgeForge\StyleCatalog` (ADR-075).
 *
 * A geometria inteira vem de `resources/badgeforge/styles.json` - o mesmo
 * arquivo lido pelo PHP. Estilo desconhecido cai no default, como no servidor.
 */

interface RawStyle {
    height?: number;
    radius?: number;
    padding?: number;
    gap?: number;
    font_size?: number;
    font_weight?: number;
    uppercase?: boolean;
    letter_spacing?: number;
    baseline?: number;
    logo_size?: number;
    font_family?: string;
    metrics_family?: string;
    gradient?: string | null;
    text_color?: string | null;
    label_color?: string | null;
    message_color?: string | null;
    bottom_shadow?: {
        height?: number;
        color?: string;
        opacity?: string;
    } | null;
}

interface RawData {
    default?: string;
    order?: string[];
    gradients?: Record<
        string,
        Array<{ offset: string; color: string; opacity: string }>
    >;
    styles?: Record<string, RawStyle>;
}

const styles = data as RawData;

/** Ids na ordem do `order`, como `StyleCatalog::ids()`. */
export function styleIds(): string[] {
    return (styles.order ?? []).filter(
        (id) => styles.styles?.[id] !== undefined,
    );
}

export function defaultStyleId(): string {
    const fallback = styles.default ?? 'flat';

    return styles.styles?.[fallback] !== undefined ? fallback : 'flat';
}

export function styleById(id: string | null | undefined): BadgeStyle {
    const key = typeof id === 'string' ? id.toLowerCase().trim() : '';
    const stylesMap = styles.styles ?? {};
    const resolved =
        key !== '' && stylesMap[key] !== undefined ? key : defaultStyleId();
    const raw = stylesMap[resolved] ?? {};
    const shadow = raw.bottom_shadow ?? null;

    return {
        id: resolved,
        height: raw.height ?? 20,
        radius: raw.radius ?? 0,
        padding: raw.padding ?? 6,
        gap: raw.gap ?? 2,
        fontSize: raw.font_size ?? 11,
        fontWeight: raw.font_weight ?? 600,
        uppercase: raw.uppercase ?? false,
        letterSpacing: raw.letter_spacing ?? 0,
        baseline: raw.baseline ?? 14,
        logoSize: raw.logo_size ?? 10,
        fontFamily: raw.font_family ?? 'Verdana,DejaVu Sans,sans-serif',
        metricsFamily: raw.metrics_family ?? 'sans',
        gradient: raw.gradient ?? null,
        textColor: parseColorOr(raw.text_color ?? null, '#ffffff'),
        labelColor: parseStyleColor(raw.label_color),
        messageColor: parseStyleColor(raw.message_color),
        bottomShadow: shadow
            ? {
                  height: shadow.height ?? 1,
                  color: shadow.color ?? '#000000',
                  opacity: shadow.opacity ?? '.15',
              }
            : null,
        gradientStops:
            raw.gradient != null
                ? (styles.gradients?.[raw.gradient] ?? [])
                : [],
    };
}

function parseStyleColor(value: string | null | undefined): BadgeColor | null {
    return value == null ? null : parseColor(value);
}
