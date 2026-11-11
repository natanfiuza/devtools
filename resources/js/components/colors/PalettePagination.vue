<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { PalettePaginator } from '@/types/colors';

/**
 * Paginacao de Minhas Paletas (F4).
 *
 * A pagina e decidida pelo servidor; aqui so existe o pedido. O componente nao
 * conhece os filtros - quem navega e a pagina, preservando a busca.
 */

defineProps<{
    paginator: PalettePaginator;
}>();

const emit = defineEmits<{
    select: [page: number];
}>();

const { t } = useI18n();
</script>

<template>
  <nav
    v-if="paginator.last_page > 1"
    class="flex flex-wrap items-center justify-between gap-2"
    :aria-label="t('colors.pagination.page', { page: paginator.current_page, last: paginator.last_page })"
    data-test="palette-pagination"
  >
    <p
      class="text-xs text-muted-foreground"
      data-test="palette-page-info"
    >
      {{
        t('colors.list.results', {
          from: paginator.from ?? 0,
          to: paginator.to ?? 0,
          total: paginator.total,
        })
      }}
      · {{ t('colors.pagination.page', { page: paginator.current_page, last: paginator.last_page }) }}
    </p>

    <div class="flex gap-2">
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="paginator.current_page <= 1"
        data-test="palette-page-prev"
        @click="emit('select', paginator.current_page - 1)"
      >
        {{ t('colors.pagination.previous') }}
      </Button>
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="paginator.current_page >= paginator.last_page"
        data-test="palette-page-next"
        @click="emit('select', paginator.current_page + 1)"
      >
        {{ t('colors.pagination.next') }}
      </Button>
    </div>
  </nav>
</template>
