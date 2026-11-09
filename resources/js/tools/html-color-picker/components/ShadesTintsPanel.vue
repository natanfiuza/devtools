<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatHex, shadesOf, tintsOf, type Rgba } from '@/lib/color';
import SwatchButton from '@/components/colors/SwatchButton.vue';

/**
 * Tons (mais escuros) e tintas (mais claras) da cor atual, 10 + 10 em OKLab.
 *
 * Sao uma exploracao rapida da cor, nao a escala de 11 tons da paleta da F2:
 * aqui o passo e uniforme em OKLab e o alpha da cor e preservado.
 */

const props = defineProps<{
    rgba: Rgba;
}>();

const emit = defineEmits<{
    apply: [color: Rgba];
}>();

const { t } = useI18n();

const shades = computed(() => shadesOf(props.rgba, 10));
const tints = computed(() => tintsOf(props.rgba, 10));
</script>

<template>
  <div class="flex flex-col gap-4">
    <section class="rounded-xl border bg-card p-4">
      <h2 class="text-sm font-semibold">
        {{ t('tools.html-color-picker.shades.title') }}
      </h2>
      <p class="mt-1 text-xs text-muted-foreground">
        {{ t('tools.html-color-picker.shades.description') }}
      </p>
      <div class="mt-3 grid grid-cols-5 gap-1">
        <SwatchButton
          v-for="color in shades"
          :key="`shade-${formatHex(color)}`"
          :color="color"
          :label="formatHex(color)"
          caption
          @select="emit('apply', color)"
        />
      </div>
    </section>

    <section class="rounded-xl border bg-card p-4">
      <h2 class="text-sm font-semibold">
        {{ t('tools.html-color-picker.tints.title') }}
      </h2>
      <p class="mt-1 text-xs text-muted-foreground">
        {{ t('tools.html-color-picker.tints.description') }}
      </p>
      <div class="mt-3 grid grid-cols-5 gap-1">
        <SwatchButton
          v-for="color in tints"
          :key="`tint-${formatHex(color)}`"
          :color="color"
          :label="formatHex(color)"
          caption
          @select="emit('apply', color)"
        />
      </div>
    </section>
  </div>
</template>
