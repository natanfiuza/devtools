import {
    BLACK,
    PALETTE_TONES,
    WHITE,
    canonicalKey,
    contrastReport,
    nearestToneFor,
    parseColor,
    rgbToOklch,
    type PaletteScale,
    type PaletteToneResult,
    type Rgba,
} from '@/lib/color';
import type { PaletteScaleItem } from '@/types/colors';

/**
 * Escala "congelada" a partir das cores persistidas (F4, ADR-123).
 *
 * Abrir uma paleta salva nao pode recalcular nada: os HEX gravados sao o que o
 * dono viu, mesmo que o algoritmo mude depois. Este modulo so reconstroi o
 * `PaletteScale` que a tela consome (rgba, OKLCH, contraste) a partir do HEX;
 * a primeira troca de base no editor descongela e chama `buildPaletteScale`.
 *
 * `clipped` so existe quando o arquivo importado registra os tons que tiveram
 * o croma cortado na geracao original (JSON/DTCG da F5); uma paleta salva pelo
 * editor nao guarda esse metadado e fica com `false`.
 */

export interface FrozenScaleInput {
    base: string;
    /** Os 11 tons salvos, cada um com o seu `tone`. */
    items: PaletteScaleItem[];
    /** Versao do algoritmo que gerou as cores; so informativa. */
    algorithmVersion: number;
    /**
     * Tons cujo croma foi cortado quando a escala foi gerada. Vem apenas de
     * arquivo importado; ausente = nenhum tom sinalizado.
     */
    clippedTones?: number[];
}

function parseOrThrow(hex: string): Rgba {
    const parsed = parseColor(hex);

    if (!parsed.ok) {
        throw new Error(`cor salva invalida: ${hex}`);
    }

    return parsed.color.rgba;
}

/**
 * Reconstroi a escala exatamente como foi salva.
 *
 * A ordem de `PALETTE_TONES` e a da tela; um tom ausente ou repetido e dado
 * corrompido (o servidor valida os 11), entao falha alto em vez de inventar.
 */
export function buildFrozenScale(input: FrozenScaleInput): PaletteScale {
    const base = parseOrThrow(input.base);
    const baseOklch = rgbToOklch(base);
    const clipped = new Set(input.clippedTones ?? []);
    const byTone = new Map<number, PaletteScaleItem>();

    for (const item of input.items) {
        if (byTone.has(item.tone)) {
            throw new Error(`tom repetido na escala salva: ${item.tone}`);
        }

        byTone.set(item.tone, item);
    }

    const tones = PALETTE_TONES.map((tone): PaletteToneResult => {
        const item = byTone.get(tone);

        if (item === undefined) {
            throw new Error(`escala salva sem o tom ${tone}`);
        }

        const rgba = parseOrThrow(item.hex);

        return {
            tone,
            rgba,
            oklch: rgbToOklch(rgba),
            hex: item.hex,
            clipped: clipped.has(tone),
            contrast: {
                black: contrastReport(rgba, WHITE).black,
                white: contrastReport(rgba, BLACK).white,
            },
        };
    });

    const tone500 = tones.find((entry) => entry.tone === 500);

    return {
        base,
        baseOklch,
        tones,
        nearestTone: nearestToneFor(baseOklch.l),
        baseIsTone500:
            tone500 !== undefined &&
            canonicalKey(base) === canonicalKey(tone500.rgba),
        // Ordem canonica da escala, ignorando tons que nao existem.
        clippedTones: PALETTE_TONES.filter((tone) => clipped.has(tone)),
        algorithmVersion: input.algorithmVersion,
    };
}
