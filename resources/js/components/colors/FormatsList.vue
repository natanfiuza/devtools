<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { COPY_CONFIRMATION_MS } from '@/composables/useCopyFeedback';
import { Button } from '@/components/ui/button';
import { formatAll, type ColorFormat, type Rgba } from '@/lib/color';
import CopyStatusIcon from './CopyStatusIcon.vue';

/**
 * Todos os formatos de saida, cada um com o proprio copiar.
 *
 * O nome CSS e aproximacao: o codigo exato fica ao lado, entao o rotulo nunca
 * e a unica informacao.
 */

const props = defineProps<{
    rgba: Rgba;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const { t } = useI18n();

const rows = computed(() => formatAll(props.rgba));

/** Qual linha acabou de copiar - so o icone dela vira check. */
const copiedFormat = ref<ColorFormat | null>(null);

let timer = 0;

onBeforeUnmount(() => {
    window.clearTimeout(timer);
});

function labelFor(format: ColorFormat): string {
    return t(`tools.shared.color_picker.formats.${format}`);
}

function copyLabel(format: ColorFormat): string {
    return t('tools.shared.color_picker.formats.copy', {
        format: labelFor(format),
    });
}

async function onCopy(format: ColorFormat, value: string): Promise<void> {
    const copied = await props.copy(
        value,
        t('tools.shared.color_picker.formats.copied', { value }),
    );

    if (!copied) {
        return;
    }

    copiedFormat.value = format;

    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
        copiedFormat.value = null;
    }, COPY_CONFIRMATION_MS);
}
</script>

<template>
  <section class="rounded-xl border bg-card p-4">
    <h2 class="text-sm font-semibold">
      {{ t('tools.shared.color_picker.formats.title') }}
    </h2>
    <ul class="mt-3 flex flex-col divide-y">
      <li
        v-for="row in rows"
        :key="row.format"
        class="flex items-center gap-3 py-2"
      >
        <span class="w-20 shrink-0 text-xs text-muted-foreground">
          {{ labelFor(row.format) }}
        </span>
        <code
          class="min-w-0 flex-1 truncate font-mono text-sm tabular-nums"
          :title="row.value"
        >
          {{ row.value }}
        </code>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          :aria-label="copyLabel(row.format)"
          :title="copyLabel(row.format)"
          :data-test="`format-copy-${row.format}`"
          @click="onCopy(row.format, row.value)"
        >
          <CopyStatusIcon :copied="copiedFormat === row.format" />
        </Button>
      </li>
    </ul>
    <p class="mt-2 text-xs text-muted-foreground">
      {{ t('tools.shared.color_picker.formats.name_hint') }}
    </p>
  </section>
</template>
