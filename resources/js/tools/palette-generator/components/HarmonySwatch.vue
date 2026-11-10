<script setup lang="ts">
import { Pipette } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CopyStatusIcon from '@/components/colors/CopyStatusIcon.vue';
import { checkerboardStyle, cssColor } from '@/components/colors/lib/style';
import { Button } from '@/components/ui/button';
import { useCopyConfirmation } from '@/composables/useCopyFeedback';
import type { HarmonyColor } from '@/lib/color';

/**
 * Uma cor da harmonia: o quadrado, o HEX e as duas acoes.
 *
 * Ser a base nao muda a acao (clicar na base tambem a define, e inofensivo);
 * o selo existe para a pessoa saber qual cor ja e a base. A cor nunca e o
 * unico sinal: o HEX vai escrito, e os botoes tem rotulo traduzido.
 */

const props = defineProps<{
    color: HarmonyColor;
    index: number;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const emit = defineEmits<{ use: [] }>();

const { t } = useI18n();
const { copied, confirm } = useCopyConfirmation();

const colorStyle = computed(() => ({ backgroundColor: cssColor(props.color.rgba) }));
const colorLabel = computed(() =>
    t('tools.palette-generator.harmonies.color_label', {
        index: props.index + 1,
    }),
);
const copyLabel = computed(() =>
    t('tools.palette-generator.harmonies.copy'),
);
const useLabel = computed(() =>
    t('tools.palette-generator.harmonies.use_as_base'),
);

async function onCopy(): Promise<void> {
    const ok = await props.copy(
        props.color.hex,
        t('tools.palette-generator.harmonies.copied', {
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
    :data-test="`harmony-color-${index}`"
  >
    <div
      class="relative h-12 w-full overflow-hidden rounded-md border"
      :style="checkerboardStyle"
      :title="colorLabel"
      :aria-label="colorLabel"
      role="img"
    >
      <span
        class="absolute inset-0"
        :style="colorStyle"
      />
    </div>

    <div class="flex flex-wrap items-center gap-1">
      <code class="font-mono text-xs">{{ color.hex }}</code>
      <span
        v-if="color.isBase"
        class="rounded-full border px-1.5 py-0.5 text-[10px] leading-none text-muted-foreground"
        :data-test="`harmony-base-${index}`"
      >
        {{ t('tools.palette-generator.harmonies.base_badge') }}
      </span>
      <span
        v-if="color.clipped"
        class="rounded-full border px-1.5 py-0.5 text-[10px] leading-none text-muted-foreground"
        :data-test="`harmony-clipped-${index}`"
      >
        {{ t('tools.palette-generator.harmonies.clipped_badge') }}
      </span>
    </div>

    <div class="flex flex-wrap gap-1">
      <Button
        type="button"
        size="sm"
        variant="outline"
        :aria-label="`${copyLabel}: ${color.hex}`"
        :data-test="`harmony-copy-${index}`"
        @click="onCopy"
      >
        <CopyStatusIcon :copied="copied" />
        <span class="sr-only">{{ copyLabel }}</span>
      </Button>
      <Button
        type="button"
        size="sm"
        variant="ghost"
        :aria-label="`${useLabel}: ${color.hex}`"
        :data-test="`harmony-use-${index}`"
        @click="emit('use')"
      >
        <Pipette aria-hidden="true" />
        <span class="sr-only">{{ useLabel }}</span>
      </Button>
    </div>
  </article>
</template>
