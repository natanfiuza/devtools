<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
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
import type { AdminColumn, AdminWebhookEventRow, Paginator } from '@/types';

const props = defineProps<{
    events: Paginator<AdminWebhookEventRow>;
    filters: { q: string; status: string; topic: string };
    topics: string[];
    statuses: string[];
    total: number;
}>();

const { t } = useI18n();

const ALL = 'all';

const columns = computed<AdminColumn[]>(() => [
    { key: 'createdAt', label: t('admin.webhooks.received'), mono: true },
    { key: 'topic', label: t('admin.webhooks.topic') },
    { key: 'eventId', label: t('admin.webhooks.event'), mono: true },
    { key: 'resourceId', label: t('admin.webhooks.resource') },
    { key: 'status', label: t('admin.webhooks.status') },
    {
        key: 'attempts',
        label: t('admin.webhooks.attempts'),
        align: 'right',
        mono: true,
    },
]);

const filters = reactive({
    q: props.filters.q,
    status: props.filters.status === '' ? ALL : props.filters.status,
    topic: props.filters.topic === '' ? ALL : props.filters.topic,
});

function query(extra: Record<string, string | number> = {}) {
    return {
        q: filters.q,
        status: filters.status === ALL ? '' : filters.status,
        topic: filters.topic === ALL ? '' : filters.topic,
        ...extra,
    };
}

function apply(): void {
    router.get(admin.webhooks.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clear(): void {
    filters.q = '';
    filters.status = ALL;
    filters.topic = ALL;

    apply();
}

function goToPage(page: number): void {
    router.get(admin.webhooks.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

function topicLabel(topic: string | null): string {
    if (topic === null || topic === '') {
        return '—';
    }

    return props.topics.includes(topic)
        ? t(`admin.webhooks.topics.${topic}`)
        : topic;
}

function statusBadgeVariant(
    status: AdminWebhookEventRow['status'],
): 'secondary' | 'outline' | 'destructive' {
    if (status === 'processed') {
        return 'secondary';
    }

    return status === 'failed' ? 'destructive' : 'outline';
}

const selected = ref<AdminWebhookEventRow | null>(null);

function openPayload(row: AdminWebhookEventRow): void {
    selected.value = row;
}

function setPayloadOpen(value: boolean): void {
    if (!value) {
        selected.value = null;
    }
}

const payloadJson = computed(() =>
    selected.value === null
        ? ''
        : JSON.stringify(selected.value.payload, null, 2),
);
</script>

<template>
  <Head :title="t('admin.webhooks.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.webhooks.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.webhooks.description') }}
      </p>
      <p class="mt-1 text-xs text-subtle">
        {{ t('admin.webhooks.readonly') }}
      </p>
    </header>

    <form
      class="flex flex-wrap items-center gap-2"
      @submit.prevent="apply"
    >
      <Input
        v-model="filters.q"
        class="h-9 max-w-xs"
        type="search"
        :placeholder="t('admin.webhooks.search_placeholder')"
        :aria-label="t('admin.common.search')"
      />

      <Select v-model="filters.topic">
        <SelectTrigger
          class="h-9 w-56"
          :aria-label="t('admin.webhooks.topic')"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL">
            {{ t('admin.webhooks.topic_all') }}
          </SelectItem>
          <SelectItem
            v-for="topic in topics"
            :key="topic"
            :value="topic"
          >
            {{ t(`admin.webhooks.topics.${topic}`) }}
          </SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="filters.status">
        <SelectTrigger
          class="h-9 w-44"
          :aria-label="t('admin.webhooks.status')"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="ALL">
            {{ t('admin.webhooks.status_all') }}
          </SelectItem>
          <SelectItem
            v-for="status in statuses"
            :key="status"
            :value="status"
          >
            {{ t(`admin.webhooks.statuses.${status}`) }}
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
      :rows="events"
      :empty="t('admin.webhooks.empty')"
      @page="goToPage"
    >
      <template #cell-createdAt="{ row }">
        {{ row.createdAt ?? '—' }}
      </template>

      <template #cell-topic="{ row }">
        {{ topicLabel(row.topic) }}
      </template>

      <template #cell-eventId="{ row }">
        {{ row.eventId }}
      </template>

      <template #cell-resourceId="{ row }">
        {{ row.resourceId ?? '—' }}
      </template>

      <template #cell-status="{ row }">
        <Badge :variant="statusBadgeVariant(row.status)">
          {{ t(`admin.webhooks.statuses.${row.status}`) }}
        </Badge>
      </template>

      <template #cell-attempts="{ row }">
        {{ row.attempts }}
      </template>

      <template #actions="{ row }">
        <Button
          variant="outline"
          size="sm"
          @click="openPayload(row)"
        >
          {{ t('admin.webhooks.payload') }}
        </Button>
      </template>
    </AdminTable>

    <Dialog
      :open="selected !== null"
      @update:open="setPayloadOpen"
    >
      <DialogContent class="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>
            {{
              t('admin.webhooks.payload_title', {
                id: selected?.eventId ?? '',
              })
            }}
          </DialogTitle>
          <DialogDescription>
            {{ t('admin.webhooks.payload_description') }}
          </DialogDescription>
        </DialogHeader>

        <dl
          v-if="selected"
          class="grid gap-3 text-sm sm:grid-cols-2"
        >
          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.webhooks.topic') }}
            </dt>
            <dd>{{ topicLabel(selected.topic) }}</dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.webhooks.action') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selected.action ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.webhooks.resource') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selected.resourceId ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.webhooks.received') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selected.createdAt ?? '—' }}
            </dd>
          </div>

          <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.webhooks.processed') }}
            </dt>
            <dd class="font-mono text-[13px]">
              {{ selected.processedAt ?? '—' }}
            </dd>
          </div>

          <div v-if="selected.error">
            <dt class="text-xs tracking-wide text-subtle uppercase">
              {{ t('admin.webhooks.error') }}
            </dt>
            <dd class="text-destructive">
              {{ selected.error }}
            </dd>
          </div>
        </dl>

        <pre
          class="max-h-96 overflow-auto rounded-md border border-border bg-muted p-3 font-mono text-[12px] whitespace-pre-wrap"
        >{{ payloadJson }}</pre>

        <DialogFooter>
          <Button
            variant="ghost"
            size="sm"
            @click="selected = null"
          >
            {{ t('admin.common.cancel') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
