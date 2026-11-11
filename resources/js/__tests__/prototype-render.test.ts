import { describe, expect, it } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { PALETTE_ROLES, rolesFromScaleItems } from '@/lib/palettePayload';
import { buildPrototype } from '@/tools/palette-generator/prototype/defaults';
import { prototypeFontCss } from '@/tools/palette-generator/prototype/fonts';
import {
    escapeXml,
    readableOn,
    renderSvg,
} from '@/tools/palette-generator/prototype/renderSvg';
import type { PrototypeCopy } from '@/tools/palette-generator/prototype/types';
import type { PaletteRole, PaletteRoles } from '@/types/colors';

/**
 * Serializacao do SVG do prototipo (F4, ADR-126).
 *
 * Os snapshots fixam site, celular e OG nos dois temas: qualquer mudanca de
 * desenho aparece no diff. As asseroes cobrem o que o snapshot nao explica:
 * determinismo, `data-role` -> cor do papel, escaping de XML e a fonte embutida
 * na exportacao.
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

function svgOf(
    kind: 'site' | 'mobile' | 'og',
    theme: 'light' | 'dark',
    copy: PrototypeCopy = COPY,
): string {
    return renderSvg(
        buildPrototype({
            kind,
            theme,
            roles: ROLES[theme],
            copy,
            label: 'Prototype preview',
            colors: kind === 'og' ? COLORS : undefined,
        }),
    );
}

describe('Prototipos: SVG', () => {
    it('e deterministico para a mesma entrada', () => {
        expect(svgOf('site', 'light')).toBe(svgOf('site', 'light'));
        expect(svgOf('mobile', 'dark')).toBe(svgOf('mobile', 'dark'));
    });

    it('casa o snapshot do site no tema claro', () => {
        expect(svgOf('site', 'light')).toMatchSnapshot();
    });

    it('casa o snapshot do site no tema escuro', () => {
        expect(svgOf('site', 'dark')).toMatchSnapshot();
    });

    it('casa o snapshot do celular no tema claro', () => {
        expect(svgOf('mobile', 'light')).toMatchSnapshot();
    });

    it('casa o snapshot do celular no tema escuro', () => {
        expect(svgOf('mobile', 'dark')).toMatchSnapshot();
    });

    it('casa o snapshot da OG no tema claro', () => {
        expect(svgOf('og', 'light')).toMatchSnapshot();
    });

    it('casa o snapshot da OG no tema escuro', () => {
        expect(svgOf('og', 'dark')).toMatchSnapshot();
    });

    it('desenha cada elemento com a cor do papel', () => {
        const parsed = new DOMParser().parseFromString(
            svgOf('site', 'light'),
            'image/svg+xml',
        );
        const marked = Array.from(parsed.querySelectorAll('[data-role]'));

        expect(marked.length).toBeGreaterThan(30);

        for (const element of marked) {
            expect(PALETTE_ROLES).toContain(
                element.getAttribute('data-role') as PaletteRole,
            );
        }

        const background = parsed.querySelector(
            'rect[data-role="background"]',
        );

        expect(background?.getAttribute('fill')).toBe(
            ROLES.light.background.toLowerCase(),
        );

        const primaryRects = Array.from(
            parsed.querySelectorAll('rect[data-role="primary"]'),
        );

        expect(primaryRects.length).toBeGreaterThan(0);

        for (const rect of primaryRects) {
            expect(rect.getAttribute('fill')).toBe(
                ROLES.light.primary.toLowerCase(),
            );
        }

        // O rotulo do botao nao pinta o papel: escolhe preto/branco legivel.
        const primaryText = parsed.querySelector('text[data-role="primary"]');

        expect(primaryText?.getAttribute('fill')).toBe(
            readableOn(ROLES.light.primary),
        );
    });

    it('escapa texto e atributos do XML', () => {
        const hostile: PrototypeCopy = {
            ...COPY,
            heading: '<script>alert("x") & \'y\'</script>',
        };
        const svg = svgOf('site', 'light', hostile);

        expect(svg).not.toContain('<script>');
        expect(svg).toContain('&lt;script&gt;alert(&quot;x&quot;) &amp; &apos;y&apos;&lt;/script&gt;');
        // O proprio texto do cabecalho tambem passa pelo escape.
        expect(escapeXml('Tom & Jerry <3')).toBe(
            'Tom &amp; Jerry &lt;3',
        );
    });

    it('embute a Libre Franklin Regular e Bold na exportacao', () => {
        const fonts = prototypeFontCss();
        const svg = renderSvg(
            buildPrototype({
                kind: 'site',
                theme: 'light',
                roles: ROLES.light,
                copy: COPY,
                label: 'Prototype preview',
            }),
            { fonts },
        );

        expect(fonts.match(/@font-face/g)).toHaveLength(2);
        expect(fonts).toContain("font-weight:400");
        expect(fonts).toContain("font-weight:700");
        expect(fonts).toContain('data:font/ttf;base64');

        expect(svg).toContain('<style>');
        expect(svg).toContain(fonts);
        // Sem fonte pedida, o preview nao carrega base64 nenhum.
        expect(svgOf('site', 'light')).not.toContain('<style>');
    });
});
