import type { ExtractedColor, ExtractionCount } from './extractContract';

/**
 * A paleta extraida que a tela guarda (ADR-122).
 *
 * Vive so na memoria da pagina, em `Tool.vue`: trocar de aba ou clicar numa
 * cor como base nao perde nada, recarregar perde. A F4 salva a partir daqui.
 *
 * A origem guarda **apenas** o nome do arquivo ou o host da URL - nunca a URL
 * completa, a imagem ou o blob.
 */

export type ExtractedPaletteSource =
    | { kind: 'file'; label: string }
    | { kind: 'url'; host: string };

export interface ExtractedPalette {
    colors: ExtractedColor[];
    requested: ExtractionCount;
    algorithmVersion: number;
    /** `false` quando a imagem tinha menos cores distintas que o pedido. */
    sufficient: boolean;
    visiblePixels: number;
    source: ExtractedPaletteSource;
}
