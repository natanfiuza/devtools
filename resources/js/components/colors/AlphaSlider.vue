<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { clamp, type Rgba } from '@/lib/color';
import { cssColor } from './lib/style';

/**
 * Opacidade 0..1, exibida em porcentagem.
 *
 * A pista mostra o xadrez atras do degradado transparente -> opaco, entao o
 * valor tem um sinal visual alem do numero.
 */

const props = defineProps<{
    modelValue: number;
    color: Rgba;
}>();

const emit = defineEmits<{
    change: [alpha: number];
    finalize: [];
}>();

const { t } = useI18n();

const percent = computed(() => Math.round(clamp(props.modelValue, 0, 1) * 100));

const trackStyle = computed(() => ({
    backgroundImage: [
        `linear-gradient(to right, rgba(${props.color.r}, ${props.color.g}, ${props.color.b}, 0), rgb(${props.color.r}, ${props.color.g}, ${props.color.b}))`,
        ...checkerboardLayers(),
    ].join(', '),
    backgroundSize: 'auto, 12px 12px, 12px 12px',
    backgroundPosition: '0 0, 0 0, 6px 6px',
    '--thumb-color': cssColor({ ...props.color, a: 1 }),
}));

/** As duas camadas do xadrez, sem o tamanho/posicao (que vao no objeto). */
function checkerboardLayers(): string[] {
    const layer =
        'linear-gradient(45deg, rgba(127, 127, 127, 0.22) 25%, transparent 25%, transparent 75%, rgba(127, 127, 127, 0.22) 75%)';

    return [layer, layer];
}

function onInput(event: Event): void {
    emit('change', Number((event.target as HTMLInputElement).value) / 100);
}
</script>

<template>
  <label class="block">
    <span class="flex items-center justify-between text-sm font-medium">
      <span>{{ t('tools.shared.color_picker.alpha.label') }}</span>
      <span class="text-muted-foreground tabular-nums">
        {{ percent }}%
      </span>
    </span>
    <input
      type="range"
      min="0"
      max="100"
      step="1"
      :value="percent"
      :aria-label="t('tools.shared.color_picker.alpha.label')"
      class="mt-2 h-2 w-full cursor-pointer appearance-none rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 [&::-moz-range-thumb]:size-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[var(--thumb-color)] [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:size-5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[var(--thumb-color)] [&::-webkit-slider-thumb]:shadow-md"
      :style="trackStyle"
      data-test="alpha-slider"
      @input="onInput"
      @change="emit('finalize')"
      @blur="emit('finalize')"
    >
  </label>
</template>
