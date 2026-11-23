<script setup lang="ts">
import { BellOff, BellRing } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { usePaletteMute } from '@/composables/usePaletteMute';

/**
 * Silenciar esta paleta (F7, ADR-142).
 *
 * So aparece para o dono (`canMute`). Silencia curtidas, comentarios e
 * respostas da paleta no app e no e-mail, avaliado no envio; seguir continua
 * notificando. O estado e otimista e a falha desfaz sem recarregar.
 */

const props = defineProps<{
    paletteId: string;
    muted: boolean;
    canMute: boolean;
}>();

const { t } = useI18n();

const { muted, pending, failed, toggle } = usePaletteMute({
    paletteId: props.paletteId,
    muted: props.muted,
});
</script>

<template>
  <div
    v-if="canMute"
    class="flex flex-wrap items-center gap-2"
  >
    <Button
      type="button"
      variant="outline"
      size="sm"
      :disabled="pending"
      :aria-pressed="muted"
      data-test="mute-palette-button"
      @click="toggle"
    >
      <BellOff
        v-if="muted"
        class="size-4"
      />
      <BellRing
        v-else
        class="size-4"
      />
      {{ muted ? t('colors.mute.unmute') : t('colors.mute.mute') }}
    </Button>

    <span
      v-if="muted"
      class="text-xs text-muted-foreground"
      data-test="mute-palette-state"
    >
      {{ t('colors.mute.muted') }}
    </span>

    <p
      v-if="failed"
      class="text-xs text-destructive"
      role="alert"
      data-test="mute-palette-error"
    >
      {{ t('colors.mute.error') }}
    </p>
  </div>
</template>
