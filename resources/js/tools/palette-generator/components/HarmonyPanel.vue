<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    buildHarmony,
    HARMONY_KINDS,
    HARMONY_METHODS,
    type HarmonyKind,
    type HarmonyMethod,
    type Rgba,
} from '@/lib/color';
import HarmonySwatch from './HarmonySwatch.vue';

/**
 * Harmonias da cor base (ADR-121).
 *
 * Cinco tipos, dois metodos, e a base sempre presente. As cores sao
 * **auxiliares**: nao entram nas exportacoes da escala; clicar em "usar como
 * base" muda a cor base da pagina inteira, e de la sai a escala da F2.
 *
 * O calculo e puro (`buildHarmony`); aqui so ficam os controles e a grade.
 */

const props = defineProps<{
    base: Rgba;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const emit = defineEmits<{ 'use-base': [rgba: Rgba] }>();

const { t } = useI18n();

const kind = ref<HarmonyKind>('complementary');
const method = ref<HarmonyMethod>('oklch');

const harmony = computed(() => buildHarmony(props.base, kind.value, method.value));

const reduced = computed(
    () => harmony.value.colors.length < harmony.value.expected,
);

function kindLabel(value: HarmonyKind): string {
    return t(`tools.palette-generator.harmonies.kind_${value}`);
}

function methodLabel(value: HarmonyMethod): string {
    return t(`tools.palette-generator.harmonies.method_${value}`);
}
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4"
    data-test="harmony-panel"
  >
    <div>
      <h2 class="text-sm font-semibold">
        {{ t('tools.palette-generator.harmonies.title') }}
      </h2>
      <p class="mt-1 text-xs text-muted-foreground">
        {{ t('tools.palette-generator.harmonies.description') }}
      </p>
    </div>

    <div class="flex flex-col gap-2">
      <div
        class="flex flex-wrap gap-1"
        role="group"
        :aria-label="t('tools.palette-generator.harmonies.kind_label')"
      >
        <Button
          v-for="value in HARMONY_KINDS"
          :key="value"
          type="button"
          size="sm"
          :variant="kind === value ? 'default' : 'outline'"
          :aria-pressed="kind === value"
          :data-test="`harmony-kind-${value}`"
          @click="kind = value"
        >
          {{ kindLabel(value) }}
        </Button>
      </div>

      <div
        class="flex flex-wrap items-center gap-1"
        role="group"
        :aria-label="t('tools.palette-generator.harmonies.method_label')"
      >
        <span class="mr-1 text-xs text-muted-foreground">
          {{ t('tools.palette-generator.harmonies.method_label') }}
        </span>
        <Button
          v-for="value in HARMONY_METHODS"
          :key="value"
          type="button"
          size="sm"
          :variant="method === value ? 'default' : 'outline'"
          :aria-pressed="method === value"
          :data-test="`harmony-method-${value}`"
          @click="method = value"
        >
          {{ methodLabel(value) }}
        </Button>
      </div>
    </div>

    <p
      v-if="reduced"
      class="rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground"
      data-test="harmony-achromatic"
    >
      {{ t('tools.palette-generator.harmonies.achromatic_note') }}
    </p>

    <div
      class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
      data-test="harmony-colors"
    >
      <HarmonySwatch
        v-for="(color, index) in harmony.colors"
        :key="`${harmony.kind}-${harmony.method}-${color.hex}`"
        :color="color"
        :index="index"
        :copy="copy"
        @use="emit('use-base', color.rgba)"
      />
    </div>
  </section>
</template>
