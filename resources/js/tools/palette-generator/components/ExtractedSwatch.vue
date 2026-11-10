<script setup lang="ts">
import { Pipette } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CopyStatusIcon from '@/components/colors/CopyStatusIcon.vue';
import { checkerboardStyle, cssColor } from '@/components/colors/lib/style';
import { Button } from '@/components/ui/button';
import { useCopyConfirmation } from '@/composables/useCopyFeedback';
import type { ExtractedColor } from '../lib/extract';

/**
 * Uma cor dominante da imagem: o quadrado, o HEX, o percentual e as acoes.
 *
 * O percentual e o peso real (pixels visiveis atribuidos a cor), nao uma
 * estimativa; a barra abaixo do quadrado repete a informacao em tamanho.
 */

const props = defineProps<{
    color: ExtractedColor;
    index: number;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const emit = defineEmits<{ use: [] }>();

const { t } = useI18n();
const { copied, confirm } = useCopyConfirmation();

const colorStyle = computed(() => ({ backgroundColor: cssColor(props.color.rgba) }));
const percentage = computed(() =>
    t('tools.palette-generator.extraction.percentage', {
        value: props.color.percentage,
    }),
);
const label = computed(() => `${props.color.hex} (${percentage.value})`);

async function onCopy(): Promise<void> {
    const ok = await props.copy(
        props.color.hex,
        t('tools.palette-generator.extraction.copied', {
            value: props.color.hex,
        }),
    );

    if (ok) {
        confirm();
    }
}
</script>

<template>
  <article
    class="flex min-w-0 flex-col gap-2 rounded-lg border border-border bg-card p-2"
    :data-test="`extracted-swatch-${index}`"
  >
    <div
      class="relative h-12 w-full overflow-hidden rounded-md border"
      :style="checkerboardStyle"
      :title="label"
      :aria-label="label"
      role="img"
    >
      <span
        class="absolute inset-0"
        :style="colorStyle"
      />
    </div>

    <div class="flex flex-wrap items-baseline justify-between gap-1">
      <code class="font-mono text-xs">{{ color.hex }}</code>
      <span
        class="text-[10px] text-muted-foreground tabular-nums"
        :data-test="`extracted-percentage-${index}`"
      >
        {{ percentage }}
      </span>
    </div>

    <div class="flex flex-wrap gap-1">
      <Button
        type="button"
        size="sm"
        variant="outline"
        :aria-label="`${t('tools.palette-generator.extraction.copy')}: ${color.hex}`"
        :data-test="`extracted-copy-${index}`"
        @click="onCopy"
      >
        <CopyStatusIcon :copied="copied" />
        <span class="sr-only">
          {{ t('tools.palette-generator.extraction.copy') }}
        </span>
      </Button>
      <Button
        type="button"
        size="sm"
        variant="ghost"
        :aria-label="`${t('tools.palette-generator.extraction.use_as_base')}: ${color.hex}`"
        :data-test="`extracted-use-${index}`"
        @click="emit('use')"
      >
        <Pipette aria-hidden="true" />
        <span class="sr-only">
          {{ t('tools.palette-generator.extraction.use_as_base') }}
        </span>
      </Button>
    </div>
  </article>
</template>
