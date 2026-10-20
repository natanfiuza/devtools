import type { PollTiming } from './types';

/**
 * O ritmo do polling.
 *
 * A tela comeca no atraso que o servidor mandou (1,5s hoje), dobra a cada
 * consulta ate o teto (5s) e desiste depois de 2min. A conta e uma funcao
 * pura para o teste poder cobrir a agenda sem relogio de verdade; quem agenda
 * e a `QueryPoller`.
 */

export interface PollerHooks<T> {
    /** Le o estado atual da solicitacao. */
    request: () => Promise<T>;
    /** Aplica a resposta; `true` encerra o polling. */
    onUpdate: (value: T) => boolean;
    onTimeout: () => void;
    onError: (error: unknown) => void;
}

/** Atraso antes da proxima consulta, em milissegundos. */
export function pollDelayMs(
    attempt: number,
    timing: PollTiming,
    serverDelayMs: number | null = null,
): number {
    const initial = Math.max(0, Math.floor(timing.initialMs));
    const max = Math.max(initial, Math.floor(timing.maxMs));

    // `attempt` e quantas consultas ja foram feitas: 0 e a primeira espera,
    // e so ela aceita a sugestao do servidor.
    if (attempt === 0 && serverDelayMs !== null) {
        return Math.min(max, Math.max(0, Math.floor(serverDelayMs)));
    }

    const grown = initial * 2 ** Math.max(0, Math.floor(attempt));

    return Math.min(max, Math.max(initial, Math.floor(grown)));
}

/** `true` quando a solicitacao passou do tempo maximo de espera. */
export function pollingTimedOut(
    startedAt: number,
    now: number,
    timeoutMs: number,
): boolean {
    return now - startedAt >= Math.max(0, timeoutMs);
}

export class QueryPoller<T> {
    private timer: ReturnType<typeof setTimeout> | null = null;

    private cancelled = false;

    private attempt = 0;

    private startedAt = 0;

    constructor(
        private readonly timing: PollTiming,
        private readonly hooks: PollerHooks<T>,
        private readonly now: () => number = Date.now,
    ) {}

    /**
     * (Re)comeca a espera. `serverDelayMs` e o `poll_after_ms` da resposta que
     * acabou de chegar - so a primeira espera o usa.
     */
    start(serverDelayMs: number | null = null): void {
        this.cancel();
        this.cancelled = false;
        this.attempt = 0;
        this.startedAt = this.now();
        this.schedule(pollDelayMs(0, this.timing, serverDelayMs));
    }

    cancel(): void {
        this.cancelled = true;

        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    private schedule(delayMs: number): void {
        if (this.cancelled) {
            return;
        }

        this.timer = setTimeout(
            () => {
                this.timer = null;
                void this.tick();
            },
            Math.max(0, delayMs),
        );
    }

    private async tick(): Promise<void> {
        if (this.cancelled) {
            return;
        }

        if (
            pollingTimedOut(this.startedAt, this.now(), this.timing.timeoutMs)
        ) {
            this.cancelled = true;
            this.hooks.onTimeout();

            return;
        }

        let value: T;

        try {
            value = await this.hooks.request();
        } catch (error) {
            if (!this.cancelled) {
                this.cancelled = true;
                this.hooks.onError(error);
            }

            return;
        }

        if (this.cancelled) {
            return;
        }

        if (this.hooks.onUpdate(value)) {
            this.cancelled = true;

            return;
        }

        this.attempt += 1;
        this.schedule(pollDelayMs(this.attempt, this.timing));
    }
}
