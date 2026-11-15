<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Flag, Pencil, Reply, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CommentForm from '@/components/colors/CommentForm.vue';
import CommentReportDialog from '@/components/colors/CommentReportDialog.vue';
import { useInitials } from '@/composables/useInitials';
import type { CommentThreadError } from '@/composables/useCommentThread';
import colorsProfiles from '@/routes/colors/profiles';
import type { ColorComment } from '@/types/colors';

/**
 * Um comentario da thread, com as respostas quando e raiz (F7, ADR-139).
 *
 * A profundidade e fixa em dois niveis: resposta nao responde resposta. O texto
 * pode vir de uma revisao aprovada anterior e, nesse caso, `pending` esta
 * ligado junto - o selo "em analise" nunca e trocado por um rotulo de aprovado.
 * Comentario excluido so aparece como tombstone com as respostas mantidas.
 */

const props = defineProps<{
    comment: ColorComment;
    canComment: boolean;
    maxLength: number;
    depth: number;
    busy: boolean;
    error: CommentThreadError | null;
    create: (body: string, parentId: string | null) => Promise<boolean>;
    update: (id: string, body: string) => Promise<boolean>;
    destroy: (id: string) => Promise<boolean>;
    clearError: () => void;
}>();

const { t, locale } = useI18n();
const page = usePage();
const { getInitials } = useInitials();

const replying = ref(false);
const editing = ref(false);
const confirmingDelete = ref(false);
const reporting = ref(false);

const localeSlug = computed(() => page.props.localeSlug ?? 'en');

const profileUrl = computed<string | null>(() => {
    const username = props.comment.author?.username;

    if (username === null || username === undefined) {
        return null;
    }

    return colorsProfiles.show.url({ locale: localeSlug.value, username });
});

const when = computed<string | null>(() => {
    if (props.comment.created_at === null) {
        return null;
    }

    const date = new Date(props.comment.created_at);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
});

const authorName = computed(
    () => props.comment.author?.name ?? t('colors.comments.removed_author'),
);

function startReply(): void {
    props.clearError();
    replying.value = true;
}

function startEdit(): void {
    props.clearError();
    editing.value = true;
}

async function submitReply(body: string): Promise<void> {
    if (await props.create(body, props.comment.id)) {
        replying.value = false;
    }
}

async function submitEdit(body: string): Promise<void> {
    if (await props.update(props.comment.id, body)) {
        editing.value = false;
    }
}

async function confirmDelete(): Promise<void> {
    if (await props.destroy(props.comment.id)) {
        confirmingDelete.value = false;
    }
}
</script>

<template>
  <li
    :id="comment.deleted ? undefined : `comment-${comment.id}`"
    class="flex flex-col gap-2 rounded-xl border border-border bg-card p-3"
    :data-depth="depth"
    data-test="comment-item"
  >
    <div class="flex flex-wrap items-center gap-2">
      <img
        v-if="comment.author?.avatar"
        :src="comment.author.avatar"
        :alt="authorName"
        class="size-7 rounded-full border border-border object-cover"
      >
      <span
        v-else-if="comment.author"
        class="flex size-7 items-center justify-center rounded-full border border-border bg-muted text-[10px] font-semibold text-muted-foreground"
        :aria-label="authorName"
      >
        {{ getInitials(comment.author.name) }}
      </span>

      <Link
        v-if="profileUrl !== null"
        :href="profileUrl"
        class="text-sm font-medium hover:underline"
        data-test="comment-author-link"
      >
        {{ authorName }}
      </Link>
      <span
        v-else
        class="text-sm font-medium"
        data-test="comment-author-name"
      >
        {{ authorName }}
      </span>

      <Badge
        v-if="comment.pending && !comment.deleted"
        variant="outline"
        data-test="comment-pending-badge"
      >
        {{ t('colors.comments.pending_badge') }}
      </Badge>

      <span
        v-if="when !== null"
        class="text-xs text-muted-foreground"
      >
        {{ when }}
      </span>
      <span
        v-if="comment.edited_at !== null && !comment.deleted"
        class="text-xs text-muted-foreground"
        data-test="comment-edited"
      >
        {{ t('colors.comments.edited') }}
      </span>
    </div>

    <p
      v-if="comment.deleted"
      class="text-sm text-muted-foreground italic"
      data-test="comment-tombstone"
    >
      {{ t('colors.comments.deleted_tombstone') }}
    </p>
    <p
      v-else
      class="text-sm whitespace-pre-line"
      data-test="comment-body"
    >
      {{ comment.body }}
    </p>

    <div
      v-if="!comment.deleted"
      class="flex flex-wrap items-center gap-2"
    >
      <Button
        v-if="canComment && depth === 0"
        type="button"
        variant="ghost"
        size="sm"
        data-test="comment-reply-toggle"
        @click="startReply"
      >
        <Reply class="size-3.5" />
        {{ t('colors.comments.reply') }}
      </Button>

      <Button
        v-if="comment.can_edit"
        type="button"
        variant="ghost"
        size="sm"
        data-test="comment-edit-toggle"
        @click="startEdit"
      >
        <Pencil class="size-3.5" />
        {{ t('colors.comments.edit') }}
      </Button>

      <Button
        v-if="comment.can_delete && !confirmingDelete"
        type="button"
        variant="ghost"
        size="sm"
        data-test="comment-delete-toggle"
        @click="confirmingDelete = true"
      >
        <Trash2 class="size-3.5" />
        {{ t('colors.comments.delete') }}
      </Button>

      <Button
        v-if="comment.can_report && !reporting"
        type="button"
        variant="ghost"
        size="sm"
        data-test="comment-report-toggle"
        @click="reporting = true"
      >
        <Flag class="size-3.5" />
        {{ t('colors.reports.open') }}
      </Button>
    </div>

    <CommentReportDialog
      v-if="reporting"
      :comment-id="comment.id"
      @close="reporting = false"
    />

    <div
      v-if="confirmingDelete"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-destructive/40 bg-destructive/5 p-2"
      data-test="comment-delete-confirm"
    >
      <span class="text-xs">{{
        t('colors.comments.delete_confirm')
      }}</span>
      <Button
        type="button"
        variant="destructive"
        size="sm"
        :disabled="busy"
        data-test="comment-delete-confirm-yes"
        @click="confirmDelete"
      >
        {{ t('colors.comments.delete') }}
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        data-test="comment-delete-confirm-no"
        @click="confirmingDelete = false"
      >
        {{ t('colors.comments.cancel') }}
      </Button>
    </div>

    <CommentForm
      v-if="editing"
      :max-length="maxLength"
      :busy="busy"
      :error="error"
      :initial-body="comment.body"
      kind="edit"
      compact
      @submit="submitEdit"
      @cancel="editing = false"
    />

    <CommentForm
      v-if="replying"
      :max-length="maxLength"
      :busy="busy"
      :error="error"
      kind="reply"
      compact
      @submit="submitReply"
      @cancel="replying = false"
    />

    <ul
      v-if="depth === 0 && comment.replies.length > 0"
      class="mt-1 flex flex-col gap-2 border-l border-border pl-3"
      data-test="comment-replies"
    >
      <CommentItem
        v-for="reply in comment.replies"
        :key="reply.id"
        :comment="reply"
        :can-comment="canComment"
        :max-length="maxLength"
        :depth="1"
        :busy="busy"
        :error="error"
        :create="create"
        :update="update"
        :destroy="destroy"
        :clear-error="clearError"
      />
    </ul>

    <p
      v-if="depth === 0 && comment.replies_truncated"
      class="text-xs text-muted-foreground"
      data-test="comment-replies-truncated"
    >
      {{ t('colors.comments.replies_truncated') }}
    </p>
  </li>
</template>
