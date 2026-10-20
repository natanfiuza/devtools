import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    pollDelayMs,
    pollingTimedOut,
    QueryPoller,
} from '@/tools/ai-sql-builder/polling';

/**
 * O ritmo da fila.
 *
 * A conta do atraso e uma funcao pura; o relogio e o cancelamento ficam no
 * `QueryPoller`. Os dois sao testados sem rede: o pedido e um duble e o tempo
 * e o do `vi.useFakeTimers`, para a agenda nao depender da velocidade da
 * maquina.
 */

const RITMO = { initialMs: 1500, maxMs: 5000, timeoutMs: 120_000 };

describe('pollDelayMs', () => {
    it('comeca no atraso inicial e dobra ate o teto', () => {
        expect(pollDelayMs(0, RITMO)).toBe(1500);
        expect(pollDelayMs(1, RITMO)).toBe(3000);
        expect(pollDelayMs(2, RITMO)).toBe(5000);
        expect(pollDelayMs(9, RITMO)).toBe(5000);
    });

    it('so a primeira espera aceita a sugestao do servidor', () => {
        expect(pollDelayMs(0, RITMO, 700)).toBe(700);
        expect(pollDelayMs(1, RITMO, 700)).toBe(3000);
    });

    it('mantem o atraso dentro da faixa, inclusive com numeros estranhos', () => {
        expect(pollDelayMs(0, RITMO, 99_999)).toBe(5000);
        expect(pollDelayMs(0, RITMO, -10)).toBe(0);
        expect(pollDelayMs(0, { initialMs: 0, maxMs: 0, timeoutMs: 0 })).toBe(
            0,
        );
    });
});

describe('pollingTimedOut', () => {
    it('desiste exatamente no limite, e nao antes', () => {
        expect(pollingTimedOut(0, 119_999, RITMO.timeoutMs)).toBe(false);
        expect(pollingTimedOut(0, 120_000, RITMO.timeoutMs)).toBe(true);
        expect(pollingTimedOut(0, 0, -1)).toBe(true);
    });
});

describe('QueryPoller', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('consulta no ritmo e para quando a resposta e terminal', async () => {
        vi.useFakeTimers();

        const request = vi.fn().mockResolvedValue({ status: 'processing' });
        const onUpdate = vi
            .fn()
            .mockReturnValueOnce(false)
            .mockReturnValueOnce(true);
        const onTimeout = vi.fn();
        const onError = vi.fn();

        const poller = new QueryPoller(
            RITMO,
            { request, onUpdate, onTimeout, onError },
            () => Date.now(),
        );

        poller.start(700);

        await vi.advanceTimersByTimeAsync(699);
        expect(request).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(request).toHaveBeenCalledTimes(1);

        // A segunda espera ja e a do cliente (dobro do inicial).
        await vi.advanceTimersByTimeAsync(3000);
        expect(request).toHaveBeenCalledTimes(2);

        // `onUpdate` devolveu `true`: nao ha uma terceira consulta.
        await vi.advanceTimersByTimeAsync(10_000);
        expect(request).toHaveBeenCalledTimes(2);
        expect(onTimeout).not.toHaveBeenCalled();
        expect(onError).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('desiste quando o tempo total passa do teto, sem consultar', async () => {
        vi.useFakeTimers();

        const request = vi.fn().mockResolvedValue({ status: 'processing' });
        const onTimeout = vi.fn();
        const poller = new QueryPoller(
            { initialMs: 1500, maxMs: 1500, timeoutMs: 1000 },
            {
                request,
                onUpdate: () => false,
                onTimeout,
                onError: vi.fn(),
            },
            () => Date.now(),
        );

        poller.start();

        await vi.advanceTimersByTimeAsync(1500);

        expect(onTimeout).toHaveBeenCalledTimes(1);
        expect(request).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('para de vez no cancelamento', async () => {
        vi.useFakeTimers();

        const request = vi.fn().mockResolvedValue({ status: 'queued' });
        const poller = new QueryPoller(
            RITMO,
            {
                request,
                onUpdate: () => false,
                onTimeout: vi.fn(),
                onError: vi.fn(),
            },
            () => Date.now(),
        );

        poller.start();
        poller.cancel();

        await vi.advanceTimersByTimeAsync(10_000);

        expect(request).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('entrega a falha do pedido uma vez e nao agenda mais nada', async () => {
        vi.useFakeTimers();

        const error = new Error('offline');
        const onError = vi.fn();
        const request = vi.fn().mockRejectedValue(error);
        const poller = new QueryPoller(
            RITMO,
            {
                request,
                onUpdate: () => false,
                onTimeout: vi.fn(),
                onError,
            },
            () => Date.now(),
        );

        poller.start();

        await vi.advanceTimersByTimeAsync(1500);

        expect(onError).toHaveBeenCalledWith(error);
        expect(onError).toHaveBeenCalledTimes(1);
        expect(vi.getTimerCount()).toBe(0);
    });
});
