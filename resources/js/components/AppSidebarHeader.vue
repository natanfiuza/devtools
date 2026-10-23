<script setup lang="ts">
import { SidebarTrigger } from '@/components/ui/sidebar';

/**
 * Barra da area autenticada.
 *
 * Mostra a secao aberta (vem de `useActiveSection`), e nao o breadcrumb do
 * starter kit: com a navegacao unificada a secao ja diz onde a pessoa esta, e o
 * titulo da tela fica no conteudo.
 */
defineProps<{
    /** Titulo da secao aberta, ja traduzido. */
    title: string;
}>();
</script>

<template>
  <header
    class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="flex items-center gap-2">
      <SidebarTrigger class="-ml-1" />
      <h1 class="truncate text-sm font-medium">
        {{ title }}
      </h1>
    </div>
  </header>
</template>
