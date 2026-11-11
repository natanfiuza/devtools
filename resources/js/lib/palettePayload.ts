import {
    formatHex,
    formatHex8,
    mapOklchToSrgb,
    parseColor,
    rgbToOklch,
    type PaletteScale,
    type Rgba,
} from '@/lib/color';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';
import type {
    PaletteColors,
    PaletteDetails,
    PaletteKind,
    PalettePayload,
    PaletteRole,
    PaletteRoleColors,
    PaletteRoles,
    PaletteScaleItem,
    PaletteTheme,
} from '@/types/colors';

/**
 * Payload de paleta para a API (F4, ADR-123).
 *
 * O servidor valida o formato e grava exatamente o que chega: as cores sao as
 * efetivas da tela, nunca recalculadas. Este modulo monta esse corpo a partir
 * da escala ou da extracao, com os papeis padrao calculados aqui.
 */

export const PALETTE_ROLES: readonly PaletteRole[] = [
    'primary',
    'secondary',
    'accent',
    'background',
    'surface',
    'text',
    'text_muted',
    'border',
];

export const PALETTE_THEMES: readonly PaletteTheme[] = ['light', 'dark'];

/**
 * Tom da escala usado em cada papel. E a tabela dos defaults (ADR-126):
 * claro le a escala do fundo para o texto, escuro inverte os extremos.
 */
const SCALE_ROLE_TONES: Record<PaletteTheme, Record<PaletteRole, number>> = {
    light: {
        primary: 600,
        secondary: 700,
        accent: 400,
        background: 50,
        surface: 100,
        text: 950,
        text_muted: 600,
        border: 200,
    },
    dark: {
        primary: 600,
        secondary: 700,
        accent: 400,
        background: 950,
        surface: 900,
        text: 50,
        text_muted: 400,
        border: 800,
    },
};

/**
 * Luminosidade e fator de croma dos neutros da paleta extraida. O croma e
 * relativo ao da base, entao uma imagem cinza produz neutros cinza e uma
 * imagem saturada produz neutros levemente tingidos.
 */
const EXTRACTED_NEUTRALS: Record<
    PaletteTheme,
    Record<
        'background' | 'surface' | 'text' | 'text_muted' | 'border',
        { l: number; c: number }
    >
> = {
    light: {
        background: { l: 0.985, c: 0.06 },
        surface: { l: 0.965, c: 0.06 },
        text: { l: 0.205, c: 0.1 },
        text_muted: { l: 0.52, c: 0.08 },
        border: { l: 0.9, c: 0.06 },
    },
    dark: {
        background: { l: 0.16, c: 0.06 },
        surface: { l: 0.215, c: 0.06 },
        text: { l: 0.97, c: 0.04 },
        text_muted: { l: 0.7, c: 0.06 },
        border: { l: 0.3, c: 0.06 },
    },
};

function sameColor(left: string, right: string): boolean {
    return left.toLowerCase() === right.toLowerCase();
}

function hexOf(rgba: Rgba): string {
    return rgba.a < 1 ? formatHex8(rgba) : formatHex(rgba);
}

function parseOrThrow(hex: string, context: string): Rgba {
    const parsed = parseColor(hex);

    if (!parsed.ok) {
        throw new Error(`cor invalida em ${context}: ${hex}`);
    }

    return parsed.color.rgba;
}

/** Papel -> tom da escala; falha se a escala persistida estiver incompleta. */
export function rolesFromScaleItems(items: PaletteScaleItem[]): PaletteRoles {
    const byTone = new Map<number, string>();

    for (const item of items) {
        byTone.set(item.tone, item.hex);
    }

    const roles = {} as PaletteRoles;

    for (const theme of PALETTE_THEMES) {
        const tones = SCALE_ROLE_TONES[theme];
        const colors = {} as PaletteRoleColors;

        for (const role of PALETTE_ROLES) {
            const hex = byTone.get(tones[role]);

            if (hex === undefined) {
                throw new Error(
                    `escala sem o tom ${tones[role]} para o papel ${role}`,
                );
            }

            colors[role] = hex;
        }

        roles[theme] = colors;
    }

    return roles;
}

/**
 * Papeis da paleta extraida: as tres primeiras cores por peso e neutros
 * derivados da base em OKLCH (matiz preservado, croma discreto).
 */
export function rolesFromExtracted(
    base: string,
    items: string[],
): PaletteRoles {
    if (items.length === 0) {
        throw new Error('paleta extraida sem cores');
    }

    const baseOklch = rgbToOklch(parseOrThrow(base, 'base extraida'));
    const first = items[0] as string;
    const picks: Pick<PaletteRoleColors, 'primary' | 'secondary' | 'accent'> = {
        primary: first,
        secondary: items[1] ?? first,
        accent: items[2] ?? items[1] ?? first,
    };

    const roles = {} as PaletteRoles;

    for (const theme of PALETTE_THEMES) {
        const colors: PaletteRoleColors = {
            ...picks,
        } as PaletteRoleColors;

        for (const [role, tone] of Object.entries(EXTRACTED_NEUTRALS[theme])) {
            const mapped = mapOklchToSrgb({
                l: tone.l,
                c: baseOklch.c * tone.c,
                h: baseOklch.h,
            });

            colors[role as keyof typeof EXTRACTED_NEUTRALS.light] = hexOf({
                ...mapped.rgb,
                a: 1,
            });
        }

        roles[theme] = colors;
    }

    return roles;
}

/** Tipo persistido inferido pelo shape: tom presente = escala. */
export function inferKind(colors: PaletteColors): PaletteKind {
    return colors.items.some(
        (item) => 'tone' in item && typeof item.tone === 'number',
    )
        ? 'scale'
        : 'extracted';
}

/** Corpo completo de uma paleta de escala, pronto para salvar. */
export function payloadFromScale(
    scale: PaletteScale,
    details: PaletteDetails,
): PalettePayload {
    const items: PaletteScaleItem[] = scale.tones.map((tone) => ({
        hex: tone.hex,
        tone: tone.tone,
    }));

    return {
        name: details.name,
        description: details.description,
        visibility: details.visibility,
        kind: 'scale',
        algorithm_version: scale.algorithmVersion,
        colors: { base: hexOf(scale.base), items },
        roles: rolesFromScaleItems(items),
        source: null,
        tags: details.tags,
    };
}

/**
 * Corpo completo de uma paleta extraida. A base precisa estar entre as cores
 * extraidas - e a regra do servidor, repetida aqui para falhar cedo.
 */
export function payloadFromExtracted(
    palette: ExtractedPalette,
    base: string,
    details: PaletteDetails,
): PalettePayload {
    const items = palette.colors.map((color) => ({ hex: color.hex }));

    if (!items.some((item) => sameColor(item.hex, base))) {
        throw new Error('base da paleta extraida precisa ser uma das cores');
    }

    return {
        name: details.name,
        description: details.description,
        visibility: details.visibility,
        kind: 'extracted',
        algorithm_version: palette.algorithmVersion,
        colors: { base: base.toLowerCase(), items },
        roles: rolesFromExtracted(
            base,
            items.map((item) => item.hex),
        ),
        source: palette.source,
        tags: details.tags,
    };
}
