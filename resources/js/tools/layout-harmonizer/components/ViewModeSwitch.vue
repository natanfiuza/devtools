<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { LayoutViewMode } from '../lib/boardView';

/**
 * Claro / Escuro / Comparar. Botoes nativos com `aria-pressed`, nao abas: os
 * tres modos mostram o mesmo conteudo por angulos diferentes, e a escolha
 * nunca toca no tema do site.
 */

const model = defineModel<LayoutViewMode>({ required: true });

const { t } = useI18n();

const MODES: readonly LayoutViewMode[] = ['light', 'dark', 'compare'];
</script>

<template>
  <div
    class="flex flex-wrap gap-1"
    role="group"
    :aria-label="t('tools.layout-harmonizer.a11y.view_mode')"
    data-test="view-mode-switch"
  >
    <Button
      v-for="mode in MODES"
      :key="mode"
      type="button"
      size="sm"
      :variant="model === mode ? 'secondary' : 'outline'"
      :aria-pressed="model === mode"
      :data-test="`view-mode-${mode}`"
      @click="model = mode"
    >
      {{ t(`tools.layout-harmonizer.view.${mode}`) }}
    </Button>
  </div>
</template>
