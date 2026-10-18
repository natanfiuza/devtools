import { describe, expect, it } from 'vitest';
import {
    createWorkerClient,
    type WorkerClient,
    type WorkerRequest,
    type WorkerResponse,
} from '@/lib/worker/client';

/**
 * O cliente de worker generico - o que o SQL Formatter (e o Regex Tester) usam.
 *
 * O worker de verdade nao existe no jsdom, entao ele e **fingido**: o teste
 * responde os pedidos com a mesma funcao pura que o worker real usaria. O que
 * fica coberto aqui e o que nao da para ver na tela - o descarte de resposta
 * velha, a fila que sobrevive a morte do worker, e o que acontece depois do
 * `dispose`.
 *
 * A conta do teste e de brinquedo (`dobro`) para que o que se leia aqui seja o
 * **protocolo**, e nao a formatacao de SQL.
 */

/** O payload e o resultado sao numeros: o protocolo e o que importa. */
type Payload = number;

const dobro = (payload: Payload): number => payload * 2;

class FakeWorker {
    readonly posted: WorkerRequest<Payload>[] = [];

    terminated = false;

    private readonly listeners = new Map<string, ((event: never) => void)[]>();

    addEventListener(type: string, handler: (event: never) => void): void {
        const handlers = this.listeners.get(type) ?? [];

        handlers.push(handler);
        this.listeners.set(type, handlers);
    }

    postMessage(request: WorkerRequest<Payload>): void {
        this.posted.push(request);
    }

    terminate(): void {
        this.terminated = true;
    }

    /** Responde o pedido `index` como o worker de verdade responderia. */
    answer(index: number): void {
        const request = this.posted[index];

        if (request === undefined) {
            throw new Error(`pedido ${index} nao foi enviado`);
        }

        const response: WorkerResponse<number> = {
            id: request.id,
            result: dobro(request.payload),
        };

        this.emit('message', { data: response });
    }

    /** O worker morre: e o caminho de quem tem CSP bloqueando o modulo. */
    fail(): void {
        this.emit('error', { type: 'error' });
    }

    private emit(type: string, event: unknown): void {
        for (const handler of this.listeners.get(type) ?? []) {
            handler(event as never);
        }
    }
}

function clientWithWorker(): {
    client: WorkerClient<Payload, number>;
    worker: FakeWorker;
} {
    const worker = new FakeWorker();

    return {
        client: createWorkerClient<Payload, number>(
            () => worker as unknown as Worker,
            dobro,
        ),
        worker,
    };
}

describe('cliente sem worker', () => {
    it('roda na thread principal', async () => {
        const client = createWorkerClient<Payload, number>(() => null, dobro);

        expect(client.thread()).toBe('main');
        expect(await client.run(21)).toBe(42);
    });

    it('nao roda depois de liberado', async () => {
        const client = createWorkerClient<Payload, number>(() => null, dobro);

        client.dispose();

        expect(await client.run(21)).toBeNull();
    });
});

describe('cliente com worker', () => {
    it('manda o pedido numerado e devolve o resultado', async () => {
        const { client, worker } = clientWithWorker();
        const promise = client.run(21);

        expect(client.thread()).toBe('worker');
        expect(worker.posted).toHaveLength(1);
        expect(worker.posted[0]?.payload).toBe(21);

        worker.answer(0);

        expect(await promise).toBe(42);
    });

    it('descarta o pedido que ficou para tras', async () => {
        const { client, worker } = clientWithWorker();
        const primeiro = client.run(1);
        const segundo = client.run(2);

        // O primeiro responde na hora: quem digitou ja passou por cima dele.
        expect(await primeiro).toBeNull();

        // E a resposta atrasada dele nao encontra mais ninguem esperando.
        worker.answer(0);
        worker.answer(1);

        expect(await segundo).toBe(4);
    });

    it('ignora a resposta que chega depois de um pedido mais novo', async () => {
        const { client, worker } = clientWithWorker();
        const primeiro = client.run(1);
        const segundo = client.run(2);

        worker.answer(1);

        expect(await segundo).toBe(4);
        expect(await primeiro).toBeNull();
    });

    it('leva a fila para a thread principal quando o worker morre', async () => {
        const { client, worker } = clientWithWorker();
        const pendente = client.run(21);

        worker.fail();

        // A fila nao se perde: sai daqui mesmo, com o mesmo resultado.
        expect(await pendente).toBe(42);
        expect(client.thread()).toBe('main');
    });

    it('nao manda mais nada para o worker morto', async () => {
        const { client, worker } = clientWithWorker();

        void client.run(1);
        worker.fail();

        expect(worker.terminated).toBe(true);
        expect(await client.run(21)).toBe(42);
        expect(worker.posted).toHaveLength(1);
    });

    it('libera a fila, encerra o worker e para de responder no dispose', async () => {
        const { client, worker } = clientWithWorker();
        const pendente = client.run(1);

        client.dispose();

        expect(await pendente).toBeNull();
        expect(worker.terminated).toBe(true);
        // Depois do dispose nao ha mais tela: o pedido novo nem roda.
        expect(await client.run(21)).toBeNull();
        expect(worker.posted).toHaveLength(1);
    });
});
