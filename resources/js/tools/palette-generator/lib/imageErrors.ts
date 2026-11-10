/**
 * Codigos de erro da importacao/analise de imagem (ADR-122).
 *
 * O dominio devolve o codigo; quem traduz e a tela, com uma chave por codigo.
 * Nenhuma mensagem nasce aqui - e o que permite os cinco idiomas.
 */
export type ImageErrorCode =
    | 'invalid_url'
    | 'not_https'
    | 'cors'
    | 'network'
    | 'timeout'
    | 'too_large'
    | 'not_image'
    | 'decode_failed'
    | 'dimensions_too_large'
    | 'too_many_pixels'
    | 'empty_image'
    | 'unavailable'
    /** Cancelado pelo usuario: a tela volta ao estado ocioso, sem mensagem. */
    | 'cancelled';
