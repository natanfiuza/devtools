<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CopyStatusIcon from '@/components/colors/CopyStatusIcon.vue';
import { checkerboardStyle, cssColor } from '@/components/colors/lib/style';
import { Button } from '@/components/ui/button';
import { useCopyConfirmation } from '@/composables/useCopyFeedback';
import type { ContrastVerdict, PaletteToneResult } from '@/lib/color';

/**
 * Um tom da escala: o codigo, o contraste e os selos.
 *
 * A cor nunca e o unico sinal: o tom tem numero, HEX, selo de "mais proximo",
 * selo de croma limitado e as duas linhas de contraste com o veredito escrito
 * (o `AA`/`AAA` visivel e o texto `passa`/`nao passa` para leitor de tela).
 */

const props = defineProps<{
    tone: PaletteToneResult;
    nearest: boolean;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const { t } = useI18n();
const { copied, confirm } = useCopyConfirmation();

interface ContrastLine {
    key: 'black' | 'white';
    label: string;
    verdict: ContrastVerdict;
}

function ratio(verdict: ContrastVerdict): string {
    return verdict.ratio.toFixed(2);
}

const lines = computed<ContrastLine[]>(() => [
    {
        key: 'black',
        label: t('tools.palette-generator.scale.contrast_black', {
            ratio: ratio(props.tone.contrast.black),
        }),
        verdict: props.tone.contrast.black,
    },
    {
        key: 'white',
        label: t('tools.palette-generator.scale.contrast_white', {
            ratio: ratio(props.tone.contrast.white),
        }),
        verdict: props.tone.contrast.white,
    },
]);

const copyLabel = computed(() =>
    t('tools.palette-generator.scale.tone_copy', { tone: props.tone.tone }),
);

async function onCopy(): Promise<void> {
    const ok = await props.copy(
        props.tone.hex,
        t('tools.palette-generator.scale.tone_copied', {
            value: props.tone.hex,
        }),
    );

    if (ok) {
        confirm();
    }
}
</script>

<template>
  <article
    class="flex flex-col gap-2 rounded-lg border border-border bg-card p-2"
    :data-test="`tone-card-${tone.tone}`"
  >
    <div class="flex items-center justify-between gap-1">
      <span class="font-mono text-xs font-medium tabular-nums">
        {{ tone.tone }}
      </span>
      <span
        v-if="nearest"
        class="rounded-full border px-1.5 py-0.5 text-[10px] leading-none text-muted-foreground"
        :data-test="`tone-nearest-${tone.tone}`"
      >
        {{ t('tools.palette-generator.scale.base_tone_badge') }}
      </span>
    </div>

    <div
      class="relative h-14 overflow-hidden rounded-md border shadow-xs"
      :style="checkerboardStyle"
      aria-hidden="true"
    >
      <div
        class="absolute inset-0"
        :style="{ backgroundColor: cssColor(tone.rgba) }"
      />
    </div>

    <div class="flex items-center gap-1">
      <code class="min-w-0 flex-1 truncate font-mono text-[11px] tabular-nums">
        {{ tone.hex }}
      </code>
      <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        :aria-label="copyLabel"
        :title="copyLabel"
        :data-test="`tone-copy-${tone.tone}`"
        @click="onCopy"
      >
        <CopyStatusIcon :copied="copied" />
      </Button>
    </div>

    <p
      v-if="tone.clipped"
      class="rounded border border-dashed px-1.5 py-0.5 text-[10px] leading-tight text-muted-foreground"
      :data-test="`tone-clipped-${tone.tone}`"
    >
      {{ t('tools.palette-generator.scale.clipped_badge') }}
    </p>

    <ul class="flex flex-col gap-0.5">
      <li
        v-for="line in lines"
        :key="line.key"
        class="flex flex-wrap items-center gap-1 text-[10px] leading-tight text-muted-foreground"
        :data-test="`tone-contrast-${line.key}-${tone.tone}`"
      >
        <span>{{ line.label }}</span>
        <span aria-hidden="true">{{ line.verdict.aaNormal ? '👍' : '👎' }}</span>
        <span
          v-if="line.verdict.aaNormal"
          aria-hidden="true"
          class="rounded border px-1 leading-none"
        >
          AA
        </span>
        <span
          v-if="line.verdict.aaaNormal"
          aria-hidden="true"
          class="rounded border px-1 leading-none"
        >
          AAA
        </span>
        <span class="sr-only">
          {{
            t(
              line.verdict.aaNormal
                ? 'tools.palette-generator.scale.pass'
                : 'tools.palette-generator.scale.fail',
              { level: 'AA' },
            )
          }}
        </span>
      </li>
    </ul>
  </article>
</template>
