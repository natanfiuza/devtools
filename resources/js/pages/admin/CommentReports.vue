<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type { AdminColumn, AdminCommentReportRow, Paginator } from '@/types';

/**
 * Denuncias de comentario no painel (F7, ADR-141).
 *
 * O admin le o comentario denunciado - inclusive o ja excluido - e decide
 * entre resolver e dispensar. Encerrar a denuncia nao altera o comentario:
 * rejeitar ou excluir e acao da tela de moderacao.
 */
const props = defineProps<{
    reports: Paginator<AdminCommentReportRow>;
    filters: { status: string };
}>();

const { t } = useI18n();

const columns = computed<AdminColumn[]>(() => [
    { key: 'comment', label: t('admin.comment_reports.comment') },
    { key: 'reason', label: t('admin.comment_reports.reason') },
    { key: 'reporter', label: t('admin.comment_reports.reporter') },
    { key: 'status', label: t('admin.comment_reports.status') },
    {
        key: 'reportedAt',
        label: t('admin.comment_reports.reported_at'),
        mono: true,
    },
    { key: 'actions', label: '' },
]);

const filter = computed<string>({
    get: () => props.filters.status,
    set: (status) => {
        router.get(
            admin.commentReports.index.url(),
            { status },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
});

function commentStatusVariant(
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

function resolve(row: AdminCommentReportRow): void {
    router.post(
        admin.commentReports.resolve.url(row.id),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function dismiss(row: AdminCommentReportRow): void {
    router.post(
        admin.commentReports.dismiss.url(row.id),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function goToPage(page: number): void {
    router.get(
        admin.commentReports.index.url(),
        { status: props.filters.status, page },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
  <Head :title="t('admin.comment_reports.title')" />

  <div class="space-y-4">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-lg font-medium">
          {{ t('admin.comment_reports.title') }}
        </h2>
        <p class="mt-1 text-sm text-subtle">
          {{ t('admin.comment_reports.description') }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <span class="text-xs text-subtle">
          {{ t('admin.comment_reports.filter') }}
        </span>
        <Select v-model="filter">
          <SelectTrigger class="w-44">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="pending">
              {{ t('admin.comment_reports.statuses.pending') }}
            </SelectItem>
            <SelectItem value="resolved">
              {{ t('admin.comment_reports.statuses.resolved') }}
            </SelectItem>
            <SelectItem value="dismissed">
              {{ t('admin.comment_reports.statuses.dismissed') }}
            </SelectItem>
            <SelectItem value="all">
              {{ t('admin.comment_reports.statuses.all') }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
    </header>

    <AdminTable
      :columns="columns"
      :rows="props.reports"
      :empty="t('admin.comment_reports.empty')"
      @page="goToPage"
    >
      <template #cell-comment="{ row }">
        <p class="max-w-[60ch] text-sm whitespace-pre-line">
          {{ row.comment.body }}
        </p>
        <p
          class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-subtle"
        >
          <Badge :variant="commentStatusVariant(row.comment.status)">
            {{
              t(
                `admin.comment_reports.comment_statuses.${row.comment.status}`,
              )
            }}
          </Badge>
          <Badge
            v-if="row.comment.deleted"
            variant="outline"
          >
            {{ t('admin.comment_reports.deleted') }}
          </Badge>
          <span>{{ row.comment.paletteName }}</span>
          <span v-if="row.comment.authorName !== null">
            {{ row.comment.authorName }}
          </span>
        </p>
      </template>

      <template #cell-reason="{ row }">
        {{ t(`colors.reports.reasons.${row.reason}`) }}
      </template>

      <template #cell-reporter="{ row }">
        <p>{{ row.reporterName ?? '—' }}</p>
        <p class="text-[12px] text-subtle">
          {{ row.reporterEmail }}
        </p>
      </template>

      <template #cell-status="{ row }">
        <Badge
          :variant="
            row.status === 'pending' ? 'outline' : 'secondary'
          "
        >
          {{ t(`admin.comment_reports.statuses.${row.status}`) }}
        </Badge>
        <p
          v-if="row.resolvedAt !== null"
          class="mt-1 text-[12px] text-subtle"
        >
          {{ row.resolvedAt }}
          <template v-if="row.resolverName !== null">
            · {{ row.resolverName }}
          </template>
        </p>
      </template>

      <template #cell-reportedAt="{ row }">
        {{ row.reportedAt }}
      </template>

      <template #cell-actions="{ row }">
        <div
          v-if="row.status === 'pending'"
          class="flex flex-wrap gap-2"
        >
          <Button
            type="button"
            size="sm"
            variant="outline"
            data-test="comment-report-resolve"
            @click="resolve(row)"
          >
            {{ t('admin.comment_reports.resolve') }}
          </Button>
          <Button
            type="button"
            size="sm"
            variant="ghost"
            data-test="comment-report-dismiss"
            @click="dismiss(row)"
          >
            {{ t('admin.comment_reports.dismiss') }}
          </Button>
        </div>
      </template>
    </AdminTable>
  </div>
</template>
