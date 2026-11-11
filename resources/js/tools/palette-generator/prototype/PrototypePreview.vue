<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    /** SVG ja serializado por `renderSvg`. */
    svg: string;
    /** Nome acessivel, ja traduzido. */
    label: string;
}>();

/**
 * Preview inline do prototipo.
 *
 * O `v-html` recebe apenas a saida de `renderSvg`, que escapa todo texto e
 * atributo antes de montar a string; nao ha HTML de terceiros por aqui.
 */
const markup = computed(() => props.svg);
</script>

<template>
  <!-- eslint-disable vue/no-v-html -- SVG gerado e escapado pelo renderer -->
  <div
    class="overflow-hidden rounded-xl border border-border bg-muted [&>svg]:block [&>svg]:h-auto [&>svg]:w-full"
    role="img"
    :aria-label="label"
    data-test="prototype-preview"
    v-html="markup"
  />
  <!-- eslint-enable vue/no-v-html -->
</template>
