import { describe, expect, it, vi } from 'vitest';
import type { WorkerRequest } from '@/lib/worker/client';
import type {
    ExtractionOutcome,
    ExtractionRequest,
} from '@/tools/palette-generator/lib/extract';
import { extractPalette } from '@/tools/palette-generator/lib/extract';
import { createExtractionClient } from '@/tools/palette-generator/lib/extractionClient';

/**
 * Cliente do worker da extracao (ADR-122).
 *
 * O worker de verdade nao existe no jsdom: ele e fingido, como no teste do
 * cliente generico. Aqui o contrato proprio do cliente importa - o buffer vai
 * na lista de transferencia, o pedido velho e descartado, e `cancel` derruba o
 * worker no meio da conta.
 */

class TestImageData {
    readonly colorSpace = 'srgb';

    constructor(
        readonly data: Uint8ClampedArray<ArrayBuffer>,
        readonly width: number,
        readonly height: number,
    ) {}
}

vi.stubGlobal('ImageData', TestImageData);

const OUTCOME: ExtractionOutcome = {
    ok: true,
    result: {
        colors: [
            {
                rgba: { r: 255, g: 0, b: 0, a: 1 },
                hex: '#ff0000',
                weight: 4,
                percentage: 100,
            },
        ],
        requested: 5,
        algorithmVersion: 1,
        sufficient: false,
        visiblePixels: 4,
    },
};

function request(count: ExtractionRequest['count'] = 5): ExtractionRequest {
    return {
        data: new Uint8ClampedArray([
            255, 0, 0, 255, 255, 0, 0, 255, 255, 0, 0, 255, 255, 0, 0, 255,
        ]),
        width: 2,
        height: 2,
        count,
    };
}

class FakeWorker {
    readonly posted: WorkerRequest<ExtractionRequest>[] = [];

    readonly transfers: Transferable[][] = [];

    terminated = false;

    private readonly listeners = new Map<string, ((event: never) => void)[]>();

    addEventListener(type: string, handler: (event: never) => void): void {
        const handlers = this.listeners.get(type) ?? [];

        handlers.push(handler);
        this.listeners.set(type, handlers);
    }

    postMessage(request: WorkerRequest<ExtractionRequest>, transfer?: Transferable[]): void {
        this.posted.push(request);
        this.transfers.push(transfer ?? []);
    }

    terminate(): void {
        this.terminated = true;
    }

    answer(index: number, outcome: ExtractionOutcome = OUTCOME): void {
        const posted = this.posted[index];

        if (posted === undefined) {
            throw new Error(`pedido ${index} nao foi enviado`);
        }

        this.emit('message', { data: { id: posted.id, result: outcome } });
    }

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
    client: ReturnType<typeof createExtractionClient>;
    workers: FakeWorker[];
    spawn: ReturnType<typeof vi.fn>;
} {
    const workers: FakeWorker[] = [];
    const spawn = vi.fn(() => {
        const worker = new FakeWorker();

        workers.push(worker);

        return worker as unknown as Worker;
    });

    return { client: createExtractionClient(spawn), workers, spawn };
}

describe('cliente com worker', () => {
    it('manda o RGBA transferido e devolve o resultado', async () => {
        const { client, workers } = clientWithWorker();
        const payload = request();
        const promise = client.run(payload);

        expect(client.thread()).toBe('worker');
        expect(workers[0]?.transfers[0]).toEqual([payload.data.buffer]);
        expect(workers[0]?.posted[0]?.payload).toBe(payload);

        workers[0]?.answer(0);

        expect(await promise).toEqual(OUTCOME);
    });

    it('descarta o pedido que ficou para tras', async () => {
        const { client, workers } = clientWithWorker();
        const primeiro = client.run(request());
        const segundo = client.run(request(7));

        expect(await primeiro).toBeNull();

        workers[0]?.answer(1);

        expect(await segundo).toEqual(OUTCOME);
    });

    it('ignora a resposta de um pedido mais velho', async () => {
        const { client, workers } = clientWithWorker();
        const primeiro = client.run(request());
        const segundo = client.run(request(9));

        workers[0]?.answer(0);
        workers[0]?.answer(1);

        expect(await primeiro).toBeNull();
        expect(await segundo).toEqual(OUTCOME);
    });

    it('cancel derruba o worker e o proximo pedido cria outro', async () => {
        const { client, workers, spawn } = clientWithWorker();
        const pendente = client.run(request());

        client.cancel();

        expect(await pendente).toBeNull();
        expect(workers[0]?.terminated).toBe(true);

        const proximo = client.run(request());

        expect(spawn).toHaveBeenCalledTimes(2);
        expect(client.thread()).toBe('worker');
        expect(workers[1]?.posted).toHaveLength(1);

        workers[1]?.answer(0);

        expect(await proximo).toEqual(OUTCOME);
    });

    it('a queda do worker leva a proxima extracao para a thread principal', async () => {
        const { client, workers } = clientWithWorker();
        const pendente = client.run(request());

        workers[0]?.fail();

        // O buffer do pedido morreu junto com o worker: a fila se perde.
        expect(await pendente).toBeNull();
        expect(client.thread()).toBe('main');
        expect(workers[0]?.terminated).toBe(true);

        const fallback = await client.run(request());

        expect(fallback).toEqual(extractPalette(request()));
    });

    it('dispose encerra, libera a fila e para de responder', async () => {
        const { client, workers } = clientWithWorker();
        const pendente = client.run(request());

        client.dispose();

        expect(await pendente).toBeNull();
        expect(workers[0]?.terminated).toBe(true);
        expect(await client.run(request())).toBeNull();
        expect(workers[0]?.posted).toHaveLength(1);
    });
});

describe('cliente sem worker', () => {
    it('roda a mesma extracao na thread principal', async () => {
        const client = createExtractionClient(() => null);

        expect(client.thread()).toBe('main');
        expect(await client.run(request())).toEqual(extractPalette(request()));
    });

    it('nao roda depois de liberado', async () => {
        const client = createExtractionClient(() => null);

        client.dispose();

        expect(await client.run(request())).toBeNull();
    });
});
