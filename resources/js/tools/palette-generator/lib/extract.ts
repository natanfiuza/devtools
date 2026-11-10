import { getPaletteSync } from 'colorthief';
import {
    canonicalKey,
    formatHex,
    rgbToOklab,
    type Oklab,
    type Rgb,
    type Rgba,
} from '@/lib/color';
import {
    EXTRACTION_ALGORITHM_VERSION,
    type ExtractedColor,
    type ExtractionOutcome,
    type ExtractionRequest,
} from './extractContract';

/**
 * Extracao de cores dominantes de uma imagem (ADR-122).
 *
 * `color-thief` fica isolada aqui: e a unica importacao da biblioteca no
 * projeto e so o worker (ou o fallback dinamico do cliente) carrega este
 * modulo - tipos e constantes vivem em `extractContract.ts` justamente para a
 * pagina nao puxar a quantizacao no chunk principal. Nada de DOM: entram
 * pixels crus (`ImageData`) e saem cores sRGB.
 *
 * Regras que a UI depende:
 * - pixels com alpha < 0,5 sao ignorados (sem composicao sobre fundo);
 * - o peso de cada cor e recalculado atribuindo cada pixel visivel a cor mais
 *   proxima em OKLab, entao ordenacao e percentuais nao dependem da populacao
 *   interna da biblioteca;
 * - se houver menos cores distintas que o pedido, devolvemos as existentes
 *   com `sufficient: false` - nunca inventamos cor.
 */

export {
    DEFAULT_EXTRACTION_COUNT,
    EXTRACTION_ALGORITHM_VERSION,
    EXTRACTION_COUNTS,
} from './extractContract';
export type {
    ExtractedColor,
    ExtractionCount,
    ExtractionErrorCode,
    ExtractionOutcome,
    ExtractionRequest,
    ExtractionResult,
} from './extractContract';

/**
 * Alpha minimo (0-255) para o pixel contar. 128 ~= 0,5 e e o mesmo criterio
 * repassado a biblioteca, para que pesos e quantizacao vejam os mesmos pixels.
 */
const VISIBLE_ALPHA = 128;

function countVisiblePixels(data: Uint8ClampedArray<ArrayBuffer>): number {
    let visible = 0;

    for (let offset = 3; offset < data.length; offset += 4) {
        if (data[offset]! >= VISIBLE_ALPHA) {
            visible += 1;
        }
    }

    return visible;
}

function opaqueRgb(rgb: Rgb): Rgba {
    return {
        r: Math.round(rgb.r),
        g: Math.round(rgb.g),
        b: Math.round(rgb.b),
        a: 1,
    };
}

function squaredDistance(a: Oklab, b: Oklab): number {
    const dl = a.l - b.l;
    const da = a.a - b.a;
    const db = a.b - b.b;

    return dl * dl + da * da + db * db;
}

interface PaletteColor {
    rgba: Rgba;
    oklab: Oklab;
    weight: number;
}

/**
 * Atribui cada pixel visivel a cor mais proxima em OKLab.
 *
 * O cache por chave de pixel evita recalcular a distancia do mesmo RGB em
 * areas uniformes (o caso comum numa imagem reduzida para analise).
 */
function weighPixels(
    data: Uint8ClampedArray<ArrayBuffer>,
    palette: PaletteColor[],
    visiblePixels: number,
): ExtractedColor[] {
    if (visiblePixels === 0 || palette.length === 0) {
        return [];
    }

    const nearest = new Map<number, number>();

    for (let offset = 0; offset < data.length; offset += 4) {
        if (data[offset + 3]! < VISIBLE_ALPHA) {
            continue;
        }

        const r = data[offset]!;
        const g = data[offset + 1]!;
        const b = data[offset + 2]!;
        const key = (r << 16) | (g << 8) | b;
        let index = nearest.get(key);

        if (index === undefined) {
            const oklab = rgbToOklab({ r, g, b });
            let best = 0;
            let bestDistance = Number.POSITIVE_INFINITY;

            for (let i = 0; i < palette.length; i += 1) {
                const distance = squaredDistance(oklab, palette[i]!.oklab);

                if (distance < bestDistance) {
                    bestDistance = distance;
                    best = i;
                }
            }

            index = best;
            nearest.set(key, index);
        }

        palette[index]!.weight += 1;
    }

    return palette
        .filter((color) => color.weight > 0)
        .map((color) => ({
            rgba: color.rgba,
            hex: formatHex(color.rgba),
            weight: color.weight,
            percentage:
                Math.round((color.weight / visiblePixels) * 10_000) / 100,
        }))
        .sort((a, b) => b.weight - a.weight || a.hex.localeCompare(b.hex));
}

/**
 * Extrai ate `count` cores dominantes dos pixels recebidos.
 *
 * Espera RGBA em bytes (o que sai de `canvas.getImageData()`), ja reduzido
 * para o tamanho de analise. A funcao e sincrona: quem decide executar em
 * worker e o host da extracao, nao ela.
 */
export function extractPalette(request: ExtractionRequest): ExtractionOutcome {
    const { data, width, height, count } = request;

    if (
        !Number.isInteger(width) ||
        !Number.isInteger(height) ||
        width <= 0 ||
        height <= 0 ||
        data.length !== width * height * 4
    ) {
        return { ok: false, error: 'failed' };
    }

    const visiblePixels = countVisiblePixels(data);

    if (visiblePixels === 0) {
        return { ok: false, error: 'empty_image' };
    }

    try {
        const palette = getPaletteSync(new ImageData(data, width, height), {
            colorCount: count,
            quality: 1,
            colorSpace: 'oklch',
            alphaThreshold: VISIBLE_ALPHA,
            ignoreWhite: false,
            minSaturation: 0,
        });

        if (!palette || palette.length === 0) {
            return { ok: false, error: 'failed' };
        }

        const seen = new Set<string>();
        const colors: PaletteColor[] = [];

        for (const entry of palette) {
            const rgba = opaqueRgb(entry.rgb());
            const key = canonicalKey(rgba);

            if (seen.has(key)) {
                continue;
            }

            seen.add(key);
            colors.push({ rgba, oklab: rgbToOklab(rgba), weight: 0 });
        }

        const weighted = weighPixels(data, colors, visiblePixels);

        if (weighted.length === 0) {
            return { ok: false, error: 'failed' };
        }

        return {
            ok: true,
            result: {
                colors: weighted,
                requested: count,
                algorithmVersion: EXTRACTION_ALGORITHM_VERSION,
                sufficient: weighted.length >= count,
                visiblePixels,
            },
        };
    } catch {
        // Erro da biblioteca ou pixel inesperado: a UI mostra uma mensagem
        // generica e o usuario tenta outra imagem. Nunca vaza o detalhe.
        return { ok: false, error: 'failed' };
    }
}
