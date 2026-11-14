import { ref, type Ref } from 'vue';
import { colorsApi } from '@/lib/colors/api';
import type { PaletteLikeState } from '@/types/colors';

/**
 * Curtida de uma paleta publica (F6, ADR-135).
 *
 * O estado muda na tela antes da resposta e volta se a API recusar - a lista
 * de `likes_count` do servidor e a fonte da verdade no fim de cada chamada.
 * O visitante sem conta tambem curte: o cookie anonimo e emitido pela propria
 * resposta, entao nada de identidade mora no cliente.
 */

export type UsePaletteLikeOptions = {
    paletteId: string;
    liked: boolean;
    likesCount: number;
};

export type UsePaletteLike = {
    liked: Ref<boolean>;
    likesCount: Ref<number>;
    pending: Ref<boolean>;
    failed: Ref<boolean>;
    toggle: () => Promise<void>;
};

export function usePaletteLike({
    paletteId,
    liked: initialLiked,
    likesCount: initialCount,
}: UsePaletteLikeOptions): UsePaletteLike {
    const liked = ref(initialLiked);
    const likesCount = ref(initialCount);
    const pending = ref(false);
    const failed = ref(false);

    async function toggle(): Promise<void> {
        if (pending.value) {
            return;
        }

        const next = !liked.value;
        const previous = likesCount.value;

        liked.value = next;
        likesCount.value = Math.max(0, previous + (next ? 1 : -1));
        pending.value = true;
        failed.value = false;

        try {
            const state = await colorsApi<PaletteLikeState>(
                `/palettes/${paletteId}/like`,
                { method: next ? 'POST' : 'DELETE' },
            );

            liked.value = state.liked;
            likesCount.value = state.likes_count;
        } catch {
            liked.value = !next;
            likesCount.value = previous;
            failed.value = true;
        } finally {
            pending.value = false;
        }
    }

    return { liked, likesCount, pending, failed, toggle };
}
