import {
    analyzeDocument,
    DEFAULT_ANALYZE_OPTIONS,
    type AnalyzeOptions,
    type AnalyzeOutcome,
} from './analyze';
import type { AnalyzeRequest, AnalyzeResponse } from './parser.protocol';
import { createParserWorker } from './parser.workerHost';

/**
 * O cliente da analise.
 *
 * Quem chama nao precisa saber onde a conta roda: `analyze` resolve o
 * resultado, venha ele do worker ou da thread principal. A queda acontece em
 * dois momentos - quando nao ha `Worker` no ambiente, e quando o worker morre
 * no meio da sessao (CSP, memoria) - e nos dois o resultado e o mesmo, porque
 * os dois caminhos chamam `analyzeDocument`.
 *
 * `null` quer dizer **pedido superado**: outro texto foi analisado depois, e
 * pintar a tela com este resultado mostraria o documento de antes. Quem chama
 * simplesmente ignora.
 */

export type AnalysisThread = 'worker' | 'main';

export interface AnalyzeClient {
    analyze(text: string, options?: Partial<AnalyzeOptions>): Promise<AnalyzeOutcome | null>;
    /** Onde a analise roda agora - util para a tela dizer, e para o teste. */
    thread(): AnalysisThread;
    dispose(): void;
}

/** Como o worker e criado; injetavel para o teste poder fingir um. */
export type WorkerSpawner = () => Worker | null;

async function analyzeLocally(
    text: string,
    options: AnalyzeOptions,
): Promise<AnalyzeOutcome> {
    return analyzeDocument(text, options);
}

/** Sem worker: a conta roda aqui mesmo, e trava a thread enquanto roda. */
function mainThreadClient(): AnalyzeClient {
    let released = false;

    return {
        analyze(text, options = {}) {
            return released
                ? Promise.resolve(null)
                : analyzeLocally(text, { ...DEFAULT_ANALYZE_OPTIONS, ...options });
        },
        thread: () => 'main',
        dispose() {
            released = true;
        },
    };
}

interface Pending {
    request: AnalyzeRequest;
    resolve: (outcome: AnalyzeOutcome | null) => void;
}

function workerClient(worker: Worker): AnalyzeClient {
    let nextId = 0;
    let latestId = 0;
    let degraded = false;

    const waiting = new Map<number, Pending>();

    /** Resolve o que esta na fila; `producer` decide o que cada um recebe. */
    const drain = (producer: (pending: Pending) => AnalyzeOutcome | null): void => {
        for (const [id, pending] of [...waiting]) {
            waiting.delete(id);
            pending.resolve(latestId === id ? producer(pending) : null);
        }
    };

    worker.addEventListener('message', (event: MessageEvent<AnalyzeResponse>) => {
        const { id, outcome } = event.data;
        const pending = waiting.get(id);

        if (pending === undefined) {
            return;
        }

        waiting.delete(id);
        pending.resolve(latestId === id ? outcome : null);
    });

    worker.addEventListener('error', () => {
        // O worker morreu: o que estava na fila sai daqui mesmo. A tool perde
        // velocidade, nao a funcao.
        degraded = true;
        worker.terminate();
        drain((pending) => analyzeDocument(pending.request.text, pending.request.options));
    });

    return {
        analyze(text, options = {}) {
            const optionsResolved: AnalyzeOptions = { ...DEFAULT_ANALYZE_OPTIONS, ...options };

            if (degraded) {
                return analyzeLocally(text, optionsResolved);
            }

            nextId += 1;
            const id = nextId;
            latestId = id;

            // Pedido anterior ainda em voo: a resposta dele chegaria para
            // pintar a tela com o texto de antes.
            drain(() => null);

            return new Promise((resolve) => {
                const request: AnalyzeRequest = { id, text, options: optionsResolved };

                waiting.set(id, { request, resolve });
                worker.postMessage(request);
            });
        },
        thread: () => (degraded ? 'main' : 'worker'),
        dispose() {
            degraded = true;
            drain(() => null);
            worker.terminate();
        },
    };
}

export function createAnalyzeClient(spawn: WorkerSpawner = createParserWorker): AnalyzeClient {
    const worker = spawn();

    return worker === null ? mainThreadClient() : workerClient(worker);
}
