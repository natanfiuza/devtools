<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Secundaria manual (F3). E uma cor de entrada: as variantes e os estados
 * continuam derivados por tema pelo motor. Campo vazio volta ao automatico,
 * e ha um botao explicito para isso.
 */

defineProps<{
    invalid: boolean;
    manual: boolean;
}>();

const secondaryText = defineModel<string>('secondaryText', { required: true });

const emit = defineEmits<{ reset: [] }>();

const { t } = useI18n();
</script>

<template>
    <div class="space-y-1.5" data-test="secondary-field">
        <Label for="layout-harmonizer-secondary">
            {{ t('tools.layout-harmonizer.secondary.label') }}
        </Label>

        <Input
            id="layout-harmonizer-secondary"
            v-model="secondaryText"
            type="text"
            autocomplete="off"
            spellcheck="false"
            :aria-invalid="invalid"
            aria-describedby="layout-harmonizer-secondary-help"
            data-test="secondary-input"
        />

        <p
            id="layout-harmonizer-secondary-help"
            class="text-xs"
            :class="invalid ? 'text-destructive' : 'text-muted-foreground'"
        >
            {{
                invalid
                    ? t('tools.layout-harmonizer.secondary.invalid')
                    : t('tools.layout-harmonizer.secondary.hint')
            }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <p
                class="text-xs text-muted-foreground"
                data-test="secondary-source"
            >
                {{
                    manual
                        ? t('tools.layout-harmonizer.secondary.manual')
                        : t('tools.layout-harmonizer.secondary.auto')
                }}
            </p>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                :disabled="secondaryText.trim() === ''"
                data-test="secondary-reset"
                @click="emit('reset')"
            >
                {{ t('tools.layout-harmonizer.secondary.reset') }}
            </Button>
        </div>
    </div>
</template>
