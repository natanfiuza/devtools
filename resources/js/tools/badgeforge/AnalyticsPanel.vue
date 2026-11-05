<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AnalyticsChart from '@/components/badgeforge/AnalyticsChart.vue';
import { Button } from '@/components/ui/button';
import { badgeforge as badgeforgeDashboard } from '@/routes/dashboard';
import type { AccountBadgeForgeAnalytics } from '@/types';

/**
 * Secao de analytics da dashboard (F7, ADR-086).
 *
 * Free ve so os ultimos 30 dias, com totais e por imagem; pago escolhe
 * 7/30/90 e ganha serie diaria, referrers, paises, formatos, taxa de cache e
 * CSV. A troca de janela recarrega apenas a prop `analytics`.
 */
const props = defineProps<{
    analytics: AccountBadgeForgeAnalytics | null;
    upgradeUrl: string;
}>();

const { t } = useI18n();

const analytics = computed(() => props.analytics);

const cachePercent = computed(() => {
    const rate = analytics.value?.cache?.rate;

    return rate === null || rate === undefined ? null : Math.round(rate * 100);
});

function subjectLabel(type: string): string {
    return t(`account.badgeforge.analytics.subjects.${type}`);
}

function changeWindow(days: number): void {
    router.get(
        badgeforgeDashboard.url({ query: { window: days } }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            only: ['analytics'],
        },
    );
}
</script>

<template>
  <section
    v-if="analytics === null"
    class="rounded-lg border border-border px-4 py-3"
    data-test="badgeforge-analytics-unavailable"
  >
    <p class="text-sm text-subtle">
      {{ t('account.badgeforge.analytics.unavailable') }}
    </p>
  </section>

  <section
    v-else
    class="space-y-4"
    data-test="badgeforge-analytics"
  >
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="text-sm font-medium">
          {{ t('account.badgeforge.analytics.title') }}
        </h3>
        <p class="mt-1 text-xs text-subtle">
          {{ t('account.badgeforge.analytics.description') }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <div
          v-if="analytics.isPaid"
          class="flex rounded-md border border-border"
          role="group"
          :aria-label="t('account.badgeforge.analytics.period')"
        >
          <button
            v-for="days in analytics.windows"
            :key="days"
            type="button"
            class="px-3 py-1 text-xs first:rounded-l-md last:rounded-r-md"
            :class="
              analytics.window === days
                ? 'bg-primary text-primary-foreground'
                : 'text-subtle hover:bg-muted'
            "
            :data-test="`badgeforge-analytics-window-${days}`"
            :aria-pressed="analytics.window === days"
            @click="changeWindow(days)"
          >
            {{ t('account.badgeforge.analytics.period_days', { days }) }}
          </button>
        </div>

        <Button
          v-if="analytics.exportUrl"
          variant="outline"
          size="sm"
          as-child
        >
          <a
            :href="analytics.exportUrl"
            data-test="badgeforge-analytics-export"
          >
            <Download class="size-4" />
            {{ t('account.badgeforge.analytics.export') }}
          </a>
        </Button>
      </div>
    </header>

    <dl
      class="grid grid-cols-2 gap-3 sm:grid-cols-3"
      data-test="badgeforge-analytics-totals"
    >
      <div class="rounded-lg border border-border px-4 py-3">
        <dt class="text-xs text-subtle">
          {{ t('account.badgeforge.analytics.views') }}
        </dt>
        <dd class="text-lg font-medium">
          {{ analytics.totals.views }}
        </dd>
      </div>
      <div class="rounded-lg border border-border px-4 py-3">
        <dt class="text-xs text-subtle">
          {{ t('account.badgeforge.analytics.uniques') }}
        </dt>
        <dd class="text-lg font-medium">
          {{ analytics.totals.uniques }}
        </dd>
      </div>
      <div
        v-if="analytics.cache && cachePercent !== null"
        class="rounded-lg border border-border px-4 py-3"
      >
        <dt class="text-xs text-subtle">
          {{ t('account.badgeforge.analytics.cache_rate') }}
        </dt>
        <dd class="text-lg font-medium">
          {{ cachePercent }}%
        </dd>
        <dd class="text-xs text-subtle">
          {{
            t('account.badgeforge.analytics.cache_detail', {
              hits: analytics.cache.hits,
              total: analytics.cache.total,
            })
          }}
        </dd>
      </div>
    </dl>

    <div
      v-if="analytics.days.length > 0"
      class="rounded-lg border border-border px-4 py-3"
    >
      <h4 class="mb-2 text-xs font-medium text-subtle">
        {{ t('account.badgeforge.analytics.chart_heading') }}
      </h4>
      <AnalyticsChart :days="analytics.days" />
    </div>

    <div
      v-if="analytics.isPaid"
      class="grid gap-3 sm:grid-cols-3"
    >
      <div class="rounded-lg border border-border px-4 py-3">
        <h4 class="mb-2 text-xs font-medium text-subtle">
          {{ t('account.badgeforge.analytics.referers') }}
        </h4>
        <ul
          v-if="analytics.referers.length > 0"
          class="space-y-1 text-sm"
          data-test="badgeforge-analytics-referers"
        >
          <li
            v-for="referer in analytics.referers"
            :key="referer.domain"
            class="flex items-center justify-between gap-2"
          >
            <span class="truncate">{{ referer.domain }}</span>
            <span class="text-subtle">{{ referer.views }}</span>
          </li>
        </ul>
        <p
          v-else
          class="text-xs text-subtle"
        >
          {{ t('account.badgeforge.analytics.no_data') }}
        </p>
      </div>

      <div class="rounded-lg border border-border px-4 py-3">
        <h4 class="mb-2 text-xs font-medium text-subtle">
          {{ t('account.badgeforge.analytics.countries') }}
        </h4>
        <ul
          v-if="analytics.countries.length > 0"
          class="space-y-1 text-sm"
          data-test="badgeforge-analytics-countries"
        >
          <li
            v-for="country in analytics.countries"
            :key="country.country"
            class="flex items-center justify-between gap-2"
          >
            <span>{{ country.country }}</span>
            <span class="text-subtle">{{ country.views }}</span>
          </li>
        </ul>
        <p
          v-else
          class="text-xs text-subtle"
        >
          {{ t('account.badgeforge.analytics.no_data') }}
        </p>
      </div>

      <div class="rounded-lg border border-border px-4 py-3">
        <h4 class="mb-2 text-xs font-medium text-subtle">
          {{ t('account.badgeforge.analytics.formats') }}
        </h4>
        <ul
          v-if="analytics.formats.length > 0"
          class="space-y-1 text-sm"
          data-test="badgeforge-analytics-formats"
        >
          <li
            v-for="format in analytics.formats"
            :key="format.format"
            class="flex items-center justify-between gap-2"
          >
            <span class="uppercase">{{ format.format }}</span>
            <span class="text-subtle">{{ format.views }}</span>
          </li>
        </ul>
        <p
          v-else
          class="text-xs text-subtle"
        >
          {{ t('account.badgeforge.analytics.no_data') }}
        </p>
      </div>
    </div>

    <div class="rounded-lg border border-border">
      <h4 class="border-b border-border px-4 py-2 text-xs font-medium text-subtle">
        {{ t('account.badgeforge.analytics.subjects_title') }}
      </h4>

      <p
        v-if="analytics.subjects.length === 0"
        class="px-4 py-6 text-center text-sm text-subtle"
        data-test="badgeforge-analytics-subjects-empty"
      >
        {{ t('account.badgeforge.analytics.no_data') }}
      </p>

      <table
        v-else
        class="w-full text-sm"
        data-test="badgeforge-analytics-subjects"
      >
        <thead>
          <tr class="text-left text-xs text-subtle">
            <th class="px-4 py-2 font-medium">
              {{ t('account.badgeforge.analytics.subject_name') }}
            </th>
            <th class="px-4 py-2 font-medium">
              {{ t('account.badgeforge.analytics.subject_type') }}
            </th>
            <th class="px-4 py-2 text-right font-medium">
              {{ t('account.badgeforge.analytics.views') }}
            </th>
            <th class="px-4 py-2 text-right font-medium">
              {{ t('account.badgeforge.analytics.uniques') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <tr
            v-for="subject in analytics.subjects"
            :key="`${subject.type}:${subject.id}`"
          >
            <td class="px-4 py-2">
              {{ subject.name || t('account.badgeforge.analytics.unknown_subject') }}
            </td>
            <td class="px-4 py-2 text-subtle">
              {{ subjectLabel(subject.type) }}
            </td>
            <td class="px-4 py-2 text-right">
              {{ subject.views }}
            </td>
            <td class="px-4 py-2 text-right">
              {{ subject.uniques }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="!analytics.isPaid"
      class="rounded-lg border border-dashed px-4 py-3"
      data-test="badgeforge-analytics-free-note"
    >
      <p class="text-xs text-subtle">
        {{
          t('account.badgeforge.analytics.free_note', {
            days: analytics.freeWindowDays,
          })
        }}
      </p>
      <Button
        variant="outline"
        size="sm"
        class="mt-2"
        as-child
      >
        <a
          :href="upgradeUrl"
          data-test="badgeforge-analytics-upgrade"
        >
          {{ t('account.badgeforge.analytics.upgrade') }}
        </a>
      </Button>
    </div>
  </section>
</template>
