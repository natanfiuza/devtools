<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';

/**
 * O icone do botao de copiar.
 *
 * Depois de uma copia bem-sucedida ele vira um check verde por alguns
 * segundos (`useCopyConfirmation` decide quando), no mesmo lugar e no mesmo
 * tamanho - o botao nao se mexe, so o desenho confirma.
 */
defineProps<{ copied: boolean }>();
</script>

<template>
  <Check
    v-if="copied"
    class="text-emerald-600 dark:text-emerald-400"
    aria-hidden="true"
    data-test="copied-icon"
  />
  <Copy
    v-else
    aria-hidden="true"
    data-test="copy-icon"
  />
</template>
