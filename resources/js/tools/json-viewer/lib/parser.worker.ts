import { analyzeDocument } from './analyze';
import type { AnalyzeRequest, AnalyzeResponse } from './parser.protocol';

/**
 * O worker do parser: tira do caminho da digitacao o custo de analisar um
 * documento grande.
 *
 * Ele nao guarda estado: cada mensagem e uma analise completa do texto que
 * veio, e a resposta e o resultado dela. Isso e o que permite a queda para a
 * thread principal dar **exatamente** o mesmo resultado - e a mesma funcao.
 *
 * O `self` e tipado na mao porque o projeto compila com `lib: DOM`, e incluir
 * `lib: webworker` colidiria com ela (as duas bibliotecas declaram os mesmos
 * globais, com tipos diferentes). O que o worker usa do escopo e so isto.
 */
interface WorkerScope {
    addEventListener(
        type: 'message',
        handler: (event: MessageEvent<AnalyzeRequest>) => void,
    ): void;
    postMessage(message: AnalyzeResponse): void;
}

const scope = self as unknown as WorkerScope;

scope.addEventListener('message', (event) => {
    const { id, text, options } = event.data;

    scope.postMessage({ id, outcome: analyzeDocument(text, options) });
});
