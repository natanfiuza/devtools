<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { History as HistoryIcon, Search, ChevronDown, ChevronUp } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import type { AiHistoryItem } from './types';

const props = defineProps<{
    historyEndpoint?: string;
    isGuest?: boolean;
}>();

const emit = defineEmits<{
    select: [item: AiHistoryItem];
}>();

const { t } = useI18n();

const isOpen = ref(true);
const search = ref('');
const items = ref<AiHistoryItem[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

async function fetchHistory(queryText: string = ''): Promise<void> {
    if (!props.historyEndpoint || props.isGuest) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const url = new URL(props.historyEndpoint, window.location.origin);
        if (queryText.trim() !== '') {
            url.searchParams.set('q', queryText.trim());
        }

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP error ${response.status}`);
        }

        const data = (await response.json()) as { data: AiHistoryItem[] };
        items.value = Array.isArray(data.data) ? data.data : [];
    } catch {
        error.value = t('ai.history.error');
    } finally {
        loading.value = false;
    }
}

watch(search, (newVal) => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(() => {
        void fetchHistory(newVal);
    }, 300);
});

onMounted(() => {
    void fetchHistory();
});

function onSelect(item: AiHistoryItem): void {
    emit('select', item);
}

defineExpose({
    reload: () => fetchHistory(search.value),
});
</script>

<template>
  <section
    class="mt-8 rounded-lg border p-4 sm:p-5"
    data-test="ai-query-history"
  >
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <HistoryIcon
          class="size-4 text-muted-foreground"
          aria-hidden="true"
        />
        <h2 class="text-base font-medium">
          {{ t('ai.history.title') }}
        </h2>
        <Badge
          variant="secondary"
          class="ml-1 text-xs"
        >
          {{ items.length }}
        </Badge>
      </div>

      <Button
        type="button"
        variant="ghost"
        size="sm"
        class="size-8 p-0"
        :aria-label="isOpen ? t('ai.history.collapse') : t('ai.history.expand')"
        @click="isOpen = !isOpen"
      >
        <ChevronUp
          v-if="isOpen"
          class="size-4"
        />
        <ChevronDown
          v-else
          class="size-4"
        />
      </Button>
    </div>

    <div
      v-show="isOpen"
      class="mt-4 space-y-4"
    >
      <div class="relative">
        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="search"
          type="search"
          :placeholder="t('ai.history.search_placeholder')"
          class="pl-9"
          data-test="ai-history-search"
        />
      </div>

      <div
        v-if="loading"
        class="flex items-center justify-center py-6 text-subtle"
      >
        <Spinner class="size-5 text-brand" />
        <span class="ml-2 text-sm">{{ t('ai.history.loading') }}</span>
      </div>

      <div
        v-else-if="error !== null"
        class="py-4 text-center text-sm text-destructive"
      >
        {{ error }}
      </div>

      <div
        v-else-if="items.length === 0"
        class="py-6 text-center text-sm text-muted-foreground"
      >
        <span v-if="search.trim() !== ''">
          {{ t('ai.history.no_search_results', { query: search }) }}
        </span>
        <span v-else>
          {{ t('ai.history.empty') }}
        </span>
      </div>

      <div
        v-else
        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
      >
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="flex flex-col justify-between rounded-md border p-3 text-left transition hover:border-brand/60 hover:bg-muted/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
          data-test="ai-history-item"
          @click="onSelect(item)"
        >
          <div>
            <div class="flex items-center gap-1.5">
              <Badge
                variant="outline"
                class="text-[10px] uppercase"
              >
                {{ item.dialect }}
              </Badge>
              <Badge
                variant="secondary"
                class="text-[10px]"
              >
                {{ t(`ai.targets.${item.output_target}`) }}
              </Badge>
            </div>
            <h3 class="mt-2 text-sm font-semibold text-foreground line-clamp-2">
              {{ item.title || t('ai.history.untitled') }}
            </h3>
            <p
              v-if="item.description"
              class="mt-1 text-xs text-muted-foreground line-clamp-2"
            >
              {{ item.description }}
            </p>
          </div>
          <span class="mt-3 text-[11px] text-subtle">
            {{ item.created_at ? new Date(item.created_at).toLocaleDateString() : '' }}
          </span>
        </button>
      </div>
    </div>
  </section>
</template>
