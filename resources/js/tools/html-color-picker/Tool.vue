<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AlphaSlider from '@/components/colors/AlphaSlider.vue';
import ChannelSliders from '@/components/colors/ChannelSliders.vue';
import ColorArea from '@/components/colors/ColorArea.vue';
import ColorInputField from '@/components/colors/ColorInputField.vue';
import FavoritesPanel from '@/components/colors/FavoritesPanel.vue';
import FormatsList from '@/components/colors/FormatsList.vue';
import HistoryPanel from '@/components/colors/HistoryPanel.vue';
import HueSlider from '@/components/colors/HueSlider.vue';
import { opaqueColor } from '@/components/colors/lib/applyHex';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useColorState } from '@/composables/useColorState';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import type { Rgba } from '@/lib/color';
import { useColorLibrary } from '@/lib/colors/library';
import type { ColorLibraryToolProps } from '@/types/colors';
import ColorPreview from './components/ColorPreview.vue';
import ContrastPanel from './components/ContrastPanel.vue';
import ShadesTintsPanel from './components/ShadesTintsPanel.vue';

/**
 * HTML Color Picker (`/{locale}/tools/colors/html-color-picker`).
 *
 * Uma unica porta de escrita (`useColorState`) alimenta a area 2D, os sliders,
 * o campo de texto e a previa; o historico so recebe cores finalizadas
 * (pointerup/blur/Enter), nunca cada quadro do arraste. O anonimo guarda
 * historico e favoritos no `localStorage`; quem esta logado usa a biblioteca
 * da conta, compartilhada com o gerador (F5, ADR-127).
 */

const DEFAULT_COLOR: Rgba = { r: 124, g: 58, b: 237, a: 1 };

const props = defineProps<{
    /** Biblioteca entregue pelo servidor via `ToolProps` (F5). */
    library?: ColorLibraryToolProps;
}>();

const { t } = useI18n();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;

const {
    historyColors,
    favoriteColors,
    importable,
    loadingMore,
    hasMoreHistory,
    hasMoreFavorites,
    load,
    loadMoreHistory,
    loadMoreFavorites,
    addHistory,
    isFavorite: isLibraryFavorite,
    toggleFavorite,
    clearHistory,
    clearFavorites,
    importLocalHistory,
    saveLast,
    readLast,
} = useColorLibrary(props.library);

const importing = ref(false);

const state = useColorState({
    initial: DEFAULT_COLOR,
    onFinalize: (color) => {
        addHistory(color);
        saveLast(color);
    },
});

const { rgba, hsv, hsl, text, error, gamutSource, outOfGamut } = state;

const isFavorite = computed(() => isLibraryFavorite(rgba.value));

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

function toggleCurrentFavorite(): void {
    toggleFavorite(rgba.value);
}

async function importHistory(): Promise<void> {
    if (importing.value) {
        return;
    }

    importing.value = true;

    try {
        const count = await importLocalHistory();

        feedback.value = t('tools.shared.library.import.success', { count });
    } catch {
        feedback.value = t('tools.shared.library.import.error');
    } finally {
        importing.value = false;
    }
}

onMounted(async () => {
    const last = readLast();

    if (last !== null) {
        state.setColor(last);
    }

    await load();
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
              :authenticated="props.library?.authenticated === true"
              :more="hasMoreFavorites"
              :loading-more="loadingMore"
              @apply="applyColor"
              @toggle="toggleCurrentFavorite"
              @clear="clearFavorites"
              @more="loadMoreFavorites"
            />
            <HistoryPanel
              :colors="historyColors"
              :authenticated="props.library?.authenticated === true"
              :importable="importable"
              :importing="importing"
              :more="hasMoreHistory"
              :loading-more="loadingMore"
              @apply="applyColor"
              @clear="clearHistory"
              @import="importHistory"
              @more="loadMoreHistory"
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
