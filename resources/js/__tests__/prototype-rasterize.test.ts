import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { rasterizeSvg } from '@/tools/palette-generator/prototype/rasterize';

/**
 * Rasterizacao do SVG em PNG (F4, ADR-126).
 *
 * O jsdom nao desenha nada: `Image` e canvas sao trocados por dublês que
 * registram o que foi pedido. O que se prova aqui e o que importa para a
 * exportacao - o PNG sai no tamanho do template vezes a escala, com o SVG
 * como fonte, e a URL do blob sempre e revogada.
 */

class FakeImage {
    onload: (() => void) | null = null;

    onerror: (() => void) | null = null;

    set src(value: string) {
        this.onloadUrl = value;
        queueMicrotask(() => this.onload?.());
    }

    onloadUrl = '';
}

class BrokenImage {
    onload: (() => void) | null = null;

    onerror: (() => void) | null = null;

    set src(value: string) {
        this.onloadUrl = value;
        queueMicrotask(() => this.onerror?.());
    }

    onloadUrl = '';
}

const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>';

let drawImage: ReturnType<typeof vi.fn>;
let createObjectURL: ReturnType<typeof vi.fn>;
let revokeObjectURL: ReturnType<typeof vi.fn>;
let canvasSizes: { width: number; height: number }[];
let context: CanvasRenderingContext2D | null;
let toBlobResult: Blob | null;

const originalGetContext = HTMLCanvasElement.prototype.getContext;
const originalToBlob = HTMLCanvasElement.prototype.toBlob;

describe('Prototipos: rasterizacao', () => {
    beforeEach(() => {
        drawImage = vi.fn();
        createObjectURL = vi.fn(() => 'blob:prototype-svg');
        revokeObjectURL = vi.fn();
        canvasSizes = [];
        context = { drawImage } as unknown as CanvasRenderingContext2D;
        toBlobResult = new Blob(['png'], { type: 'image/png' });

        vi.stubGlobal('Image', FakeImage);
        vi.spyOn(URL, 'createObjectURL').mockImplementation(
            createObjectURL as () => string,
        );
        vi.spyOn(URL, 'revokeObjectURL').mockImplementation(
            revokeObjectURL as () => void,
        );

        HTMLCanvasElement.prototype.getContext = vi.fn(
            () => context,
        ) as unknown as typeof HTMLCanvasElement.prototype.getContext;

        HTMLCanvasElement.prototype.toBlob = vi.fn(
            function (
                this: HTMLCanvasElement,
                callback: BlobCallback,
            ): void {
                canvasSizes.push({
                    width: this.width,
                    height: this.height,
                });
                callback(toBlobResult);
            },
        ) as unknown as typeof HTMLCanvasElement.prototype.toBlob;
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();

        HTMLCanvasElement.prototype.getContext = originalGetContext;
        HTMLCanvasElement.prototype.toBlob = originalToBlob;
    });

    it('gera o PNG em 1x no tamanho do template', async () => {
        const blob = await rasterizeSvg(
            SVG,
            { width: 1200, height: 900 },
            1,
        );

        expect(blob.type).toBe('image/png');
        expect(canvasSizes).toEqual([{ width: 1200, height: 900 }]);
        expect(drawImage).toHaveBeenCalledTimes(1);
        expect(drawImage.mock.calls[0]?.[1]).toBe(0);
        expect(drawImage.mock.calls[0]?.[2]).toBe(0);
        expect(drawImage.mock.calls[0]?.[3]).toBe(1200);
        expect(drawImage.mock.calls[0]?.[4]).toBe(900);
    });

    it('dobra as medidas em 2x', async () => {
        await rasterizeSvg(SVG, { width: 390, height: 844 }, 2);

        expect(canvasSizes).toEqual([{ width: 780, height: 1688 }]);
        expect(drawImage.mock.calls[0]?.[3]).toBe(780);
        expect(drawImage.mock.calls[0]?.[4]).toBe(1688);
    });

    it('carrega o proprio SVG como imagem e revoga a URL', async () => {
        await rasterizeSvg(SVG, { width: 10, height: 10 }, 1);

        const source = createObjectURL.mock.calls[0]?.[0] as Blob;

        expect(source.type).toBe('image/svg+xml;charset=utf-8');
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:prototype-svg');
    });

    it('revoga a URL mesmo quando o canvas nao esta disponivel', async () => {
        context = null;

        await expect(
            rasterizeSvg(SVG, { width: 10, height: 10 }, 1),
        ).rejects.toThrow('canvas 2d indisponivel');

        expect(revokeObjectURL).toHaveBeenCalledWith('blob:prototype-svg');
        expect(canvasSizes).toEqual([]);
    });

    it('falha quando o navegador devolve um PNG vazio', async () => {
        toBlobResult = null;

        await expect(
            rasterizeSvg(SVG, { width: 10, height: 10 }, 1),
        ).rejects.toThrow('png nao gerado');
    });

    it('falha quando o SVG nao carrega', async () => {
        vi.stubGlobal('Image', BrokenImage);

        await expect(
            rasterizeSvg(SVG, { width: 10, height: 10 }, 1),
        ).rejects.toThrow('svg nao carregou');

        expect(revokeObjectURL).toHaveBeenCalledWith('blob:prototype-svg');
    });
});
