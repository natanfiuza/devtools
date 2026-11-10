/**
 * Tetos da importacao de imagem (ADR-122).
 *
 * Chegam do servidor por `ColorsToolProps` (config/tools.php): nenhum limite
 * mora no cliente. A tool os repassa a decodificacao e a busca por URL.
 */
export interface ImageLimits {
    /** Teto do arquivo/da resposta, em bytes, antes de decodificar. */
    maxBytes: number;
    /** Lado maximo da imagem original, em pixels. */
    maxSide: number;
    /** Total de pixels da imagem original. */
    maxPixels: number;
    /** Lado maximo da copia reduzida em que a quantizacao roda. */
    analysisSide: number;
    /** Teto da busca por URL, em milissegundos. */
    fetchTimeoutMs: number;
}
