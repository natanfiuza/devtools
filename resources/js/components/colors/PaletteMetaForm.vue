<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    PaletteFormLimits,
    PaletteTag,
    PaletteVisibility,
} from '@/types/colors';
import TagInput from './TagInput.vue';

/**
 * Metadados editaveis da paleta (F4, ADR-123).
 *
 * O mesmo bloco do modal de salvar, agora no editor: nome, descricao,
 * visibilidade e tags. Os limites vem do servidor; o valor editado so vira
 * payload quando o editor salvar as alteracoes.
 */

const props = withDefaults(
    defineProps<{
        name: string;
        description: string;
        visibility: PaletteVisibility;
        tags: string[];
        limits: PaletteFormLimits;
        suggestions?: PaletteTag[];
        /** Erro devolvido pelo servidor, no lugar do campo. */
        nameError?: string | null;
        descriptionError?: string | null;
        tagsError?: string | null;
    }>(),
    {
        suggestions: () => [],
        nameError: null,
        descriptionError: null,
        tagsError: null,
    },
);

const emit = defineEmits<{
    'update:name': [value: string];
    'update:description': [value: string];
    'update:visibility': [value: PaletteVisibility];
    'update:tags': [value: string[]];
}>();

const { t } = useI18n();

const nameMessage = computed<string | null>(() => {
    const value = props.name.trim();

    if (value === '') {
        return t('colors.form.errors.name_required');
    }

    if (value.length > props.limits.nameMaxLength) {
        return t('colors.form.errors.name_too_long', {
            max: props.limits.nameMaxLength,
        });
    }

    return props.nameError;
});

const descriptionMessage = computed<string | null>(() => {
    if (props.description.length > props.limits.descriptionMaxLength) {
        return t('colors.form.errors.description_too_long', {
            max: props.limits.descriptionMaxLength,
        });
    }

    return props.descriptionError;
});
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4"
    data-test="palette-meta-form"
  >
    <div class="flex flex-col gap-2">
      <div class="flex items-baseline justify-between gap-2">
        <Label for="palette-meta-name">
          {{ t('colors.form.name_label') }}
        </Label>
        <span class="text-xs text-muted-foreground">
          {{ name.trim().length }}/{{ limits.nameMaxLength }}
        </span>
      </div>
      <Input
        id="palette-meta-name"
        :model-value="name"
        type="text"
        autocomplete="off"
        :maxlength="limits.nameMaxLength"
        :aria-invalid="nameMessage !== null"
        :placeholder="t('colors.form.name_placeholder')"
        data-test="palette-meta-name"
        @update:model-value="emit('update:name', String($event))"
      />
      <InputError :message="nameMessage ?? undefined" />
    </div>

    <div class="flex flex-col gap-2">
      <div class="flex items-baseline justify-between gap-2">
        <Label for="palette-meta-description">
          {{ t('colors.form.description_label') }}
        </Label>
        <span class="text-xs text-muted-foreground">
          {{ description.length }}/{{ limits.descriptionMaxLength }}
        </span>
      </div>
      <textarea
        id="palette-meta-description"
        :value="description"
        :maxlength="limits.descriptionMaxLength"
        :aria-invalid="descriptionMessage !== null"
        :placeholder="t('colors.form.description_placeholder')"
        class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
        data-test="palette-meta-description"
        @input="emit('update:description', ($event.target as HTMLTextAreaElement).value)"
      />
      <InputError :message="descriptionMessage ?? undefined" />
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
          data-test="palette-meta-visibility-private"
          @click="emit('update:visibility', 'private')"
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
          data-test="palette-meta-visibility-public"
          @click="emit('update:visibility', 'public')"
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
        id="palette-meta-tags"
        :model-value="tags"
        :suggestions="suggestions"
        :max="limits.tagsPerPalette"
        :name-max-length="limits.tagNameMaxLength"
        data-test="palette-meta-tags"
        @update:model-value="emit('update:tags', $event)"
      />
      <InputError :message="tagsError ?? undefined" />
    </div>
  </section>
</template>
