import { downloadBlob } from '@/lib/download';
import { buildPrototype, type PrototypeBuildInput } from './defaults';
import { rasterizeSvg, type RasterScale } from './rasterize';
import { renderSvg } from './renderSvg';
import type { PrototypeDocument } from './types';

/**
 * Exportacao do prototipo (F4, ADR-126).
 *
 * SVG vetorial e PNG 1x/2x saem do mesmo documento; a unica diferenca e que a
 * exportacao embute a fonte. O modulo da fonte so entra no bundle quando alguem
 * exporta: o preview nao paga esses ~290 KB de base64.
 */

/** Nome estavel do arquivo, sem texto traduzido nem o nome da paleta. */
export function prototypeFileName(
    input: PrototypeBuildInput,
    extension: 'svg' | 'png',
): string {
    return `palette-prototype-${input.kind}-${input.theme}.${extension}`;
}

async function fontCss(): Promise<string> {
    const { prototypeFontCss } = await import('./fonts');

    return prototypeFontCss();
}

/** SVG final, com a fonte embutida para abrir igual fora do app. */
export async function prototypeSvg(
    input: PrototypeBuildInput,
): Promise<string> {
    return renderSvg(buildPrototype(input), { fonts: await fontCss() });
}

/** PNG de um documento ja montado, no tamanho dele vezes a escala. */
export async function prototypePngFromDocument(
    document: PrototypeDocument,
    scale: RasterScale,
): Promise<Blob> {
    return rasterizeSvg(
        renderSvg(document, { fonts: await fontCss() }),
        { width: document.width, height: document.height },
        scale,
    );
}

/** PNG do mesmo SVG, no tamanho do template vezes a escala. */
export async function prototypePng(
    input: PrototypeBuildInput,
    scale: RasterScale,
): Promise<Blob> {
    return prototypePngFromDocument(buildPrototype(input), scale);
}

export async function downloadPrototypeSvg(
    input: PrototypeBuildInput,
): Promise<void> {
    downloadBlob(
        prototypeFileName(input, 'svg'),
        new Blob([await prototypeSvg(input)], {
            type: 'image/svg+xml;charset=utf-8',
        }),
    );
}

export async function downloadPrototypePng(
    input: PrototypeBuildInput,
    scale: RasterScale,
): Promise<void> {
    downloadBlob(
        prototypeFileName(input, 'png'),
        await prototypePng(input, scale),
    );
}
