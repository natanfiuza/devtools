<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ColorPickerControls from '@/components/colors/ColorPickerControls.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ColorState } from '@/composables/useColorState';

/**
 * Entrada da seed: o picker compartilhado e, quando a cor tem alpha, o campo
 * de fundo da composicao. O fundo nunca tem padrao: sem ele o motor recusa a
 * geracao e a pagina mantem o ultimo quadro valido (ADR-149).
 */

defineProps<{
    picker: ColorState;
    empty: boolean;
    backgroundVisible: boolean;
    backgroundInvalid: boolean;
}>();

const backgroundText = defineModel<string>('backgroundText', {
    required: true,
});

const { t } = useI18n();
</script>

<template>
    <div class="space-y-4">
        <ColorPickerControls
            :state="picker"
            :empty="empty"
            :empty-label="t('tools.layout-harmonizer.seed.empty_value')"
        />

        <div
            v-if="backgroundVisible"
            class="space-y-1.5"
            data-test="seed-background-field"
        >
            <Label for="layout-harmonizer-background">
                {{ t('tools.layout-harmonizer.seed.background_label') }}
            </Label>

            <Input
                id="layout-harmonizer-background"
                v-model="backgroundText"
                type="text"
                autocomplete="off"
                spellcheck="false"
                :aria-invalid="backgroundInvalid"
                aria-describedby="layout-harmonizer-background-help"
                data-test="seed-background-input"
            />

            <p
                id="layout-harmonizer-background-help"
                class="text-xs"
                :class="
                    backgroundInvalid
                        ? 'text-destructive'
                        : 'text-muted-foreground'
                "
            >
                {{
                    backgroundInvalid
                        ? t('tools.layout-harmonizer.seed.background_invalid')
                        : t('tools.layout-harmonizer.seed.background_hint')
                }}
            </p>
        </div>
    </div>
</template>
