import type { ImageErrorCode } from './imageErrors';
import type { ImageLimits } from './imageLimits';

/**
 * Decodificacao e reducao da imagem para analise (ADR-122).
 *
 * Os limites chegam do servidor (`config/tools.php`), nunca daqui: bytes sao
 * conferidos **antes** de decodificar, dimensoes e pixels **depois** (so o
 * bitmap sabe o tamanho real). A copia de analise e reduzida para
 * `analysisSide`, num canvas local - a imagem original nunca sai do navegador.
 */

/** Tipos raster aceitos. SVG e recusado: e documento, nao imagem. */
export const ACCEPTED_IMAGE_TYPES: readonly string[] = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'image/avif',
];

/** `image/png; charset=binary` tambem aceita; a extensao do arquivo nao conta. */
export function acceptsImageType(type: string): boolean {
    const essence = type.split(';')[0]?.trim().toLowerCase() ?? '';

    return ACCEPTED_IMAGE_TYPES.includes(essence);
}

/**
 * Tamanho da copia de analise, mantendo a proporcao e nunca aumentando.
 *
 * O lado maior para em `side`; o menor acompanha. Imagens minusculas seguem
 * como estao (reduzir nao ajudaria a extracao), e o resultado nunca tem lado
 * zero.
 */
export function fitAnalysisSize(
    size: { width: number; height: number },
    side: number,
): { width: number; height: number } {
    if (
        !Number.isFinite(size.width) ||
        !Number.isFinite(size.height) ||
        !Number.isFinite(side) ||
        size.width <= 0 ||
        size.height <= 0 ||
        side <= 0
    ) {
        return { width: 0, height: 0 };
    }

    const scale = Math.min(1, side / Math.max(size.width, size.height));

    return {
        width: Math.max(1, Math.round(size.width * scale)),
        height: Math.max(1, Math.round(size.height * scale)),
    };
}

/**
 * Confere dimensoes ja decodificadas contra os tetos. `null` quer dizer ok.
 */
export function validateImageDimensions(
    width: number,
    height: number,
    limits: ImageLimits,
): ImageErrorCode | null {
    if (
        !Number.isInteger(width) ||
        !Number.isInteger(height) ||
        width <= 0 ||
        height <= 0
    ) {
        return 'decode_failed';
    }

    if (width > limits.maxSide || height > limits.maxSide) {
        return 'dimensions_too_large';
    }

    if (width * height > limits.maxPixels) {
        return 'too_many_pixels';
    }

    return null;
}

export interface DecodedImage {
    /** RGBA da copia de analise, pronta para o worker. */
    data: Uint8ClampedArray<ArrayBuffer>;
    /** Dimensoes originais, para a tela e para o modelo da F4. */
    width: number;
    height: number;
    sampledWidth: number;
    sampledHeight: number;
    downscaled: boolean;
}

export type DecodeOutcome =
    | { ok: true; image: DecodedImage }
    | { ok: false; error: ImageErrorCode };

export async function decodeImage(
    source: Blob,
    limits: ImageLimits,
): Promise<DecodeOutcome> {
    if (source.size > limits.maxBytes) {
        return { ok: false, error: 'too_large' };
    }

    if (!acceptsImageType(source.type)) {
        return { ok: false, error: 'not_image' };
    }

    if (typeof createImageBitmap !== 'function') {
        return { ok: false, error: 'unavailable' };
    }

    let bitmap: ImageBitmap;

    try {
        bitmap = await createImageBitmap(source);
    } catch {
        return { ok: false, error: 'decode_failed' };
    }

    try {
        const width = bitmap.width;
        const height = bitmap.height;
        const invalid = validateImageDimensions(width, height, limits);

        if (invalid !== null) {
            return { ok: false, error: invalid };
        }

        const size = fitAnalysisSize({ width, height }, limits.analysisSide);
        const canvas = document.createElement('canvas');

        canvas.width = size.width;
        canvas.height = size.height;

        const context = canvas.getContext('2d', { willReadFrequently: true });

        if (context === null) {
            return { ok: false, error: 'unavailable' };
        }

        // Suavizar na reducao e o que faz a media de area: sem isso, um
        // vizinho-mais-proximo escolheria pixels soltos e a paleta mentiria.
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(bitmap, 0, 0, size.width, size.height);

        const imageData = context.getImageData(0, 0, size.width, size.height);

        return {
            ok: true,
            image: {
                data: imageData.data,
                width,
                height,
                sampledWidth: size.width,
                sampledHeight: size.height,
                downscaled: size.width !== width || size.height !== height,
            },
        };
    } catch {
        return { ok: false, error: 'decode_failed' };
    } finally {
        // Libera o bitmap decodificado em qualquer caminho; o RGBA da copia
        // de analise e o unico dado que segue adiante.
        bitmap.close();
    }
}
