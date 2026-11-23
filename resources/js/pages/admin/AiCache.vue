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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type { AdminAiCacheRow, AdminColumn, Paginator } from '@/types';

/**
 * Cache de respostas da IA (F7).
 *
 * So metadados: hash truncado, modelo, versao do prompt, tokens, acertos e
 * datas. Prompt, schema e resposta ficam no servidor. A unica escrita e a poda
 * das linhas vencidas, com confirmacao.
 */
const props = defineProps<{
    entries: Paginator<AdminAiCacheRow>;
    filters: { state: string };
    expired: number;
    total: number;
}>();

const { t } = useI18n();

const ALL = 'all';

const columns = computed<AdminColumn[]>(() => [
    { key: 'hash', label: t('admin.ai_cache.hash'), mono: true },
    { key: 'model', label: t('admin.ai_cache.model') },
    {
        key: 'promptVersion',
        label: t('admin.ai_cache.prompt_version'),
        align: 'right',
        mono: true,
    },
    { key: 'tokens', label: t('admin.ai_cache.tokens') },
    {
        key: 'hits',
        label: t('admin.ai_cache.hits'),
        align: 'right',
        mono: true,
    },
    { key: 'createdAt', label: t('admin.ai_cache.created'), mono: true },
    { key: 'expiresAt', label: t('admin.ai_cache.expires'), mono: true },
    { key: 'state', label: t('admin.ai_cache.state') },
]);

const filters = reactive({
    state: props.filters.state === '' ? ALL : props.filters.state,
});

function query(extra: Record<string, string | number> = {}) {
    return {
        state: filters.state === ALL ? '' : filters.state,
        ...extra,
    };
}

function apply(): void {
    router.get(admin.aiCache.index.url(), query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function goToPage(page: number): void {
    router.get(admin.aiCache.index.url(), query({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

const confirmOpen = ref(false);

function prune(): void {
    router.delete(admin.aiCache.destroyExpired.url(), {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
  <Head :title="t('admin.ai_cache.title')" />

  <div class="space-y-4">
    <header>
      <h2 class="text-lg font-medium">
        {{ t('admin.ai_cache.title') }}
      </h2>
      <p class="mt-1 text-sm text-subtle">
        {{ t('admin.ai_cache.description') }}
      </p>
      <p class="mt-1 text-xs text-subtle">
        {{ t('admin.ai_cache.readonly') }}
      </p>
    </header>

    <div class="flex flex-wrap items-center gap-2">
      <form
        class="flex items-center gap-2"
        @submit.prevent="apply"
      >
        <Select v-model="filters.state">
          <SelectTrigger
            class="h-9 w-44"
            :aria-label="t('admin.ai_cache.state')"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="ALL">
              {{ t('admin.ai_cache.state_all') }}
            </SelectItem>
            <SelectItem value="valid">
              {{ t('admin.ai_cache.state_valid') }}
            </SelectItem>
            <SelectItem value="expired">
              {{ t('admin.ai_cache.state_expired') }}
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
      </form>

      <Button
        variant="outline"
        size="sm"
        :disabled="expired === 0"
        class="ml-auto"
        data-test="ai-cache-prune"
        @click="confirmOpen = true"
      >
        {{ t('admin.ai_cache.prune') }} ({{
          t('admin.ai_cache.expired_count', { count: expired })
        }})
      </Button>
    </div>

    <AdminTable
      :columns="columns"
      :rows="entries"
      :empty="t('admin.ai_cache.empty')"
      @page="goToPage"
    >
      <template #cell-hash="{ row }">
        {{ row.hash }}
      </template>

      <template #cell-model="{ row }">
        {{ row.model }}
      </template>

      <template #cell-promptVersion="{ row }">
        {{ row.promptVersion }}
      </template>

      <template #cell-tokens="{ row }">
        {{
          t('admin.ai_cache.tokens_count', {
            in: row.tokensIn,
            out: row.tokensOut,
          })
        }}
      </template>

      <template #cell-hits="{ row }">
        {{ row.hits }}
      </template>

      <template #cell-createdAt="{ row }">
        {{ row.createdAt ?? '—' }}
      </template>

      <template #cell-expiresAt="{ row }">
        {{ row.expiresAt ?? '—' }}
      </template>

      <template #cell-state="{ row }">
        <Badge :variant="row.expired ? 'destructive' : 'secondary'">
          {{
            row.expired
              ? t('admin.ai_cache.expired')
              : t('admin.ai_cache.valid')
          }}
        </Badge>
      </template>
    </AdminTable>

    <Dialog v-model:open="confirmOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {{ t('admin.ai_cache.prune_confirm_title') }}
          </DialogTitle>
          <DialogDescription>
            {{ t('admin.ai_cache.prune_confirm_description') }}
          </DialogDescription>
        </DialogHeader>

        <DialogFooter>
          <Button
            variant="ghost"
            size="sm"
            @click="confirmOpen = false"
          >
            {{ t('admin.common.cancel') }}
          </Button>
          <Button
            variant="destructive"
            size="sm"
            data-test="ai-cache-prune-confirm"
            @click="prune"
          >
            {{ t('admin.ai_cache.prune_confirm_button') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
