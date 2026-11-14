<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    downloadPaletteImagePng,
    downloadPaletteImageSvg,
    paletteImagePreview,
} from './exportImage';
import {
    PALETTE_IMAGE_LAYOUTS,
    PALETTE_IMAGE_SIZES,
    type PaletteImageLayout,
} from './types';

/**
 * Imagem tipografica da paleta (F6, ADR-138).
 *
 * A previa e o proprio SVG do export, exibido como `data:` - o mesmo texto que
 * o botao de SVG entrega e que o PNG rasteriza. Nada sai do navegador: a
 * fonte viaja embutida e a marca do devtools e fixa no rodape.
 */

const props = defineProps<{
    name: string;
    colors: string[];
}>();

const { t } = useI18n();

const layout = ref<PaletteImageLayout>('horizontal');
const busy = ref(false);
const pngFailed = ref(false);

const source = computed(() => ({
    name: props.name,
    colors: props.colors,
}));

const preview = computed(() => {
    const svg = paletteImagePreview(source.value, layout.value);

    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
});

const size = computed(() => PALETTE_IMAGE_SIZES[layout.value]);

function selectLayout(next: PaletteImageLayout): void {
    layout.value = next;
    pngFailed.value = false;
}

async function downloadSvg(): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        await downloadPaletteImageSvg(source.value, layout.value);
    } finally {
        busy.value = false;
    }
}

async function downloadPng(scale: 1 | 2): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    pngFailed.value = false;

    try {
        await downloadPaletteImagePng(source.value, layout.value, scale);
    } catch {
        pngFailed.value = true;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
  <section
    class="flex flex-col gap-4"
    data-test="palette-image-panel"
  >
    <div>
      <h2 class="text-sm font-semibold">
        {{ t('colors.image.title') }}
      </h2>
      <p class="mt-1 text-xs text-muted-foreground">
        {{ t('colors.image.description') }}
      </p>
    </div>

    <div class="flex flex-col gap-2">
      <span class="text-xs font-medium">
        {{ t('colors.image.layout_label') }}
      </span>
      <div
        class="flex flex-wrap gap-2"
        role="group"
        :aria-label="t('colors.image.layout_label')"
      >
        <Button
          v-for="option in PALETTE_IMAGE_LAYOUTS"
          :key="option"
          type="button"
          size="sm"
          :variant="layout === option ? 'secondary' : 'outline'"
          :aria-pressed="layout === option"
          :data-test="`image-layout-${option}`"
          @click="selectLayout(option)"
        >
          {{ t(`colors.image.layout_${option}`) }}
        </Button>
      </div>
      <p
        class="text-xs text-muted-foreground"
        data-test="palette-image-size"
      >
        {{ t(`colors.image.size_${layout}`) }} ·
        {{ t('colors.image.note') }}
      </p>
    </div>

    <img
      :src="preview"
      :alt="t('colors.image.preview_alt', { name, layout: t(`colors.image.layout_${layout}`) })"
      :width="size.width"
      :height="size.height"
      class="w-full rounded-lg border border-border bg-white"
      data-test="palette-image-preview"
    >

    <div class="flex flex-wrap items-center gap-2">
      <Button
        type="button"
        :disabled="busy"
        data-test="palette-image-download-svg"
        @click="downloadSvg"
      >
        {{ t('colors.image.download_svg') }}
      </Button>
      <Button
        type="button"
        variant="outline"
        :disabled="busy"
        data-test="palette-image-download-png"
        @click="downloadPng(1)"
      >
        {{ t('colors.image.download_png') }}
      </Button>
      <Button
        type="button"
        variant="outline"
        :disabled="busy"
        data-test="palette-image-download-png-2x"
        @click="downloadPng(2)"
      >
        {{ t('colors.image.download_png_2x') }}
      </Button>
    </div>

    <p
      v-if="pngFailed"
      class="text-xs text-destructive"
      role="alert"
      data-test="palette-image-error"
    >
      {{ t('colors.image.png_error') }}
    </p>
  </section>
</template>
