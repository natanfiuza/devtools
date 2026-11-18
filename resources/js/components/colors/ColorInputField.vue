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

const props = withDefaults(
    defineProps<{
        modelValue: string;
        error: ParseErrorCode | null;
        /** Permite mais de um campo na mesma pagina sem ids duplicados. */
        id?: string;
        testId?: string;
    }>(),
    { id: 'color-value', testId: 'color-input' },
);

const emit = defineEmits<{
    update: [text: string];
    finalize: [];
}>();

const { t } = useI18n();

const helpId = computed(() => `${props.id}-help`);

const errorMessage = computed(() =>
    props.error === null
        ? null
        : t(`tools.shared.color_picker.color_input.${props.error}`),
);
</script>

<template>
    <div class="flex flex-col gap-2">
        <Label :for="id">
            {{ t('tools.shared.color_picker.color_input.label') }}
        </Label>
        <Input
            :id="id"
            :model-value="modelValue"
            :placeholder="
                t('tools.shared.color_picker.color_input.placeholder')
            "
            autocomplete="off"
            spellcheck="false"
            :aria-invalid="error !== null ? 'true' : undefined"
            :aria-describedby="helpId"
            :data-test="testId"
            @update:model-value="emit('update', String($event))"
            @keydown.enter.prevent="emit('finalize')"
            @blur="emit('finalize')"
        />
        <p
            v-if="errorMessage !== null"
            :id="helpId"
            class="text-sm text-destructive"
            role="alert"
        >
            {{ errorMessage }}
        </p>
        <p v-else :id="helpId" class="text-xs text-muted-foreground">
            {{ t('tools.shared.color_picker.color_input.hint') }}
        </p>
    </div>
</template>
