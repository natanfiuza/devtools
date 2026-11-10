<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AlphaSlider from '@/components/colors/AlphaSlider.vue';
import ChannelSliders from '@/components/colors/ChannelSliders.vue';
import ColorArea from '@/components/colors/ColorArea.vue';
import ColorInputField from '@/components/colors/ColorInputField.vue';
import FormatsList from '@/components/colors/FormatsList.vue';
import HueSlider from '@/components/colors/HueSlider.vue';
import { opaqueColor } from '@/components/colors/lib/applyHex';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useColorState } from '@/composables/useColorState';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { canonicalKey, parseColor, type Rgba } from '@/lib/color';
import ColorPreview from './components/ColorPreview.vue';
import ContrastPanel from './components/ContrastPanel.vue';
import FavoritesPanel from './components/FavoritesPanel.vue';
import HistoryPanel from './components/HistoryPanel.vue';
import ShadesTintsPanel from './components/ShadesTintsPanel.vue';
import { browserStorage, createColorLibrary } from './lib/storage';

/**
 * HTML Color Picker (`/{locale}/tools/colors/html-color-picker`).
 *
 * Uma unica porta de escrita (`useColorState`) alimenta a area 2D, os sliders,
 * o campo de texto e a previa; o historico so recebe cores finalizadas
 * (pointerup/blur/Enter), nunca cada quadro do arraste. Tudo roda no navegador:
 * a unica persistencia e o `localStorage` local.
 */

const DEFAULT_COLOR: Rgba = { r: 124, g: 58, b: 237, a: 1 };

const { t } = useI18n();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;

const library = createColorLibrary(browserStorage());

const historyKeys = ref<string[]>([]);
const favoriteKeys = ref<string[]>([]);

const state = useColorState({
    initial: DEFAULT_COLOR,
    onFinalize: (color) => {
        historyKeys.value = library.addHistory(color);
        library.saveLast(color);
    },
});

const { rgba, hsv, hsl, text, error, gamutSource, outOfGamut } = state;

const isFavorite = computed(() =>
    favoriteKeys.value.includes(canonicalKey(rgba.value)),
);

function parseKey(key: string): Rgba | null {
    const result = parseColor(key);

    return result.ok ? result.color.rgba : null;
}

function colorsFromKeys(keys: string[]): Rgba[] {
    return keys.map(parseKey).filter((color): color is Rgba => color !== null);
}

const historyColors = computed(() => colorsFromKeys(historyKeys.value));
const favoriteColors = computed(() => colorsFromKeys(favoriteKeys.value));

function applyColor(color: Rgba): void {
    state.setColor(color);
    state.finalize();
}

function applyPicked(hex: string): void {
    const color = opaqueColor(hex);

    if (color === null) {
        return;
    }

    state.setColor(color);
    state.finalize();
}

function toggleFavorite(): void {
    favoriteKeys.value = isFavorite.value
        ? library.removeFavorite(rgba.value)
        : library.addFavorite(rgba.value);
}

function clearHistory(): void {
    historyKeys.value = library.clearHistory();
}

function clearFavorites(): void {
    favoriteKeys.value = library.clearFavorites();
}

onMounted(() => {
    const last = library.readLast();

    if (last !== null) {
        state.setColor(last);
    }

    historyKeys.value = library.readHistory();
    favoriteKeys.value = library.readFavorites();
});
</script>

<template>
  <div class="flex flex-col gap-6">
    <div
      class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start"
    >
      <div class="flex min-w-0 flex-col gap-6">
        <section
          class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4"
        >
          <ColorPreview
            :rgba="rgba"
            :out-of-gamut="outOfGamut"
            :gamut-source="gamutSource"
            @picked="applyPicked"
          />

          <ColorInputField
            :model-value="text"
            :error="error"
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
        </section>

        <FormatsList
          :rgba="rgba"
          :copy="copyFeedback.copy"
        />
      </div>

      <div class="min-w-0">
        <Tabs default-value="contrast">
          <TabsList class="grid w-full grid-cols-3">
            <TabsTrigger value="contrast">
              {{ t('tools.html-color-picker.tabs.contrast') }}
            </TabsTrigger>
            <TabsTrigger value="shades">
              {{ t('tools.html-color-picker.tabs.shades') }}
            </TabsTrigger>
            <TabsTrigger value="library">
              {{ t('tools.html-color-picker.tabs.library') }}
            </TabsTrigger>
          </TabsList>

          <TabsContent
            value="contrast"
            class="mt-3"
          >
            <ContrastPanel :rgba="rgba" />
          </TabsContent>

          <TabsContent
            value="shades"
            class="mt-3"
          >
            <ShadesTintsPanel
              :rgba="rgba"
              @apply="applyColor"
            />
          </TabsContent>

          <TabsContent
            value="library"
            class="mt-3 flex flex-col gap-4"
          >
            <FavoritesPanel
              :colors="favoriteColors"
              :is-favorite="isFavorite"
              @apply="applyColor"
              @toggle="toggleFavorite"
              @clear="clearFavorites"
            />
            <HistoryPanel
              :colors="historyColors"
              @apply="applyColor"
              @clear="clearHistory"
            />
          </TabsContent>
        </Tabs>
      </div>
    </div>

    <p
      class="min-h-5 text-sm text-muted-foreground"
      role="status"
      aria-live="polite"
      data-test="picker-feedback"
    >
      {{ feedback }}
    </p>
  </div>
</template>
