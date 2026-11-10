<script setup lang="ts">
import { Pipette } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { ColorState } from '@/composables/useColorState';
import { formatHex, formatHex8 } from '@/lib/color';
import { isEyeDropperSupported, pickScreenColor } from '@/lib/eyedropper';
import AlphaSlider from './AlphaSlider.vue';
import ChannelSliders from './ChannelSliders.vue';
import ColorArea from './ColorArea.vue';
import ColorInputField from './ColorInputField.vue';
import HueSlider from './HueSlider.vue';
import { withCurrentAlpha } from './lib/applyHex';
import { checkerboardStyle, cssColor } from './lib/style';

/**
 * O picker compacto: swatch, campo de texto, conta-gotas, area 2D, matiz,
 * opacidade e os sliders de canal. Reutilizado por qualquer tool que precise
 * escolher uma cor.
 *
 * O estado chega pronto (`ColorState`) e continua sendo a unica porta de
 * escrita. O erro `empty` nao aparece aqui: campo vazio e um estado da pagina
 * que usa o componente, nao um erro de formato.
 *
 * O conta-gotas so aparece quando o navegador tem a API `EyeDropper`; onde
 * ela nao existe (Firefox, por exemplo) a cor continua vindo do campo, da
 * area 2D ou dos sliders.
 */

const props = defineProps<{
    state: ColorState;
}>();

const { t } = useI18n();

const { rgba, hsv, hsl, text, error } = props.state;

const eyedropperSupported = isEyeDropperSupported();
const picking = ref(false);

const value = computed(() =>
    rgba.value.a < 1 ? formatHex8(rgba.value) : formatHex(rgba.value),
);

const displayedError = computed(() =>
    error.value === 'empty' ? null : error.value,
);

/**
 * Mede a tela e aplica a cor escolhida preservando o alpha atual - a tela
 * entrega um pixel opaco, mas a transparencia da base e uma escolha da pessoa.
 */
async function eyedropper(): Promise<void> {
    picking.value = true;

    try {
        const hex = await pickScreenColor();

        if (hex === null) {
            return;
        }

        const color = withCurrentAlpha(hex, rgba.value);

        if (color === null) {
            return;
        }

        props.state.setColor(color);
        props.state.finalize();
    } finally {
        picking.value = false;
    }
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
    </div>

    <Button
      v-if="eyedropperSupported"
      type="button"
      variant="outline"
      size="sm"
      class="self-start"
      :disabled="picking"
      data-test="picker-eyedropper"
      @click="eyedropper"
    >
      <Pipette aria-hidden="true" />
      {{ t('tools.shared.color_picker.preview.eyedropper') }}
    </Button>

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
