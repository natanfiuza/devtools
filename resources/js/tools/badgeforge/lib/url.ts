import { parseColorOr } from './color';
import type { BadgeColor, BadgeForgeToolProps, BadgeSpec } from '../types';

/**
 * URL canonica do endpoint (ADR-076).
 *
 * Cada segmento viaja codificado para o `BadgeRequest::decodeText` do outro
 * lado: `_` vira `__`, espaco vira `_` e `/` vira `%252F` (o router decodifica
 * o path antes do match, entao a barra precisa chegar dupla-codificada). A
 * query leva so o que difere do padrao.
 */

export function encodeSegment(text: string): string {
    let encoded = '';

    for (const char of Array.from(text)) {
        if (char === '_') {
            encoded += '__';
        } else if (char === ' ') {
            encoded += '_';
        } else if (char === '/') {
            encoded += '%252F';
        } else {
            encoded += encodeURIComponent(char);
        }
    }

    return encoded;
}

export function buildBadgeUrl(
    origin: string,
    endpoint: string,
    spec: BadgeSpec,
    props: BadgeForgeToolProps,
): string {
    const defaults = props.defaults;
    const base = endpoint.replace(/\/+$/, '');
    const path = `${base}/${encodeSegment(spec.label)}/${encodeSegment(spec.message)}.svg`;
    const params: string[] = [];

    if (spec.style.id !== defaults.style) {
        params.push(`style=${encodeURIComponent(spec.style.id)}`);
    }

    // O estilo social fixa as duas cores: manda-las na URL nao mudaria nada.
    if (spec.style.messageColor === null) {
        pushColor(params, 'color', spec.messageColor, defaults.color);
    }

    if (spec.style.labelColor === null) {
        pushColor(params, 'labelColor', spec.labelColor, defaults.labelColor);
    }

    if (spec.logo !== null) {
        params.push(`logo=${encodeURIComponent(spec.logo.slug)}`);
    }

    pushColor(params, 'logoColor', spec.logoColor, defaults.logoColor);

    if (spec.logo !== null && spec.logoSize !== spec.style.logoSize) {
        params.push(`logoSize=${spec.logoSize}`);
    }

    if (spec.logoPosition === 'right') {
        params.push('logoPosition=right');
    }

    if (spec.link !== null) {
        params.push(`link=${encodeURIComponent(spec.link)}`);
    }

    if (spec.cacheSeconds !== props.limits.cacheSeconds.default) {
        params.push(`cacheSeconds=${spec.cacheSeconds}`);
    }

    const query = params.length > 0 ? `?${params.join('&')}` : '';

    return `${origin.replace(/\/+$/, '')}${path}${query}`;
}

function pushColor(
    params: string[],
    key: string,
    color: BadgeColor,
    fallback: string,
): void {
    const serialized = serializeColor(color);

    if (serialized === serializeColor(parseColorOr(fallback, '#000000'))) {
        return;
    }

    params.push(`${key}=${encodeURIComponent(serialized)}`);
}

/** `#rrggbb`, com `aa` em hexadecimal quando ha transparencia. */
export function serializeColor(color: BadgeColor): string {
    if (color.alpha >= 1) {
        return color.hex;
    }

    const byte = Math.round(color.alpha * 255)
        .toString(16)
        .padStart(2, '0');

    return `${color.hex}${byte}`;
}
