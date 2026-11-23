<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type { AdminAuditRow, AdminColumn, Paginator } from '@/types';

const props = defineProps<{
    entries: Paginator<AdminAuditRow>;
    filters: { action: string };
    actionTypes: string[];
    total: number;
}>();

const { t } = useI18n();

const columns = computed<AdminColumn[]>(() => [
    { key: 'at', label: t('admin.audit.at'), mono: true },
    { key: 'action', label: t('admin.audit.action') },
    { key: 'actor', label: t('admin.audit.actor') },
    { key: 'target', label: t('admin.audit.target') },
    { key: 'ip', label: t('admin.audit.ip'), mono: true },
]);

const ALL = 'all';

const action = ref(props.filters.action === '' ? ALL : props.filters.action);

function query(extra: Record<string, string | number> = {}) {
    return { action: action.value === ALL ? '' : action.value, ...extra };
}

function apply(): void {
    router.get(admin.audit.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    action.value = ALL;

    apply();
}

function goToPage(page: number): void {
    router.get(admin.audit.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

/** O valor do enum e o caminho da chave: `user.verified` (AdminActionType). */
function label(value: string): string {
    return t(`admin.actions.${value}`);
}
</script>

<template>
  <Head :title="t('admin.audit.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.audit.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.audit.description') }}
      </p>
    </header>

    <form
      class="flex flex-wrap items-center gap-2"
      @submit.prevent="apply"
    >
      <Select v-model="action">
        <SelectTrigger
          class="h-9 w-64"
          :aria-label="t('admin.audit.filter')"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL">
            {{ t('admin.audit.filter_all') }}
          </SelectItem>
          <SelectItem
            v-for="type in actionTypes"
            :key="type"
            :value="type"
          >
            {{ label(type) }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Button
        type="submit"
        variant="outline"
        size="sm"
      >
        {{ t('admin.common.search') }}
      </Button>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        @click="clear"
      >
        {{ t('admin.common.clear') }}
      </Button>
    </form>

    <AdminTable
      :columns="columns"
      :rows="entries"
      :empty="t('admin.audit.empty')"
      @page="goToPage"
    >
      <template #cell-at="{ row }">
        {{ row.at }}
      </template>
      <template #cell-action="{ row }">
        {{ label(row.action) }}
      </template>
      <template #cell-actor="{ row }">
        {{ row.actor ?? t('admin.audit.system') }}
      </template>
      <template #cell-target="{ row }">
        {{
          row.target ??
            (row.targetId === null
              ? t('admin.user.no_value')
              : `#${row.targetId}`)
        }}
      </template>
      <template #cell-ip="{ row }">
        {{ row.ip }}
      </template>
    </AdminTable>
  </div>
</template>
