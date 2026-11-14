import { describe, expect, it } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { paletteSignature } from '@/lib/paletteSave';
import type { ExtractedPalette } from '@/tools/palette-generator/lib/extractedPalette';

/**
 * Assinatura do snapshot importado (F5, Etapa 6).
 *
 * Os papeis de um arquivo de tokens so continuam validos enquanto o tipo, a
 * base e as cores efetivas forem as mesmas. A assinatura e o que o gerador
 * compara antes de continuar oferecendo esses papeis.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));

const EXTRACTED: ExtractedPalette = {
    colors: [
        { rgba: colorOf('#ff0000'), hex: '#ff0000', weight: 3, percentage: 60 },
        { rgba: colorOf('#0000ff'), hex: '#0000ff', weight: 2, percentage: 40 },
        { rgba: colorOf('#00ff00'), hex: '#00ff00', weight: 0, percentage: 0 },
        { rgba: colorOf('#ffff00'), hex: '#ffff00', weight: 0, percentage: 0 },
        { rgba: colorOf('#ff00ff'), hex: '#ff00ff', weight: 0, percentage: 0 },
    ],
    requested: 5,
    algorithmVersion: 1,
    sufficient: true,
    visiblePixels: 5,
    source: { kind: 'file', label: 'foto.png' },
};

describe('paletteSignature', () => {
    it('e estavel para o mesmo snapshot', () => {
        const source = { kind: 'scale' as const, base: '#6366f1', scale: SCALE };

        expect(paletteSignature(source)).toBe(paletteSignature({ ...source }));
    });

    it('muda quando a base muda', () => {
        const source = { kind: 'scale' as const, base: '#6366f1', scale: SCALE };

        expect(paletteSignature(source)).not.toBe(
            paletteSignature({ ...source, base: '#4f46e5' }),
        );
    });

    it('muda quando um tom da escala muda', () => {
        const source = { kind: 'scale' as const, base: '#6366f1', scale: SCALE };
        const changed = {
            ...SCALE,
            tones: SCALE.tones.map((tone, index) =>
                index === 0 ? { ...tone, hex: '#123456' } : tone,
            ),
        };

        expect(paletteSignature(source)).not.toBe(
            paletteSignature({ ...source, scale: changed }),
        );
    });

    it('muda quando a ordem ou o conjunto extraido muda', () => {
        const source = {
            kind: 'extracted' as const,
            base: '#ff0000',
            extracted: EXTRACTED,
        };
        const reordered = {
            ...EXTRACTED,
            colors: [...EXTRACTED.colors].reverse(),
        };

        expect(paletteSignature(source)).not.toBe(
            paletteSignature({ ...source, extracted: reordered }),
        );
    });

    it('distingue escala de extracao com a mesma base', () => {
        expect(
            paletteSignature({ kind: 'scale', base: '#ff0000', scale: SCALE }),
        ).not.toBe(
            paletteSignature({
                kind: 'extracted',
                base: '#ff0000',
                extracted: EXTRACTED,
            }),
        );
    });

    it('ignora caixa no HEX', () => {
        const source = { kind: 'scale' as const, base: '#6366f1', scale: SCALE };

        expect(paletteSignature({ ...source, base: '#6366F1' })).toBe(
            paletteSignature(source),
        );
    });
});
