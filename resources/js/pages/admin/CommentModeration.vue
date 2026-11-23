<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type {
    AdminColumn,
    AdminCommentModerationRow,
    Paginator,
} from '@/types';

/**
 * Moderacao de comentarios no painel (F7, ADR-140).
 *
 * A fila mostra o texto em qualquer estado - inclusive excluido -, porque e
 * ele que sustenta a decisao. Cada acao bumpa a `revision` no servidor, entao
 * um job de moderacao em voo nao sobrescreve o que o admin decidiu aqui.
 */
const props = defineProps<{
    comments: Paginator<AdminCommentModerationRow>;
    filters: { status: string; q: string };
}>();

const { t } = useI18n();

const form = ref<{ status: string; q: string }>({
    status: props.filters.status,
    q: props.filters.q,
});

const columns = computed<AdminColumn[]>(() => [
    { key: 'comment', label: t('admin.comments.comment') },
    { key: 'author', label: t('admin.comments.author') },
    { key: 'palette', label: t('admin.comments.palette') },
    { key: 'reports', label: t('admin.comments.reports') },
    { key: 'status', label: t('admin.comments.filter') },
    { key: 'createdAt', label: t('admin.comments.created_at'), mono: true },
    { key: 'actions', label: '' },
]);

function apply(): void {
    router.get(
        admin.comments.index.url(),
        { status: form.value.status, q: form.value.q },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function goToPage(page: number): void {
    router.get(
        admin.comments.index.url(),
        { status: props.filters.status, q: props.filters.q, page },
        { preserveState: true, preserveScroll: true },
    );
}

function approve(row: AdminCommentModerationRow): void {
    router.post(
        admin.comments.approve.url(row.id),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function reject(row: AdminCommentModerationRow): void {
    router.post(
        admin.comments.reject.url(row.id),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

const pendingDelete = ref<AdminCommentModerationRow | null>(null);

function destroy(): void {
    const row = pendingDelete.value;

    if (row === null) {
        return;
    }

    router.delete(admin.comments.destroy.url(row.id), {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
    });
}

function handleDeleteOpen(open: boolean): void {
    if (!open) {
        pendingDelete.value = null;
    }
}

function statusVariant(
    status: string,
): 'secondary' | 'outline' | 'destructive' {
    if (status === 'approved') {
        return 'secondary';
    }

    if (status === 'rejected') {
        return 'destructive';
    }

    return 'outline';
}
</script>

<template>
  <Head :title="t('admin.comments.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.comments.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.comments.description') }}
      </p>
    </header>

    <form
      class="flex flex-wrap items-center gap-2"
      @submit.prevent="apply"
    >
      <Input
        v-model="form.q"
        class="h-9 max-w-xs"
        type="search"
        data-test="comment-moderation-search"
        :placeholder="t('admin.comments.search_placeholder')"
        :aria-label="t('admin.comments.search')"
      />

      <Select v-model="form.status">
        <SelectTrigger
          class="h-9 w-44"
          :aria-label="t('admin.comments.filter')"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="pending">
            {{ t('admin.comments.statuses.pending') }}
          </SelectItem>
          <SelectItem value="flagged">
            {{ t('admin.comments.statuses.flagged') }}
          </SelectItem>
          <SelectItem value="rejected">
            {{ t('admin.comments.statuses.rejected') }}
          </SelectItem>
          <SelectItem value="approved">
            {{ t('admin.comments.statuses.approved') }}
          </SelectItem>
          <SelectItem value="all">
            {{ t('admin.comments.statuses.all') }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Button
        type="submit"
        variant="outline"
        size="sm"
      >
        {{ t('admin.comments.search') }}
      </Button>
    </form>

    <AdminTable
      :columns="columns"
      :rows="props.comments"
      :empty="t('admin.comments.empty')"
      @page="goToPage"
    >
      <template #cell-comment="{ row }">
        <p class="max-w-[60ch] text-sm whitespace-pre-line">
          {{ row.body }}
        </p>
        <p
          v-if="
            row.approvedBody !== null &&
              row.approvedBody !== row.body
          "
          class="mt-1 max-w-[60ch] text-[12px] whitespace-pre-line text-subtle"
        >
          {{ row.approvedBody }}
        </p>
      </template>

      <template #cell-author="{ row }">
        <p>{{ row.authorName ?? '—' }}</p>
        <p
          v-if="row.authorUsername !== null"
          class="text-[12px] text-subtle"
        >
          {{ row.authorUsername }}
        </p>
      </template>

      <template #cell-palette="{ row }">
        {{ row.paletteName ?? '—' }}
      </template>

      <template #cell-reports="{ row }">
        {{ row.pendingReports }}
      </template>

      <template #cell-status="{ row }">
        <Badge :variant="statusVariant(row.status)">
          {{ t(`admin.comments.statuses.${row.status}`) }}
        </Badge>
        <Badge
          v-if="row.deleted"
          variant="outline"
          class="ml-1"
        >
          {{ t('admin.comments.deleted') }}
        </Badge>
      </template>

      <template #cell-createdAt="{ row }">
        {{ row.createdAt }}
      </template>

      <template #cell-actions="{ row }">
        <template v-if="!row.deleted">
          <Button
            v-if="row.status !== 'approved'"
            type="button"
            size="sm"
            variant="outline"
            data-test="comment-moderation-approve"
            @click="approve(row)"
          >
            {{ t('admin.comments.approve') }}
          </Button>
          <Button
            v-if="row.status !== 'rejected'"
            type="button"
            size="sm"
            variant="ghost"
            data-test="comment-moderation-reject"
            @click="reject(row)"
          >
            {{ t('admin.comments.reject') }}
          </Button>
          <Button
            type="button"
            size="sm"
            variant="ghost"
            data-test="comment-moderation-delete"
            @click="pendingDelete = row"
          >
            {{ t('admin.comments.delete') }}
          </Button>
        </template>
      </template>
    </AdminTable>

    <Dialog
      :open="pendingDelete !== null"
      @update:open="handleDeleteOpen"
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {{ t('admin.comments.delete_confirm_title') }}
          </DialogTitle>
          <DialogDescription>
            {{ t('admin.comments.delete_confirm_description') }}
          </DialogDescription>
        </DialogHeader>

        <DialogFooter>
          <Button
            variant="ghost"
            size="sm"
            @click="pendingDelete = null"
          >
            {{ t('admin.common.cancel') }}
          </Button>
          <Button
            variant="destructive"
            size="sm"
            data-test="comment-moderation-delete-confirm"
            @click="destroy"
          >
            {{ t('admin.comments.delete') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
