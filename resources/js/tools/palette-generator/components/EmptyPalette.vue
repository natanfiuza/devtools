<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';

/**
 * Estado vazio: o campo de cor ficou sem texto, entao nao ha cor base e nao
 * ha escala. O botao devolve o exemplo para a pagina nao virar um beco.
 */

const emit = defineEmits<{
    restore: [];
}>();

const { t } = useI18n();
</script>

<template>
  <section
    class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-border p-6"
    data-test="palette-empty"
  >
    <h2 class="text-base font-semibold">
      {{ t('tools.palette-generator.empty.title') }}
    </h2>
    <p class="max-w-prose text-sm text-muted-foreground">
      {{ t('tools.palette-generator.empty.body') }}
    </p>
    <Button
      type="button"
      variant="outline"
      data-test="palette-restore"
      @click="emit('restore')"
    >
      {{ t('tools.palette-generator.empty.restore') }}
    </Button>
  </section>
</template>
