<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Pergunta de importacao do rascunho anonimo (F4, ADR-161).
 *
 * Importar hidrata a tela sem salvar nem consumir cota; descartar apaga o
 * registro. Envelope de versao desconhecida nao pode ser aberto: so
 * "Descartar" aparece, com o motivo. Fechar com Esc/overlay nao decide nada -
 * o rascunho so e apagado pelo botao explicito, que e o consentimento.
 */

defineProps<{
    open: boolean;
    /** `false` quando o envelope e de uma versao que este editor nao abre. */
    supported: boolean;
}>();

const emit = defineEmits<{
    import: [];
    discard: [];
}>();

const { t } = useI18n();

// Sem handler de `update:open`: Esc/overlay/close nao apagam o rascunho. O
// dialogo continua aberto ate a pessoa escolher importar ou descartar.
</script>

<template>
  <Dialog :open="open">
    <DialogContent
      class="sm:max-w-md"
      data-test="layout-draft-dialog"
    >
      <DialogHeader>
        <DialogTitle>
          {{
            supported
              ? t('tools.layout-harmonizer.draft.title')
              : t(
                'tools.layout-harmonizer.draft.unsupported_title',
              )
          }}
        </DialogTitle>
        <DialogDescription>
          {{
            supported
              ? t('tools.layout-harmonizer.draft.description')
              : t(
                'tools.layout-harmonizer.draft.unsupported_description',
              )
          }}
        </DialogDescription>
      </DialogHeader>

      <DialogFooter class="gap-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          data-test="layout-draft-discard"
          @click="emit('discard')"
        >
          {{ t('tools.layout-harmonizer.draft.discard') }}
        </Button>
        <Button
          v-if="supported"
          type="button"
          size="sm"
          data-test="layout-draft-import"
          @click="emit('import')"
        >
          {{ t('tools.layout-harmonizer.draft.import') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
