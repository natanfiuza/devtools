import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { useStorage } from '@vueuse/core';

/**
 * Divisao dos dois paineis da pagina de tool (DESIGN: paineis
 * redimensionaveis).
 *
 * A conta fica aqui, em funcao pura, e nao no componente: assim da para testar
 * arrasto, teclado e limites sem medir elemento nenhum (jsdom nao tem layout).
 * O percentual e a **largura do painel de entrada**; os limites existem para
 * que nenhum dos dois desapareca por um arrasto descuidado.
 */
export const SPLIT_DEFAULT = 50;

export const SPLIT_MIN = 20;

export const SPLIT_MAX = 80;

/** Passo das setas do teclado, em pontos percentuais. */
export const SPLIT_STEP = 2;

/** Traz um percentual qualquer para dentro dos limites. */
export function clampPercent(value: number): number {
    if (!Number.isFinite(value)) {
        return SPLIT_DEFAULT;
    }

    return Math.min(SPLIT_MAX, Math.max(SPLIT_MIN, value));
}

/** Percentual correspondente a um ponteiro, dado o retangulo do container. */
export function percentFromPointer(
    clientX: number,
    left: number,
    width: number,
): number {
    if (!(width > 0)) {
        return SPLIT_DEFAULT;
    }

    return clampPercent(((clientX - left) / width) * 100);
}

/** Um passo de teclado a partir do percentual atual. */
export function stepPercent(current: number, delta: number): number {
    return clampPercent(current + delta);
}

/** Passos do teclado sobre o divisor, por tecla. */
const KEY_STEPS: Record<string, number> = {
    ArrowLeft: -SPLIT_STEP,
    ArrowRight: SPLIT_STEP,
};

export interface SplitPane {
    /** Largura do painel de entrada, em porcento. */
    percent: ComputedRef<number>;
    dragging: Ref<boolean>;
    onPointerDown: (event: PointerEvent, container: HTMLElement | null) => void;
    onKeydown: (event: KeyboardEvent) => void;
    reset: () => void;
}

/**
 * @param key identificador da tool - a divisao escolhida volta na proxima
 * visita, e cada ferramenta tem a sua (`tool-split:<slug>`).
 */
export function useSplitPane(key: string): SplitPane {
    const stored = useStorage(`tool-split:${key}`, SPLIT_DEFAULT);
    const dragging = ref(false);

    // O container do arrasto muda a cada gesto; os ouvintes da janela sao
    // fixos e leem daqui.
    let container: HTMLElement | null = null;

    function move(event: PointerEvent): void {
        if (container === null) {
            return;
        }

        const rect = container.getBoundingClientRect();

        stored.value = percentFromPointer(event.clientX, rect.left, rect.width);
    }

    function stop(): void {
        dragging.value = false;
        container = null;

        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', stop);
    }

    function onPointerDown(
        event: PointerEvent,
        element: HTMLElement | null,
    ): void {
        if (element === null) {
            return;
        }

        event.preventDefault();

        const target = event.currentTarget;

        if (target instanceof HTMLElement) {
            target.setPointerCapture(event.pointerId);
        }

        container = element;
        dragging.value = true;

        // Os ouvintes ficam na janela (e nao no divisor): o ponteiro sai do
        // elemento de poucos pixels no primeiro movimento.
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', stop);
    }

    function onKeydown(event: KeyboardEvent): void {
        const step = KEY_STEPS[event.key];

        if (step !== undefined) {
            event.preventDefault();
            stored.value = stepPercent(stored.value, step);

            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            stored.value = SPLIT_MIN;
        }

        if (event.key === 'End') {
            event.preventDefault();
            stored.value = SPLIT_MAX;
        }
    }

    function reset(): void {
        stored.value = SPLIT_DEFAULT;
    }

    return {
        percent: computed(() => clampPercent(stored.value)),
        dragging,
        onPointerDown,
        onKeydown,
        reset,
    };
}
