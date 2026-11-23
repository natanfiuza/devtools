<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import palettes from '@/routes/dashboard/colors/palettes';
import type { PaletteSaveSource } from '@/lib/paletteSave';
import { payloadFromExtracted, payloadFromScale } from '@/lib/palettePayload';
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
import type {
    PaletteDetails,
    PalettePayload,
    PaletteToolProps,
    PaletteVisibility,
} from '@/types/colors';
import TagInput from './TagInput.vue';

/**
 * Modal de salvar a paleta do gerador (F4, ADR-123).
 *
 * Recebe a escala (ou a extracao com a base ja resolvida) do workspace e so
 * acrescenta os metadados. Nome, descricao e tags passam pelos limites que o
 * servidor entregou; a cota e a validacao final continuam no servidor, e a
 * mensagem dele aparece aqui quando volta. Nenhuma cor e recalculada.
 */

const props = defineProps<{
    open: boolean;
    /** O que sera salvo; `null` mantem o envio desabilitado. */
    source: PaletteSaveSource | null;
    /** Limites do formulario e tags da conta (ColorsToolProps). */
    palette: PaletteToolProps;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useI18n();

const name = ref('');
const description = ref('');
const visibility = ref<PaletteVisibility>('private');
const tags = ref<string[]>([]);
const serverErrors = ref<Record<string, string>>({});
const saving = ref(false);

function reset(): void {
    name.value = '';
    description.value = '';
    visibility.value = 'private';
    tags.value = [];
    serverErrors.value = {};
    saving.value = false;
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        }
    },
);

const nameError = computed<string | null>(() => {
    const value = name.value.trim();

    if (value === '') {
        return t('colors.form.errors.name_required');
    }

    if (value.length > props.palette.nameMaxLength) {
        return t('colors.form.errors.name_too_long', {
            max: props.palette.nameMaxLength,
        });
    }

    return serverErrors.value.name ?? null;
});

const descriptionError = computed<string | null>(() => {
    if (description.value.length > props.palette.descriptionMaxLength) {
        return t('colors.form.errors.description_too_long', {
            max: props.palette.descriptionMaxLength,
        });
    }

    return serverErrors.value.description ?? null;
});

const tagsError = computed<string | null>(
    () => serverErrors.value.tags ?? serverErrors.value['tags.0'] ?? null,
);

/** Erros de corpora (cota, formato, versao) no lugar do campo. */
const formErrors = computed<string[]>(() =>
    Object.entries(serverErrors.value)
        .filter(
            ([key]) =>
                !['name', 'description', 'tags'].includes(key) &&
                !key.startsWith('tags.'),
        )
        .map(([, message]) => message),
);

const insufficient = computed(
    () =>
        props.source?.kind === 'extracted' && !props.source.palette.sufficient,
);

const payload = computed<PalettePayload | null>(() => {
    if (props.source === null) {
        return null;
    }

    const details: PaletteDetails = {
        name: name.value.trim(),
        description:
            description.value.trim() === '' ? null : description.value.trim(),
        visibility: visibility.value,
        tags: tags.value,
    };

    try {
        const body =
            props.source.kind === 'scale'
                ? payloadFromScale(props.source.scale, details)
                : payloadFromExtracted(
                      props.source.palette,
                      props.source.base,
                      details,
                  );

        // Papéis importados do arquivo de tokens vencem os defaults; os dois
        // shapes ja sao completos (8 papeis x 2 temas).
        return props.source.roles
            ? { ...body, roles: props.source.roles }
            : body;
    } catch {
        // Base fora das cores extraidas ou escala incompleta: o envio fica
        // bloqueado em vez de mandar um corpo invalido.
        return null;
    }
});

const canSubmit = computed(
    () =>
        !saving.value &&
        payload.value !== null &&
        nameError.value === null &&
        descriptionError.value === null,
);

function submit(): void {
    const body = payload.value;

    if (!canSubmit.value || body === null) {
        return;
    }

    saving.value = true;
    serverErrors.value = {};

    router.post(
        palettes.store.url(),
        { ...body },
        {
            preserveScroll: true,
            onError: (errors) => {
                serverErrors.value = { ...errors };
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
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
      class="sm:max-w-lg"
      data-test="palette-save-dialog"
    >
      <DialogHeader>
        <DialogTitle>{{ t('colors.form.title') }}</DialogTitle>
        <DialogDescription>
          {{ t('colors.form.description') }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-4"
        @submit.prevent="submit"
      >
        <div class="flex flex-col gap-2">
          <div class="flex items-baseline justify-between gap-2">
            <Label for="palette-save-name">
              {{ t('colors.form.name_label') }}
            </Label>
            <span class="text-xs text-muted-foreground">
              {{ name.trim().length }}/{{ palette.nameMaxLength }}
            </span>
          </div>
          <Input
            id="palette-save-name"
            v-model="name"
            type="text"
            autocomplete="off"
            :maxlength="palette.nameMaxLength"
            :aria-invalid="nameError !== null"
            :placeholder="t('colors.form.name_placeholder')"
            data-test="save-name"
          />
          <InputError :message="nameError ?? undefined" />
        </div>

        <div class="flex flex-col gap-2">
          <div class="flex items-baseline justify-between gap-2">
            <Label for="palette-save-description">
              {{ t('colors.form.description_label') }}
            </Label>
            <span class="text-xs text-muted-foreground">
              {{ description.length }}/{{
                palette.descriptionMaxLength
              }}
            </span>
          </div>
          <textarea
            id="palette-save-description"
            v-model="description"
            :maxlength="palette.descriptionMaxLength"
            :aria-invalid="descriptionError !== null"
            :placeholder="t('colors.form.description_placeholder')"
            class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
            data-test="save-description"
          />
          <InputError :message="descriptionError ?? undefined" />
        </div>

        <fieldset class="flex flex-col gap-2">
          <legend class="text-sm font-medium">
            {{ t('colors.form.visibility_label') }}
          </legend>
          <div class="grid gap-2 sm:grid-cols-2">
            <button
              type="button"
              role="radio"
              :aria-checked="visibility === 'private'"
              class="rounded-lg border px-3 py-2 text-left text-sm"
              :class="
                visibility === 'private'
                  ? 'border-primary bg-primary/5'
                  : 'border-border'
              "
              data-test="save-visibility-private"
              @click="visibility = 'private'"
            >
              <span class="block font-medium">
                {{ t('colors.form.visibility_private') }}
              </span>
              <span class="block text-xs text-muted-foreground">
                {{ t('colors.form.visibility_private_hint') }}
              </span>
            </button>
            <button
              type="button"
              role="radio"
              :aria-checked="visibility === 'public'"
              class="rounded-lg border px-3 py-2 text-left text-sm"
              :class="
                visibility === 'public'
                  ? 'border-primary bg-primary/5'
                  : 'border-border'
              "
              data-test="save-visibility-public"
              @click="visibility = 'public'"
            >
              <span class="block font-medium">
                {{ t('colors.form.visibility_public') }}
              </span>
              <span class="block text-xs text-muted-foreground">
                {{ t('colors.form.visibility_public_hint') }}
              </span>
            </button>
          </div>
        </fieldset>

        <div class="flex flex-col gap-1">
          <TagInput
            id="palette-save-tags"
            v-model="tags"
            :suggestions="palette.tags"
            :max="palette.tagsPerPalette"
            :name-max-length="palette.tagNameMaxLength"
          />
          <InputError :message="tagsError ?? undefined" />
        </div>

        <p
          v-if="insufficient"
          class="rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground"
          data-test="save-insufficient"
        >
          {{
            t('colors.form.insufficient', {
              found:
                source?.kind === 'extracted'
                  ? source.palette.colors.length
                  : 0,
            })
          }}
        </p>

        <div
          v-if="formErrors.length > 0"
          class="flex flex-col gap-1 rounded-lg border border-destructive/40 px-3 py-2 text-xs text-destructive"
          role="alert"
          data-test="save-error"
        >
          <p
            v-for="message in formErrors"
            :key="message"
          >
            {{ message }}
          </p>
        </div>

        <DialogFooter class="gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            :disabled="saving"
            data-test="save-cancel"
            @click="emit('close')"
          >
            {{ t('colors.form.cancel') }}
          </Button>
          <Button
            type="submit"
            size="sm"
            :disabled="!canSubmit"
            data-test="save-submit"
          >
            {{
              saving
                ? t('colors.form.saving')
                : t('colors.form.submit')
            }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
