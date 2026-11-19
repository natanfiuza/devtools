import { downloadBlob } from '@/lib/download';
import { renderSvg } from '@/lib/prototype/renderSvg';
import { rasterizeSvg, type RasterScale } from '@/lib/prototype/rasterize';
import { buildPaletteImage } from './buildImage';
import { PALETTE_IMAGE_SIZES } from './types';
import type { PaletteImageLayout, PaletteImageSource } from './types';

/**
 * Exportacao da imagem tipografica (F6, ADR-138).
 *
 * Reusa o serializador, a fonte embutida e o rasterizador do prototipo: o PNG
 * e exatamente o SVG exportado passado por um canvas, sem biblioteca e sem
 * requisicao. O modulo da fonte so entra no bundle quando alguem exporta.
 */

async function fontCss(): Promise<string> {
    const { prototypeFontCss } = await import('@/lib/prototype/fonts');

    return prototypeFontCss();
}

/** SVG da previa: sem fonte embutida, o app ja tem a familia carregada. */
export function paletteImagePreview(
    source: PaletteImageSource,
    layout: PaletteImageLayout,
): string {
    return renderSvg(buildPaletteImage(source, layout));
}

/** SVG final, com a fonte embutida para abrir igual fora do app. */
export async function paletteImageSvg(
    source: PaletteImageSource,
    layout: PaletteImageLayout,
): Promise<string> {
    return renderSvg(buildPaletteImage(source, layout), {
        fonts: await fontCss(),
    });
}

/** PNG no tamanho fixo do formato vezes a escala pedida. */
export async function paletteImagePng(
    source: PaletteImageSource,
    layout: PaletteImageLayout,
    scale: RasterScale,
): Promise<Blob> {
    const document = buildPaletteImage(source, layout);

    return rasterizeSvg(
        renderSvg(document, { fonts: await fontCss() }),
        PALETTE_IMAGE_SIZES[layout],
        scale,
    );
}

/** Nome estavel: formato e extensao, sem texto traduzido nem nome da paleta. */
export function paletteImageFileName(
    layout: PaletteImageLayout,
    extension: 'svg' | 'png',
): string {
    return `palette-${layout}.${extension}`;
}

export async function downloadPaletteImageSvg(
    source: PaletteImageSource,
    layout: PaletteImageLayout,
): Promise<void> {
    downloadBlob(
        paletteImageFileName(layout, 'svg'),
        new Blob([await paletteImageSvg(source, layout)], {
            type: 'image/svg+xml;charset=utf-8',
        }),
    );
}

export async function downloadPaletteImagePng(
    source: PaletteImageSource,
    layout: PaletteImageLayout,
    scale: RasterScale,
): Promise<void> {
    downloadBlob(
        paletteImageFileName(layout, 'png'),
        await paletteImagePng(source, layout, scale),
    );
}
