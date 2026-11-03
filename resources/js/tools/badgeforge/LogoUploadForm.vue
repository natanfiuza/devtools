<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { computed, useTemplateRef } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeLogo } from '@/routes/badgeforge/logos';

/**
 * Upload de logo personalizado (ADR-078), compartilhado pela tool e pela
 * pagina de conta.
 *
 * Envia o arquivo como multipart e deixa o parser do servidor decidir: o
 * navegador nao valida o SVG. A tool so toca o servidor nesta acao explicita
 * (ADR-079).
 */
const props = defineProps<{
    maxBytes: number;
    nameMaxLength: number;
    /** `null` = ilimitado; `0` nunca chega aqui (gestao fechada). */
    limit: number | null;
    used: number;
    upgradeUrl: string;
}>();

const emit = defineEmits<{
    uploaded: [];
}>();

const { t } = useI18n();

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');

const form = useForm<{ name: string; logo: File | null }>({
    name: '',
    logo: null,
});

const limitReached = computed<boolean>(
    () => props.limit !== null && props.used >= props.limit,
);

const maxKilobytes = computed<number>(() =>
    Math.max(1, Math.floor(props.maxBytes / 1024)),
);

function onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;

    form.logo = input.files?.[0] ?? null;
}

function submit(): void {
    if (limitReached.value || form.processing) {
        return;
    }

    form.post(storeLogo.url(), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            form.reset();

            if (fileInput.value !== null) {
                fileInput.value.value = '';
            }

            emit('uploaded');
        },
    });
}
</script>

<template>
  <form
    class="space-y-3"
    data-test="badge-logo-upload"
    @submit.prevent="submit"
  >
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="space-y-2">
        <Label for="badge-logo-name">
          {{ t('tools.badgeforge.management.name') }}
        </Label>
        <Input
          id="badge-logo-name"
          v-model="form.name"
          :maxlength="nameMaxLength"
          :placeholder="
            t('tools.badgeforge.management.name_placeholder')
          "
          data-test="badge-logo-name"
        />
        <InputError :message="form.errors.name" />
      </div>

      <div class="space-y-2">
        <Label for="badge-logo-file">
          {{ t('tools.badgeforge.management.logo_file') }}
        </Label>
        <input
          id="badge-logo-file"
          ref="fileInput"
          type="file"
          accept=".svg,image/svg+xml"
          class="block w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium"
          data-test="badge-logo-file"
          @change="onFileSelected"
        >
        <InputError :message="form.errors.logo" />
      </div>
    </div>

    <p class="text-xs text-muted-foreground">
      {{
        t('tools.badgeforge.management.upload_hint', {
          size: maxKilobytes,
        })
      }}
    </p>

    <div class="flex flex-wrap items-center gap-3">
      <Button
        type="submit"
        size="sm"
        :disabled="limitReached || form.processing"
        data-test="badge-logo-upload-submit"
      >
        <Upload class="size-4" />
        {{
          form.processing
            ? t('tools.badgeforge.management.uploading')
            : t('tools.badgeforge.management.upload')
        }}
      </Button>

      <p
        v-if="limit !== null"
        class="text-xs text-muted-foreground"
        data-test="badge-logo-limit-note"
      >
        {{
          t('tools.badgeforge.management.limit_note', {
            used,
            limit,
          })
        }}
      </p>
      <p
        v-else
        class="text-xs text-muted-foreground"
        data-test="badge-logo-limit-note"
      >
        {{ t('tools.badgeforge.management.unlimited_note', { used }) }}
      </p>
    </div>

    <p
      v-if="limitReached"
      class="text-xs text-amber-600"
      data-test="badge-logo-upgrade"
    >
      {{ t('tools.badgeforge.management.upgrade_cta', { limit }) }}
      <a
        :href="upgradeUrl"
        class="font-medium underline underline-offset-2"
      >
        {{ t('tools.badgeforge.management.upgrade') }}
      </a>
    </p>
  </form>
</template>
