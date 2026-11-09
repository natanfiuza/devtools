<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ColorPickerControls from '@/components/colors/ColorPickerControls.vue';
import { useColorState } from '@/composables/useColorState';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import BaseColorRow from './components/BaseColorRow.vue';
import EmptyPalette from './components/EmptyPalette.vue';
import ExportPanel from './components/ExportPanel.vue';
import ToneScale from './components/ToneScale.vue';

/**
 * Color Palette Generator (`/{locale}/tools/colors/palette-generator`).
 *
 * Uma cor base, uma escala de 11 tons em OKLCH e as saidas em CSS, SCSS, JSON
 * e Tailwind. O picker compacto e o mesmo estado de cor do HTML Color Picker;
 * a escala e derivada (`computed`), nunca guardada. Tudo roda no navegador e
 * nada e persistido nesta fase.
 */

const DEFAULT_BASE = '#6366f1';

const { t } = useI18n();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;

function exampleColor(): Rgba {
    const parsed = parseColor(DEFAULT_BASE);

    if (!parsed.ok) {
        throw new Error('cor de exemplo invalida');
    }

    return parsed.color.rgba;
}

const state = useColorState({ initial: exampleColor() });

const scale = computed(() => buildPaletteScale(state.rgba.value));
const isEmpty = computed(() => state.text.value.trim() === '');
const showStale = computed(
    () => !isEmpty.value && state.error.value !== null,
);

function restoreExample(): void {
    state.setColor(exampleColor());
    state.finalize();
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)] lg:items-start">
      <aside class="min-w-0 lg:sticky lg:top-6">
        <section
          class="flex flex-col gap-4 rounded-xl border border-border bg-surface p-4"
        >
          <h2 class="text-sm font-semibold">
            {{ t('tools.palette-generator.base.title') }}
          </h2>
          <ColorPickerControls :state="state" />
        </section>
      </aside>

      <div class="flex min-w-0 flex-col gap-6">
        <EmptyPalette
          v-if="isEmpty"
          @restore="restoreExample"
        />

        <template v-else>
          <p
            v-if="showStale"
            class="rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground"
            role="status"
            data-test="palette-stale"
          >
            {{ t('tools.palette-generator.state.stale') }}
          </p>

          <BaseColorRow
            :scale="scale"
            :copy="copyFeedback.copy"
          />

          <ToneScale
            :scale="scale"
            :copy="copyFeedback.copy"
          />

          <ExportPanel
            :scale="scale"
            :copy="copyFeedback.copy"
          />
        </template>
      </div>
    </div>

    <p
      class="min-h-5 text-sm text-muted-foreground"
      role="status"
      aria-live="polite"
      data-test="palette-feedback"
    >
      {{ feedback }}
    </p>
  </div>
</template>
