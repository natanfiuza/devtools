import { describe, expect, it } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { rolesFromScaleItems } from '@/lib/palettePayload';
import {
    buildPrototype,
    PROTOTYPE_SIZES,
    PROTOTYPE_TEMPLATES,
} from '@/tools/palette-generator/prototype/defaults';
import { readableOn } from '@/tools/palette-generator/prototype/renderSvg';
import type { PrototypeCopy } from '@/tools/palette-generator/prototype/types';
import type { PaletteRoles } from '@/types/colors';

/**
 * Catalogo e montagem dos prototipos (F4, ADR-126).
 *
 * Tamanhos fixos (site 1200x900, celular 390x844, OG 1200x630), documento com
 * o fundo do papel `background` e texto legivel sobre qualquer preenchimento.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));

const ROLES: PaletteRoles = rolesFromScaleItems(
    SCALE.tones.map((tone) => ({ hex: tone.hex, tone: tone.tone })),
);

const COPY: PrototypeCopy = {
    brand: 'Acme',
    nav: ['Product', 'Features', 'Pricing'],
    heading: 'Design that feels consistent',
    body: 'Every screen uses the same color roles.',
    primaryAction: 'Get started',
    secondaryAction: 'See features',
    links: ['Documentation', 'Changelog', 'Support'],
    sectionTitle: 'Built for teams',
    cards: ['Fast', 'Consistent', 'Accessible'],
    cardBody: 'The same roles on every screen.',
    cardLink: 'Learn more',
    formTitle: 'Try it with your team',
    formLabel: 'Work email',
    formPlaceholder: 'you@company.com',
    formAction: 'Invite',
    footer: '© Acme — sample interface',
    tabs: ['Home', 'Search', 'Profile'],
    tagline: 'A palette preview generated in your browser',
};

describe('Prototipos: catalogo', () => {
    it('publica os tamanhos dos tres documentos', () => {
        expect(PROTOTYPE_SIZES.site).toEqual({ width: 1200, height: 900 });
        expect(PROTOTYPE_SIZES.mobile).toEqual({ width: 390, height: 844 });
        expect(PROTOTYPE_SIZES.og).toEqual({ width: 1200, height: 630 });
        expect(PROTOTYPE_TEMPLATES).toEqual(['site', 'mobile']);
    });

    it('monta um documento por template com o fundo do papel background', () => {
        for (const kind of ['site', 'mobile', 'og'] as const) {
            const document = buildPrototype({
                kind,
                theme: 'dark',
                roles: ROLES.dark,
                copy: COPY,
                label: `Preview ${kind}`,
            });

            expect(document.kind).toBe(kind);
            expect(document.theme).toBe('dark');
            expect(document.width).toBe(PROTOTYPE_SIZES[kind].width);
            expect(document.height).toBe(PROTOTYPE_SIZES[kind].height);
            expect(document.label).toBe(`Preview ${kind}`);
            expect(document.nodes.length).toBeGreaterThan(10);

            const first = document.nodes[0];

            expect(first).toMatchObject({
                kind: 'rect',
                fill: ROLES.dark.background,
                role: 'background',
                section: 'canvas',
            });
        }
    });

    it('escolhe texto legivel sobre qualquer preenchimento', () => {
        expect(readableOn('#ffffff')).toBe('#111111');
        expect(readableOn('#fef3c7')).toBe('#111111');
        expect(readableOn('#111111')).toBe('#ffffff');
        expect(readableOn('#1e1b4b')).toBe('#ffffff');
        // Entrada que nao e cor: branco e a escolha conservadora.
        expect(readableOn('nao-e-cor')).toBe('#ffffff');
    });
});
