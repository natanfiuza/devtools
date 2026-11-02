import { describe, expect, it } from 'vitest';
import { fontAsset } from '@/tools/badgeforge/lib/fonts';
import { renderBadge } from '@/tools/badgeforge/lib/render';
import { styleById } from '@/tools/badgeforge/lib/styles';
import { logoAsset } from '@/tools/badgeforge/logos';
import cases from '../../../tests/Fixtures/badgeforge/cases.json';
import toolProps from '../../../tests/Fixtures/badgeforge/tool-props.json';
import type { BadgeForgeToolProps, BadgeSpec } from '@/tools/badgeforge/types';

/**
 * Paridade byte a byte com o renderer PHP (ADR-075).
 *
 * As fixtures sao as mesmas de `tests/Unit/BadgeForge/StaticBadgeRendererTest`:
 * se o espelho TypeScript divergir em um atributo, uma casa decimal ou uma
 * ordem de tag, este teste e o do Pest quebram juntos.
 */

const expectedFiles = import.meta.glob(
    '../../../tests/Fixtures/badgeforge/expected/*.svg',
    { query: '?raw', import: 'default', eager: true },
) as Record<string, string>;

const PROPS = toolProps as BadgeForgeToolProps;

interface FixtureCase {
    name: string;
    spec: {
        label: string;
        message: string;
        style: string;
        font?: string | null;
        labelColor: { hex: string; alpha: number };
        messageColor: { hex: string; alpha: number };
        logo: string | null;
        logoColor: { hex: string; alpha: number };
        logoSize: number;
        logoPosition: 'left' | 'right';
        link: string | null;
        cacheSeconds: number;
    };
}

function specFrom(data: FixtureCase['spec']): BadgeSpec {
    return {
        label: data.label,
        message: data.message,
        style: styleById(data.style),
        font: fontAsset(data.font ?? null, PROPS),
        labelColor: { ...data.labelColor },
        messageColor: { ...data.messageColor },
        logo: data.logo === null ? null : logoAsset(data.logo),
        logoColor: { ...data.logoColor },
        logoSize: data.logoSize,
        logoPosition: data.logoPosition,
        link: data.link,
        cacheSeconds: data.cacheSeconds,
    };
}

function expectedFor(name: string): string {
    const entry = Object.entries(expectedFiles).find(([path]) =>
        path.endsWith(`/${name}.svg`),
    );

    if (entry === undefined) {
        throw new Error(`fixture ausente: ${name}`);
    }

    return entry[1];
}

describe('renderBadge', () => {
    const fixtures = cases.cases as FixtureCase[];

    it.each(fixtures.map((item) => [item.name, item] as const))(
        'reproduz a fixture %s byte a byte',
        (name, fixture) => {
            expect(renderBadge(specFrom(fixture.spec))).toBe(expectedFor(name));
        },
    );

    it('produz o mesmo SVG em renders repetidos', () => {
        const spec = specFrom(fixtures[0]!.spec);

        expect(renderBadge(spec)).toBe(renderBadge(spec));
    });

    it('inclui role, title e aria-label', () => {
        const svg = renderBadge(specFrom(fixtures[0]!.spec));

        expect(svg).toContain('role="img"');
        expect(svg).toContain('<title>build: passing</title>');
        expect(svg).toContain('aria-label="build: passing"');
    });

    it('escapa o texto e nunca emite script', () => {
        const fixture = fixtures.find((item) => item.name === 'xss_text');
        const svg = renderBadge(specFrom(fixture!.spec));

        expect(svg).not.toContain('<script');
        expect(svg).not.toContain('javascript:');
        expect(svg).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    });

    it('tem um logo cru para cada slug do toolProps', () => {
        for (const logo of toolProps.logos) {
            expect(logoAsset(logo.slug)).not.toBeNull();
        }
    });
});
