import { describe, expect, it } from 'vitest';
import {
    PALETTE_TONES,
    buildPaletteScale,
    canonicalKey,
    parseColor,
    rgbToOklch,
    type Rgba,
} from '@/lib/color';
import { buildFrozenScale } from '@/lib/frozenScale';
import type { PaletteScaleItem } from '@/types/colors';

/**
 * Escala congelada (ADR-123).
 *
 * Abrir uma paleta salva reconstroi a escala a partir dos HEX gravados, sem
 * chamar o algoritmo. O teste troca uma cor salva por um valor que a escala
 * jamais produziria: se o resultado carregar esse valor, nada foi recalculado.
 */

function rgbaOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

function savedItems(): PaletteScaleItem[] {
    return buildPaletteScale(rgbaOf('#6366f1')).tones.map((tone) => ({
        hex: tone.hex,
        tone: tone.tone,
    }));
}

describe('buildFrozenScale', () => {
    it('reconstroi a mesma escala da geracao', () => {
        const generated = buildPaletteScale(rgbaOf('#6366f1'));
        const frozen = buildFrozenScale({
            base: '#6366f1',
            items: savedItems(),
            algorithmVersion: generated.algorithmVersion,
        });

        expect(frozen.algorithmVersion).toBe(generated.algorithmVersion);
        expect(frozen.tones.map((tone) => tone.hex)).toEqual(
            generated.tones.map((tone) => tone.hex),
        );
        expect(frozen.tones.map((tone) => tone.tone)).toEqual([
            ...PALETTE_TONES,
        ]);
        expect(canonicalKey(frozen.base)).toBe(canonicalKey(generated.base));
        expect(frozen.nearestTone).toBe(generated.nearestTone);
        expect(frozen.baseIsTone500).toBe(generated.baseIsTone500);
        expect(frozen.clippedTones).toEqual([]);
    });

    it('preserva o HEX salvo mesmo quando o algoritmo atual geraria outro', () => {
        const items = savedItems();
        items[7] = { hex: '#123456', tone: 700 };

        const frozen = buildFrozenScale({
            base: '#6366f1',
            items,
            algorithmVersion: 1,
        });
        const tone700 = frozen.tones.find((tone) => tone.tone === 700);

        expect(tone700?.hex).toBe('#123456');
        expect(canonicalKey(tone700?.rgba ?? { a: 0, r: 0, g: 0, b: 0 })).toBe(
            canonicalKey(rgbaOf('#123456')),
        );
        expect(tone700?.oklch).toEqual(rgbToOklch(rgbaOf('#123456')));
        expect(tone700?.clipped).toBe(false);
    });

    it('recalcula contraste e base a partir das cores salvas', () => {
        const frozen = buildFrozenScale({
            base: '#4f46e5',
            items: savedItems(),
            algorithmVersion: 1,
        });

        for (const tone of frozen.tones) {
            expect(tone.contrast.black.ratio).toBeGreaterThan(0);
            expect(tone.contrast.white.ratio).toBeGreaterThan(0);
        }

        expect(frozen.baseOklch).toEqual(rgbToOklch(rgbaOf('#4f46e5')));
    });

    it('mantem alpha quando o HEX salvo e HEX8', () => {
        const items = savedItems();
        items[0] = { hex: '#eef2ff80', tone: 50 };

        const frozen = buildFrozenScale({
            base: '#6366f1',
            items,
            algorithmVersion: 1,
        });

        expect(frozen.tones[0]?.hex).toBe('#eef2ff80');
        expect(frozen.tones[0]?.rgba.a).toBeCloseTo(128 / 255, 5);
    });

    it('falha quando falta um tom ou ele se repete', () => {
        const missing = savedItems().slice(0, 10);
        const duplicated = [
            ...savedItems().slice(0, 10),
            { hex: '#000000', tone: 100 },
        ];

        expect(() =>
            buildFrozenScale({
                base: '#6366f1',
                items: missing,
                algorithmVersion: 1,
            }),
        ).toThrow();
        expect(() =>
            buildFrozenScale({
                base: '#6366f1',
                items: duplicated,
                algorithmVersion: 1,
            }),
        ).toThrow();
    });
});
