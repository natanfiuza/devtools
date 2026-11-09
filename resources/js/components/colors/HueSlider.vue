<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { hslToRgb, normalizeHue } from '@/lib/color';

/**
 * Slider de matiz 0..360.
 *
 * E um `input[type=range]` nativo de proposito: teclado, leitor de tela e
 * arraste vem de graca. O degradado e so a pista visual.
 */

const props = defineProps<{
    modelValue: number;
}>();

const emit = defineEmits<{
    change: [hue: number];
    finalize: [];
}>();

const { t } = useI18n();

const thumbColor = computed(() => {
    const rgb = hslToRgb({ h: normalizeHue(props.modelValue), s: 100, l: 50 });

    return `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
});

function onInput(event: Event): void {
    emit('change', Number((event.target as HTMLInputElement).value));
}
</script>

<template>
  <label class="block">
    <span class="text-sm font-medium">
      {{ t('tools.shared.color_picker.hue.label') }}
    </span>
    <input
      type="range"
      min="0"
      max="360"
      step="1"
      :value="Math.round(modelValue)"
      :aria-label="t('tools.shared.color_picker.hue.label')"
      class="mt-2 h-2 w-full cursor-pointer appearance-none rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 [&::-moz-range-thumb]:size-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[var(--thumb-color)] [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:size-5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[var(--thumb-color)] [&::-webkit-slider-thumb]:shadow-md"
      :style="{
        background:
          'linear-gradient(to right, #ff0000 0%, #ffff00 17%, #00ff00 33%, #00ffff 50%, #0000ff 67%, #ff00ff 83%, #ff0000 100%)',
        '--thumb-color': thumbColor,
      }"
      data-test="hue-slider"
      @input="onInput"
      @change="emit('finalize')"
      @blur="emit('finalize')"
    >
  </label>
</template>
