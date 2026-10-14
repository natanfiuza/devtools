import type { AnalyzeOptions, AnalyzeOutcome } from './analyze';

/**
 * O combinado entre a thread principal e o worker.
 *
 * Um pedido de cada vez, com `id`: a resposta carrega o `id` do pedido que a
 * originou, e quem respondeu a um pedido velho e descartado (quem digitou ja
 * passou por cima).
 *
 * Tudo o que atravessa e clonavel por estrutura: numero, texto, e a arvore
 * achatada, que so tem primitivos.
 */

export interface AnalyzeRequest {
    id: number;
    text: string;
    options: AnalyzeOptions;
}

export interface AnalyzeResponse {
    id: number;
    outcome: AnalyzeOutcome;
}
