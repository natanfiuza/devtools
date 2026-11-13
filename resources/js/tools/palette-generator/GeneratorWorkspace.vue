<script setup lang="ts">
import { computed, onMounted, ref, useSlots, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ColorPickerControls from '@/components/colors/ColorPickerControls.vue';
import FavoritesPanel from '@/components/colors/FavoritesPanel.vue';
import HistoryPanel from '@/components/colors/HistoryPanel.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useColorState } from '@/composables/useColorState';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import {
    buildPaletteScale,
    formatHex,
    formatHex8,
    parseColor,
    type PaletteScale,
    type Rgba,
} from '@/lib/color';
import { useColorLibrary } from '@/lib/colors/library';
import type { WorkspaceSnapshot } from '@/lib/paletteSave';
import type {
    DraftExtractedPalette,
    PaletteDraft,
    PaletteDraftTab,
} from '@/lib/saveDraft';
import type { ColorLibraryToolProps, PaletteKind } from '@/types/colors';
import BaseColorRow from './components/BaseColorRow.vue';
import EmptyPalette from './components/EmptyPalette.vue';
import ExportPanel from './components/ExportPanel.vue';
import ExtractionPanel from './components/ExtractionPanel.vue';
import HarmonyPanel from './components/HarmonyPanel.vue';
import ToneScale from './components/ToneScale.vue';
import type { ExtractedColor, ExtractionCount } from './lib/extractContract';
import type { ExtractedPalette } from './lib/extractedPalette';
import type { ImageLimits } from './lib/imageLimits';

/**
 * O corpo do Color Palette Generator (F2/F3), reutilizado pelo editor (F4).
 *
 * Uma cor base, a escala de 11 tons, harmonias e extracao de imagem. A pagina
 * da tool usa o workspace como esta; o editor passa a escala/extraida salvos e
 * usa os slots `tabs-extra`/`panels-extra` para acrescentar a aba Prototipo
 * sem duplicar um segundo gerador.
 *
 * A escala salva e congelada: vem dos HEX persistidos (`buildFrozenScale`, do
 * lado de quem chama) e a primeira mudanca de base descongela e recalcula.
 * Quem salva a paleta e a pagina; daqui so sai a biblioteca de cores da F5
 * (historico/favoritos), que e a mesma do picker e usa a conta so quando ha
 * sessao - o anonimo continua no `localStorage`.
 */

const props = defineProps<{
    /** Tetos da importacao de imagem, entregues pelo servidor (ADR-122). */
    image: ImageLimits;
    /** Base inicial; o exemplo e usado quando ausente ou invalido. */
    initialBase?: string;
    /** Escala salva, exibida sem recalcular ate a base mudar. */
    initialScale?: PaletteScale | null;
    /** Extracao restaurada de uma paleta salva ou de um rascunho. */
    initialExtracted?: ExtractedPalette | null;
    /** Aba inicial; aceita a aba extra do editor (`prototype`). */
    initialTab?: string;
    /** Biblioteca da conta (F5); ausente = anonimo no `localStorage`. */
    library?: ColorLibraryToolProps;
}>();

const slots = useSlots();

type SaveTab = PaletteDraftTab;

const DEFAULT_BASE = '#6366f1';

const { t } = useI18n();
const copyFeedback = useCopyFeedback();
const feedback = copyFeedback.message;

const activeTab = ref<string>(props.initialTab ?? 'scale');
const extracted = ref<ExtractedPalette | null>(props.initialExtracted ?? null);
const frozen = ref<PaletteScale | null>(props.initialScale ?? null);

function rgbaOf(input: string): Rgba | null {
    const parsed = parseColor(input);

    return parsed.ok ? parsed.color.rgba : null;
}

function exampleColor(): Rgba {
    const parsed = parseColor(DEFAULT_BASE);

    if (!parsed.ok) {
        throw new Error('cor de exemplo invalida');
    }

    return parsed.color.rgba;
}

const {
    historyColors,
    favoriteColors,
    importable,
    loadingMore,
    hasMoreHistory,
    hasMoreFavorites,
    load: loadLibrary,
    loadMoreHistory,
    loadMoreFavorites,
    addHistory,
    isFavorite: isLibraryFavorite,
    toggleFavorite,
    clearHistory,
    clearFavorites,
    importLocalHistory,
} = useColorLibrary(props.library);

const importing = ref(false);

const state = useColorState({
    initial: rgbaOf(props.initialBase ?? '') ?? exampleColor(),
    // Toda base finalizada entra no historico compartilhado, como no picker.
    onFinalize: (color) => addHistory(color),
});

onMounted(() => {
    void loadLibrary();
});

const scale = computed(
    () => frozen.value ?? buildPaletteScale(state.rgba.value),
);
const isEmpty = computed(() => state.text.value.trim() === '');
const showStale = computed(() => !isEmpty.value && state.error.value !== null);
const hasExtraTabs = computed(() => slots['tabs-extra'] !== undefined);
const hasExtraPanels = computed(() => slots['panels-extra'] !== undefined);

// A escala salva deixa de valer na primeira mudanca da base: a partir dali o
// algoritmo atual e a fonte da verdade.
watch(state.rgba, () => {
    frozen.value = null;
});

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

function baseHex(): string {
    return state.rgba.value.a < 1
        ? formatHex8(state.rgba.value)
        : formatHex(state.rgba.value);
}

function draftTab(): SaveTab {
    return activeTab.value === 'harmonies' || activeTab.value === 'extraction'
        ? activeTab.value
        : 'scale';
}

/** Na extraida a base precisa estar entre as cores; sem escolha, a primeira. */
function extractedBase(palette: ExtractedPalette): string {
    const current = baseHex().toLowerCase();
    const belongs = palette.colors.some(
        (color) => color.hex.toLowerCase() === current,
    );

    return belongs ? baseHex() : (palette.colors[0]?.hex ?? baseHex());
}

function snapshot(): WorkspaceSnapshot {
    const table = extracted.value;
    const tab = draftTab();
    const kind: PaletteKind =
        tab === 'extraction' && table !== null ? 'extracted' : 'scale';

    return {
        kind,
        tab,
        isEmpty: isEmpty.value,
        base:
            kind === 'extracted' && table !== null
                ? extractedBase(table)
                : baseHex(),
        scale: scale.value,
        extracted: table,
    };
}

function draft(): Omit<PaletteDraft, 'version' | 'savedAt'> {
    const current = snapshot();

    return {
        kind: current.kind,
        base: current.base,
        tab: current.tab,
        extracted:
            current.kind === 'extracted' && current.extracted !== null
                ? serializeExtracted(current.extracted)
                : null,
    };
}

/** So o que redesenha a extracao; a imagem e a URL completa ficam de fora. */
function serializeExtracted(palette: ExtractedPalette): DraftExtractedPalette {
    return {
        colors: palette.colors.map((color) => ({
            hex: color.hex,
            weight: color.weight,
            percentage: color.percentage,
        })),
        requested: palette.requested,
        algorithmVersion: palette.algorithmVersion,
        sufficient: palette.sufficient,
        visiblePixels: palette.visiblePixels,
        source: palette.source,
    };
}

function toExtractedPalette(
    draft: DraftExtractedPalette,
): ExtractedPalette | null {
    const colors: ExtractedColor[] = [];

    for (const color of draft.colors) {
        const rgba = rgbaOf(color.hex);

        if (rgba === null) {
            return null;
        }

        colors.push({ ...color, rgba });
    }

    return {
        colors,
        requested: draft.requested as ExtractionCount,
        algorithmVersion: draft.algorithmVersion,
        sufficient: draft.sufficient,
        visiblePixels: draft.visiblePixels,
        source: draft.source,
    };
}

function restoreDraft(draft: PaletteDraft): void {
    const rgba = rgbaOf(draft.base);

    if (rgba !== null) {
        state.setColor(rgba);
        state.finalize();
    }

    activeTab.value = draft.tab;
    extracted.value =
        draft.extracted === null ? null : toExtractedPalette(draft.extracted);
    frozen.value = null;
}

/** Recado da pagina no mesmo lugar do "copiado". */
function notify(message: string): void {
    feedback.value = message;
}

const currentIsFavorite = computed(() => isLibraryFavorite(state.rgba.value));

function toggleCurrentFavorite(): void {
    toggleFavorite(state.rgba.value);
}

async function importHistory(): Promise<void> {
    if (importing.value) {
        return;
    }

    importing.value = true;

    try {
        const count = await importLocalHistory();

        notify(t('tools.shared.library.import.success', { count }));
    } catch {
        notify(t('tools.shared.library.import.error'));
    } finally {
        importing.value = false;
    }
}

defineExpose({
    snapshot,
    draft,
    restoreDraft,
    notify,
    isEmpty,
    activeTab,
    scale,
    extracted,
    rgba: state.rgba,
});
</script>

<template>
  <div class="flex flex-col gap-6">
    <div
      class="grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)] lg:items-start"
    >
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
          <TabsList
            :class="
              hasExtraTabs
                ? 'flex w-full flex-wrap'
                : 'grid w-full grid-cols-4'
            "
          >
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
            <TabsTrigger
              value="library"
              data-test="page-tab-library"
            >
              {{ t('tools.palette-generator.tabs.library') }}
            </TabsTrigger>
            <slot name="tabs-extra" />
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

          <TabsContent
            value="library"
            class="mt-3 flex flex-col gap-4"
          >
            <FavoritesPanel
              :colors="favoriteColors"
              :is-favorite="currentIsFavorite"
              :authenticated="props.library?.authenticated === true"
              :more="hasMoreFavorites"
              :loading-more="loadingMore"
              @apply="useBase"
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
              @apply="useBase"
              @clear="clearHistory"
              @import="importHistory"
              @more="loadMoreHistory"
            />
          </TabsContent>

          <slot
            v-if="hasExtraPanels"
            name="panels-extra"
          />
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
