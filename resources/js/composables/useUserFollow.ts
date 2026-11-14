import { ref, type Ref } from 'vue';
import { colorsApi } from '@/lib/colors/api';
import type { UserFollowState } from '@/types/colors';

/**
 * Seguir uma conta (F6, ADR-136).
 *
 * Seguir exige conta; o servidor responde 401 para o visitante e 403 para o
 * proprio perfil. A tela aplica o desejo antes e desfaz na falha, usando o
 * `followers_count` devolvido pela API como valor final.
 */

export type UseUserFollowOptions = {
    userId: number;
    following: boolean;
    followersCount: number;
};

export type UseUserFollow = {
    following: Ref<boolean>;
    followersCount: Ref<number>;
    pending: Ref<boolean>;
    failed: Ref<boolean>;
    toggle: () => Promise<void>;
};

export function useUserFollow({
    userId,
    following: initialFollowing,
    followersCount: initialCount,
}: UseUserFollowOptions): UseUserFollow {
    const following = ref(initialFollowing);
    const followersCount = ref(initialCount);
    const pending = ref(false);
    const failed = ref(false);

    async function toggle(): Promise<void> {
        if (pending.value) {
            return;
        }

        const next = !following.value;
        const previous = followersCount.value;

        following.value = next;
        followersCount.value = Math.max(0, previous + (next ? 1 : -1));
        pending.value = true;
        failed.value = false;

        try {
            const state = await colorsApi<UserFollowState>(
                `/users/${userId}/follow`,
                { method: next ? 'POST' : 'DELETE' },
            );

            following.value = state.following;
            followersCount.value = state.followers_count;
        } catch {
            following.value = !next;
            followersCount.value = previous;
            failed.value = true;
        } finally {
            pending.value = false;
        }
    }

    return { following, followersCount, pending, failed, toggle };
}
