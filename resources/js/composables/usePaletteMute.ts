import { ref, type Ref } from 'vue';
import { colorsApi } from '@/lib/colors/api';
import type { PaletteMuteState } from '@/types/notifications';

/**
 * Silenciar as notificacoes de uma paleta (F7, ADR-142).
 *
 * So o dono tem os botoes; o servidor responde 404 para qualquer outra conta.
 * O estado otimista e desfeito na falha, e a tela mostra o erro sem recarregar.
 */

export type UsePaletteMuteOptions = {
    paletteId: string;
    muted: boolean;
};

export type UsePaletteMute = {
    muted: Ref<boolean>;
    pending: Ref<boolean>;
    failed: Ref<boolean>;
    toggle: () => Promise<void>;
};

export function usePaletteMute({
    paletteId,
    muted: initialMuted,
}: UsePaletteMuteOptions): UsePaletteMute {
    const muted = ref(initialMuted);
    const pending = ref(false);
    const failed = ref(false);

    async function toggle(): Promise<void> {
        if (pending.value) {
            return;
        }

        const next = !muted.value;

        muted.value = next;
        pending.value = true;
        failed.value = false;

        try {
            const state = await colorsApi<PaletteMuteState>(
                `/palettes/${paletteId}/mute`,
                { method: next ? 'POST' : 'DELETE' },
            );

            muted.value = state.muted;
        } catch {
            muted.value = !next;
            failed.value = true;
        } finally {
            pending.value = false;
        }
    }

    return { muted, pending, failed, toggle };
}
