<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    clampPixel,
    fitImageSize,
    pixelStep,
    pointToPixel,
} from '../lib/imageSampler';
import type { Point, Size } from '../lib/imageSampler';
import { formatHex, type Rgba } from '@/lib/color';

/**
 * Amostrador por imagem - o fallback de quem nao tem `EyeDropper`.
 *
 * A imagem nunca sai do navegador: e lida num canvas local (no maximo 1024 px
 * para amostragem), ampliada 8x numa lupa e nada e enviado a lugar nenhum.
 * Ponteiro para escolher, setas para andar de pixel em pixel, Enter aplica.
 */

const emit = defineEmits<{
    picked: [hex: string];
}>();

const { t } = useI18n();

const MAX_SAMPLE = 1024;
const MAX_DISPLAY = { width: 360, height: 280 };
const MAGNIFIER_SIZE = 96;
const MAGNIFIER_SOURCE = 12;

const fileInput = ref<HTMLInputElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const magnifier = ref<HTMLCanvasElement | null>(null);

const loaded = ref(false);
const error = ref(false);
const pixel = ref<Point>({ x: 0, y: 0 });
const hovered = ref<Rgba | null>(null);
const sampleSize = ref<Size>({ width: 0, height: 0 });
const displaySize = ref<Size>({ width: 0, height: 0 });

let sampleSource: HTMLCanvasElement | null = null;
let objectUrl: string | null = null;

function releaseUrl(): void {
    if (objectUrl !== null) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
}

function onFile(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file === undefined) {
        return;
    }

    releaseUrl();
    error.value = false;
    loaded.value = false;
    hovered.value = null;
    objectUrl = URL.createObjectURL(file);

    const image = new Image();

    image.onload = () => {
        prepare(image);
    };
    image.onerror = () => {
        error.value = true;
    };
    image.src = objectUrl;
}

function prepare(image: HTMLImageElement): void {
    const natural = {
        width: image.naturalWidth,
        height: image.naturalHeight,
    };
    const sampled = fitImageSize(natural, {
        width: MAX_SAMPLE,
        height: MAX_SAMPLE,
    });
    const offscreen = document.createElement('canvas');

    offscreen.width = sampled.width;
    offscreen.height = sampled.height;

    const context = offscreen.getContext('2d', { willReadFrequently: true });

    if (context === null || sampled.width === 0 || sampled.height === 0) {
        error.value = true;

        return;
    }

    context.drawImage(image, 0, 0, sampled.width, sampled.height);
    sampleSource = offscreen;
    sampleSize.value = sampled;
    displaySize.value = fitImageSize(natural, MAX_DISPLAY);
    loaded.value = true;
    pixel.value = {
        x: Math.floor(sampled.width / 2),
        y: Math.floor(sampled.height / 2),
    };

    void nextTick(() => {
        drawDisplay();
        samplePixel();
    });
}

function drawDisplay(): void {
    const element = canvas.value;

    if (element === null || sampleSource === null) {
        return;
    }

    element.width = displaySize.value.width;
    element.height = displaySize.value.height;

    const context = element.getContext('2d');

    if (context === null) {
        return;
    }

    context.imageSmoothingEnabled = false;
    context.drawImage(
        sampleSource,
        0,
        0,
        displaySize.value.width,
        displaySize.value.height,
    );
}

function samplePixel(): void {
    if (sampleSource === null) {
        return;
    }

    const context = sampleSource.getContext('2d', {
        willReadFrequently: true,
    });

    if (context === null) {
        return;
    }

    const data = context.getImageData(pixel.value.x, pixel.value.y, 1, 1).data;

    hovered.value = {
        r: data[0] ?? 0,
        g: data[1] ?? 0,
        b: data[2] ?? 0,
        a: (data[3] ?? 255) / 255,
    };

    drawMagnifier();
}

function drawMagnifier(): void {
    const element = magnifier.value;

    if (element === null || sampleSource === null) {
        return;
    }

    element.width = MAGNIFIER_SIZE;
    element.height = MAGNIFIER_SIZE;

    const context = element.getContext('2d');

    if (context === null) {
        return;
    }

    const sourceWidth = Math.min(MAGNIFIER_SOURCE, sampleSize.value.width);
    const sourceHeight = Math.min(MAGNIFIER_SOURCE, sampleSize.value.height);
    const sourceX = Math.min(
        Math.max(0, pixel.value.x - Math.floor(sourceWidth / 2)),
        sampleSize.value.width - sourceWidth,
    );
    const sourceY = Math.min(
        Math.max(0, pixel.value.y - Math.floor(sourceHeight / 2)),
        sampleSize.value.height - sourceHeight,
    );
    const scale = MAGNIFIER_SIZE / Math.max(sourceWidth, sourceHeight, 1);

    context.imageSmoothingEnabled = false;
    context.clearRect(0, 0, MAGNIFIER_SIZE, MAGNIFIER_SIZE);
    context.drawImage(
        sampleSource,
        sourceX,
        sourceY,
        sourceWidth,
        sourceHeight,
        0,
        0,
        sourceWidth * scale,
        sourceHeight * scale,
    );
}

function pickAt(event: PointerEvent): void {
    const element = canvas.value;

    if (element === null || sampleSource === null) {
        return;
    }

    const rect = element.getBoundingClientRect();

    pixel.value = pointToPixel(
        { x: event.clientX, y: event.clientY },
        {
            left: rect.left,
            top: rect.top,
            width: rect.width,
            height: rect.height,
        },
        sampleSize.value,
    );
    samplePixel();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        apply();

        return;
    }

    const step = pixelStep(event.key, event.shiftKey);

    if (step === null) {
        return;
    }

    event.preventDefault();
    pixel.value = clampPixel(
        {
            x: pixel.value.x + step.x,
            y: pixel.value.y + step.y,
        },
        sampleSize.value,
    );
    samplePixel();
}

function apply(): void {
    if (hovered.value === null) {
        return;
    }

    emit('picked', formatHex(hovered.value));
}

onBeforeUnmount(() => {
    releaseUrl();
});
</script>

<template>
  <div class="flex flex-col gap-3">
    <p class="text-sm text-muted-foreground">
      {{ t('tools.html-color-picker.image.description') }}
    </p>

    <input
      ref="fileInput"
      type="file"
      accept="image/*"
      :aria-label="t('tools.html-color-picker.image.choose')"
      class="block w-full text-sm text-muted-foreground file:mr-3 file:cursor-pointer file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-foreground hover:file:bg-accent"
      data-test="image-input"
      @change="onFile"
    >

    <p
      v-if="error"
      class="text-sm text-destructive"
      role="alert"
    >
      {{ t('tools.html-color-picker.image.error') }}
    </p>

    <div
      v-if="loaded"
      class="flex flex-col gap-3 sm:flex-row sm:items-start"
    >
      <div class="min-w-0">
        <canvas
          ref="canvas"
          tabindex="0"
          role="application"
          :aria-label="
            t('tools.html-color-picker.image.instructions')
          "
          class="max-w-full cursor-crosshair rounded-md border outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          data-test="image-canvas"
          @pointermove="pickAt"
          @pointerdown="pickAt"
          @keydown="onKeydown"
        />
        <p class="mt-1 text-xs text-muted-foreground">
          {{ t('tools.html-color-picker.image.instructions') }}
        </p>
      </div>

      <div class="flex shrink-0 flex-col items-center gap-2">
        <canvas
          ref="magnifier"
          width="96"
          height="96"
          aria-hidden="true"
          class="size-24 rounded-md border"
        />
        <p
          v-if="hovered !== null"
          class="font-mono text-xs text-muted-foreground"
        >
          {{ formatHex(hovered) }}
        </p>
        <Button
          type="button"
          size="sm"
          :disabled="hovered === null"
          @click="apply"
        >
          {{ t('tools.html-color-picker.image.apply') }}
        </Button>
      </div>
    </div>
  </div>
</template>
