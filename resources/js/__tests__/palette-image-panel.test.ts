import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    PALETTE_IMAGE_WATERMARK,
    type PaletteImageSource,
} from '@/tools/palette-generator/image/types';
import PaletteImagePanel from '@/tools/palette-generator/image/PaletteImagePanel.vue';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';

/**
 * Painel da imagem tipografica (F6, ADR-138).
 *
 * A previa e o proprio SVG do export como `data:`; os botoes so trocam o
 * formato e chamam a exportacao. Os downloads sao dublês: o documento tem
 * teste proprio em `palette-image-export.test.ts`.
 */

const downloadSvgMock = vi.hoisted(() => vi.fn());
const downloadPngMock = vi.hoisted(() => vi.fn());

vi.mock(
    '@/tools/palette-generator/image/exportImage',
    async (importOriginal) => {
        const actual =
            await importOriginal<
                typeof import('@/tools/palette-generator/image/exportImage')
            >();

        return {
            ...actual,
            downloadPaletteImageSvg: downloadSvgMock,
            downloadPaletteImagePng: downloadPngMock,
        };
    },
);

const TRADUCOES = {
    colors: {
        image: {
            tab: 'IMAGEM',
            title: 'IMAGEM_TITULO',
            description: 'IMAGEM_DESCRICAO',
            layout_label: 'FORMATO',
            layout_horizontal: 'HORIZONTAL',
            layout_vertical: 'VERTICAL',
            layout_card: 'CARTAO',
            size_horizontal: '1600 × 500 px',
            size_vertical: '1080 × 1920 px',
            size_card: '1080 × 1080 px',
            note: 'NOME_E_HEX',
            open: 'GERAR_IMAGEM',
            close: 'FECHAR',
            preview_alt: 'PREVIA {layout} DE {name}',
            download_svg: 'BAIXAR_SVG',
            download_png: 'BAIXAR_PNG',
            download_png_2x: 'BAIXAR_PNG_2X',
            png_error: 'ERRO_PNG',
        },
    },
};

const COLORS = ['#0f172a', '#1e3a8a', '#2563eb', '#60a5fa', '#dbeafe'];

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: TRADUCOES,
    });

    return instance;
}

function mountPanel(source: Partial<PaletteImageSource> = {}) {
    return mount(PaletteImagePanel, {
        props: { name: 'Oceano', colors: COLORS, ...source },
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });
}

function query<T extends HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

async function click(selector: string): Promise<void> {
    query<HTMLButtonElement>(selector).click();
    await nextTick();
}

function preview(): HTMLImageElement {
    return query<HTMLImageElement>('[data-test="palette-image-preview"]');
}

function previewSvg(): string {
    return decodeURIComponent(
        preview().getAttribute('src')?.split(',').slice(1).join(',') ?? '',
    );
}

beforeEach(() => {
    downloadSvgMock.mockReset();
    downloadSvgMock.mockResolvedValue(undefined);
    downloadPngMock.mockReset();
    downloadPngMock.mockResolvedValue(undefined);
    document.body.innerHTML = '';
});

describe('PaletteImagePanel', () => {
    it('abre no formato horizontal com as medidas certas', () => {
        mountPanel();

        expect(
            query('[data-test="palette-image-size"]').textContent,
        ).toContain('1600 × 500 px');
        expect(preview().getAttribute('width')).toBe('1600');
        expect(preview().getAttribute('height')).toBe('500');
        expect(preview().getAttribute('src')).toMatch(
            /^data:image\/svg\+xml;charset=utf-8,/,
        );
        expect(previewSvg()).toContain(PALETTE_IMAGE_WATERMARK);
        expect(
            query('[data-test="image-layout-horizontal"]').getAttribute(
                'aria-pressed',
            ),
        ).toBe('true');
    });

    it('troca de formato e redesenha a previa', async () => {
        mountPanel();

        await click('[data-test="image-layout-vertical"]');

        expect(
            query('[data-test="palette-image-size"]').textContent,
        ).toContain('1080 × 1920 px');
        expect(preview().getAttribute('width')).toBe('1080');
        expect(preview().getAttribute('height')).toBe('1920');
        expect(
            query('[data-test="image-layout-vertical"]').getAttribute(
                'aria-pressed',
            ),
        ).toBe('true');
        expect(
            query('[data-test="image-layout-horizontal"]').getAttribute(
                'aria-pressed',
            ),
        ).toBe('false');

        await click('[data-test="image-layout-card"]');

        expect(
            query('[data-test="palette-image-size"]').textContent,
        ).toContain('1080 × 1080 px');
    });

    it('baixa o SVG do formato atual', async () => {
        mountPanel();

        await click('[data-test="image-layout-card"]');
        await click('[data-test="palette-image-download-svg"]');
        await flushPromises();

        expect(downloadSvgMock).toHaveBeenCalledWith(
            { name: 'Oceano', colors: COLORS },
            'card',
        );
    });

    it('baixa o PNG em 1x e 2x', async () => {
        mountPanel();

        await click('[data-test="palette-image-download-png"]');
        await flushPromises();

        expect(downloadPngMock).toHaveBeenLastCalledWith(
            { name: 'Oceano', colors: COLORS },
            'horizontal',
            1,
        );

        await click('[data-test="palette-image-download-png-2x"]');
        await flushPromises();

        expect(downloadPngMock).toHaveBeenLastCalledWith(
            { name: 'Oceano', colors: COLORS },
            'horizontal',
            2,
        );
    });

    it('mostra o erro do PNG e limpa ao trocar de formato', async () => {
        downloadPngMock.mockRejectedValueOnce(new Error('sem canvas'));

        mountPanel();

        await click('[data-test="palette-image-download-png"]');
        await flushPromises();

        expect(
            query('[data-test="palette-image-error"]').textContent,
        ).toContain('ERRO_PNG');

        await click('[data-test="image-layout-vertical"]');

        expect(
            document.querySelector('[data-test="palette-image-error"]'),
        ).toBeNull();
    });

    it('nao dispara dois downloads ao mesmo tempo', async () => {
        let finish: () => void = () => {};

        downloadPngMock.mockImplementation(
            () =>
                new Promise<void>((resolve) => {
                    finish = resolve;
                }),
        );

        mountPanel();

        await click('[data-test="palette-image-download-png"]');
        await nextTick();

        // O botao fica desabilitado enquanto o download corre.
        expect(
            query<HTMLButtonElement>('[data-test="palette-image-download-png"]')
                .disabled,
        ).toBe(true);

        query<HTMLButtonElement>(
            '[data-test="palette-image-download-png"]',
        ).click();
        await nextTick();

        expect(downloadPngMock).toHaveBeenCalledTimes(1);

        finish();
        await flushPromises();

        expect(
            query<HTMLButtonElement>('[data-test="palette-image-download-png"]')
                .disabled,
        ).toBe(false);
    });
});
