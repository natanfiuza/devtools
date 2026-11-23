<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { cssColor } from '@/components/colors/lib/style';
import type { FeedbackTokenId, LayoutToken, LayoutTokenId } from '../engine';

/**
 * Previa neutra das familias de feedback: cada cor com o seu `on-*`.
 *
 * Nao imita alerta, formulario ou botao de produto - o objetivo e mostrar o
 * par de tokens real, com as legendas fora dos swatches. As familias de
 * feedback sao fixas e independentes da seed (ADR-147).
 */

const props = defineProps<{
    tokens: Record<LayoutTokenId, LayoutToken>;
}>();

const { t } = useI18n();

const IDS: readonly FeedbackTokenId[] = ['error', 'success', 'warning', 'info'];

const pairs = computed(() =>
    IDS.map((id) => ({
        id,
        base: props.tokens[id],
        on: props.tokens[`on-${id}` as LayoutTokenId],
    })),
);
</script>

<template>
  <section
    class="space-y-2 border-t pt-3"
    :aria-label="t('tools.layout-harmonizer.feedback_preview.title')"
    data-test="feedback-preview"
  >
    <h3 class="text-sm font-medium">
      {{ t('tools.layout-harmonizer.feedback_preview.title') }}
    </h3>
    <p class="text-xs text-muted-foreground">
      {{ t('tools.layout-harmonizer.feedback_preview.description') }}
    </p>

    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4">
      <li
        v-for="pair in pairs"
        :key="pair.id"
        class="space-y-1"
      >
        <div class="flex overflow-hidden rounded-md border">
          <span
            class="h-10 flex-1"
            :style="{ backgroundColor: cssColor(pair.base.rgba) }"
            aria-hidden="true"
          />
          <span
            class="h-10 flex-1"
            :style="{ backgroundColor: cssColor(pair.on.rgba) }"
            aria-hidden="true"
          />
        </div>
        <div
          class="flex justify-between gap-2 font-mono text-[11px] text-muted-foreground"
        >
          <span>{{ pair.id }}</span>
          <span>{{ pair.on.id }}</span>
        </div>
      </li>
    </ul>
  </section>
</template>
