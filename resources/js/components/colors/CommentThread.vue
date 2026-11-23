<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import CommentForm from '@/components/colors/CommentForm.vue';
import CommentItem from '@/components/colors/CommentItem.vue';
import { Button } from '@/components/ui/button';
import { useCommentThread } from '@/composables/useCommentThread';
import type { ColorCommentPaginator } from '@/types/colors';

/**
 * Thread de comentarios da pagina publica (F7, ADR-139/ADR-143).
 *
 * So e montada quando a prop `comments` existe - com a flag desligada a pagina
 * nem renderiza a secao. Visitante le a thread; so a conta logada ganha o
 * formulario. A paginacao recarrega a pagina preservando a posicao, e a lista
 * local acompanha os props quando a pagina muda.
 */

const props = defineProps<{
    paletteId: string;
    comments: ColorCommentPaginator;
    canComment: boolean;
    maxLength: number;
}>();

const { t } = useI18n();
const page = usePage();

// `threadItems` evita sombrear a prop `comments` (a paginacao vem dela).
const {
    comments: threadItems,
    busy,
    error,
    create,
    update,
    remove,
    clearError,
} = useCommentThread({
    paletteId: props.paletteId,
    initial: props.comments.data,
});

watch(
    () => props.comments,
    (value) => {
        threadItems.value = value.data;
    },
);

const hasPages = computed(() => props.comments.last_page > 1);

function goToPage(target: number): void {
    if (target < 1 || target > props.comments.last_page) {
        return;
    }

    const url = new URL(page.url, 'http://localhost');
    url.searchParams.set('comments_page', String(target));

    router.get(
        `${url.pathname}${url.search}`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
  <section
    class="flex flex-col gap-3"
    data-test="comment-thread"
  >
    <h2 class="text-lg font-semibold">
      {{ t('colors.comments.title') }}
    </h2>

    <CommentForm
      v-if="canComment"
      :max-length="maxLength"
      :busy="busy"
      :error="error"
      @submit="(body) => create(body, null)"
    />

    <p
      v-if="threadItems.length === 0"
      class="text-sm text-muted-foreground"
      data-test="comment-thread-empty"
    >
      {{ t('colors.comments.empty') }}
    </p>

    <ul
      v-else
      class="flex flex-col gap-3"
      data-test="comment-thread-list"
    >
      <CommentItem
        v-for="comment in threadItems"
        :key="comment.id"
        :comment="comment"
        :can-comment="canComment"
        :max-length="maxLength"
        :depth="0"
        :busy="busy"
        :error="error"
        :create="create"
        :update="update"
        :destroy="remove"
        :clear-error="clearError"
      />
    </ul>

    <div
      v-if="hasPages"
      class="flex flex-wrap items-center justify-center gap-2"
      data-test="comment-pagination"
    >
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="comments.current_page <= 1"
        data-test="comment-page-prev"
        @click="goToPage(comments.current_page - 1)"
      >
        {{ t('colors.comments.page_prev') }}
      </Button>

      <span class="text-xs text-muted-foreground">
        {{
          t('colors.comments.page_status', {
            page: comments.current_page,
            pages: comments.last_page,
          })
        }}
      </span>

      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="comments.current_page >= comments.last_page"
        data-test="comment-page-next"
        @click="goToPage(comments.current_page + 1)"
      >
        {{ t('colors.comments.page_next') }}
      </Button>
    </div>
  </section>
</template>
