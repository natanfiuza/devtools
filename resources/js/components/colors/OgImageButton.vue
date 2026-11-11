<script setup lang="ts">
import { Image as ImageIcon } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';

/**
 * Botao "Gerar imagem" do cartao social (F4, ADR-125).
 *
 * Presentacional: o editor e quem gera e envia o PNG (a geracao automatica
 * tambem usa o mesmo caminho). Aqui ficam so o rotulo, o estado ocupado e o
 * aviso quando a ultima tentativa falhou.
 */

defineProps<{
    /** O servidor ja tem uma OG para esta paleta (ou esta sessao enviou). */
    hasOg: boolean;
    busy: boolean;
    error: boolean;
}>();

const emit = defineEmits<{ generate: [] }>();

const { t } = useI18n();
</script>

<template>
  <div
    class="flex flex-col items-end gap-1"
    data-test="og-image"
  >
    <Button
      type="button"
      size="sm"
      variant="outline"
      :disabled="busy"
      :aria-busy="busy"
      data-test="og-image-generate"
      @click="emit('generate')"
    >
      <ImageIcon class="size-4" />
      {{
        busy
          ? t('colors.og.generating')
          : hasOg
            ? t('colors.og.regenerate')
            : t('colors.og.generate')
      }}
    </Button>

    <p
      v-if="error"
      class="max-w-60 text-end text-xs text-destructive"
      role="alert"
      data-test="og-image-error"
    >
      {{ t('colors.og.error') }}
    </p>
    <p
      v-else-if="!hasOg"
      class="max-w-60 text-end text-xs text-muted-foreground"
    >
      {{ t('colors.og.hint') }}
    </p>
  </div>
</template>
