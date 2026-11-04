<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeEndpoint } from '@/routes/badgeforge/endpoints';
import type { BadgeForgeToolProps } from './types';

/**
 * Cadastro de endpoint JSON (F5, ADR-083).
 *
 * O dono informa nome e URL de origem. A URL vive no servidor: nao volta em
 * props, flash ou lista - so a imagem publica com o ULID. O servidor valida
 * SSRF e busca uma amostra antes de gravar; origem fora do ar nao impede o
 * cadastro, porque a imagem cai em fallback ate a origem voltar.
 */

const props = defineProps<{
    open: boolean;
    toolProps: BadgeForgeToolProps;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();

const form = useForm({ name: '', url: '' });

watch(
    () => props.open,
    (open) => {
        if (!open) {
            form.reset();
            form.clearErrors();
        }
    },
);

function submit(): void {
    if (form.processing) {
        return;
    }

    form.post(storeEndpoint.url(), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();
            emit('update:open', false);
        },
    });
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="(value: boolean) => emit('update:open', value)"
  >
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>
          {{ t('tools.badgeforge.endpoint.dialog_title') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('tools.badgeforge.endpoint.dialog_description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="space-y-4"
        data-test="badge-endpoint-form"
        @submit.prevent="submit"
      >
        <div class="space-y-2">
          <Label for="badge-endpoint-name">
            {{ t('tools.badgeforge.endpoint.name') }}
          </Label>
          <Input
            id="badge-endpoint-name"
            v-model="form.name"
            :maxlength="toolProps.limits.endpointBadges.nameMaxLength"
            :placeholder="t('tools.badgeforge.endpoint.name_placeholder')"
            data-test="badge-endpoint-name"
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="space-y-2">
          <Label for="badge-endpoint-url">
            {{ t('tools.badgeforge.endpoint.url') }}
          </Label>
          <Input
            id="badge-endpoint-url"
            v-model="form.url"
            type="url"
            autocomplete="off"
            spellcheck="false"
            :maxlength="toolProps.limits.endpointBadges.urlMaxLength"
            :placeholder="t('tools.badgeforge.endpoint.url_placeholder')"
            data-test="badge-endpoint-url"
          />
          <InputError :message="form.errors.url" />
          <p class="text-xs text-muted-foreground">
            {{ t('tools.badgeforge.endpoint.url_hint') }}
          </p>
        </div>

        <div class="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="sm"
            type="button"
            data-test="badge-endpoint-cancel"
            @click="emit('update:open', false)"
          >
            {{ t('tools.badgeforge.management.cancel') }}
          </Button>
          <Button
            size="sm"
            type="submit"
            :disabled="form.processing"
            data-test="badge-endpoint-submit"
          >
            {{
              form.processing
                ? t('tools.badgeforge.endpoint.creating')
                : t('tools.badgeforge.endpoint.create')
            }}
          </Button>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>
