<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import palettes from '@/routes/dashboard/colors/palettes';

/**
 * Confirmacao da exclusao definitiva (F4, ADR-123).
 *
 * Excluir apaga a linha, o OG e libera a vaga da cota; nao ha lixeira. Por
 * isso o dono digita o nome exato - e o servidor compara com o nome da paleta,
 * nunca com um id enviado pelo cliente. Arquivar continua sendo o caminho
 * reversivel.
 */

const props = defineProps<{
    open: boolean;
    paletteId: string;
    paletteName: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const confirmName = ref('');
const serverError = ref<string | null>(null);
const deleting = ref(false);

function reset(): void {
    confirmName.value = '';
    serverError.value = null;
    deleting.value = false;
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
);

const matches = computed(
    () => confirmName.value === props.paletteName,
);

function destroy(): void {
    if (!matches.value || deleting.value) {
        return;
    }

    deleting.value = true;
    serverError.value = null;

    router.delete(palettes.destroy.url({ colorPalette: props.paletteId }), {
        data: { confirm_name: confirmName.value },
        preserveScroll: true,
        onError: (errors) => {
            serverError.value = errors.confirm_name ?? null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

function setOpen(value: boolean): void {
    if (!value) {
        emit('close');
    }
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="setOpen"
  >
    <DialogContent
      class="sm:max-w-md"
      data-test="palette-delete-dialog"
    >
      <DialogHeader>
        <DialogTitle>{{ t('colors.delete.title') }}</DialogTitle>
        <DialogDescription>
          {{ t('colors.delete.description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-3"
        @submit.prevent="destroy"
      >
        <div class="flex flex-col gap-2">
          <Label for="palette-delete-name">
            {{ t('colors.delete.label') }}
          </Label>
          <Input
            id="palette-delete-name"
            v-model="confirmName"
            type="text"
            autocomplete="off"
            :placeholder="paletteName"
            :aria-invalid="serverError !== null"
            data-test="palette-delete-name"
          />
          <InputError :message="serverError ?? undefined" />
        </div>

        <DialogFooter class="gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="deleting"
            data-test="palette-delete-cancel"
            @click="emit('close')"
          >
            {{ t('colors.delete.cancel') }}
          </Button>
          <Button
            type="submit"
            variant="destructive"
            size="sm"
            :disabled="!matches || deleting"
            data-test="palette-delete-confirm"
          >
            {{
              deleting ? t('colors.delete.deleting') : t('colors.delete.confirm')
            }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
