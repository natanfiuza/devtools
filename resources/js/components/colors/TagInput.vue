<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PaletteTag } from '@/types/colors';

/**
 * Campo de tags da paleta (F4, ADR-103).
 *
 * A tag e texto livre: Enter ou virgula acrescenta, Backspace no campo vazio
 * remove a ultima. A comparacao ignora caixa e espacos repetidos, entao "Web"
 * e "web" sao a mesma tag; o limite por paleta e o tamanho do nome vem do
 * servidor. As sugestoes sao as tags que a conta ja tem.
 */

const props = withDefaults(
    defineProps<{
        /** Id do campo, ligado ao label; o modal passa um valor estavel. */
        id: string;
        modelValue: string[];
        /** Tags existentes da conta, na ordem global do usuario. */
        suggestions?: PaletteTag[];
        max: number;
        nameMaxLength: number;
    }>(),
    { suggestions: () => [] },
);

const emit = defineEmits<{
    'update:modelValue': [tags: string[]];
}>();

const { t } = useI18n();

const draft = ref('');
const message = ref<string | null>(null);

function normalize(value: string): string {
    return value.trim().replace(/\s+/g, ' ');
}

function keyOf(value: string): string {
    return normalize(value).toLowerCase();
}

const selectedKeys = computed(
    () => new Set(props.modelValue.map((tag) => keyOf(tag))),
);

const unusedSuggestions = computed(() =>
    props.suggestions.filter((tag) => !selectedKeys.value.has(keyOf(tag.name))),
);

const hint = computed(() =>
    t('colors.form.tags_hint', {
        count: props.modelValue.length,
        max: props.max,
        length: props.nameMaxLength,
    }),
);

function add(raw: string): void {
    const name = normalize(raw);

    if (name === '') {
        return;
    }

    if (name.length > props.nameMaxLength) {
        message.value = t('colors.form.tags_too_long', {
            length: props.nameMaxLength,
        });

        return;
    }

    if (selectedKeys.value.has(keyOf(name))) {
        draft.value = '';
        message.value = null;

        return;
    }

    if (props.modelValue.length >= props.max) {
        message.value = t('colors.form.tags_limit', { max: props.max });

        return;
    }

    emit('update:modelValue', [...props.modelValue, name]);
    draft.value = '';
    message.value = null;
}

function removeAt(index: number): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, position) => position !== index),
    );
    message.value = null;
}

function removeLast(): void {
    if (props.modelValue.length > 0) {
        removeAt(props.modelValue.length - 1);
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        add(draft.value);

        return;
    }

    if (
        event.key === 'Backspace' &&
        draft.value === '' &&
        props.modelValue.length > 0
    ) {
        event.preventDefault();
        removeLast();
    }
}

function onBlur(): void {
    add(draft.value);
}
</script>

<template>
  <div
    class="flex flex-col gap-2"
    data-test="tag-input"
  >
    <div class="flex items-baseline justify-between gap-2">
      <Label :for="id">
        {{ t('colors.form.tags_label') }}
      </Label>
      <span class="text-xs text-muted-foreground">{{ hint }}</span>
    </div>

    <ul
      v-if="modelValue.length > 0"
      class="flex flex-wrap gap-1"
      data-test="tag-chips"
    >
      <li
        v-for="(tag, index) in modelValue"
        :key="`${tag}-${index}`"
        class="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs"
        data-test="tag-chip"
      >
        <span>{{ tag }}</span>
        <button
          type="button"
          class="rounded-full px-1 leading-none text-muted-foreground hover:text-foreground"
          :aria-label="t('colors.form.tags_remove', { name: tag })"
          data-test="tag-chip-remove"
          @click="removeAt(index)"
        >
          ×
        </button>
      </li>
    </ul>

    <Input
      :id="id"
      v-model="draft"
      type="text"
      autocomplete="off"
      :maxlength="nameMaxLength"
      :placeholder="t('colors.form.tags_placeholder')"
      data-test="tag-input-field"
      @keydown="onKeydown"
      @blur="onBlur"
    />

    <div
      v-if="unusedSuggestions.length > 0"
      class="flex flex-wrap items-center gap-1"
    >
      <span class="text-xs text-muted-foreground">
        {{ t('colors.form.tags_suggestions') }}
      </span>
      <button
        v-for="tag in unusedSuggestions"
        :key="tag.id"
        type="button"
        class="rounded-full border border-dashed border-border px-2 py-0.5 text-xs hover:bg-muted"
        data-test="tag-suggestion"
        @mousedown.prevent
        @click="add(tag.name)"
      >
        {{ tag.name }}
      </button>
    </div>

    <p
      v-if="message !== null"
      class="text-xs text-destructive"
      role="alert"
      data-test="tag-input-message"
    >
      {{ message }}
    </p>
  </div>
</template>
