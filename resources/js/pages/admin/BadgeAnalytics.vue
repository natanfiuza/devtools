<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import admin from '@/routes/admin';
import type { AdminBadgeAnalytics, AdminBadgeAnalyticsDay } from '@/types';

/**
 * Volume global do BadgeForge (F7, ADR-086).
 *
 * Numeros vem do agregado diario: volume e fatia servida do cache. Visitantes
 * unicos nao aparecem porque o agregado e particionado por dimensao e somar
 * as linhas contaria o mesmo IP mais de uma vez.
 */
const props = defineProps<{ analytics: AdminBadgeAnalytics }>();

const { t } = useI18n();

const WIDTH = 600;
const HEIGHT = 160;
const PAD_X = 8;
const PAD_TOP = 12;
const PAD_BOTTOM = 8;

const maxViews = computed(() =>
    Math.max(1, ...props.analytics.days.map((day) => day.views)),
);

const slotWidth = computed(() =>
    props.analytics.days.length > 0
        ? (WIDTH - PAD_X * 2) / props.analytics.days.length
        : 0,
);

const cachePercent = computed(() =>
    props.analytics.totals.cacheRate === null
        ? null
        : Math.round(props.analytics.totals.cacheRate * 100),
);

function barHeight(value: number): number {
    return ((HEIGHT - PAD_TOP - PAD_BOTTOM) * value) / maxViews.value;
}

function barX(index: number): number {
    return PAD_X + index * slotWidth.value + slotWidth.value * 0.15;
}

function barWidth(): number {
    return Math.max(1, slotWidth.value * 0.7);
}

function barY(value: number): number {
    return HEIGHT - PAD_BOTTOM - barHeight(value);
}

function barTitle(day: AdminBadgeAnalyticsDay): string {
    return t('admin.badgeforge_analytics.bar_title', {
        date: day.date,
        views: day.views,
    });
}

function selectWindow(days: number): void {
    if (days === props.analytics.window) {
        return;
    }

    router.get(
        admin.badgeforge.analytics.index.url(),
        { window: days },
        { preserveScroll: true, preserveState: true, only: ['analytics'] },
    );
}
</script>

<template>
  <Head :title="t('admin.badgeforge_analytics.title')" />

  <div class="space-y-4">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-medium">
          {{ t('admin.badgeforge_analytics.title') }}
        </h2>
        <p class="mt-1 text-sm text-subtle">
          {{ t('admin.badgeforge_analytics.description') }}
        </p>
      </div>

      <div
        class="flex flex-wrap items-center gap-2"
        :aria-label="t('admin.badgeforge_analytics.window')"
      >
        <button
          v-for="days in analytics.windows"
          :key="days"
          type="button"
          class="rounded-md border px-3 py-1.5 text-sm"
          :class="
            days === analytics.window
              ? 'border-border bg-accent/10 text-foreground'
              : 'border-border text-subtle hover:bg-muted'
          "
          :aria-pressed="days === analytics.window"
          :data-test="`admin-badgeforge-window-${days}`"
          @click="selectWindow(days)"
        >
          {{ t('admin.badgeforge_analytics.window_days', { days }) }}
        </button>
      </div>
    </header>

    <p
      v-if="analytics.totals.views === 0"
      class="text-sm text-subtle"
      data-test="admin-badgeforge-analytics-empty"
    >
      {{ t('admin.badgeforge_analytics.no_data') }}
    </p>

    <template v-else>
      <dl
        class="grid gap-3 sm:grid-cols-3"
        data-test="admin-badgeforge-analytics-totals"
      >
        <div class="rounded-lg border border-border px-4 py-3">
          <dt class="text-[12px] tracking-wide text-subtle uppercase">
            {{ t('admin.badgeforge_analytics.views') }}
          </dt>
          <dd class="mt-1 font-mono text-2xl">
            {{ analytics.totals.views }}
          </dd>
        </div>
        <div class="rounded-lg border border-border px-4 py-3">
          <dt class="text-[12px] tracking-wide text-subtle uppercase">
            {{ t('admin.badgeforge_analytics.cache_hits') }}
          </dt>
          <dd class="mt-1 font-mono text-2xl">
            {{ analytics.totals.cacheHits }}
          </dd>
        </div>
        <div class="rounded-lg border border-border px-4 py-3">
          <dt class="text-[12px] tracking-wide text-subtle uppercase">
            {{ t('admin.badgeforge_analytics.cache_rate') }}
          </dt>
          <dd class="mt-1 font-mono text-2xl">
            {{
              cachePercent === null
                ? t('admin.badgeforge_analytics.cache_none')
                : `${cachePercent}%`
            }}
          </dd>
          <p class="mt-1 text-[12px] text-subtle">
            {{
              t('admin.badgeforge_analytics.cache_detail', {
                hits: analytics.totals.cacheHits,
              })
            }}
          </p>
        </div>
      </dl>

      <svg
        :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
        class="w-full max-w-3xl"
        role="img"
        :aria-label="t('admin.badgeforge_analytics.title')"
        data-test="admin-badgeforge-analytics-chart"
      >
        <title>{{ t('admin.badgeforge_analytics.title') }}</title>
        <rect
          v-for="(day, index) in analytics.days"
          :key="day.date"
          :x="barX(index)"
          :y="barY(day.views)"
          :width="barWidth()"
          :height="barHeight(day.views)"
          class="fill-current text-accent"
        >
          <title>{{ barTitle(day) }}</title>
        </rect>
      </svg>
    </template>

    <p class="text-xs text-subtle">
      {{ t('admin.badgeforge_analytics.note') }}
    </p>
  </div>
</template>
