<script setup lang="ts">
import { Copy } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import type { PaletteScale } from '@/lib/color';
import ToneCard from './ToneCard.vue';

/**
 * A grade dos 11 tons, na ordem de `PALETTE_TONES`.
 *
 * Quatro colunas no maximo: o cartao carrega HEX e duas linhas de contraste, e
 * onze colunas nao caberiam nem dariam para ler. O rodape diz a versao do
 * algoritmo e, quando houve corte de croma, quais tons foram cortados.
 */

const props = defineProps<{
    scale: PaletteScale;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const { t } = useI18n();

const scaleText = computed(() =>
    props.scale.tones.map((tone) => `${tone.tone}: ${tone.hex}`).join('\n'),
);

const clippedText = computed(() => props.scale.clippedTones.join(', '));

async function onCopyScale(): Promise<void> {
    await props.copy(
        scaleText.value,
        t('tools.palette-generator.scale.copied_scale', {
            count: props.scale.tones.length,
        }),
    );
}
</script>

<template>
  <section
    class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4"
    data-test="tone-scale"
  >
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h2 class="text-sm font-semibold">
          {{ t('tools.palette-generator.scale.title') }}
        </h2>
        <p class="mt-1 text-xs text-muted-foreground">
          {{ t('tools.palette-generator.scale.description') }}
        </p>
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        data-test="scale-copy"
        @click="onCopyScale"
      >
        <Copy aria-hidden="true" />
        {{ t('tools.palette-generator.scale.copy_scale') }}
      </Button>
    </div>

    <div
      class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"
      data-test="tone-grid"
    >
      <ToneCard
        v-for="tone in scale.tones"
        :key="tone.tone"
        :tone="tone"
        :nearest="tone.tone === scale.nearestTone"
        :copy="copy"
      />
    </div>

    <div class="flex flex-col gap-1 text-xs text-muted-foreground">
      <p data-test="scale-version">
        {{
          t('tools.palette-generator.scale.algorithm_version', {
            version: scale.algorithmVersion,
          })
        }}
      </p>
      <p
        v-if="scale.clippedTones.length > 0"
        data-test="scale-clipped"
      >
        {{
          t('tools.palette-generator.scale.clipped_summary', {
            tones: clippedText,
          })
        }}
      </p>
    </div>
  </section>
</template>
