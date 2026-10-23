<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type { AdminColumn, AdminSubscriberRow, Paginator } from '@/types';

const props = defineProps<{
    subscribers: Paginator<AdminSubscriberRow>;
    filters: { q: string; status: string };
    total: number;
}>();

const { t } = useI18n();

const columns = computed<AdminColumn[]>(() => [
    { key: 'email', label: t('admin.subscribers.email') },
    { key: 'locale', label: t('admin.subscribers.locale') },
    { key: 'source', label: t('admin.subscribers.source') },
    { key: 'status', label: t('admin.subscribers.status') },
    { key: 'createdAt', label: t('admin.subscribers.created'), mono: true },
]);

const ALL = 'all';

const form = reactive({
    q: props.filters.q,
    status: props.filters.status === '' ? ALL : props.filters.status,
});

function query(extra: Record<string, string | number> = {}) {
    return {
        q: form.q,
        status: form.status === ALL ? '' : form.status,
        ...extra,
    };
}

// O nome do modulo e `exportMethod` (nao se chama uma const de `export`), mas
// a chave no objeto gerado e `export`.
const exportUrl = computed<string>(() =>
    admin.subscribers.export.url({ query: query() }),
);

function apply(): void {
    router.get(admin.subscribers.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    form.q = '';
    form.status = ALL;

    apply();
}

function goToPage(page: number): void {
    router.get(admin.subscribers.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

/** Remover e reinscrever nao apagam a linha (ADR-024). */
function resubscribe(row: AdminSubscriberRow): void {
    router.post(
        admin.subscribers.resubscribe.url(row.id),
        {},
        { preserveScroll: true },
    );
}

function unsubscribe(row: AdminSubscriberRow): void {
    router.delete(admin.subscribers.destroy.url(row.id), {
        preserveScroll: true,
    });
}
</script>

<template>
  <Head :title="t('admin.subscribers.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.subscribers.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.subscribers.description') }}
      </p>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.subscribers.help') }}
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
        :placeholder="t('admin.subscribers.search_placeholder')"
        :aria-label="t('admin.common.search')"
      />

      <Select v-model="form.status">
        <SelectTrigger
          class="h-9 w-44"
          :aria-label="t('admin.subscribers.status')"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL">
            {{ t('admin.subscribers.status_all') }}
          </SelectItem>
          <SelectItem value="confirmed">
            {{ t('admin.subscribers.status_confirmed') }}
          </SelectItem>
          <SelectItem value="pending">
            {{ t('admin.subscribers.status_pending') }}
          </SelectItem>
          <SelectItem value="unsubscribed">
            {{ t('admin.subscribers.status_unsubscribed') }}
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

      <Button
        variant="outline"
        size="sm"
        as-child
        class="ml-auto"
      >
        <a :href="exportUrl">{{ t('admin.subscribers.export') }}</a>
      </Button>
    </form>

    <AdminTable
      :columns="columns"
      :rows="subscribers"
      :empty="t('admin.subscribers.empty')"
      @page="goToPage"
    >
      <template #cell-email="{ row }">
        {{ row.email }}
      </template>
      <template #cell-locale="{ row }">
        {{ row.locale }}
      </template>
      <template #cell-source="{ row }">
        {{ row.source }}
      </template>

      <template #cell-status="{ row }">
        <Badge
          :variant="
            row.status === 'unsubscribed' ? 'outline' : 'secondary'
          "
        >
          {{ t(`admin.subscribers.status_${row.status}`) }}
        </Badge>
      </template>

      <template #cell-createdAt="{ row }">
        {{ row.createdAt }}
      </template>

      <template #actions="{ row }">
        <Button
          v-if="row.status === 'unsubscribed'"
          variant="outline"
          size="sm"
          @click="resubscribe(row)"
        >
          {{ t('admin.subscribers.resubscribe') }}
        </Button>
        <Button
          v-else
          variant="outline"
          size="sm"
          @click="unsubscribe(row)"
        >
          {{ t('admin.subscribers.remove') }}
        </Button>
      </template>
    </AdminTable>
  </div>
</template>
