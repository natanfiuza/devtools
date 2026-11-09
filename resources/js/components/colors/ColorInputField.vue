<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ParseErrorCode } from '@/lib/color';

/**
 * Campo de texto da cor.
 *
 * Aplica enquanto digita quando o texto ja e valido; quando nao e, a cor
 * anterior continua na tela e a mensagem diz o que houve. Enter/blur
 * normalizam o texto para HEX.
 */

const props = defineProps<{
    modelValue: string;
    error: ParseErrorCode | null;
}>();

const emit = defineEmits<{
    update: [text: string];
    finalize: [];
}>();

const { t } = useI18n();

const errorMessage = computed(() =>
    props.error === null
        ? null
        : t(`tools.shared.color_picker.color_input.${props.error}`),
);
</script>

<template>
  <div class="flex flex-col gap-2">
    <Label for="color-value">
      {{ t('tools.shared.color_picker.color_input.label') }}
    </Label>
    <Input
      id="color-value"
      :model-value="modelValue"
      :placeholder="t('tools.shared.color_picker.color_input.placeholder')"
      autocomplete="off"
      spellcheck="false"
      :aria-invalid="error !== null ? 'true' : undefined"
      aria-describedby="color-value-help"
      data-test="color-input"
      @update:model-value="emit('update', String($event))"
      @keydown.enter.prevent="emit('finalize')"
      @blur="emit('finalize')"
    />
    <p
      v-if="errorMessage !== null"
      id="color-value-help"
      class="text-sm text-destructive"
      role="alert"
    >
      {{ errorMessage }}
    </p>
    <p
      v-else
      id="color-value-help"
      class="text-xs text-muted-foreground"
    >
      {{ t('tools.shared.color_picker.color_input.hint') }}
    </p>
  </div>
</template>
