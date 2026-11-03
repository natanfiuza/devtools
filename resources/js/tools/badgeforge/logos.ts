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

for (const [path, svg] of Object.entries(files)) {
    const slug = path
        .split('/')
        .pop()
        ?.replace(/\.svg$/i, '');

    if (slug === undefined) {
        continue;
    }

    const viewBox = /<svg\b[^>]*\bviewBox="([^"]+)"/i.exec(svg);
    const pathData = /<path\b[^>]*\bd="([^"]+)"/i.exec(svg);

    if (
        viewBox !== null &&
        pathData !== null &&
        viewBox[1] !== undefined &&
        pathData[1] !== undefined
    ) {
        logos[slug] = {
            slug,
            viewBox: viewBox[1],
            paths: [{ d: pathData[1], fillRule: null, clipRule: null }],
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
