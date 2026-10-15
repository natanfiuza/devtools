import type { WorkerRequest, WorkerResponse } from './client';

/**
 * O outro lado do cliente: o worker que responde.
 *
 * Um worker de tool nao guarda estado - cada mensagem e uma conta completa do
 * payload que veio, e e isso que permite a queda para a thread principal dar
 * **exatamente** o mesmo resultado, porque e a mesma funcao que roda nos dois
 * lados.
 *
 * O `self` e tipado na mao porque o projeto compila com `lib: DOM`, e incluir
 * `lib: webworker` colidiria com ela (as duas bibliotecas declaram os mesmos
 * globais, com tipos diferentes). O que o worker usa do escopo e so isto.
 */
interface WorkerScope<Payload, Result> {
    addEventListener(
        type: 'message',
        handler: (event: MessageEvent<WorkerRequest<Payload>>) => void,
    ): void;
    postMessage(message: WorkerResponse<Result>): void;
}

/**
 * Liga a funcao pura ao worker: chega `{id, payload}`, sai `{id, result}`.
 *
 * Erro que escapar da conta derruba o worker - e o cliente cai para a thread
 * principal, onde o mesmo erro acontece. Quem tem um erro **previsto** trata
 * dele dentro da propria funcao, devolvendo-o como resultado (e o que o SQL
 * Formatter faz com o texto que nao e SQL).
 */
export function serve<Payload, Result>(run: (payload: Payload) => Result): void {
    const scope = self as unknown as WorkerScope<Payload, Result>;

    scope.addEventListener('message', (event) => {
        const { id, payload } = event.data;

        scope.postMessage({ id, result: run(payload) });
    });
}
