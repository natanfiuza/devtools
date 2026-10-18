import {
    createWorkerClient,
    type WorkerClient,
    type WorkerSpawner,
    type WorkerThread,
} from '@/lib/worker/client';
import { search, type RegexRequest, type RegexResult } from './run';
import { createRunWorker } from './run.workerHost';

/**
 * O unico lugar da fase que **mata** um worker.
 *
 * O SQL Formatter e o JSON Viewer esperam o worker o quanto ele precisar, porque
 * a conta deles sempre acaba. Aqui nao: um padrao catastrofico roda por anos, e
 * esperar nao e esperar - e a aba parada com a resposta prometida para sempre.
 * Entao cada pedido tem relogio, e quem estoura leva `terminate`.
 *
 * Tres regras que o desenho segue:
 *
 * - **Estourou, nao ha resultado.** A conta continua viva no worker morto e
 *   acabaria chegando; mostra-la depois seria dizer que o padrao passou num
 *   texto que talvez nem tenha sido lido inteiro. A tela diz que estourou e
 *   mostra **nada** (Q20);
 * - **nao ha queda para a thread principal depois do estouro.** A queda normal
 *   (worker morto por CSP, memoria) roda a mesma funcao aqui - e aqui e
 *   exatamente onde a conta que estourou travaria a aba. Depois do estouro o
 *   que existe e um worker **novo**, e o proximo pedido nasce nele;
 * - **sem worker nao ha relogio.** Na thread principal o temporizador nao
 *   interrompe nada (a thread esta presa na conta) e so serviria para acusar de
 *   demorado um resultado que ainda vem.
 */

export type RunnerOutcome =
    | { status: 'done'; result: RegexResult }
    /** O pedido foi superado por outro mais novo: quem pinta a tela e o outro. */
    | { status: 'superseded' }
    | { status: 'timeout' };

export interface Runner {
    run(request: RegexRequest): Promise<RunnerOutcome>;
    /** Onde a conta roda agora: `worker` ou `main`. */
    thread(): WorkerThread;
    dispose(): void;
}

export interface RunnerOptions {
    /** O tempo maximo de uma conta, em ms (`TOOLS_REGEX_TIMEOUT_MS`). */
    timeoutMs: number;
    /** Injetavel para o teste poder fingir um worker. */
    spawn?: WorkerSpawner;
}

export function createRunner(options: RunnerOptions): Runner {
    let client = connect();

    function connect(): WorkerClient<RegexRequest, RegexResult> {
        return createWorkerClient<RegexRequest, RegexResult>(
            options.spawn ?? createRunWorker,
            search,
        );
    }

    return {
        async run(request) {
            const answer = client
                .run(request)
                .then((result): RunnerOutcome =>
                    result === null
                        ? { status: 'superseded' }
                        : { status: 'done', result },
                );

            if (client.thread() === 'main') {
                return answer;
            }

            let timer: ReturnType<typeof setTimeout> | undefined;
            const deadline = new Promise<{ status: 'timeout' }>((resolve) => {
                timer = setTimeout(
                    () => resolve({ status: 'timeout' }),
                    options.timeoutMs,
                );
            });

            try {
                const outcome = await Promise.race([answer, deadline]);

                if (outcome.status === 'timeout') {
                    // `dispose` e quem encerra o worker - e libera o pedido que
                    // ficou preso dentro dele, que ninguem vai querer.
                    client.dispose();
                    client = connect();
                }

                return outcome;
            } finally {
                clearTimeout(timer);
            }
        },
        thread: () => client.thread(),
        dispose() {
            client.dispose();
        },
    };
}
