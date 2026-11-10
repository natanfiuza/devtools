import type { Rgba } from '@/lib/color';

/**
 * Contrato da extracao de cores (ADR-122), sem a biblioteca.
 *
 * Tipos e constantes ficam aqui, longe de `extract.ts`, para a pagina poder
 * importar a quantidade valida e o formato do resultado sem puxar a
 * color-thief: so o worker (e o fallback dinamico) carregam a quantizacao.
 */

export type ExtractionCount = 5 | 7 | 9 | 11;

export const EXTRACTION_COUNTS: readonly ExtractionCount[] = [5, 7, 9, 11];

export const DEFAULT_EXTRACTION_COUNT: ExtractionCount = 11;

/**
 * Versao do algoritmo de extracao. Muda quando as regras de quantizacao,
 * peso ou transparencia mudarem; entra no resultado para a F4 saber com o
 * que uma paleta salva foi gerada.
 */
export const EXTRACTION_ALGORITHM_VERSION = 1;

export interface ExtractionRequest {
    /** RGBA em bytes, 4 por pixel, sem copia do worker. */
    data: Uint8ClampedArray<ArrayBuffer>;
    width: number;
    height: number;
    count: ExtractionCount;
}

export interface ExtractedColor {
    rgba: Rgba;
    hex: string;
    /** Pixels visiveis atribuidos a esta cor. */
    weight: number;
    /** Fatia dos pixels visiveis, em 0..100 com duas casas. */
    percentage: number;
}

export interface ExtractionResult {
    /** Ordenado por peso decrescente; empate pela cor (HEX). */
    colors: ExtractedColor[];
    requested: ExtractionCount;
    algorithmVersion: number;
    /** `false` quando a imagem tem menos cores distintas que o pedido. */
    sufficient: boolean;
    /** Pixels com alpha >= 0,5 considerados na extracao. */
    visiblePixels: number;
}

export type ExtractionErrorCode = 'empty_image' | 'failed';

export type ExtractionOutcome =
    | { ok: true; result: ExtractionResult }
    | { ok: false; error: ExtractionErrorCode };
