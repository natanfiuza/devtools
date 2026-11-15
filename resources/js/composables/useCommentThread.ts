import { ref, type Ref } from 'vue';
import { colorsApi } from '@/lib/colors/api';
import type { ColorComment } from '@/types/colors';

/**
 * Thread de comentarios de uma paleta publica (F7, ADR-139).
 *
 * A lista exibida e a fonte da verdade: a criacao devolve o item ja moldado
 * pelo servidor (revisao pendente, selo "em analise") e a edicao substitui o
 * corpo mantendo as respostas que ja estavam na tela. Nada aqui decide
 * visibilidade - o servidor decide o `body` exibido.
 *
 * O erro chega como codigo, nunca como texto: a traducao fica nos
 * componentes, que conhecem o namespace `colors.comments`.
 */

export type CommentThreadError = 'invalid' | 'rate_limited' | 'generic';

export type UseCommentThreadOptions = {
    paletteId: string;
    initial: ColorComment[];
};

export type UseCommentThread = {
    comments: Ref<ColorComment[]>;
    busy: Ref<boolean>;
    error: Ref<CommentThreadError | null>;
    create: (body: string, parentId: string | null) => Promise<boolean>;
    update: (id: string, body: string) => Promise<boolean>;
    remove: (id: string) => Promise<boolean>;
    clearError: () => void;
};

type CommentResponse = { comment: ColorComment };

type DeleteResponse = {
    deleted: boolean;
    tombstone: boolean;
    comment: ColorComment | null;
};

/** `colorsApi` so carrega o status; o codigo vira a chave de traducao. */
function errorFrom(cause: unknown): CommentThreadError {
    const message = cause instanceof Error ? cause.message : '';

    if (message.endsWith('_422')) {
        return 'invalid';
    }

    if (message.endsWith('_429')) {
        return 'rate_limited';
    }

    return 'generic';
}

export function useCommentThread({
    paletteId,
    initial,
}: UseCommentThreadOptions): UseCommentThread {
    const comments = ref<ColorComment[]>(initial);
    const busy = ref(false);
    const error = ref<CommentThreadError | null>(null);

    function clearError(): void {
        error.value = null;
    }

    /** Troca a linha preservando as respostas que o presenter nao devolve. */
    function replace(id: string, next: ColorComment): void {
        comments.value = comments.value.map((root) => {
            if (root.id === id) {
                return { ...next, replies: root.replies };
            }

            const replies = root.replies.map((reply) =>
                reply.id === id ? { ...next, replies: [] } : reply,
            );

            return { ...root, replies };
        });
    }

    function drop(id: string): void {
        comments.value = comments.value
            .filter((root) => root.id !== id)
            .map((root) => ({
                ...root,
                replies: root.replies.filter((reply) => reply.id !== id),
            }));
    }

    async function create(
        body: string,
        parentId: string | null,
    ): Promise<boolean> {
        if (busy.value) {
            return false;
        }

        busy.value = true;
        error.value = null;

        try {
            const payload =
                parentId === null ? { body } : { body, parent_id: parentId };

            const response = await colorsApi<CommentResponse>(
                `/palettes/${paletteId}/comments`,
                { method: 'POST', body: payload },
            );

            if (parentId === null) {
                comments.value = [response.comment, ...comments.value];

                return true;
            }

            comments.value = comments.value.map((root) =>
                root.id === parentId
                    ? {
                          ...root,
                          replies: [...root.replies, response.comment],
                      }
                    : root,
            );

            return true;
        } catch (cause) {
            error.value = errorFrom(cause);

            return false;
        } finally {
            busy.value = false;
        }
    }

    async function update(id: string, body: string): Promise<boolean> {
        if (busy.value) {
            return false;
        }

        busy.value = true;
        error.value = null;

        try {
            const response = await colorsApi<CommentResponse>(
                `/comments/${id}`,
                { method: 'PATCH', body: { body } },
            );

            replace(id, response.comment);

            return true;
        } catch (cause) {
            error.value = errorFrom(cause);

            return false;
        } finally {
            busy.value = false;
        }
    }

    async function remove(id: string): Promise<boolean> {
        if (busy.value) {
            return false;
        }

        busy.value = true;
        error.value = null;

        try {
            const response = await colorsApi<DeleteResponse>(
                `/comments/${id}`,
                { method: 'DELETE' },
            );

            if (response.tombstone && response.comment !== null) {
                replace(id, response.comment);
            } else {
                drop(id);
            }

            return true;
        } catch (cause) {
            error.value = errorFrom(cause);

            return false;
        } finally {
            busy.value = false;
        }
    }

    return { comments, busy, error, create, update, remove, clearError };
}
