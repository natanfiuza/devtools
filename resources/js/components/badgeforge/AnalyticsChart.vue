<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AccountBadgeForgeAnalyticsDay } from '@/types';

/**
 * Serie diaria de visualizacoes (F7, ADR-086).
 *
 * SVG a mao, sem biblioteca de graficos: barras para visualizacoes e uma linha
 * para visitantes unicos. O titulo e as descricoes por barra saem em `<title>`
 * e `aria-label`, com o texto vindo do i18n.
 */
const props = defineProps<{
    days: AccountBadgeForgeAnalyticsDay[];
}>();

const { t } = useI18n();

const WIDTH = 600;
const HEIGHT = 200;
const PAD_X = 8;
const PAD_TOP = 16;
const PAD_BOTTOM = 8;

const maxValue = computed(() => {
    const values = props.days.flatMap((day) => [day.views, day.uniques]);

    return Math.max(1, ...values);
});

const slotWidth = computed(() =>
    props.days.length > 0 ? (WIDTH - PAD_X * 2) / props.days.length : 0,
);

const chartLabel = computed(() =>
    t('account.badgeforge.analytics.chart_title', {
        start: props.days[0]?.date ?? '',
        end: props.days[props.days.length - 1]?.date ?? '',
    }),
);

function barX(index: number): number {
    return PAD_X + index * slotWidth.value + slotWidth.value * 0.15;
}

function barWidth(): number {
    return Math.max(1, slotWidth.value * 0.7);
}

function barHeight(value: number): number {
    return ((HEIGHT - PAD_TOP - PAD_BOTTOM) * value) / maxValue.value;
}

function barY(value: number): number {
    return HEIGHT - PAD_BOTTOM - barHeight(value);
}

const linePoints = computed(() =>
    props.days
        .map((day, index) => {
            const x = PAD_X + index * slotWidth.value + slotWidth.value / 2;
            const y = barY(day.uniques);

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' '),
);

function barTitle(day: AccountBadgeForgeAnalyticsDay): string {
    return t('account.badgeforge.analytics.chart_bar_title', {
        date: day.date,
        views: day.views,
        uniques: day.uniques,
    });
}
</script>

<template>
  <svg
    :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
    role="img"
    :aria-label="chartLabel"
    class="h-48 w-full"
    data-test="badgeforge-analytics-chart"
    preserveAspectRatio="none"
  >
    <title>{{ chartLabel }}</title>

    <line
      v-for="step in 4"
      :key="step"
      :x1="PAD_X"
      :x2="WIDTH - PAD_X"
      :y1="PAD_TOP + ((HEIGHT - PAD_TOP - PAD_BOTTOM) / 4) * (step - 1)"
      :y2="PAD_TOP + ((HEIGHT - PAD_TOP - PAD_BOTTOM) / 4) * (step - 1)"
      class="stroke-border"
      stroke-width="1"
      stroke-dasharray="4 4"
    />

    <rect
      v-for="(day, index) in days"
      :key="day.date"
      :x="barX(index)"
      :y="barY(day.views)"
      :width="barWidth()"
      :height="barHeight(day.views)"
      class="fill-primary opacity-70"
      rx="1"
    >
      <title>{{ barTitle(day) }}</title>
    </rect>

    <polyline
      v-if="days.length > 1"
      :points="linePoints"
      fill="none"
      class="stroke-primary"
      stroke-width="2"
      stroke-linejoin="round"
      stroke-linecap="round"
    />
  </svg>

  <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-subtle">
    <span class="flex items-center gap-1">
      <span class="inline-block size-2 rounded-sm bg-primary/70" />
      {{ t('account.badgeforge.analytics.views') }}
    </span>
    <span class="flex items-center gap-1">
      <span class="inline-block h-0.5 w-3 bg-primary" />
      {{ t('account.badgeforge.analytics.uniques') }}
    </span>
  </div>
</template>
