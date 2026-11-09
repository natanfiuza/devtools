<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { formatHex, type Rgba } from '@/lib/color';
import SwatchButton from '@/components/colors/SwatchButton.vue';

/**
 * Historico local das cores finalizadas (ate 50).
 *
 * So guarda no navegador, uma vez por cor canonica; nada e enviado ao servidor.
 */

defineProps<{
    colors: Rgba[];
}>();

const emit = defineEmits<{
    apply: [color: Rgba];
    clear: [];
}>();

const { t } = useI18n();
</script>

<template>
  <section class="rounded-xl border bg-card p-4">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold">
        {{ t('tools.html-color-picker.history.title') }}
      </h2>
      <Button
        v-if="colors.length > 0"
        type="button"
        variant="ghost"
        size="sm"
        @click="emit('clear')"
      >
        {{ t('tools.html-color-picker.history.clear') }}
      </Button>
    </div>
    <p class="mt-1 text-xs text-muted-foreground">
      {{ t('tools.html-color-picker.history.description') }}
    </p>

    <p
      v-if="colors.length === 0"
      class="mt-3 text-sm text-muted-foreground"
    >
      {{ t('tools.html-color-picker.history.empty') }}
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
  </section>
</template>
