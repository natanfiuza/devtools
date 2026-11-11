import { describe, expect, it } from 'vitest';
import {
    buildPaletteScale,
    parseColor,
    rgbToOklch,
    type Rgba,
} from '@/lib/color';
import {
    inferKind,
    payloadFromExtracted,
    payloadFromScale,
    rolesFromExtracted,
    rolesFromScaleItems,
} from '@/lib/palettePayload';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';
import type {
    PaletteDetails,
    PaletteRole,
    PaletteRoles,
    PaletteScaleItem,
} from '@/types/colors';

/**
 * Payload de paleta (ADR-123).
 *
 * O corpo salvo e o que o navegador exibiu: nenhuma cor e recalculada no
 * servidor nem ao reabrir. Os papeis padrao saem da tabela de tons da escala
 * ou dos neutros em OKLCH da paleta extraida.
 */

const DETAILS: PaletteDetails = {
    name: 'Oceano',
    description: 'Azuis calmos.',
    visibility: 'private',
    tags: ['web', 'brand'],
};

const EXTRACTED_HEXES = ['#112233', '#445566', '#778899', '#aabbcc', '#ddeeff'];

function rgbaOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

function scaleItems(): PaletteScaleItem[] {
    return buildPaletteScale(rgbaOf('#6366f1')).tones.map((tone) => ({
        hex: tone.hex,
        tone: tone.tone,
    }));
}

function extractedPalette(
    overrides: Partial<ExtractedPalette> = {},
): ExtractedPalette {
    return {
        colors: EXTRACTED_HEXES.map((hex) => ({
            rgba: rgbaOf(hex),
            hex,
            weight: 100,
            percentage: 20,
        })),
        requested: 5,
        algorithmVersion: 1,
        sufficient: true,
        visiblePixels: 500,
        source: { kind: 'file', label: 'foto.png' },
        ...overrides,
    };
}

function lightnessOf(
    roles: PaletteRoles,
    theme: keyof PaletteRoles,
    role: PaletteRole,
): number {
    return rgbToOklch(rgbaOf(roles[theme][role])).l;
}

describe('payloadFromScale', () => {
    it('grava os 11 tons com tom, a base e os metadados', () => {
        const payload = payloadFromScale(
            buildPaletteScale(rgbaOf('#6366f1')),
            DETAILS,
        );

        expect(payload).toMatchObject({
            name: 'Oceano',
            description: 'Azuis calmos.',
            visibility: 'private',
            kind: 'scale',
            algorithm_version: 1,
            tags: ['web', 'brand'],
            source: null,
        });
        expect(payload.colors.base).toBe('#6366f1');
        expect(payload.colors.items).toHaveLength(11);
        expect(
            payload.colors.items.map((item) => 'tone' in item && item.tone),
        ).toEqual([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
        const tone500 = payload.colors.items.find(
            (item) => 'tone' in item && item.tone === 500,
        );

        expect(tone500?.hex).toMatch(/^#[0-9a-f]{6}$/);
    });

    it('aplica a tabela de tons dos papeis nos dois temas', () => {
        const payload = payloadFromScale(
            buildPaletteScale(rgbaOf('#6366f1')),
            DETAILS,
        );
        const byTone = new Map(
            payload.colors.items.map((item) => [
                'tone' in item ? item.tone : -1,
                item.hex,
            ]),
        );

        expect(payload.roles.light).toEqual({
            primary: byTone.get(600),
            secondary: byTone.get(700),
            accent: byTone.get(400),
            background: byTone.get(50),
            surface: byTone.get(100),
            text: byTone.get(950),
            text_muted: byTone.get(600),
            border: byTone.get(200),
        });
        expect(payload.roles.dark).toEqual({
            primary: byTone.get(600),
            secondary: byTone.get(700),
            accent: byTone.get(400),
            background: byTone.get(950),
            surface: byTone.get(900),
            text: byTone.get(50),
            text_muted: byTone.get(400),
            border: byTone.get(800),
        });
    });
});

describe('rolesFromExtracted', () => {
    it('usa as tres primeiras cores por peso nos papeis de destaque', () => {
        const roles = rolesFromExtracted('#112233', EXTRACTED_HEXES);

        for (const theme of ['light', 'dark'] as const) {
            expect(roles[theme].primary).toBe('#112233');
            expect(roles[theme].secondary).toBe('#445566');
            expect(roles[theme].accent).toBe('#778899');
        }
    });

    it('deriva neutros claros e escuros em OKLCH', () => {
        const roles = rolesFromExtracted('#112233', EXTRACTED_HEXES);

        expect(lightnessOf(roles, 'light', 'background')).toBeGreaterThan(
            lightnessOf(roles, 'light', 'surface'),
        );
        expect(lightnessOf(roles, 'light', 'surface')).toBeGreaterThan(
            lightnessOf(roles, 'light', 'border'),
        );
        expect(lightnessOf(roles, 'light', 'text_muted')).toBeGreaterThan(
            lightnessOf(roles, 'light', 'text'),
        );
        expect(lightnessOf(roles, 'dark', 'text')).toBeGreaterThan(
            lightnessOf(roles, 'dark', 'background'),
        );
    });

    it('nao tinge os neutros quando a base e acromatica', () => {
        const roles = rolesFromExtracted('#808080', EXTRACTED_HEXES);

        for (const theme of ['light', 'dark'] as const) {
            for (const role of [
                'background',
                'surface',
                'text',
                'text_muted',
                'border',
            ] as const) {
                expect(rgbToOklch(rgbaOf(roles[theme][role])).c).toBeLessThan(
                    0.005,
                );
            }
        }
    });

    it('falha sem cores', () => {
        expect(() => rolesFromExtracted('#112233', [])).toThrow();
    });
});

describe('payloadFromExtracted', () => {
    it('preserva ordem, origem e versao da extracao', () => {
        const payload = payloadFromExtracted(
            extractedPalette(),
            '#112233',
            DETAILS,
        );

        expect(payload).toMatchObject({
            kind: 'extracted',
            algorithm_version: 1,
            source: { kind: 'file', label: 'foto.png' },
        });
        expect(payload.colors.base).toBe('#112233');
        expect(payload.colors.items).toEqual(
            EXTRACTED_HEXES.map((hex) => ({ hex })),
        );
        expect(Object.keys(payload.roles.light)).toHaveLength(8);
        expect(Object.keys(payload.roles.dark)).toHaveLength(8);
    });

    it('aceita a base em caixa diferente', () => {
        const payload = payloadFromExtracted(
            extractedPalette(),
            '#AABBCC',
            DETAILS,
        );

        expect(payload.colors.base).toBe('#aabbcc');
    });

    it('recusa base fora das cores extraidas', () => {
        expect(() =>
            payloadFromExtracted(extractedPalette(), '#ffffff', DETAILS),
        ).toThrow();
    });
});

describe('inferKind', () => {
    it('reconhece escala pelo tom e extracao sem tom', () => {
        expect(inferKind({ base: '#6366f1', items: scaleItems() })).toBe(
            'scale',
        );
        expect(
            inferKind({
                base: '#112233',
                items: [{ hex: '#112233' }, { hex: '#445566' }],
            }),
        ).toBe('extracted');
    });
});

describe('rolesFromScaleItems', () => {
    it('falha quando falta um tom', () => {
        expect(() => rolesFromScaleItems(scaleItems().slice(0, 10))).toThrow();
    });
});
