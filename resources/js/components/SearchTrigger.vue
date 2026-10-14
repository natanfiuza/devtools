<script setup lang="ts">
import { computed } from 'vue';
import { Search } from '@lucide/vue';
import { useI18n } from 'vue-i18n';

/**
 * Campo de busca do header. A palette (Cmd+K) chega na F1, junto com o
 * registro de tools: o botao ja fica no lugar certo, com o atalho visivel,
 * mas ainda nao abre nada (aria-disabled + title explicando).
 *
 * O atalho nao passa pelo i18n de proposito: ele muda com a plataforma
 * (Mac/Windows), nao com o idioma.
 */
const { t } = useI18n();

const shortcut = computed<string>(() => {
    if (typeof navigator === 'undefined') {
        return 'Ctrl K';
    }

    return /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘K' : 'Ctrl K';
});
</script>

<template>
    <button
        type="button"
        aria-disabled="true"
        :title="t('app.actions.search_coming_soon')"
        class="text-muted-foreground hover:border-border-strong focus-visible:outline-ring inline-flex h-9 w-full max-w-64 cursor-default items-center gap-2 rounded-md border px-3 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2"
    >
        <Search class="size-4 shrink-0" aria-hidden="true" />
        <span class="truncate">{{ t('app.actions.search_placeholder') }}</span>
        <kbd
            class="bg-surface-2 text-subtle ml-auto hidden rounded border px-1.5 font-mono text-[11px] sm:inline"
        >
            {{ shortcut }}
        </kbd>
    </button>
</template>
