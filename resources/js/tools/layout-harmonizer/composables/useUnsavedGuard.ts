import { getCurrentInstance, onBeforeUnmount, ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Guarda de alteracoes nao salvas (F4, ADR-169).
 *
 * Duas saidas precisam de aviso: fechar/recarregar a aba (`beforeunload`, com
 * o dialogo do navegador) e navegar dentro do Inertia. A navegacao e segura
 * aqui: o `before` e cancelado, a URL fica esperando e o dialogo da pagina
 * decide. Salvamentos chamam `release()` antes do envio para nao pedir
 * confirmacao do proprio salvamento.
 */

export interface UnsavedGuardOptions {
    /** Ha alteracoes em relacao a baseline. */
    dirty: () => boolean;
    /** Desliga a guarda (somente leitura, arquivado). */
    enabled?: () => boolean;
}

export interface UnsavedGuard {
    /** Ha uma navegacao bloqueada esperando confirmacao. */
    pending: Ref<boolean>;
    /** Deixa ir para a URL bloqueada. */
    confirm(): void;
    /** Fica na pagina. */
    cancel(): void;
    /** Libera a proxima navegacao (salvamento ja confirmado). */
    release(): void;
    dispose(): void;
}

export function useUnsavedGuard(options: UnsavedGuardOptions): UnsavedGuard {
    const pending = ref(false);
    let target: string | null = null;
    let releaseNext = false;

    function shouldGuard(): boolean {
        return (options.enabled?.() ?? true) && options.dirty();
    }

    const stop = router.on('before', (event) => {
        if (releaseNext) {
            releaseNext = false;

            return;
        }

        // Ja existe uma navegacao bloqueada: o dialogo continua o mesmo e a
        // nova tentativa nao a substitui.
        if (target !== null) {
            event.preventDefault();

            return;
        }

        if (!shouldGuard()) {
            return;
        }

        target = event.detail.visit.url.toString();
        pending.value = true;
        event.preventDefault();
    });

    function confirm(): void {
        const url = target;

        if (url === null) {
            return;
        }

        target = null;
        pending.value = false;
        releaseNext = true;
        router.visit(url);
    }

    function cancel(): void {
        target = null;
        pending.value = false;
    }

    function release(): void {
        releaseNext = true;
    }

    const beforeUnload = (event: BeforeUnloadEvent): void => {
        if (!shouldGuard()) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    };

    if (typeof window !== 'undefined') {
        window.addEventListener('beforeunload', beforeUnload);
    }

    function dispose(): void {
        stop();

        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeunload', beforeUnload);
        }

        target = null;
        pending.value = false;
    }

    if (getCurrentInstance() !== null) {
        onBeforeUnmount(dispose);
    }

    return { pending, confirm, cancel, release, dispose };
}
