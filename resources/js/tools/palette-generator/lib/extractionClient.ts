import type {
    WorkerRequest,
    WorkerResponse,
    WorkerSpawner,
    WorkerThread,
} from '@/lib/worker/client';
import type { ExtractionOutcome, ExtractionRequest } from './extractContract';

/**
 * Cliente do worker de extracao (ADR-122).
 *
 * Parecido com o cliente generico de `@/lib/worker/client`, com duas
 * diferencas que vem do trabalho ser **pesado e cancelavel**:
 *
 * - o RGBA vai **transferido** (`ArrayBuffer` na lista de transferencia), sem
 *   copia. Por isso o buffer do pedido e consumido: depois de `run` ninguem
 *   pode reusa-lo;
 * - `cancel()` derruba o worker no meio da conta e o proximo `run` cria outro.
 *   O cliente generico nao faz isso, e tambem nao pode: ele reexecuta o
 *   payload na thread principal quando o worker morre, o que aqui toparia num
 *   buffer ja desanexado. Aqui a falha do worker resolve a fila com `null` e a
 *   proxima extracao (com pixels novos) segue na thread principal.
 *
 * Resposta de pedido superado vira `null`, como no cliente generico: quem ja
 * trocou de imagem nao pode ver a paleta da anterior.
 */

interface Pending {
    resolve: (outcome: ExtractionOutcome | null) => void;
}

export interface ExtractionClient {
    /** `null` quer dizer pedido superado, cancelado ou descartado. */
    run(request: ExtractionRequest): Promise<ExtractionOutcome | null>;
    thread(): WorkerThread;
    /** Interrompe a conta atual; a proxima extracao cria outro worker. */
    cancel(): void;
    dispose(): void;
}

function spawnOrDegrade(spawn: WorkerSpawner): Worker | null {
    try {
        return spawn();
    } catch {
        return null;
    }
}

export function createExtractionClient(spawn: WorkerSpawner): ExtractionClient {
    let worker: Worker | null = spawnOrDegrade(spawn);
    let degraded = worker === null;
    let disposed = false;
    let nextId = 0;
    let latestId = 0;

    const waiting = new Map<number, Pending>();

    const drain = (): void => {
        for (const [id, pending] of [...waiting]) {
            waiting.delete(id);
            pending.resolve(null);
        }
    };

    const stop = (): void => {
        worker?.terminate();
        worker = null;
        drain();
    };

    const attach = (target: Worker): void => {
        target.addEventListener(
            'message',
            (event: MessageEvent<WorkerResponse<ExtractionOutcome>>) => {
                const { id, result } = event.data;
                const pending = waiting.get(id);

                if (pending === undefined) {
                    return;
                }

                waiting.delete(id);
                pending.resolve(latestId === id ? result : null);
            },
        );

        target.addEventListener('error', () => {
            // O worker morreu com o buffer do pedido: a fila se perde e a
            // proxima extracao roda na thread principal, com pixels novos.
            degraded = true;
            stop();
        });
    };

    const ensureWorker = (): Worker | null => {
        if (degraded) {
            return null;
        }

        if (worker === null) {
            worker = spawnOrDegrade(spawn);

            if (worker === null) {
                degraded = true;

                return null;
            }

            attach(worker);
        }

        return worker;
    };

    if (worker !== null) {
        attach(worker);
    }

    return {
        run(request) {
            if (disposed) {
                return Promise.resolve(null);
            }

            const active = ensureWorker();

            if (active === null) {
                // Sem worker: a mesma funcao pura, aqui mesmo. O import
                // dinamico mantem a color-thief fora do chunk principal - ela
                // so chega quando nao ha worker (navegador antigo ou teste).
                return import('./extract').then(
                    ({ extractPalette }) => extractPalette(request),
                    () => null,
                );
            }

            nextId += 1;
            const id = nextId;

            latestId = id;
            // Pedido anterior ainda em voo: a resposta dele chegaria para
            // pintar a tela com a imagem de antes.
            drain();

            return new Promise((resolve) => {
                const message: WorkerRequest<ExtractionRequest> = {
                    id,
                    payload: request,
                };

                waiting.set(id, { resolve });
                active.postMessage(message, [request.data.buffer]);
            });
        },
        thread: () => (degraded ? 'main' : 'worker'),
        cancel() {
            stop();
        },
        dispose() {
            disposed = true;
            stop();
        },
    };
}
