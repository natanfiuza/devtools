<script setup lang="ts">
/**
 * Cabecalho e card das telas de acesso: titulo e descricao centrados e, abaixo,
 * um card de borda 1px. O DESIGN.md nao usa sombra, entao o relevo do card e so
 * a borda - e o hover nao ganha destaque porque nao e clicavel.
 */
defineProps<{
    /** Titulo da tela. Vem traduzido: `t('auth.login.title')`. */
    title: string;
    /** Descricao opcional, tambem ja traduzida. */
    description?: string;
}>();
</script>

<template>
  <div class="space-y-2 text-center">
    <h1 class="text-xl font-medium tracking-tight">
      {{ title }}
    </h1>
    <p
      v-if="description"
      class="text-sm text-muted-foreground"
    >
      {{ description }}
    </p>
  </div>

  <div class="mt-6 rounded-xl border bg-card p-6">
    <slot />
  </div>
</template>
