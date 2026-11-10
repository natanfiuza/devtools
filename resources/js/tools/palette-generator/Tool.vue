<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ColorPickerControls from '@/components/colors/ColorPickerControls.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useColorState } from '@/composables/useColorState';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import BaseColorRow from './components/BaseColorRow.vue';
import EmptyPalette from './components/EmptyPalette.vue';
import ExportPanel from './components/ExportPanel.vue';
import ExtractionPanel from './components/ExtractionPanel.vue';
import HarmonyPanel from './components/HarmonyPanel.vue';
import ToneScale from './components/ToneScale.vue';
import type { ExtractedPalette } from './lib/extractedPalette';
import type { ImageLimits } from './lib/imageLimits';

/**
 * Color Palette Generator (`/{locale}/tools/colors/palette-generator`).
 *
 * Uma cor base, uma escala de 11 tons em OKLCH e as saidas em CSS, SCSS, JSON
 * e Tailwind; na F3, as harmonias (ADR-121) e a extracao de cores dominantes
 * de imagem (ADR-122) entram como abas auxiliares. O picker compacto e o
 * mesmo estado de cor do HTML Color Picker e fica sempre visivel: clicar numa
 * cor de harmonia ou de imagem troca a base e a escala acompanha.
 *
 * A paleta extraida mora aqui, so na memoria da pagina: trocar de aba ou de
 * base nao a perde, recarregar perde. Nada e persistido nesta fase.
 */

defineProps<{
    /** Tetos da importacao de imagem, entregues pelo servidor (ADR-122). */
    image: ImageLimits;
}>();

type PageTab = 'scale' | 'harmonies' | 'extraction';

const DEFAULT_BASE = '#6366f1';

const { t } = useI18n();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;

const activeTab = ref<PageTab>('scale');
const extracted = ref<ExtractedPalette | null>(null);

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

/**
 * Uma unica porta de escrita da base: a mesma do picker. Finaliza para o
 * historico do picker registrar a troca, como se fosse digitada.
 */
function useBase(color: Rgba): void {
    state.setColor(color);
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
        <Tabs
          v-model="activeTab"
          data-test="page-tabs"
        >
          <TabsList class="grid w-full grid-cols-3">
            <TabsTrigger
              value="scale"
              data-test="page-tab-scale"
            >
              {{ t('tools.palette-generator.tabs.scale') }}
            </TabsTrigger>
            <TabsTrigger
              value="harmonies"
              data-test="page-tab-harmonies"
            >
              {{ t('tools.palette-generator.tabs.harmonies') }}
            </TabsTrigger>
            <TabsTrigger
              value="extraction"
              data-test="page-tab-extraction"
            >
              {{ t('tools.palette-generator.tabs.extraction') }}
            </TabsTrigger>
          </TabsList>

          <TabsContent
            value="scale"
            class="mt-3 flex flex-col gap-6"
          >
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
          </TabsContent>

          <TabsContent
            value="harmonies"
            class="mt-3 flex flex-col gap-6"
          >
            <EmptyPalette
              v-if="isEmpty"
              @restore="restoreExample"
            />

            <template v-else>
              <p
                v-if="showStale"
                class="rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground"
                role="status"
                data-test="harmonies-stale"
              >
                {{ t('tools.palette-generator.state.stale') }}
              </p>

              <HarmonyPanel
                :base="state.rgba.value"
                :copy="copyFeedback.copy"
                @use-base="useBase"
              />
            </template>
          </TabsContent>

          <TabsContent
            value="extraction"
            class="mt-3"
          >
            <ExtractionPanel
              v-model="extracted"
              :image="image"
              :copy="copyFeedback.copy"
              @use-base="useBase"
            />
          </TabsContent>
        </Tabs>
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
