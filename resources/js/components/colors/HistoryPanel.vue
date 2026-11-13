<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { formatHex, type Rgba } from '@/lib/color';
import SwatchButton from '@/components/colors/SwatchButton.vue';
import ImportLocalHistoryDialog from './ImportLocalHistoryDialog.vue';

/**
 * Historico de cores do picker e do gerador (F5, ADR-127).
 *
 * O anonimo ve o historico local (ate 20); quem esta logado ve o da conta e,
 * com historico local ainda presente, o botao de importa-lo. A importacao e
 * sempre explicita e confirmada - nada sobe em segundo plano (ADR-132).
 */

withDefaults(
    defineProps<{
        colors: Rgba[];
        authenticated: boolean;
        importable?: number;
        importing?: boolean;
        more?: boolean;
        loadingMore?: boolean;
    }>(),
    {
        importable: 0,
        importing: false,
        more: false,
        loadingMore: false,
    },
);

const emit = defineEmits<{
    apply: [color: Rgba];
    clear: [];
    import: [];
    more: [];
}>();

const { t } = useI18n();

const importOpen = ref(false);

function confirmImport(): void {
    importOpen.value = false;
    emit('import');
}
</script>

<template>
  <section class="rounded-xl border bg-card p-4">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold">
        {{ t('tools.shared.library.history.title') }}
      </h2>
      <Button
        v-if="colors.length > 0"
        type="button"
        variant="ghost"
        size="sm"
        @click="emit('clear')"
      >
        {{ t('tools.shared.library.history.clear') }}
      </Button>
    </div>
    <p class="mt-1 text-xs text-muted-foreground">
      {{
        authenticated
          ? t('tools.shared.library.history.description.account')
          : t('tools.shared.library.history.description.local')
      }}
    </p>

    <Button
      v-if="authenticated && importable > 0"
      type="button"
      variant="outline"
      size="sm"
      class="mt-3"
      :disabled="importing"
      data-test="history-import"
      @click="importOpen = true"
    >
      {{
        importing
          ? t('tools.shared.library.import.busy')
          : t('tools.shared.library.import.button', { count: importable })
      }}
    </Button>

    <p
      v-if="colors.length === 0"
      class="mt-3 text-sm text-muted-foreground"
      data-test="history-empty"
    >
      {{ t('tools.shared.library.history.empty') }}
    </p>

    <div
      v-else
      class="mt-3 grid grid-cols-5 gap-1 sm:grid-cols-8"
    >
      <SwatchButton
        v-for="color in colors"
        :key="formatHex(color)"
        :color="color"
        :label="formatHex(color)"
        @select="emit('apply', color)"
      />
    </div>

    <Button
      v-if="more"
      type="button"
      variant="ghost"
      size="sm"
      class="mt-3"
      :disabled="loadingMore"
      data-test="history-more"
      @click="emit('more')"
    >
      {{ t('tools.shared.library.load_more') }}
    </Button>

    <ImportLocalHistoryDialog
      :open="importOpen"
      :count="importable"
      :busy="importing"
      @close="importOpen = false"
      @confirm="confirmImport"
    />
  </section>
</template>
