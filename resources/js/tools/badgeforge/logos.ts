import type { BadgeLogo } from './types';

/**
 * Logos embutidos lidos dos SVG crus de `resources/badgeforge/logos`.
 *
 * Mesmas regexes do `LogoCatalog` no PHP: o primeiro `viewBox` do `<svg>` e o
 * primeiro `path d`. Arquivo fora desse formato e ignorado - o badge segue sem
 * logo, como no servidor.
 */
const files = import.meta.glob('../../../badgeforge/logos/*.svg', {
    query: '?raw',
    import: 'default',
    eager: true,
}) as Record<string, string>;

const logos: Record<string, BadgeLogo> = {};

function extractRule(
    attributes: string,
    attribute: 'fill-rule' | 'clip-rule',
): 'nonzero' | 'evenodd' | null {
    const match = new RegExp(
        `\\b${attribute}="(nonzero|evenodd)"`,
        'i',
    ).exec(attributes);

    if (match === null || match[1] === undefined) {
        return null;
    }

    return match[1].toLowerCase() as 'nonzero' | 'evenodd';
}

for (const [path, svg] of Object.entries(files)) {
    const slug = path
        .split('/')
        .pop()
        ?.replace(/\.svg$/i, '');

    if (slug === undefined) {
        continue;
    }

    const viewBox = /<svg\b[^>]*\bviewBox="([^"]+)"/i.exec(svg);

    if (viewBox === null || viewBox[1] === undefined) {
        continue;
    }

    const paths: BadgeLogo['paths'] = [];
    let invalidPath = false;

    for (const pathMatch of svg.matchAll(/<path\b([^>]*)>/gi)) {
        const attributes = pathMatch[1] ?? '';
        const dMatch = /\bd="([^"]+)"/i.exec(attributes);

        if (dMatch === null || dMatch[1] === undefined) {
            invalidPath = true;
            break;
        }

        paths.push({
            d: dMatch[1],
            fillRule: extractRule(attributes, 'fill-rule'),
            clipRule: extractRule(attributes, 'clip-rule'),
        });
    }

    if (!invalidPath && paths.length > 0) {
        logos[slug] = {
            slug,
            viewBox: viewBox[1],
            paths,
        };
    }
}

export function logoCatalog(): Record<string, BadgeLogo> {
    return logos;
}

export function logoAsset(slug: string | null): BadgeLogo | null {
    if (slug === null) {
        return null;
    }

    return logos[slug.toLowerCase().trim()] ?? null;
}
