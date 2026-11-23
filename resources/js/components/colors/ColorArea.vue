<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { clamp, hsvToRgb, type Hsv } from '@/lib/color';
import { cssColor, hueBackground } from './lib/style';

/**
 * Area 2D de saturacao x brilho.
 *
 * O fundo e o matiz puro; a camada branca horizontal tira a saturacao e a
 * preta vertical tira o brilho - o mesmo desenho dos seletores de imagem.
 * Ponteiro capturado durante o arraste; teclado move um ponto por seta (10 com
 * Shift). `finalize` so sai no pointerup/tecla, para o historico nao encher de
 * estados intermediarios.
 */

const props = defineProps<{
    hsv: Hsv;
}>();

const emit = defineEmits<{
    change: [saturation: number, value: number];
    finalize: [];
}>();

const { t } = useI18n();

const area = ref<HTMLElement | null>(null);
const dragging = ref(false);

const thumbColor = computed(() => {
    const rgb = hsvToRgb({
        h: props.hsv.h,
        s: props.hsv.s,
        v: props.hsv.v,
    });

    return cssColor({ ...rgb, a: 1 });
});

const valueText = computed(
    () => `${Math.round(props.hsv.s)}% · ${Math.round(props.hsv.v)}%`,
);

function applyFromPointer(event: PointerEvent): void {
    const element = area.value;

    if (element === null) {
        return;
    }

    const rect = element.getBoundingClientRect();

    if (rect.width === 0 || rect.height === 0) {
        return;
    }

    const saturation = clamp((event.clientX - rect.left) / rect.width, 0, 1);
    const value = clamp(1 - (event.clientY - rect.top) / rect.height, 0, 1);

    emit('change', saturation * 100, value * 100);
}

function onPointerDown(event: PointerEvent): void {
    const element = event.currentTarget as HTMLElement;

    element.setPointerCapture(event.pointerId);
    dragging.value = true;
    applyFromPointer(event);
}

function onPointerMove(event: PointerEvent): void {
    if (dragging.value) {
        applyFromPointer(event);
    }
}

function onPointerEnd(event: PointerEvent): void {
    if (!dragging.value) {
        return;
    }

    applyFromPointer(event);
    dragging.value = false;

    const element = event.currentTarget as HTMLElement;

    if (element.hasPointerCapture(event.pointerId)) {
        element.releasePointerCapture(event.pointerId);
    }

    emit('finalize');
}

function onKeydown(event: KeyboardEvent): void {
    const step = event.shiftKey ? 10 : 1;
    let saturation = props.hsv.s;
    let value = props.hsv.v;

    switch (event.key) {
        case 'ArrowLeft':
            saturation -= step;
            break;
        case 'ArrowRight':
            saturation += step;
            break;
        case 'ArrowUp':
            value += step;
            break;
        case 'ArrowDown':
            value -= step;
            break;
        default:
            return;
    }

    event.preventDefault();
    emit('change', clamp(saturation, 0, 100), clamp(value, 0, 100));
    emit('finalize');
}
</script>

<template>
  <div
    ref="area"
    role="slider"
    tabindex="0"
    :aria-label="t('tools.shared.color_picker.area.label')"
    :aria-valuetext="valueText"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-valuenow="Math.round(hsv.s)"
    class="relative h-56 w-full touch-none overflow-hidden rounded-lg border select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
    :style="{ backgroundColor: hueBackground(hsv.h) }"
    data-test="color-area"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerEnd"
    @pointercancel="onPointerEnd"
    @keydown="onKeydown"
  >
    <div
      class="pointer-events-none absolute inset-0"
      style="
                background-image: linear-gradient(
                    to right,
                    #ffffff,
                    rgba(255, 255, 255, 0)
                );
            "
    />
    <div
      class="pointer-events-none absolute inset-0"
      style="
                background-image: linear-gradient(
                    to top,
                    #000000,
                    rgba(0, 0, 0, 0)
                );
            "
    />
    <div
      class="pointer-events-none absolute size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-md"
      :style="{
        left: `${hsv.s}%`,
        top: `${100 - hsv.v}%`,
        backgroundColor: thumbColor,
      }"
      aria-hidden="true"
    />
  </div>
  <p class="sr-only">
    {{ t('tools.shared.color_picker.area.hint') }}
  </p>
</template>
