/**
 * Imagem tipografica da paleta (F6, ADR-138).
 *
 * Tres formatos fixos para quem quer apresentar as cores fora do app. A
 * composicao e sempre a mesma - nome, codigos HEX e a marca do devtools - e
 * muda so o arranjo. As medidas moram aqui, nao espalhadas pelo painel.
 */

export const PALETTE_IMAGE_LAYOUTS = ['horizontal', 'vertical', 'card'] as const;

export type PaletteImageLayout = (typeof PALETTE_IMAGE_LAYOUTS)[number];

export type PaletteImageSize = {
    width: number;
    height: number;
};

/** Medidas fixas de cada formato, como pedido na fase 6. */
export const PALETTE_IMAGE_SIZES: Record<
    PaletteImageLayout,
    PaletteImageSize
> = {
    horizontal: { width: 1600, height: 500 },
    vertical: { width: 1080, height: 1920 },
    card: { width: 1080, height: 1080 },
};

/** Marca fixa no rodape de toda imagem exportada. */
export const PALETTE_IMAGE_WATERMARK = 'devtools.natanfiuza.dev.br';

/** Fonte da imagem: o nome da paleta e os HEX na ordem gravada. */
export type PaletteImageSource = {
    name: string;
    colors: string[];
};

/** Cores de apoio do desenho (nao vem da paleta). */
export const IMAGE_BACKGROUND = '#ffffff';
export const IMAGE_TEXT = '#111111';
export const IMAGE_MUTED = '#666666';
