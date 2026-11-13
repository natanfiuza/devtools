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
 * Confirmacao da importacao do historico anonimo (F5, ADR-132).
 *
 * O historico local so entra na conta por esta acao explicita; o alerta conta
 * quantas cores serao levadas e deixa claro que a lista local e limpa depois.
 */

const props = defineProps<{
    open: boolean;
    count: number;
    busy: boolean;
}>();

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();

const { t } = useI18n();

function setOpen(value: boolean): void {
    if (!value) {
        emit('close');
    }
}
</script>

<template>
  <Dialog
    :open="props.open"
    @update:open="setOpen"
  >
    <DialogContent
      class="sm:max-w-md"
      data-test="history-import-dialog"
    >
      <DialogHeader>
        <DialogTitle>{{ t('tools.shared.library.import.title') }}</DialogTitle>
        <DialogDescription>
          {{
            t('tools.shared.library.import.description', {
              count: props.count,
            })
          }}
        </DialogDescription>
      </DialogHeader>

      <DialogFooter class="gap-2">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          :disabled="props.busy"
          @click="emit('close')"
        >
          {{ t('tools.shared.library.import.cancel') }}
        </Button>
        <Button
          type="button"
          size="sm"
          :disabled="props.busy"
          data-test="history-import-confirm"
          @click="emit('confirm')"
        >
          {{
            props.busy
              ? t('tools.shared.library.import.busy')
              : t('tools.shared.library.import.confirm')
          }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
