import { describe, expect, it } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { rolesFromScaleItems } from '@/lib/palettePayload';
import { buildPrototype } from '@/tools/palette-generator/prototype/defaults';
import type {
    PrototypeCopy,
    PrototypeDocument,
    SvgNode,
    SvgTextNode,
} from '@/tools/palette-generator/prototype/types';
import type { PaletteRoles } from '@/types/colors';

/**
 * Tamanhos, regioes e limites dos templates (F4, ADR-126).
 *
 * O site precisa das sete regioes pedidas pelo prompt da F4 (cabecalho, hero,
 * botoes, links, cards, formulario e rodape). O celular navega por tabs no
 * topo e **nao** tem bottom nav; nenhum elemento pode sair da tela.
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

const COLORS = SCALE.tones.map((tone) => tone.hex);

function documentOf(
    kind: 'site' | 'mobile' | 'og',
    colors?: string[],
): PrototypeDocument {
    return buildPrototype({
        kind,
        theme: 'light',
        roles: ROLES.light,
        copy: COPY,
        label: 'Preview',
        colors,
    });
}

function sectionsOf(nodes: SvgNode[]): string[] {
    return [
        ...new Set(
            nodes
                .map((node) => node.section)
                .filter((section): section is NonNullable<typeof section> => section !== undefined),
        ),
    ];
}

/**
 * Caixa de rect/circle. Texto fica de fora: a largura real depende das
 * metricas da fonte, e a checagem util para ele e outra (ponto de ancoragem
 * dentro da tela).
 */
function boundsOf(node: SvgNode): {
    x: number;
    y: number;
    width: number;
    height: number;
} | null {
    if (node.kind === 'rect') {
        return {
            x: node.x,
            y: node.y,
            width: node.width,
            height: node.height,
        };
    }

    if (node.kind === 'circle') {
        return {
            x: node.cx - node.radius,
            y: node.cy - node.radius,
            width: node.radius * 2,
            height: node.radius * 2,
        };
    }

    return null;
}

describe('Prototipo de site', () => {
    const document = documentOf('site');
    const sections = sectionsOf(document.nodes);

    it('tem 1200x900 e todas as regioes do prompt', () => {
        expect(document.width).toBe(1200);
        expect(document.height).toBe(900);

        for (const section of [
            'header',
            'hero',
            'buttons',
            'links',
            'cards',
            'form',
            'footer',
        ]) {
            expect(sections).toContain(section);
        }
    });

    it('nenhum elemento sai da tela', () => {
        for (const node of document.nodes) {
            const bounds = boundsOf(node);

            if (node.kind === 'text') {
                expect(node.x).toBeGreaterThanOrEqual(0);
                expect(node.x).toBeLessThanOrEqual(1200);
                expect(node.y).toBeGreaterThanOrEqual(0);
                expect(node.y).toBeLessThanOrEqual(900);

                continue;
            }

            if (bounds === null) {
                continue;
            }

            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.y).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(1200);
            expect(bounds.y + bounds.height).toBeLessThanOrEqual(900);
        }
    });

    it('pinta os botoes com os papeis primary e secondary', () => {
        const buttons = document.nodes.filter(
            (node) => node.section === 'buttons' && node.kind === 'rect',
        );

        expect(buttons.map((node) => node.role).sort()).toEqual([
            'primary',
            'secondary',
        ]);
    });
});

describe('Prototipo de celular', () => {
    const document = documentOf('mobile');

    it('tem 390x844 e navega por tabs no topo, sem bottom nav', () => {
        expect(document.width).toBe(390);
        expect(document.height).toBe(844);

        const tabs = document.nodes.filter(
            (node): node is SvgTextNode =>
                node.section === 'tabs' && node.kind === 'text',
        );

        expect(tabs).toHaveLength(3);

        for (const tab of tabs) {
            expect(tab.y).toBeLessThan(844 / 2);
        }

        // Nenhuma regiao de navegacao no rodape e nenhum texto de tab repetido
        // embaixo: o rodape e so o texto institucional.
        expect(sectionsOf(document.nodes)).not.toContain('bottom_nav');
        expect(
            document.nodes.some(
                (node) =>
                    node.kind === 'text' &&
                    (COPY.tabs as readonly string[]).includes(node.text) &&
                    node.section !== 'tabs',
            ),
        ).toBe(false);
    });

    it('nenhum elemento sai da tela', () => {
        for (const node of document.nodes) {
            const bounds = boundsOf(node);

            if (node.kind === 'text') {
                expect(node.x).toBeGreaterThanOrEqual(0);
                expect(node.x).toBeLessThanOrEqual(390);
                expect(node.y).toBeGreaterThanOrEqual(0);
                expect(node.y).toBeLessThanOrEqual(844);

                continue;
            }

            if (bounds === null) {
                continue;
            }

            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(390);
            expect(bounds.y + bounds.height).toBeLessThanOrEqual(844);
        }
    });
});

describe('Prototipo de OG', () => {
    it('tem 1200x630 com a faixa da escala', () => {
        const document = documentOf('og', COLORS);

        expect(document.width).toBe(1200);
        expect(document.height).toBe(630);

        const strip = document.nodes.filter(
            (node) => node.section === 'palette',
        );

        expect(strip).toHaveLength(11);
        expect(strip.map((node) => node.fill)).toEqual(COLORS);
    });

    it('sem a escala da paleta, usa os seis papeis principais', () => {
        const document = documentOf('og');
        const strip = document.nodes.filter(
            (node) => node.section === 'palette',
        );

        expect(strip).toHaveLength(6);
        expect(strip[0]?.fill).toBe(ROLES.light.primary);
        expect(strip[2]?.fill).toBe(ROLES.light.accent);
    });
});
