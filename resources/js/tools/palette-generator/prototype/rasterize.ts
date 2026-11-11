import type { PrototypeSize } from './types';

/**
 * Rasterizacao do SVG do prototipo (F4, ADR-126).
 *
 * O PNG e o mesmo SVG do preview passado por um canvas: nenhuma biblioteca de
 * captura, nenhuma requisicao. A fonte viaja embutida no SVG (data URI), entao
 * o `<img>` desenha com a mesma tipografia da tela. 1x e a medida do template;
 * 2x o dobro, para telas densas.
 */

export type RasterScale = 1 | 2;

export const RASTER_SCALES: readonly RasterScale[] = [1, 2];

function loadImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();

        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('svg nao carregou'));
        image.src = url;
    });
}

function toBlob(canvas: HTMLCanvasElement): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob === null) {
                reject(new Error('png nao gerado'));

                return;
            }

            resolve(blob);
        }, 'image/png');
    });
}

/**
 * SVG -> PNG no tamanho do template multiplicado pela escala.
 *
 * A URL do blob e revogada sempre, inclusive quando o canvas falha: o SVG
 * so existe na memoria da pagina.
 */
export async function rasterizeSvg(
    svg: string,
    size: PrototypeSize,
    scale: RasterScale,
): Promise<Blob> {
    const url = URL.createObjectURL(
        new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }),
    );

    try {
        const image = await loadImage(url);
        const canvas = document.createElement('canvas');

        canvas.width = Math.round(size.width * scale);
        canvas.height = Math.round(size.height * scale);

        const context = canvas.getContext('2d');

        if (context === null) {
            throw new Error('canvas 2d indisponivel');
        }

        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        return await toBlob(canvas);
    } finally {
        URL.revokeObjectURL(url);
    }
}
