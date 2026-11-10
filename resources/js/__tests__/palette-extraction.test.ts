import { describe, expect, it, vi } from 'vitest';
import { hslToRgb } from '@/lib/color';
import {
    DEFAULT_EXTRACTION_COUNT,
    EXTRACTION_ALGORITHM_VERSION,
    EXTRACTION_COUNTS,
    extractPalette,
    type ExtractionCount,
    type ExtractionRequest,
    type ExtractionResult,
} from '@/tools/palette-generator/lib/extract';

/**
 * Extracao de paleta (ADR-122).
 *
 * O jsdom nao implementa `ImageData`, entao a classe padrao e substituida por
 * uma minima equivalente. Assim o `color-thief` real (quantizacao MMCQ em
 * OKLCH) roda de verdade nos testes, sem mock da biblioteca.
 */

class TestImageData {
    readonly colorSpace = 'srgb';

    constructor(
        readonly data: Uint8ClampedArray<ArrayBuffer>,
        readonly width: number,
        readonly height: number,
    ) {}
}

vi.stubGlobal('ImageData', TestImageData);

const WIDTH = 64;
const HEIGHT = 64;

type Pixel = [r: number, g: number, b: number, a?: number];

function image(pixel: (x: number, y: number) => Pixel): Uint8ClampedArray<ArrayBuffer> {
    const data = new Uint8ClampedArray(WIDTH * HEIGHT * 4);

    for (let y = 0; y < HEIGHT; y += 1) {
        for (let x = 0; x < WIDTH; x += 1) {
            const [r, g, b, a = 255] = pixel(x, y);
            const offset = (y * WIDTH + x) * 4;

            data[offset] = r;
            data[offset + 1] = g;
            data[offset + 2] = b;
            data[offset + 3] = a;
        }
    }

    return data;
}

function request(
    data: Uint8ClampedArray<ArrayBuffer>,
    count: ExtractionCount = DEFAULT_EXTRACTION_COUNT,
): ExtractionRequest {
    return { data, width: WIDTH, height: HEIGHT, count };
}

function expectOk(outcome: ReturnType<typeof extractPalette>): ExtractionResult {
    if (!outcome.ok) {
        throw new Error(`extracao falhou: ${outcome.error}`);
    }

    return outcome.result;
}

/** Imagem com matiz variando em X e luminosidade em Y: milhares de cores. */
function gradient(): Uint8ClampedArray<ArrayBuffer> {
    return image((x, y) => {
        const rgb = hslToRgb({
            h: (x / WIDTH) * 360,
            s: 80,
            l: 25 + (y / (HEIGHT - 1)) * 50,
        });

        return [Math.round(rgb.r), Math.round(rgb.g), Math.round(rgb.b)];
    });
}

describe('quadrantes e pesos', () => {
    it('recupera as quatro cores com 25 por cento cada', () => {
        const corners: Pixel[] = [
            [255, 0, 0],
            [0, 255, 0],
            [0, 0, 255],
            [255, 255, 255],
        ];
        const data = image((x, y) => {
            const quadrant = (y < HEIGHT / 2 ? 0 : 2) + (x < WIDTH / 2 ? 0 : 1);

            return corners[quadrant] as Pixel;
        });
        const result = expectOk(extractPalette(request(data, 5)));
        const hexes = result.colors.map((color) => color.hex).sort();

        expect(hexes).toEqual(
            ['#0000ff', '#00ff00', '#ff0000', '#ffffff'],
        );
        for (const color of result.colors) {
            expect(color.percentage).toBeCloseTo(25, 4);
            expect(color.weight).toBe((WIDTH / 2) * (HEIGHT / 2));
        }
        expect(result.visiblePixels).toBe(WIDTH * HEIGHT);
        expect(result.sufficient).toBe(false);
        expect(result.requested).toBe(5);
        expect(result.algorithmVersion).toBe(EXTRACTION_ALGORITHM_VERSION);
    });

    it('ordena por peso decrescente e calcula o percentual', () => {
        const data = image((x) => (x < 48 ? [255, 0, 0] : [0, 0, 255]));
        const result = expectOk(extractPalette(request(data, 5)));

        expect(result.colors).toHaveLength(2);
        expect(result.colors[0]?.hex).toBe('#ff0000');
        expect(result.colors[0]?.percentage).toBeCloseTo(75, 4);
        expect(result.colors[0]?.weight).toBe(48 * HEIGHT);
        expect(result.colors[1]?.hex).toBe('#0000ff');
        expect(result.colors[1]?.percentage).toBeCloseTo(25, 4);
    });

    it('soma os percentuais em 100', () => {
        const result = expectOk(extractPalette(request(gradient(), 7)));
        const total = result.colors.reduce(
            (sum, color) => sum + color.percentage,
            0,
        );

        expect(total).toBeCloseTo(100, 1);
    });
});

describe('transparencia', () => {
    it('ignora pixels com alpha abaixo de 0,5', () => {
        const data = image((x) =>
            x < WIDTH / 2 ? [255, 0, 0, 255] : [0, 255, 0, 127],
        );
        const result = expectOk(extractPalette(request(data, 5)));

        expect(result.visiblePixels).toBe((WIDTH / 2) * HEIGHT);
        expect(result.colors.map((color) => color.hex)).toEqual(['#ff0000']);
        expect(result.colors[0]?.percentage).toBeCloseTo(100, 4);
    });

    it('conta pixels exatamente no limite de 0,5', () => {
        const data = image((x) =>
            x < WIDTH / 2 ? [255, 0, 0, 128] : [0, 0, 255, 128],
        );
        const result = expectOk(extractPalette(request(data, 5)));

        expect(result.colors).toHaveLength(2);
        expect(result.visiblePixels).toBe(WIDTH * HEIGHT);
    });

    it('imagem toda transparente vira empty_image', () => {
        const data = image(() => [255, 0, 0, 0]);

        expect(extractPalette(request(data))).toEqual({
            ok: false,
            error: 'empty_image',
        });
    });
});

describe('quantidade pedida', () => {
    it.each(EXTRACTION_COUNTS)('devolve %i cores de uma imagem variada', (count) => {
        const result = expectOk(extractPalette(request(gradient(), count)));

        expect(result.colors).toHaveLength(count);
        expect(result.sufficient).toBe(true);
        expect(new Set(result.colors.map((color) => color.hex)).size).toBe(count);
    });

    it('imagem uniforme devolve uma cor e nao inventa as demais', () => {
        const result = expectOk(extractPalette(request(image(() => [18, 52, 86]))));

        expect(result.colors).toHaveLength(1);
        expect(result.colors[0]?.hex).toBe('#123456');
        expect(result.colors[0]?.percentage).toBe(100);
        expect(result.sufficient).toBe(false);
    });

    it('mantem o branco quando ignoreWhite esta desligado', () => {
        const data = image((x) => (x < WIDTH / 2 ? [255, 255, 255] : [0, 0, 0]));
        const result = expectOk(extractPalette(request(data, 5)));

        expect(result.colors.map((color) => color.hex).sort()).toEqual([
            '#000000',
            '#ffffff',
        ]);
    });
});

describe('determinismo e erros', () => {
    it('duas execucoes iguais devolvem o mesmo resultado', () => {
        const data = gradient();

        expect(extractPalette(request(data, 9))).toEqual(
            extractPalette(request(data, 9)),
        );
    });

    it('dados inconsistentes viram failed', () => {
        expect(
            extractPalette({
                data: new Uint8ClampedArray(16),
                width: WIDTH,
                height: HEIGHT,
                count: 5,
            }),
        ).toEqual({ ok: false, error: 'failed' });
    });

    it('dimensoes invalidas viram failed', () => {
        expect(
            extractPalette({
                data: new Uint8ClampedArray(0),
                width: 0,
                height: 0,
                count: 5,
            }),
        ).toEqual({ ok: false, error: 'failed' });
    });
});

describe('contrato', () => {
    it('expoe os tetos, o padrao e a versao do algoritmo', () => {
        expect(EXTRACTION_COUNTS).toEqual([5, 7, 9, 11]);
        expect(DEFAULT_EXTRACTION_COUNT).toBe(11);
        expect(EXTRACTION_ALGORITHM_VERSION).toBe(1);
    });

    it('cada cor traz rgba opaco, hex e peso', () => {
        const result = expectOk(extractPalette(request(gradient(), 5)));

        for (const color of result.colors) {
            expect(color.rgba.a).toBe(1);
            expect(color.hex).toMatch(/^#[0-9a-f]{6}$/);
            expect(color.weight).toBeGreaterThan(0);
        }
    });
});
