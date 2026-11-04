<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { BadgeMode } from './types';

/**
 * Abas do construtor (F4).
 *
 * O modo estatico continua 100% local (ADR-002); o modo provider troca o
 * formulario por metrica + params e a previa por uma `<img>` do servidor.
 */

defineProps<{ modelValue: BadgeMode }>();

const emit = defineEmits<{ 'update:modelValue': [value: BadgeMode] }>();

const { t } = useI18n();

const MODES = ['static', 'provider'] as const;
</script>

<template>
  <div
    class="inline-flex items-center gap-1 rounded-md border p-1"
    role="tablist"
    data-test="badge-modes"
  >
    <Button
      v-for="mode in MODES"
      :key="mode"
      type="button"
      size="sm"
      role="tab"
      :variant="modelValue === mode ? 'default' : 'ghost'"
      :aria-selected="modelValue === mode"
      :data-test="`badge-mode-${mode}`"
      @click="emit('update:modelValue', mode)"
    >
      {{ t(`tools.badgeforge.modes.${mode}`) }}
    </Button>
  </div>
</template>
