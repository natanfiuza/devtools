<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { formatHex, formatHex8, parseColor } from '@/lib/color';

const props = defineProps<{
    label: string;
    /** HEX6/HEX8 do papel. */
    modelValue: string;
    testId: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const text = ref(props.modelValue);

watch(
    () => props.modelValue,
    (value) => {
        text.value = value;
    },
);

/** `input[type=color]` so aceita HEX6; o alfa fica no campo de texto. */
const swatch = computed(() => {
    const parsed = parseColor(props.modelValue);

    if (!parsed.ok) {
        return '#000000';
    }

    return formatHex({ ...parsed.color.rgba, a: 1 });
});

function fromSwatch(event: Event): void {
    emit('update:modelValue', (event.target as HTMLInputElement).value);
}

/** Texto livre: aceita qualquer formato do parser e normaliza para HEX. */
function fromText(): void {
    const parsed = parseColor(text.value.trim());

    if (!parsed.ok) {
        text.value = props.modelValue;

        return;
    }

    const rgba = parsed.color.rgba;

    text.value = rgba.a < 1 ? formatHex8(rgba) : formatHex(rgba);
    emit('update:modelValue', text.value);
}
</script>

<template>
  <div
    class="flex items-center gap-2"
    :data-test="testId"
  >
    <input
      type="color"
      class="size-9 shrink-0 cursor-pointer rounded-md border border-border bg-transparent p-0"
      :value="swatch"
      :aria-label="label"
      @input="fromSwatch"
    >
    <label class="flex min-w-0 flex-col">
      <span class="text-xs font-medium">{{ label }}</span>
      <input
        v-model="text"
        type="text"
        class="w-28 rounded-md border border-border bg-background px-2 py-1 font-mono text-xs"
        autocomplete="off"
        spellcheck="false"
        :data-test="`${testId}-text`"
        @change="fromText"
      >
    </label>
  </div>
</template>
