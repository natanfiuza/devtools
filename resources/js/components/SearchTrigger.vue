<script setup lang="ts">
import { computed } from 'vue';
import { Search } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { searchShortcutLabel } from '@/lib/platform';

/**
 * Gatilho da palette de busca: vive no header e no hero da home, e os dois
 * abrem a mesma palette (useCommandPalette), montada uma vez pelo layout.
 *
 * O atalho nao passa pelo i18n: ele muda com a plataforma (Mac/Windows),
 * nao com o idioma.
 */
withDefaults(defineProps<{ variant?: 'header' | 'hero' }>(), {
    variant: 'header',
});

const { t } = useI18n();
const { open } = useCommandPalette();

const shortcut = computed<string>(() => searchShortcutLabel());
</script>

<template>
  <button
    type="button"
    data-test="search-trigger"
    aria-keyshortcuts="Meta+K Control+K"
    :class="
      variant === 'hero'
        ? 'h-11 w-full max-w-xl gap-3 rounded-lg px-4 text-base'
        : 'h-9 max-w-64 gap-2 rounded-md px-3 text-sm'
    "
    class="inline-flex items-center border text-muted-foreground transition-colors hover:border-border-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
    @click="open()"
  >
    <Search
      class="size-4 shrink-0"
      aria-hidden="true"
    />
    <span class="truncate">{{ t('app.search.trigger') }}</span>
    <kbd
      class="ml-auto hidden rounded border bg-surface-2 px-1.5 font-mono text-[11px] text-subtle sm:inline"
    >
      {{ shortcut }}
    </kbd>
  </button>
</template>
