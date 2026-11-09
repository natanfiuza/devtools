<script setup lang="ts">
import { computed } from 'vue';
import type { Rgba } from '@/lib/color';
import { checkerboardStyle, cssColor } from './lib/style';

/**
 * Um quadradinho de cor clicavel.
 *
 * O xadrez atras deixa o alpha visivel; o HEX vai no `title` e no texto
 * acessivel, e `caption` mostra o codigo quando ha espaco. Cor nunca e o
 * unico sinal.
 */

const props = defineProps<{
    color: Rgba;
    label: string;
    selected?: boolean;
    caption?: boolean;
}>();

const emit = defineEmits<{
    select: [];
}>();

const colorStyle = computed(() => ({ backgroundColor: cssColor(props.color) }));
</script>

<template>
  <button
    type="button"
    :title="label"
    :aria-label="label"
    :aria-pressed="selected === true ? 'true' : undefined"
    class="flex min-w-0 flex-col items-center gap-1 rounded-md p-1 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
    :class="selected === true ? 'bg-accent ring-1 ring-ring' : ''"
    data-test="color-swatch"
    @click="emit('select')"
  >
    <span
      class="relative block h-8 w-full max-w-10 overflow-hidden rounded-md border shadow-xs"
      :style="checkerboardStyle"
      aria-hidden="true"
    >
      <span
        class="absolute inset-0"
        :style="colorStyle"
      />
    </span>
    <span
      v-if="caption === true"
      class="w-full truncate text-center text-[10px] leading-tight text-muted-foreground tabular-nums"
      aria-hidden="true"
    >
      {{ label }}
    </span>
  </button>
</template>
