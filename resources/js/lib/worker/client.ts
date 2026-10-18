/**
 * O cliente de worker: a conta roda fora da thread principal quando ha worker,
 * e aqui quando nao ha.
 *
 * Nasceu no JSON Viewer (F2, `parser.workerHost` + `parseClient`) e virou `lib`
 * quando o SQL Formatter precisou da mesma coisa: formatar 1 MB de SQL leva
 * **nove segundos**, e isso nao pode travar a digitacao. O Regex Tester usa o
 * mesmo cliente.
 *
 * O desenho tem tres partes, e as tres existem para que os dois caminhos deem
 * o mesmo resultado:
 *
 * - `run` e uma funcao **pura**, a mesma nos dois lados - o worker so a chama;
 * - os pedidos levam `id`, e a resposta carrega o `id` que a originou. Quem
 *   respondeu a um pedido velho e **descartado** (devolve `null`): quem digitou
 *   ja passou por cima, e pintar a tela com o texto de antes seria mentir;
 * - a queda e silenciosa e pode acontecer no meio da sessao (CSP, memoria): o
 *   worker morre, o que estava na fila sai daqui mesmo e a tool perde
 *   velocidade, nao a funcao.
 *
 * O payload tem que ser **clonavel por estrutura**: nada de funcao, classe ou
 * `Proxy` atravessa.
 */

/** O que atravessa para o worker. */
export interface WorkerRequest<Payload> {
    id: number;
    payload: Payload;
}

/** O que volta do worker. */
export interface WorkerResponse<Result> {
    id: number;
    result: Result;
}

/** Como o worker e criado; injetavel para o teste poder fingir um. */
export type WorkerSpawner = () => Worker | null;

export type WorkerThread = 'worker' | 'main';

export interface WorkerClient<Payload, Result> {
    /** `null` quer dizer pedido superado: outro texto foi processado depois. */
    run(payload: Payload): Promise<Result | null>;
    /** Onde a conta roda agora - o teste usa, e a tela poderia dizer. */
    thread(): WorkerThread;
    dispose(): void;
}

interface Pending<Payload, Result> {
    payload: Payload;
    resolve: (result: Result | null) => void;
}

/** Sem worker: a conta roda aqui mesmo, e trava a thread enquanto roda. */
function mainThreadClient<Payload, Result>(
    run: (payload: Payload) => Result,
): WorkerClient<Payload, Result> {
    let released = false;

    return {
        run(payload) {
            return released
                ? Promise.resolve(null)
                : Promise.resolve(run(payload));
        },
        thread: () => 'main',
        dispose() {
            released = true;
        },
    };
}

function workerClient<Payload, Result>(
    worker: Worker,
    run: (payload: Payload) => Result,
): WorkerClient<Payload, Result> {
    let nextId = 0;
    let latestId = 0;
    let degraded = false;
    let disposed = false;

    const waiting = new Map<number, Pending<Payload, Result>>();

    /** Resolve o que esta na fila; `producer` decide o que cada um recebe. */
    const drain = (
        producer: (pending: Pending<Payload, Result>) => Result | null,
    ): void => {
        for (const [id, pending] of [...waiting]) {
            waiting.delete(id);
            pending.resolve(latestId === id ? producer(pending) : null);
        }
    };

    worker.addEventListener(
        'message',
        (event: MessageEvent<WorkerResponse<Result>>) => {
            const { id, result } = event.data;
            const pending = waiting.get(id);

            if (pending === undefined) {
                return;
            }

            waiting.delete(id);
            pending.resolve(latestId === id ? result : null);
        },
    );

    worker.addEventListener('error', () => {
        // O worker morreu: o que estava na fila sai daqui mesmo.
        degraded = true;
        worker.terminate();
        drain((pending) => run(pending.payload));
    });

    return {
        run(payload) {
            // Depois do `dispose` nao ha mais tela para pintar: a tool ja saiu.
            if (disposed) {
                return Promise.resolve(null);
            }

            if (degraded) {
                return Promise.resolve(run(payload));
            }

            nextId += 1;
            const id = nextId;
            latestId = id;

            // Pedido anterior ainda em voo: a resposta dele chegaria para
            // pintar a tela com o texto de antes.
            drain(() => null);

            return new Promise((resolve) => {
                const request: WorkerRequest<Payload> = { id, payload };

                waiting.set(id, { payload, resolve });
                worker.postMessage(request);
            });
        },
        thread: () => (degraded ? 'main' : 'worker'),
        dispose() {
            // `disposed` **e** `degraded`: o que estava na fila se perde (nao ha
            // mais quem mostre), e o worker para de ser usado.
            disposed = true;
            degraded = true;
            drain(() => null);
            worker.terminate();
        },
    };
}

export function createWorkerClient<Payload, Result>(
    spawn: WorkerSpawner,
    run: (payload: Payload) => Result,
): WorkerClient<Payload, Result> {
    const worker = spawn();

    return worker === null ? mainThreadClient(run) : workerClient(worker, run);
}
