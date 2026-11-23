<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Secundaria manual (F3, emenda da F6). E uma cor de entrada: as variantes e
 * os estados continuam derivados por tema pelo motor. Campo vazio volta ao
 * automatico, e ha um botao explicito para isso.
 *
 * Com alpha, a secundaria exige um fundo de composicao proprio: o campo so
 * aparece quando a cor digitada tem transparencia (ou quando ja tem texto, para
 * poder ser limpo). Nada de fundo padrao.
 */

defineProps<{
    invalid: boolean;
    manual: boolean;
    /** A secundaria informada tem alpha e exige fundo de composicao. */
    needsBackground: boolean;
    backgroundInvalid: boolean;
    backgroundAlpha: boolean;
}>();

const secondaryText = defineModel<string>('secondaryText', { required: true });
const secondaryBackgroundText = defineModel<string>('secondaryBackgroundText', {
    required: true,
});

const emit = defineEmits<{ reset: [] }>();

const { t } = useI18n();
</script>

<template>
  <div
    class="space-y-1.5"
    data-test="secondary-field"
  >
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

    <div
      v-if="needsBackground || secondaryBackgroundText.trim() !== ''"
      class="space-y-1.5"
      data-test="secondary-background-field"
    >
      <Label for="layout-harmonizer-secondary-background">
        {{ t('tools.layout-harmonizer.secondary.background_label') }}
      </Label>

      <Input
        id="layout-harmonizer-secondary-background"
        v-model="secondaryBackgroundText"
        type="text"
        autocomplete="off"
        spellcheck="false"
        :aria-invalid="backgroundInvalid"
        aria-describedby="layout-harmonizer-secondary-background-help"
        data-test="secondary-background-input"
      />

      <p
        id="layout-harmonizer-secondary-background-help"
        class="text-xs"
        :class="
          backgroundInvalid
            ? 'text-destructive'
            : 'text-muted-foreground'
        "
      >
        {{
          backgroundAlpha
            ? t(
              'tools.layout-harmonizer.secondary.background_alpha_invalid',
            )
            : backgroundInvalid
              ? t(
                'tools.layout-harmonizer.secondary.background_invalid',
              )
              : t(
                'tools.layout-harmonizer.secondary.background_hint',
              )
        }}
      </p>
    </div>

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
        :disabled="
          secondaryText.trim() === '' &&
            secondaryBackgroundText.trim() === ''
        "
        data-test="secondary-reset"
        @click="emit('reset')"
      >
        {{ t('tools.layout-harmonizer.secondary.reset') }}
      </Button>
    </div>
  </div>
</template>
