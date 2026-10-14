import { describe, expect, it } from 'vitest';
import {
    analyzeDocument,
    DEFAULT_ANALYZE_OPTIONS,
    INITIAL_COLLAPSE_DEPTH,
    type AnalyzeOutcome,
    type AnalyzeSuccess,
} from '@/tools/json-viewer/lib/analyze';
import { createAnalyzeClient, type AnalyzeClient } from '@/tools/json-viewer/lib/parseClient';
import type { AnalyzeRequest, AnalyzeResponse } from '@/tools/json-viewer/lib/parser.protocol';
import { createParserWorker } from '@/tools/json-viewer/lib/parser.workerHost';

/**
 * O worker do parser e a queda para a thread principal.
 *
 * O worker de verdade nao existe no jsdom, entao ele e **fingido**: o teste
 * responde os pedidos com a mesma funcao que o worker real usa. O que fica
 * coberto aqui e o que nao da para ver na tela - o descarte de resposta velha,
 * a fila que sobrevive a morte do worker, e o fato de tudo o que atravessa ser
 * clonavel por estrutura.
 */

class FakeWorker {
    readonly posted: AnalyzeRequest[] = [];

    terminated = false;

    private readonly listeners = new Map<string, ((event: never) => void)[]>();

    addEventListener(type: string, handler: (event: never) => void): void {
        const handlers = this.listeners.get(type) ?? [];

        handlers.push(handler);
        this.listeners.set(type, handlers);
    }

    postMessage(request: AnalyzeRequest): void {
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

        const response: AnalyzeResponse = {
            id: request.id,
            outcome: analyzeDocument(request.text, request.options),
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

function clientWithWorker(): { client: AnalyzeClient; worker: FakeWorker } {
    const worker = new FakeWorker();

    return { client: createAnalyzeClient(() => worker as unknown as Worker), worker };
}

function expectOk(outcome: AnalyzeOutcome | null): AnalyzeSuccess {
    if (outcome === null || !outcome.ok) {
        throw new Error(`esperava uma analise valida, veio ${JSON.stringify(outcome)}`);
    }

    return outcome;
}

describe('analyzeDocument', () => {
    it('devolve a arvore, as estatisticas e o colapso inicial', () => {
        const outcome = expectOk(analyzeDocument('{"a":{"b":1},"c":[1,2]}'));

        expect(outcome.stats).toEqual({ bytes: 23, nodes: 6, keys: 3, items: 2 });
        // A raiz nasce aberta; os dois containers do primeiro nivel, fechados.
        expect(outcome.collapsed).toEqual([1, 3]);
        expect(INITIAL_COLLAPSE_DEPTH).toBe(1);
    });

    it('nao reescreve o texto sem pedido', () => {
        expect(expectOk(analyzeDocument('{"a":1}')).output).toBeNull();
    });

    it('reescreve o texto na forma pedida', () => {
        const pretty = expectOk(
            analyzeDocument('{"a":1}', { ...DEFAULT_ANALYZE_OPTIONS, output: 'pretty' }),
        );
        const minify = expectOk(
            analyzeDocument('{\n  "a": 1\n}', { ...DEFAULT_ANALYZE_OPTIONS, output: 'minify' }),
        );

        expect(pretty.output).toBe('{\n  "a": 1\n}');
        expect(minify.output).toBe('{"a":1}');
    });

    it('ordena a arvore e o texto pelo mesmo valor', () => {
        const outcome = expectOk(
            analyzeDocument('{"b":1,"a":2}', {
                ...DEFAULT_ANALYZE_OPTIONS,
                sortKeys: true,
                output: 'pretty',
            }),
        );

        // O caminho copiado da primeira linha tem que valer no texto reescrito.
        expect(outcome.nodes[1]?.key).toBe('a');
        expect(outcome.output).toBe('{\n  "a": 2,\n  "b": 1\n}');
    });

    it('devolve o erro do parse como veio', () => {
        const outcome = analyzeDocument('{', { ...DEFAULT_ANALYZE_OPTIONS, output: 'pretty' });

        expect(outcome.ok).toBe(false);
    });

    it('so devolve dados clonaveis por estrutura', () => {
        const ok = expectOk(analyzeDocument('[1,{"a":null},true]'));
        const failure = analyzeDocument('{');

        // O worker devolve isso por `postMessage`: um Map, uma funcao ou uma
        // referencia circular aqui quebraria no navegador, e nao neste teste.
        expect(structuredClone(ok)).toEqual(ok);
        expect(structuredClone(failure)).toEqual(failure);
    });
});

describe('cliente sem worker', () => {
    it('analisa na thread principal', async () => {
        const client = createAnalyzeClient(() => null);

        expect(client.thread()).toBe('main');
        expect(expectOk(await client.analyze('{"a":1}')).stats.nodes).toBe(2);
    });

    it('nao analisa depois de liberado', async () => {
        const client = createAnalyzeClient(() => null);

        client.dispose();

        expect(await client.analyze('{"a":1}')).toBeNull();
    });

    it('nao cria worker onde o ambiente nao tem Worker', () => {
        // No jsdom e no SSR nao existe `Worker`: e justamente o caminho da queda.
        expect(typeof Worker === 'undefined' ? createParserWorker() : null).toBeNull();
    });
});

describe('cliente com worker', () => {
    it('manda o pedido com as opcoes padrao preenchidas', async () => {
        const { client, worker } = clientWithWorker();
        const promise = client.analyze('{"a":1}');

        expect(client.thread()).toBe('worker');
        expect(worker.posted).toHaveLength(1);
        expect(worker.posted[0]?.options).toEqual(DEFAULT_ANALYZE_OPTIONS);

        worker.answer(0);

        expect(expectOk(await promise).stats.nodes).toBe(2);
    });

    it('deixa a opcao informada sobrescrever a padrao', async () => {
        const { client, worker } = clientWithWorker();
        const promise = client.analyze('{"a":1}', { output: 'minify' });

        expect(worker.posted[0]?.options.output).toBe('minify');

        worker.answer(0);

        expect(expectOk(await promise).output).toBe('{"a":1}');
    });

    it('descarta o pedido que ficou para tras', async () => {
        const { client, worker } = clientWithWorker();
        const first = client.analyze('{"a":1}');
        const second = client.analyze('{"a":2}');

        // O primeiro responde na hora: quem digitou ja passou por cima dele.
        expect(await first).toBeNull();

        // E a resposta atrasada dele nao encontra mais ninguem esperando.
        worker.answer(0);
        worker.answer(1);

        expect(expectOk(await second).stats.bytes).toBe(7);
    });

    it('ignora a resposta que chega depois de um pedido mais novo', async () => {
        const { client, worker } = clientWithWorker();
        const first = client.analyze('{"a":1}');
        const second = client.analyze('{"a":2}');

        worker.answer(1);

        expect(expectOk(await second).stats.bytes).toBe(7);
        expect(await first).toBeNull();
    });

    it('leva a fila para a thread principal quando o worker morre', async () => {
        const { client, worker } = clientWithWorker();
        const pending = client.analyze('{"a":1}');

        worker.fail();

        // A fila nao se perde: sai daqui mesmo, com o mesmo resultado.
        expect(expectOk(await pending).stats.nodes).toBe(2);
        expect(client.thread()).toBe('main');
    });

    it('nao manda mais nada para o worker morto', async () => {
        const { client, worker } = clientWithWorker();

        client.analyze('{"a":1}');
        worker.fail();

        expect(client.thread()).toBe('main');
        expect(worker.terminated).toBe(true);

        expect(expectOk(await client.analyze('{"b":2}')).stats.nodes).toBe(2);
        expect(worker.posted).toHaveLength(1);
    });

    it('libera a fila e encerra o worker no dispose', async () => {
        const { client, worker } = clientWithWorker();
        const pending = client.analyze('{"a":1}');

        client.dispose();

        expect(await pending).toBeNull();
        expect(worker.terminated).toBe(true);
    });
});
