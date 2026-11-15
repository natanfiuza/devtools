import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    buildPaletteImage,
    imageColors,
} from '@/tools/palette-generator/image/buildImage';
import {
    downloadPaletteImagePng,
    downloadPaletteImageSvg,
    paletteImageFileName,
    paletteImagePng,
    paletteImagePreview,
    paletteImageSvg,
} from '@/tools/palette-generator/image/exportImage';
import {
    PALETTE_IMAGE_SIZES,
    PALETTE_IMAGE_WATERMARK,
    type PaletteImageLayout,
    type PaletteImageSource,
} from '@/tools/palette-generator/image/types';

/**
 * Imagem tipografica da paleta (F6, ADR-138).
 *
 * O modulo e puro: a mesma paleta gera o mesmo SVG, com o nome, os HEX em
 * minusculas e a marca fixa. O rasterizador e a gravacao sao dublês porque o
 * canvas nao existe no jsdom; o que se prova aqui e o documento entregue e os
 * dois downloads, nao a pintura.
 */

const rasterizeMock = vi.hoisted(() => vi.fn());
const downloadMock = vi.hoisted(() => vi.fn());

vi.mock('@/tools/palette-generator/prototype/rasterize', () => ({
    rasterizeSvg: rasterizeMock,
}));

vi.mock('@/lib/download', () => ({ downloadBlob: downloadMock }));

const SOURCE: PaletteImageSource = {
    name: 'Oceano',
    colors: ['#0F172A', '#1E3A8A', '#2563EB', '#60A5FA', '#DBEAFE'],
};

const LAYOUTS: PaletteImageLayout[] = ['horizontal', 'vertical', 'card'];

function textOf(svg: string): Document {
    return new DOMParser().parseFromString(svg, 'image/svg+xml');
}

beforeEach(() => {
    rasterizeMock.mockReset();
    rasterizeMock.mockImplementation(
        async () => new Blob(['png'], { type: 'image/png' }),
    );
    downloadMock.mockReset();
});

describe('Imagem da paleta: documento', () => {
    it.each(LAYOUTS)('usa as medidas fixas do formato %s', (layout) => {
        const document = buildPaletteImage(SOURCE, layout);
        const expected = PALETTE_IMAGE_SIZES[layout];

        expect(document.kind).toBe('image');
        expect(document.theme).toBe('light');
        expect({ width: document.width, height: document.height }).toEqual(
            expected,
        );
    });

    it('e deterministica para a mesma entrada', () => {
        for (const layout of LAYOUTS) {
            expect(paletteImagePreview(SOURCE, layout)).toBe(
                paletteImagePreview(SOURCE, layout),
            );
        }
    });

    it('assina toda imagem com a marca fixa do devtools', () => {
        for (const layout of LAYOUTS) {
            expect(paletteImagePreview(SOURCE, layout)).toContain(
                PALETTE_IMAGE_WATERMARK,
            );
        }
    });

    it('pinta um retangulo por cor, na ordem da paleta', () => {
        const document = buildPaletteImage(SOURCE, 'horizontal');
        const fills = document.nodes
            .filter((node) => node.kind === 'rect')
            .map((node) => node.fill);

        // Primeiro o fundo, depois as cinco cores.
        expect(fills).toHaveLength(SOURCE.colors.length + 1);
        expect(fills.slice(1)).toEqual(
            SOURCE.colors.map((hex) => hex.toLowerCase()),
        );
    });

    it('escreve o HEX de cada cor no SVG', () => {
        const svg = paletteImagePreview(SOURCE, 'card');

        for (const hex of SOURCE.colors) {
            expect(svg).toContain(hex.toLowerCase());
        }
    });

    it('normaliza o HEX e descarta cor invalida', () => {
        const source: PaletteImageSource = {
            name: 'Mista',
            colors: [
                ' #AaBbCc ',
                'vermelho',
                '#12345',
                'javascript:alert(1)',
                '#000000ff',
            ],
        };

        expect(imageColors(source)).toEqual(['#aabbcc', '#000000ff']);
        expect(imageColors(source)).not.toContain('vermelho');
    });

    it('encurta o nome no limite de cada formato', () => {
        const longName =
            'Uma paleta com um nome realmente muito comprido demais';

        for (const layout of LAYOUTS) {
            const document = buildPaletteImage(
                { ...SOURCE, name: longName },
                layout,
            );

            expect(document.label.length).toBeLessThanOrEqual(
                layout === 'horizontal' ? 26 : 22,
            );
            expect(document.label.endsWith('…')).toBe(true);
        }
    });

    it('escapa texto hostil e nao cria referencia externa', () => {
        const svg = paletteImagePreview(
            { name: 'A < B & "C"', colors: SOURCE.colors },
            'horizontal',
        );

        expect(svg).not.toContain('<script');
        expect(svg).not.toContain('A < B');
        expect(svg).toContain('A &lt; B &amp; &quot;C&quot;');
    });

    it.each(LAYOUTS)('gera SVG sanitizado no formato %s', (layout) => {
        const svg = paletteImagePreview(SOURCE, layout);

        expect(svg).not.toMatch(/<script/i);
        expect(svg).not.toMatch(/<image/i);
        expect(svg).not.toMatch(/<foreignObject/i);
        expect(svg).not.toMatch(/href=/i);
        expect(svg).not.toMatch(/src=/i);
        expect(svg).not.toMatch(/url\(/i);
        expect(svg).toMatch(/^<svg xmlns="http:\/\/www\.w3\.org\/2000\/svg"/);
    });

    it('a previa nao carrega a fonte e o SVG exportado carrega', async () => {
        const preview = paletteImagePreview(SOURCE, 'vertical');

        expect(preview).not.toContain('<style>');

        const exported = await paletteImageSvg(SOURCE, 'vertical');

        expect(exported).toContain('<style>');
        expect(exported).toContain('data:font/ttf;base64');
        expect(textOf(exported).querySelector('title')?.textContent).toBe(
            'Oceano',
        );
    });
});

describe('Imagem da paleta: exportacao', () => {
    it('rasteriza o SVG no tamanho do formato vezes a escala', async () => {
        const blob = await paletteImagePng(SOURCE, 'card', 2);
        const [svg, size, scale] = rasterizeMock.mock.calls[0] as [
            string,
            { width: number; height: number },
            number,
        ];

        expect(blob.type).toBe('image/png');
        expect(svg).toContain(PALETTE_IMAGE_WATERMARK);
        expect(svg).toContain('data:font/ttf;base64');
        expect(size).toEqual({ width: 1080, height: 1080 });
        expect(scale).toBe(2);
    });

    it('propaga a falha do rasterizador', async () => {
        rasterizeMock.mockRejectedValueOnce(new Error('canvas indisponivel'));

        await expect(paletteImagePng(SOURCE, 'card', 1)).rejects.toThrow(
            'canvas indisponivel',
        );
    });

    it('usa nome de arquivo estavel por formato', () => {
        expect(paletteImageFileName('horizontal', 'svg')).toBe(
            'palette-horizontal.svg',
        );
        expect(paletteImageFileName('vertical', 'png')).toBe(
            'palette-vertical.png',
        );
    });

    it('baixa o SVG com o tipo correto', async () => {
        await downloadPaletteImageSvg(SOURCE, 'horizontal');

        expect(downloadMock).toHaveBeenCalledTimes(1);

        const [filename, blob] = downloadMock.mock.calls[0] as [string, Blob];

        expect(filename).toBe('palette-horizontal.svg');
        expect(blob.type).toBe('image/svg+xml;charset=utf-8');
        await expect(blob.text()).resolves.toContain(PALETTE_IMAGE_WATERMARK);
    });

    it('baixa o PNG na escala pedida', async () => {
        await downloadPaletteImagePng(SOURCE, 'card', 1);

        const [filename, blob] = downloadMock.mock.calls[0] as [string, Blob];

        expect(filename).toBe('palette-card.png');
        expect(blob.type).toBe('image/png');
        expect(rasterizeMock).toHaveBeenCalledTimes(1);
        expect(rasterizeMock.mock.calls[0]?.[2]).toBe(1);
    });
});
