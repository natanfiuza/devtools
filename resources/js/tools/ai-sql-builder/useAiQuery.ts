import {
    computed,
    onBeforeUnmount,
    ref,
    type ComputedRef,
    type Ref,
} from 'vue';
import { getQuery, postQuery, toAiApiError } from './api';
import type { AiApiError } from './api';
import { QueryPoller } from './polling';
import type {
    AiQueryEndpoints,
    AiQueryInput,
    AiQueryResponse,
    AiQueryResult,
    PollTiming,
    Quota,
    QueryViewStatus,
    TokenUsage,
} from './types';

/**
 * A maquina de estados da solicitacao: enviar, acompanhar, terminar, desistir.
 *
 * O servidor devolve `queued|processing|done|failed`; a tela acrescenta
 * `idle`, `submitting` e `timeout` - estados que so existem enquanto a aba
 * esta aberta. O polling e cancelado quando o componente sai ou quando uma
 * nova solicitacao comeca, para uma resposta velha nao pintar a tela por cima
 * da nova.
 */

export interface UseAiQueryOptions {
    endpoints: AiQueryEndpoints;
    poll: PollTiming;
    initialQuota: Quota | null;
}

export interface UseAiQuery {
    status: Ref<QueryViewStatus>;
    result: Ref<AiQueryResult | null>;
    tokens: Ref<TokenUsage>;
    cacheHit: Ref<boolean>;
    quota: Ref<Quota | null>;
    /** Falha do transporte ou do envelope; `failed` do servidor vem em `errorCode`. */
    error: Ref<AiApiError | null>;
    errorCode: Ref<string | null>;
    busy: ComputedRef<boolean>;
    submit: (input: AiQueryInput) => Promise<void>;
    /** Retoma o polling de um pedido que estourou o tempo. */
    retry: () => void;
    reset: () => void;
    restore: (restoredResult: AiQueryResult | null) => void;
}

function emptyTokens(): TokenUsage {
    return { in: null, out: null };
}

export function useAiQuery(options: UseAiQueryOptions): UseAiQuery {
    const status = ref<QueryViewStatus>('idle');
    const result = ref<AiQueryResult | null>(null);
    const tokens = ref<TokenUsage>(emptyTokens());
    const cacheHit = ref(false);
    const quota = ref<Quota | null>(options.initialQuota);
    const error = ref<AiApiError | null>(null);
    const errorCode = ref<string | null>(null);

    let queryId: string | null = null;
    let poller: QueryPoller<AiQueryResponse> | null = null;

    const busy = computed<boolean>(
        () =>
            status.value === 'submitting' ||
            status.value === 'queued' ||
            status.value === 'processing',
    );

    function apply(response: AiQueryResponse): void {
        result.value = response.result;
        tokens.value = response.tokens;
        cacheHit.value = response.cache_hit;
        errorCode.value = response.error_code;
        quota.value = response.quota ?? quota.value;
        status.value = response.status;
    }

    function fail(cause: unknown): void {
        poller?.cancel();
        error.value = toAiApiError(cause);
        errorCode.value = null;
        status.value = 'failed';
    }

    function startPolling(serverDelayMs: number | null): void {
        poller?.cancel();

        const id = queryId;

        if (id === null) {
            return;
        }

        poller = new QueryPoller<AiQueryResponse>(options.poll, {
            request: () => getQuery(options.endpoints.show, id),
            onUpdate: (response) => {
                apply(response);

                return (
                    response.status === 'done' || response.status === 'failed'
                );
            },
            onTimeout: () => {
                status.value = 'timeout';
            },
            onError: (cause) => {
                fail(cause);
            },
        });

        poller.start(serverDelayMs);
    }

    async function submit(input: AiQueryInput): Promise<void> {
        poller?.cancel();
        poller = null;

        status.value = 'submitting';
        result.value = null;
        tokens.value = emptyTokens();
        cacheHit.value = false;
        error.value = null;
        errorCode.value = null;
        queryId = null;

        try {
            const response = await postQuery(options.endpoints.store, input);

            queryId = response.id;
            apply(response);

            if (response.status === 'done' || response.status === 'failed') {
                return;
            }

            startPolling(response.poll_after_ms);
        } catch (cause) {
            fail(cause);
        }
    }

    function retry(): void {
        if (queryId === null) {
            return;
        }

        error.value = null;
        errorCode.value = null;
        status.value = 'queued';
        startPolling(null);
    }

    function reset(): void {
        poller?.cancel();
        poller = null;
        queryId = null;
        status.value = 'idle';
        result.value = null;
        tokens.value = emptyTokens();
        cacheHit.value = false;
        error.value = null;
        errorCode.value = null;
        quota.value = options.initialQuota;
    }

    function restore(restoredResult: AiQueryResult | null): void {
        poller?.cancel();
        poller = null;
        queryId = null;
        status.value = restoredResult !== null ? 'done' : 'idle';
        result.value = restoredResult;
        tokens.value = emptyTokens();
        cacheHit.value = false;
        error.value = null;
        errorCode.value = null;
    }

    onBeforeUnmount(() => {
        poller?.cancel();
    });

    return {
        status,
        result,
        tokens,
        cacheHit,
        quota,
        error,
        errorCode,
        busy,
        submit,
        retry,
        reset,
        restore,
    };
}
