<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CopyStatusIcon from '@/components/colors/CopyStatusIcon.vue';
import FormatsList from '@/components/colors/FormatsList.vue';
import { checkerboardStyle, cssColor } from '@/components/colors/lib/style';
import { Button } from '@/components/ui/button';
import { useCopyConfirmation } from '@/composables/useCopyFeedback';
import { formatHex, formatHex8, type PaletteScale } from '@/lib/color';

/**
 * A cor escolhida: o valor exato, o selo de base e as notas neutras.
 *
 * A base nunca e um dos 11 cartoes e nunca e alterada; se ela nao caiu no tom
 * 500, a pagina so avisa onde ela ficou. Os formatos convertidos da base saem
 * do `FormatsList` compartilhado.
 */

const props = defineProps<{
    scale: PaletteScale;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const { t } = useI18n();
const { copied, confirm } = useCopyConfirmation();

const hex = computed(() =>
    props.scale.base.a < 1
        ? formatHex8(props.scale.base)
        : formatHex(props.scale.base),
);

const format = computed(() => (props.scale.base.a < 1 ? 'hex8' : 'hex'));

const nearestClipped = computed(() =>
    props.scale.clippedTones.includes(props.scale.nearestTone),
);

async function onCopy(): Promise<void> {
    const ok = await props.copy(
        hex.value,
        t('tools.palette-generator.base.copied', { value: hex.value }),
    );

    if (ok) {
        confirm();
    }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <section
      class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4"
      data-test="base-color-row"
    >
      <div class="flex items-center gap-3">
        <div
          class="relative size-12 shrink-0 overflow-hidden rounded-lg border shadow-xs"
          :style="checkerboardStyle"
          aria-hidden="true"
          data-test="base-swatch"
        >
          <div
            class="absolute inset-0"
            :style="{ backgroundColor: cssColor(scale.base) }"
          />
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold">
              {{ t('tools.palette-generator.base.title') }}
            </h2>
            <span
              class="rounded-full border px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
            >
              {{ t('tools.palette-generator.base.badge') }}
            </span>
          </div>
          <code
            class="mt-1 block truncate font-mono text-sm tabular-nums"
            data-test="base-value"
          >
            {{ hex }}
          </code>
        </div>

        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          :aria-label="
            t('tools.palette-generator.base.copy', {
              format: t(`tools.shared.color_picker.formats.${format}`),
            })
          "
          :title="
            t('tools.palette-generator.base.copy', {
              format: t(`tools.shared.color_picker.formats.${format}`),
            })
          "
          data-test="base-copy"
          @click="onCopy"
        >
          <CopyStatusIcon :copied="copied" />
        </Button>
      </div>

      <p
        class="text-xs text-muted-foreground"
        data-test="base-nearest"
      >
        {{
          t('tools.palette-generator.base.nearest', {
            tone: scale.nearestTone,
          })
        }}
      </p>

      <p
        class="text-xs text-muted-foreground"
        data-test="base-tone-note"
      >
        {{
          scale.baseIsTone500
            ? t('tools.palette-generator.base.matches_500')
            : t('tools.palette-generator.base.not_500')
        }}
      </p>

      <p
        v-if="nearestClipped"
        class="text-xs text-muted-foreground"
        data-test="base-clipped"
      >
        {{ t('tools.palette-generator.base.clipped') }}
      </p>

      <p
        v-if="scale.base.a < 1"
        class="text-xs text-muted-foreground"
        data-test="base-alpha-note"
      >
        {{ t('tools.palette-generator.base.alpha_note') }}
      </p>
    </section>

    <FormatsList
      :rgba="scale.base"
      :copy="copy"
    />
  </div>
</template>
