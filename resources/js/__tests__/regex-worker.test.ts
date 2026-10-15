import { afterEach, describe, expect, it, vi } from 'vitest';
import { search, type RegexRequest, type RegexResult } from '@/tools/regex-tester/lib/run';
import {
    createRunner,
    type Runner,
    type RunnerOutcome,
} from '@/tools/regex-tester/lib/runner';
import { createRunWorker } from '@/tools/regex-tester/lib/run.workerHost';
import type { WorkerRequest, WorkerResponse } from '@/lib/worker/client';

/**
 * O worker do Regex Tester e o relogio que o mata.
 *
 * O worker de verdade nao existe no jsdom, entao ele e **fingido**: o teste
 * responde os pedidos com a mesma funcao pura que o worker real usa, e - no
 * teste que importa - **nao responde**, que e o unico jeito de ver o estouro de
 * tempo acontecer sem esperar dois segundos de verdade.
 *
 * O que fica coberto aqui e o que a tela nao mostra: o worker encerrado no
 * estouro, a ausencia de resultado depois dele, o worker novo que nasce para o
 * proximo pedido, e o padrao invalido atravessando sem derrubar ninguem.
 */

/** O teto dos testes; o valor de verdade vem de `config/tools.php`. */
const LIMITE_MS = 2000;

/** Um pedido com o minimo; cada teste diz so o que e dele. */
function pedido(overrides: Partial<RegexRequest>): RegexRequest {
    return {
        pattern: '',
        flags: 'g',
        text: '',
        replacement: null,
        maxMatches: 100,
        ...overrides,
    };
}

/**
 * O worker fingido, com o relogio nas maos de quem escreve o teste.
 *
 * Ele **nao** responde sozinho: quem responde e o teste, com `answer`. E e isso
 * que permite deixar um pedido pendurado e ver o tempo estourar.
 */
class FakeWorker {
    readonly posted: WorkerRequest<RegexRequest>[] = [];

    terminated = false;

    private readonly listeners = new Map<string, ((event: never) => void)[]>();

    addEventListener(type: string, handler: (event: never) => void): void {
        const handlers = this.listeners.get(type) ?? [];

        handlers.push(handler);
        this.listeners.set(type, handlers);
    }

    postMessage(request: WorkerRequest<RegexRequest>): void {
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

        // O `id` e o do pedido que chegou: e por ele que o cliente casa a
        // resposta, e inventa-lo aqui esconderia um erro do protocolo.
        this.emit('message', {
            data: {
                id: request.id,
                result: search(request.payload),
            } satisfies WorkerResponse<RegexResult>,
        });
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

/** Um runner com um worker fingido na mao, e o worker de volta. */
function comWorker(): { runner: Runner; worker: FakeWorker } {
    const worker = new FakeWorker();

    return {
        runner: createRunner({ timeoutMs: LIMITE_MS, spawn: () => worker as unknown as Worker }),
        worker,
    };
}

/** O resultado de um pedido que **tem** que ter dado certo. */
async function ok(promise: Promise<RunnerOutcome>): Promise<RegexResult> {
    const outcome = await promise;

    if (outcome.status !== 'done') {
        throw new Error(`esperava um resultado, veio ${outcome.status}`);
    }

    return outcome.result;
}

afterEach(() => {
    vi.useRealTimers();
});

describe('sem worker', () => {
    it('busca na thread principal', async () => {
        const runner = createRunner({ timeoutMs: LIMITE_MS, spawn: () => null });

        expect(runner.thread()).toBe('main');

        const outcome = await ok(runner.run(pedido({ pattern: 'a', text: 'banana' })));

        expect(outcome.ok).toBe(true);
    });

    it('nao cria worker onde o ambiente nao tem Worker', () => {
        // No jsdom e no SSR nao existe `Worker`: e justamente o caminho da queda.
        expect(typeof Worker === 'undefined' ? createRunWorker() : null).toBeNull();
    });
});

describe('com worker', () => {
    it('manda o pedido e devolve o resultado', async () => {
        const { runner, worker } = comWorker();
        const promise = runner.run(pedido({ pattern: 'a', text: 'banana' }));

        expect(runner.thread()).toBe('worker');
        expect(worker.posted).toHaveLength(1);

        worker.answer(0);

        const outcome = await ok(promise);

        expect(outcome.ok && outcome.matches).toHaveLength(3);
    });

    it('descarta o pedido que ficou para tras', async () => {
        const { runner, worker } = comWorker();
        const first = runner.run(pedido({ pattern: 'a', text: 'banana' }));
        const second = runner.run(pedido({ pattern: 'a', text: 'uva' }));

        expect(await first).toEqual({ status: 'superseded' });

        worker.answer(1);

        expect((await ok(second)).ok).toBe(true);
    });

    it('devolve o padrao invalido sem derrubar o worker', async () => {
        const { runner, worker } = comWorker();
        const promise = runner.run(pedido({ pattern: 'a(' }));

        worker.answer(0);

        const outcome = await ok(promise);

        expect(outcome.ok).toBe(false);
        expect(worker.terminated).toBe(false);
    });

    it('leva a busca para a thread principal quando o worker morre', async () => {
        const { runner, worker } = comWorker();
        const pending = runner.run(pedido({ pattern: 'a', text: 'banana' }));

        worker.fail();

        expect(runner.thread()).toBe('main');
        expect((await ok(pending)).ok).toBe(true);
    });

    it('nao manda nada depois do dispose', async () => {
        const { runner, worker } = comWorker();

        runner.dispose();

        expect(await runner.run(pedido({ pattern: 'a' }))).toEqual({ status: 'superseded' });
        expect(worker.posted).toHaveLength(0);
        expect(worker.terminated).toBe(true);
    });
});

describe('o tempo limite', () => {
    it('mata o worker e nao devolve resultado nenhum', async () => {
        vi.useFakeTimers();

        const { runner, worker } = comWorker();
        const promise = runner.run(
            pedido({ pattern: '(a+)+$', text: 'aaaaaaaaaaaaaaaaaaaaaaaaab' }),
        );

        // O worker esta preso na conta e nao responde: quem decide e o relogio.
        await vi.advanceTimersByTimeAsync(LIMITE_MS);

        expect(await promise).toEqual({ status: 'timeout' });
        expect(worker.terminated).toBe(true);
    });

    it('nao devolve o resultado que chega depois do estouro', async () => {
        vi.useFakeTimers();

        const { runner, worker } = comWorker();
        const promise = runner.run(pedido({ pattern: 'a', text: 'banana' }));

        await vi.advanceTimersByTimeAsync(LIMITE_MS);

        // A resposta atrasada chega **depois** do estouro: ela nao tem mais
        // ninguem esperando por ela, e nao vira resultado.
        worker.answer(0);
        await vi.advanceTimersByTimeAsync(0);

        expect(await promise).toEqual({ status: 'timeout' });
    });

    it('o pedido seguinte nasce num worker novo', async () => {
        vi.useFakeTimers();

        const workers: FakeWorker[] = [];
        const runner = createRunner({
            timeoutMs: LIMITE_MS,
            spawn: () => {
                const worker = new FakeWorker();

                workers.push(worker);

                return worker as unknown as Worker;
            },
        });

        const first = runner.run(pedido({ pattern: 'a', text: 'banana' }));

        await vi.advanceTimersByTimeAsync(LIMITE_MS);
        expect(await first).toEqual({ status: 'timeout' });
        expect(workers[0]?.terminated).toBe(true);

        // Um worker morto nao volta: o proximo pedido nasce em outro, e senao a
        // tool ficaria sem buscar nada pelo resto da sessao.
        const second = runner.run(pedido({ pattern: 'a', text: 'banana' }));

        expect(runner.thread()).toBe('worker');
        expect(workers).toHaveLength(2);
        expect(workers[0]?.posted).toHaveLength(1);
        expect(workers[1]?.posted).toHaveLength(1);

        workers[1]?.answer(0);

        expect((await ok(second)).ok).toBe(true);
    });

    it('nao acusa estouro de tempo quando a resposta chega antes', async () => {
        vi.useFakeTimers();

        const { runner, worker } = comWorker();
        const promise = runner.run(pedido({ pattern: 'a', text: 'banana' }));

        worker.answer(0);
        await vi.advanceTimersByTimeAsync(LIMITE_MS);

        expect((await ok(promise)).ok).toBe(true);
        expect(worker.terminated).toBe(false);
    });
});
