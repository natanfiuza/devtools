<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { LayoutPairCheck } from '../engine';
import { formatRatio } from '../lib/boardView';

/**
 * Resumo dos pares que nao atingem AA no tema.
 *
 * Falha e diagnostico, nunca conserto: o motor reporta o par e o quadro
 * mostra o valor real; a F3 e quem oferece alvos e sugestoes. O veredito vem
 * do numero sem arredondar; so a razao exibida e arredondada.
 */

const props = defineProps<{ checks: LayoutPairCheck[] }>();

const { t, locale } = useI18n();

const failing = computed(() =>
    props.checks.filter((check) => !check.verdict.aaNormal),
);

function ratio(value: number): string {
    return formatRatio(value, locale.value);
}
</script>

<template>
  <section
    class="space-y-2 border-t pt-3"
    :aria-label="t('tools.layout-harmonizer.a11y.contrast_summary')"
    data-test="contrast-summary"
  >
    <h3 class="text-sm font-medium">
      {{ t('tools.layout-harmonizer.contrast.summary_title') }}
    </h3>

    <p
      v-if="failing.length === 0"
      class="text-xs text-muted-foreground"
      data-test="contrast-all-pass"
    >
      {{ t('tools.layout-harmonizer.contrast.all_pass') }}
    </p>

    <ul
      v-else
      class="space-y-1 text-xs"
    >
      <li
        v-for="check in failing"
        :key="`${check.foreground}-${check.background}`"
        class="flex flex-wrap items-baseline gap-x-2"
        data-test="contrast-failure"
      >
        <code class="font-mono">{{ check.foreground }}</code>
        <span class="text-muted-foreground">
          {{ t('tools.layout-harmonizer.contrast.on') }}
        </span>
        <code class="font-mono">{{ check.background }}</code>
        <span class="text-muted-foreground">
          {{ ratio(check.verdict.ratio) }}:1
        </span>
      </li>
    </ul>
  </section>
</template>
