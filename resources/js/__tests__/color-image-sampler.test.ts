import { describe, expect, it } from 'vitest';
import {
    clampPixel,
    fitImageSize,
    pixelStep,
    pointToPixel,
} from '@/tools/html-color-picker/lib/imageSampler';

/**
 * Matematica do amostrador por imagem (ADR-117).
 */

describe('fitImageSize', () => {
    it('reduz mantendo a proporcao', () => {
        expect(
            fitImageSize(
                { width: 2048, height: 1024 },
                { width: 1024, height: 1024 },
            ),
        ).toEqual({ width: 1024, height: 512 });
    });

    it('nao amplia imagem pequena', () => {
        expect(
            fitImageSize(
                { width: 320, height: 200 },
                { width: 1024, height: 1024 },
            ),
        ).toEqual({ width: 320, height: 200 });
    });

    it('limita pelo lado mais estrito', () => {
        expect(
            fitImageSize(
                { width: 400, height: 1000 },
                { width: 360, height: 280 },
            ),
        ).toEqual({ width: 112, height: 280 });
    });

    it('nunca devolve zero para imagem valida', () => {
        const size = fitImageSize(
            { width: 5000, height: 1 },
            { width: 10, height: 10 },
        );

        expect(size.width).toBe(10);
        expect(size.height).toBe(1);
    });

    it('trata dimensoes invalidas', () => {
        expect(
            fitImageSize({ width: 0, height: 100 }, { width: 10, height: 10 }),
        ).toEqual({ width: 0, height: 0 });
    });
});

describe('clampPixel', () => {
    const size = { width: 10, height: 5 };

    it('prende dentro da imagem', () => {
        expect(clampPixel({ x: -3, y: 99 }, size)).toEqual({ x: 0, y: 4 });
        expect(clampPixel({ x: 5.9, y: 2.1 }, size)).toEqual({ x: 5, y: 2 });
    });
});

describe('pointToPixel', () => {
    const rect = { left: 100, top: 50, width: 200, height: 100 };
    const size = { width: 400, height: 200 };

    it('mapeia o ponto para o pixel da imagem', () => {
        expect(pointToPixel({ x: 200, y: 100 }, rect, size)).toEqual({
            x: 200,
            y: 100,
        });
    });

    it('prende ponteiro fora da area', () => {
        expect(pointToPixel({ x: 0, y: 0 }, rect, size)).toEqual({
            x: 0,
            y: 0,
        });
        expect(pointToPixel({ x: 9999, y: 9999 }, rect, size)).toEqual({
            x: 399,
            y: 199,
        });
    });

    it('devolve zero quando a area nao tem tamanho', () => {
        expect(
            pointToPixel(
                { x: 10, y: 10 },
                { left: 0, top: 0, width: 0, height: 10 },
                size,
            ),
        ).toEqual({ x: 0, y: 0 });
    });
});

describe('pixelStep', () => {
    it('anda um pixel por seta', () => {
        expect(pixelStep('ArrowLeft', false)).toEqual({ x: -1, y: 0 });
        expect(pixelStep('ArrowRight', false)).toEqual({ x: 1, y: 0 });
        expect(pixelStep('ArrowUp', false)).toEqual({ x: 0, y: -1 });
        expect(pixelStep('ArrowDown', false)).toEqual({ x: 0, y: 1 });
    });

    it('anda dez pixels com Shift', () => {
        expect(pixelStep('ArrowRight', true)).toEqual({ x: 10, y: 0 });
    });

    it('ignora outras teclas', () => {
        expect(pixelStep('Enter', false)).toBeNull();
        expect(pixelStep('a', false)).toBeNull();
    });
});
