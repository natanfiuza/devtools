<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ColorState } from '@/composables/useColorState';
import { formatHex, formatHex8 } from '@/lib/color';
import AlphaSlider from './AlphaSlider.vue';
import ChannelSliders from './ChannelSliders.vue';
import ColorArea from './ColorArea.vue';
import ColorInputField from './ColorInputField.vue';
import HueSlider from './HueSlider.vue';
import { withCurrentAlpha } from './lib/applyHex';
import { checkerboardStyle, cssColor } from './lib/style';

/**
 * O picker compacto: swatch, campo de texto, area 2D, matiz, opacidade e os
 * sliders de canal. Reutilizado por qualquer tool que precise escolher uma cor.
 *
 * O estado chega pronto (`ColorState`) e continua sendo a unica porta de
 * escrita. O erro `empty` nao aparece aqui: campo vazio e um estado da pagina
 * que usa o componente, nao um erro de formato.
 */

const props = defineProps<{
    state: ColorState;
}>();

const { t } = useI18n();

const { rgba, hsv, hsl, text, error } = props.state;

const value = computed(() =>
    rgba.value.a < 1 ? formatHex8(rgba.value) : formatHex(rgba.value),
);

const displayedError = computed(() =>
    error.value === 'empty' ? null : error.value,
);

function onNative(event: Event): void {
    const color = withCurrentAlpha(
        (event.target as HTMLInputElement).value,
        rgba.value,
    );

    if (color === null) {
        return;
    }

    props.state.setColor(color);
    props.state.finalize();
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center gap-3">
      <div
        class="relative size-14 shrink-0 overflow-hidden rounded-lg border shadow-xs"
        :style="checkerboardStyle"
        aria-hidden="true"
      >
        <div
          class="absolute inset-0"
          :style="{ backgroundColor: cssColor(rgba) }"
        />
      </div>
      <code
        class="min-w-0 flex-1 truncate font-mono text-lg font-medium tabular-nums"
        data-test="picker-value"
      >
        {{ value }}
      </code>
      <input
        type="color"
        :value="formatHex(rgba)"
        :aria-label="t('tools.shared.color_picker.preview.native')"
        :title="t('tools.shared.color_picker.preview.native')"
        class="size-9 shrink-0 cursor-pointer rounded-md border bg-transparent p-1"
        data-test="picker-native"
        @input="onNative"
      >
    </div>

    <ColorInputField
      :model-value="text"
      :error="displayedError"
      @update="state.setFromText"
      @finalize="state.finalizeText"
    />

    <ColorArea
      :hsv="hsv"
      @change="state.setSaturationValue"
      @finalize="state.finalize"
    />

    <HueSlider
      :model-value="hsv.h"
      @change="state.setHue"
      @finalize="state.finalize"
    />

    <AlphaSlider
      :model-value="rgba.a"
      :color="rgba"
      @change="state.setAlpha"
      @finalize="state.finalize"
    />

    <ChannelSliders
      :rgba="rgba"
      :hsl="hsl"
      @set-rgb="state.setRgbChannel"
      @set-hsl="state.setHslChannel"
      @finalize="state.finalize"
    />
  </div>
</template>
