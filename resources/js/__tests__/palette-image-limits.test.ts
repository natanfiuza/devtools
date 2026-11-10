import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    ACCEPTED_IMAGE_TYPES,
    acceptsImageType,
    decodeImage,
    fitAnalysisSize,
    validateImageDimensions,
} from '@/tools/palette-generator/lib/decodeImage';
import type { ImageLimits } from '@/tools/palette-generator/lib/imageLimits';

/**
 * Decodificacao e limites (ADR-122).
 *
 * O jsdom nao tem `createImageBitmap` nem canvas 2D de verdade: o bitmap e o
 * canvas sao fingidos, e o que fica coberto e **a ordem das checagens** e o
 * que acontece com o bitmap quando algo falha.
 */

const LIMITS: ImageLimits = {
    maxBytes: 1024,
    maxSide: 4096,
    maxPixels: 16_000_000,
    analysisSide: 512,
    fetchTimeoutMs: 8000,
};

interface FakeCanvas {
    width: number;
    height: number;
    getContext: ReturnType<typeof vi.fn>;
}

interface FakeContext {
    imageSmoothingEnabled: boolean;
    imageSmoothingQuality: string;
    drawImage: ReturnType<typeof vi.fn>;
    getImageData: ReturnType<typeof vi.fn>;
}

function fakeCanvas(
    getImageData?: FakeContext['getImageData'],
): { canvas: FakeCanvas; context: FakeContext } {
    const context: FakeContext = {
        imageSmoothingEnabled: false,
        imageSmoothingQuality: 'low',
        drawImage: vi.fn(),
        getImageData:
            getImageData ??
            vi.fn((_x: number, _y: number, width: number, height: number) => ({
                data: new Uint8ClampedArray(width * height * 4),
                width,
                height,
            })),
    };
    const canvas: FakeCanvas = {
        width: 0,
        height: 0,
        getContext: vi.fn(() => context),
    };

    return { canvas, context };
}

function useCanvas(fake: { canvas: FakeCanvas }): void {
    const original = document.createElement.bind(document);

    vi.spyOn(document, 'createElement').mockImplementation(
        (tag: string, options?: ElementCreationOptions) =>
            tag === 'canvas'
                ? (fake.canvas as unknown as HTMLElement)
                : original(tag, options),
    );
}

function bitmap(width: number, height: number): ImageBitmap {
    return {
        width,
        height,
        close: vi.fn(),
    } as unknown as ImageBitmap;
}

function useBitmap(value: ImageBitmap | Error): void {
    vi.stubGlobal(
        'createImageBitmap',
        vi.fn(() =>
            value instanceof Error
                ? Promise.reject(value)
                : Promise.resolve(value),
        ),
    );
}

function file(bytes = 8, type = 'image/png'): File {
    return new File([new Uint8Array(bytes)], 'foto.png', { type });
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('fitAnalysisSize', () => {
    it('reduz paisagem pelo lado maior', () => {
        expect(fitAnalysisSize({ width: 4096, height: 2048 }, 512)).toEqual({
            width: 512,
            height: 256,
        });
    });

    it('reduz retrato pelo lado maior', () => {
        expect(fitAnalysisSize({ width: 1000, height: 4000 }, 512)).toEqual({
            width: 128,
            height: 512,
        });
    });

    it('nunca aumenta uma imagem pequena', () => {
        expect(fitAnalysisSize({ width: 320, height: 200 }, 512)).toEqual({
            width: 320,
            height: 200,
        });
    });

    it('mantem pelo menos um pixel no lado menor', () => {
        expect(fitAnalysisSize({ width: 5000, height: 1 }, 10)).toEqual({
            width: 10,
            height: 1,
        });
    });

    it('dimensao invalida vira tamanho zero', () => {
        expect(fitAnalysisSize({ width: 0, height: 10 }, 512)).toEqual({
            width: 0,
            height: 0,
        });
        expect(fitAnalysisSize({ width: 10, height: 10 }, 0)).toEqual({
            width: 0,
            height: 0,
        });
    });
});

describe('validateImageDimensions', () => {
    it('aceita dentro dos tetos', () => {
        expect(validateImageDimensions(4096, 3906, LIMITS)).toBeNull();
    });

    it('recusa lado acima do teto', () => {
        expect(validateImageDimensions(4097, 10, LIMITS)).toBe(
            'dimensions_too_large',
        );
        expect(validateImageDimensions(10, 4097, LIMITS)).toBe(
            'dimensions_too_large',
        );
    });

    it('recusa area acima do teto de pixels', () => {
        expect(
            validateImageDimensions(4000, 4000, {
                ...LIMITS,
                maxPixels: 1_000_000,
            }),
        ).toBe('too_many_pixels');
    });

    it('recusa dimensao degenerada', () => {
        expect(validateImageDimensions(0, 10, LIMITS)).toBe('decode_failed');
        expect(validateImageDimensions(10.5, 10, LIMITS)).toBe('decode_failed');
    });
});

describe('acceptsImageType', () => {
    it('aceita os rasters suportados, com parametros', () => {
        for (const type of ACCEPTED_IMAGE_TYPES) {
            expect(acceptsImageType(type)).toBe(true);
            expect(acceptsImageType(`${type}; charset=binary`)).toBe(true);
        }
    });

    it('recusa SVG, HTML e tipo vazio', () => {
        expect(acceptsImageType('image/svg+xml')).toBe(false);
        expect(acceptsImageType('text/html')).toBe(false);
        expect(acceptsImageType('')).toBe(false);
    });
});

describe('decodeImage', () => {
    it('confere os bytes antes de decodificar', async () => {
        const spy = vi.fn();

        vi.stubGlobal('createImageBitmap', spy);

        const outcome = await decodeImage(file(64), { ...LIMITS, maxBytes: 10 });

        expect(outcome).toEqual({ ok: false, error: 'too_large' });
        expect(spy).not.toHaveBeenCalled();
    });

    it('recusa tipo nao raster antes de decodificar', async () => {
        const spy = vi.fn();

        vi.stubGlobal('createImageBitmap', spy);

        const outcome = await decodeImage(file(8, 'image/svg+xml'), LIMITS);

        expect(outcome).toEqual({ ok: false, error: 'not_image' });
        expect(spy).not.toHaveBeenCalled();
    });

    it('reduz para o lado de analise e devolve o RGBA', async () => {
        const fake = fakeCanvas();
        const image = bitmap(4096, 2048);

        useCanvas(fake);
        useBitmap(image);

        const outcome = await decodeImage(file(), LIMITS);

        expect(outcome.ok).toBe(true);

        if (outcome.ok) {
            expect(outcome.image.width).toBe(4096);
            expect(outcome.image.height).toBe(2048);
            expect(outcome.image.sampledWidth).toBe(512);
            expect(outcome.image.sampledHeight).toBe(256);
            expect(outcome.image.downscaled).toBe(true);
            expect(outcome.image.data).toHaveLength(512 * 256 * 4);
        }

        expect(fake.context.imageSmoothingEnabled).toBe(true);
        expect(fake.context.imageSmoothingQuality).toBe('high');
        expect(fake.context.drawImage).toHaveBeenCalledWith(image, 0, 0, 512, 256);
        expect(fake.context.getImageData).toHaveBeenCalledWith(0, 0, 512, 256);
        expect(image.close).toHaveBeenCalled();
    });

    it('nao reduz imagem menor que o lado de analise', async () => {
        const fake = fakeCanvas();

        useCanvas(fake);
        useBitmap(bitmap(320, 200));

        const outcome = await decodeImage(file(), LIMITS);

        expect(outcome.ok).toBe(true);

        if (outcome.ok) {
            expect(outcome.image.sampledWidth).toBe(320);
            expect(outcome.image.sampledHeight).toBe(200);
            expect(outcome.image.downscaled).toBe(false);
        }
    });

    it('recusa lado grande demais e fecha o bitmap', async () => {
        const image = bitmap(5000, 100);

        useCanvas(fakeCanvas());
        useBitmap(image);

        const outcome = await decodeImage(file(), LIMITS);

        expect(outcome).toEqual({ ok: false, error: 'dimensions_too_large' });
        expect(image.close).toHaveBeenCalled();
    });

    it('recusa pixels demais', async () => {
        useCanvas(fakeCanvas());
        useBitmap(bitmap(4000, 4000));

        const outcome = await decodeImage(file(), {
            ...LIMITS,
            maxPixels: 1_000_000,
        });

        expect(outcome).toEqual({ ok: false, error: 'too_many_pixels' });
    });

    it('bitmap que nao decodifica vira decode_failed', async () => {
        useBitmap(new Error('formato quebrado'));

        const outcome = await decodeImage(file(), LIMITS);

        expect(outcome).toEqual({ ok: false, error: 'decode_failed' });
    });

    it('sem createImageBitmap vira unavailable', async () => {
        const outcome = await decodeImage(file(), LIMITS);

        expect(outcome).toEqual({ ok: false, error: 'unavailable' });
    });

    it('sem contexto 2D vira unavailable e fecha o bitmap', async () => {
        const image = bitmap(100, 100);
        const original = document.createElement.bind(document);

        vi.spyOn(document, 'createElement').mockImplementation(
            (tag: string, options?: ElementCreationOptions) =>
                tag === 'canvas'
                    ? ({
                          width: 0,
                          height: 0,
                          getContext: () => null,
                      } as unknown as HTMLElement)
                    : original(tag, options),
        );
        useBitmap(image);

        const outcome = await decodeImage(file(), LIMITS);

        expect(outcome).toEqual({ ok: false, error: 'unavailable' });
        expect(image.close).toHaveBeenCalled();
    });

    it('erro ao ler os pixels vira decode_failed e fecha o bitmap', async () => {
        const image = bitmap(100, 100);
        const fake = fakeCanvas(
            vi.fn(() => {
                throw new Error('tainted');
            }),
        );

        useCanvas(fake);
        useBitmap(image);

        const outcome = await decodeImage(file(), LIMITS);

        expect(outcome).toEqual({ ok: false, error: 'decode_failed' });
        expect(image.close).toHaveBeenCalled();
    });
});
