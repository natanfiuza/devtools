<script setup lang="ts">
import { BarChart3, Calendar, Layers } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    ApiTokenDailyUsage,
    ApiTokenUsagePeriod,
    ApiTokenUsageStats,
} from '@/types';

const props = defineProps<{
    stats?: ApiTokenUsageStats;
}>();

const emit = defineEmits<{
    (
        e: 'periodChange',
        payload: {
            period: ApiTokenUsagePeriod['type'];
            startDate?: string;
            endDate?: string;
        },
    ): void;
}>();

const { t } = useI18n();

type ChartView = 'total' | 'by_token';

const currentView = ref<ChartView>('total');
const selectedPeriod = ref<ApiTokenUsagePeriod['type']>(
    props.stats?.period.type ?? 'current_month',
);

const customStartDate = ref(props.stats?.period.startDate ?? '');
const customEndDate = ref(props.stats?.period.endDate ?? '');
const hoveredDayIndex = ref<number | null>(null);

watch(
    () => props.stats?.period,
    (period) => {
        if (!period) return;
        selectedPeriod.value = period.type;
        customStartDate.value = period.startDate;
        customEndDate.value = period.endDate;
    },
    { deep: true },
);

// Paleta acessivel para tokens
const TOKEN_COLORS = [
    '#6366f1', // Indigo
    '#10b981', // Emerald
    '#f59e0b', // Amber
    '#ec4899', // Pink
    '#06b6d4', // Cyan
    '#8b5cf6', // Purple
    '#3b82f6', // Blue
    '#14b8a6', // Teal
];
const REVOKED_COLOR = '#64748b'; // Slate neutro

const tokenColorMap = computed(() => {
    const map = new Map<number | 'revoked', string>();
    const tokens = props.stats?.tokens ?? [];

    let colorIdx = 0;
    for (const token of tokens) {
        if (token.isRevoked || token.id === 'revoked') {
            map.set(token.id, REVOKED_COLOR);
        } else {
            map.set(token.id, TOKEN_COLORS[colorIdx % TOKEN_COLORS.length]);
            colorIdx++;
        }
    }

    return map;
});

function getTokenColor(tokenId: number | 'revoked', isRevoked = false): string {
    if (isRevoked || tokenId === 'revoked') {
        return REVOKED_COLOR;
    }
    return tokenColorMap.value.get(tokenId) ?? TOKEN_COLORS[0];
}

const days = computed<ApiTokenDailyUsage[]>(() => props.stats?.days ?? []);

const maxTokens = computed(() => {
    const max = Math.max(...days.value.map((d) => d.totalTokens), 0);
    return max > 0 ? max : 1000;
});

// Arredonda o topo do eixo Y para um numero amigavel
const yAxisMax = computed(() => {
    const max = maxTokens.value;
    const factor = Math.pow(10, Math.floor(Math.log10(max)));
    return Math.ceil(max / factor) * factor;
});

const yAxisTicks = computed(() => {
    const max = yAxisMax.value;
    return [
        { value: max, yPercent: 0 },
        { value: Math.round(max * 0.75), yPercent: 25 },
        { value: Math.round(max * 0.5), yPercent: 50 },
        { value: Math.round(max * 0.25), yPercent: 75 },
        { value: 0, yPercent: 100 },
    ];
});

function formatTokenCount(num: number): string {
    if (num >= 1_000_000) {
        return `${(num / 1_000_000).toFixed(1)}M`;
    }
    if (num >= 1_000) {
        return `${(num / 1_000).toFixed(num % 1000 === 0 ? 0 : 1)}k`;
    }
    return num.toLocaleString();
}

function handlePeriodSelect(value: unknown): void {
    const periodValue = String(value) as ApiTokenUsagePeriod['type'];
    selectedPeriod.value = periodValue;

    if (periodValue !== 'custom') {
        hoveredDayIndex.value = null;
        emit('periodChange', { period: periodValue });
    }
}

function applyCustomPeriod(): void {
    if (!customStartDate.value || !customEndDate.value) {
        return;
    }
    hoveredDayIndex.value = null;
    emit('periodChange', {
        period: 'custom',
        startDate: customStartDate.value,
        endDate: customEndDate.value,
    });
}

const hasUsage = computed(() => (props.stats?.summary.totalTokens ?? 0) > 0);

const activeDay = computed<ApiTokenDailyUsage | null>(() => {
    if (hoveredDayIndex.value === null) return null;
    return days.value[hoveredDayIndex.value] ?? null;
});

// Geometria do grafico SVG (base 800 x 240)
const SVG_WIDTH = 800;
const SVG_HEIGHT = 220;
const PADDING = { top: 20, right: 16, bottom: 30, left: 50 };

const chartWidth = computed(() => SVG_WIDTH - PADDING.left - PADDING.right);
const chartHeight = computed(() => SVG_HEIGHT - PADDING.top - PADDING.bottom);

const slotWidth = computed(() => {
    const totalDays = days.value.length;
    if (totalDays === 0) return chartWidth.value;
    return chartWidth.value / totalDays;
});

const barWidth = computed(() => {
    const slot = slotWidth.value;
    return Math.max(3, Math.min(22, slot * 0.7));
});

// Calcula fatias empilhadas para cada dia
type StackSegment = {
    tokenId: number | 'revoked';
    tokenName: string;
    tokens: number;
    color: string;
    y: number;
    height: number;
    percentOfDay: number;
};

const computedDayBars = computed(() => {
    const max = yAxisMax.value;
    const height = chartHeight.value;

    return days.value.map((day, index) => {
        const xCenter = PADDING.left + index * slotWidth.value + slotWidth.value / 2;
        const x = xCenter - barWidth.value / 2;

        let currentY = PADDING.top + height;
        const segments: StackSegment[] = [];

        if (currentView.value === 'total') {
            const barH = (day.totalTokens / max) * height;
            segments.push({
                tokenId: 'total',
                tokenName: t('settings.api_tokens.usage_chart.views.total'),
                tokens: day.totalTokens,
                color: 'var(--color-primary, #6366f1)',
                y: currentY - barH,
                height: Math.max(day.totalTokens > 0 ? 2 : 0, barH),
                percentOfDay: 100,
            });
        } else {
            // Visao por token: empilhado
            for (const item of day.breakdown) {
                const segH = (item.tokens / max) * height;
                const segY = currentY - segH;
                segments.push({
                    tokenId: item.tokenId,
                    tokenName: item.tokenName,
                    tokens: item.tokens,
                    color: getTokenColor(item.tokenId, item.isRevoked),
                    y: segY,
                    height: Math.max(item.tokens > 0 ? 1 : 0, segH),
                    percentOfDay:
                        day.totalTokens > 0
                            ? Math.round((item.tokens / day.totalTokens) * 100)
                            : 0,
                });
                currentY = segY;
            }
        }

        return {
            date: day.date,
            dayLabel: day.dayLabel,
            xCenter,
            x,
            totalTokens: day.totalTokens,
            segments,
        };
    });
});

// Determinacao de quais labels exibir no eixo X para evitar sobreposicao
function shouldShowDayLabel(index: number, totalDays: number): boolean {
    if (totalDays <= 14) return true;
    if (totalDays <= 31) return index === 0 || (index + 1) % 5 === 0 || index === totalDays - 1;
    return index === 0 || (index + 1) % 10 === 0 || index === totalDays - 1;
}

// Posicionamento do tooltip flutuante
const tooltipStyle = computed(() => {
    if (hoveredDayIndex.value === null || !computedDayBars.value[hoveredDayIndex.value]) {
        return { display: 'none' };
    }

    const bar = computedDayBars.value[hoveredDayIndex.value];
    const leftPercent = (bar.xCenter / SVG_WIDTH) * 100;

    return {
        left: `${leftPercent}%`,
        transform: leftPercent > 70 ? 'translateX(-100%)' : leftPercent < 30 ? 'translateX(0)' : 'translateX(-50%)',
    };
});
</script>

<template>
  <div
    data-test="api-tokens-usage-chart-card"
    class="rounded-lg border border-border bg-surface p-4 sm:p-5"
  >
    <!-- Header com titulo e controles -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <div class="flex items-center gap-2">
          <BarChart3
            class="size-5 text-accent"
            aria-hidden="true"
          />
          <h3 class="text-sm font-semibold tracking-tight">
            {{ t('settings.api_tokens.usage_chart.title') }}
          </h3>
        </div>
        <p class="mt-1 text-xs text-subtle">
          {{ t('settings.api_tokens.usage_chart.description') }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <!-- Alternancia entre Total diario e Por token de API -->
        <div
          class="inline-flex rounded-lg border border-border bg-muted/40 p-0.5"
          role="group"
          data-test="chart-view-toggle"
        >
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
            :class="
              currentView === 'total'
                ? 'bg-background text-foreground shadow-xs'
                : 'text-subtle hover:text-foreground'
            "
            data-test="chart-view-total-btn"
            @click="currentView = 'total'"
          >
            <BarChart3 class="size-3.5" />
            <span>{{ t('settings.api_tokens.usage_chart.views.total') }}</span>
          </button>

          <button
            type="button"
            class="flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
            :class="
              currentView === 'by_token'
                ? 'bg-background text-foreground shadow-xs'
                : 'text-subtle hover:text-foreground'
            "
            data-test="chart-view-by-token-btn"
            @click="currentView = 'by_token'"
          >
            <Layers class="size-3.5" />
            <span>{{ t('settings.api_tokens.usage_chart.views.by_token') }}</span>
          </button>
        </div>

        <!-- Seletor de periodo -->
        <div class="min-w-36">
          <Select
            :model-value="selectedPeriod"
            data-test="chart-period-select"
            @update:model-value="handlePeriodSelect"
          >
            <SelectTrigger
              class="h-8 text-xs"
              data-test="chart-period-trigger"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="current_month">
                {{ t('settings.api_tokens.usage_chart.periods.current_month') }}
              </SelectItem>
              <SelectItem value="previous_month">
                {{ t('settings.api_tokens.usage_chart.periods.previous_month') }}
              </SelectItem>
              <SelectItem value="custom">
                {{ t('settings.api_tokens.usage_chart.periods.custom') }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>

    <!-- Controles customizados de data quando periodo e custom -->
    <div
      v-if="selectedPeriod === 'custom'"
      data-test="chart-custom-range-controls"
      class="mt-3 flex flex-wrap items-end gap-3 rounded-md border border-border/80 bg-muted/20 p-3"
    >
      <div class="grid gap-1">
        <Label
          for="chart-start-date"
          class="text-xs"
        >
          {{ t('settings.api_tokens.usage_chart.custom.start_date') }}
        </Label>
        <Input
          id="chart-start-date"
          v-model="customStartDate"
          type="date"
          class="h-8 text-xs"
          data-test="chart-custom-start-input"
        />
      </div>

      <div class="grid gap-1">
        <Label
          for="chart-end-date"
          class="text-xs"
        >
          {{ t('settings.api_tokens.usage_chart.custom.end_date') }}
        </Label>
        <Input
          id="chart-end-date"
          v-model="customEndDate"
          type="date"
          class="h-8 text-xs"
          data-test="chart-custom-end-input"
        />
      </div>

      <Button
        size="sm"
        class="h-8 text-xs"
        data-test="chart-custom-apply-btn"
        @click="applyCustomPeriod"
      >
        {{ t('settings.api_tokens.usage_chart.custom.apply') }}
      </Button>
    </div>

    <!-- Mini-resumo do periodo -->
    <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-subtle">
      <div class="flex items-center gap-1.5 font-mono">
        <Calendar class="size-3.5 text-muted-foreground" />
        <span>{{ stats?.period.startDate }} ~ {{ stats?.period.endDate }}</span>
      </div>
      <div>
        <span>{{ t('settings.api_tokens.usage_chart.summary.tokens') }}:</span>
        <strong
          class="ml-1 font-mono text-foreground"
          data-test="chart-summary-tokens"
        >
          {{ (stats?.summary.totalTokens ?? 0).toLocaleString() }}
        </strong>
      </div>
      <div>
        <span>{{ t('settings.api_tokens.usage_chart.summary.requests') }}:</span>
        <strong
          class="ml-1 font-mono text-foreground"
          data-test="chart-summary-requests"
        >
          {{ (stats?.summary.totalRequests ?? 0).toLocaleString() }}
        </strong>
      </div>
    </div>

    <!-- Area do Grafico SVG -->
    <div class="relative mt-4 overflow-hidden rounded-md border border-border/70 bg-card p-2 sm:p-3">
      <!-- Tooltip flutuante interativo -->
      <div
        v-if="activeDay"
        data-test="chart-active-tooltip"
        class="pointer-events-none absolute top-3 z-20 min-w-56 rounded-md border border-border bg-popover/95 p-2.5 text-popover-foreground shadow-md backdrop-blur-xs transition-all duration-75"
        :style="tooltipStyle"
      >
        <p class="font-mono text-xs font-semibold text-foreground">
          {{ activeDay.formattedDate }}
        </p>

        <div class="mt-1 flex items-center justify-between gap-4 text-xs">
          <span class="text-subtle">
            {{ t('settings.api_tokens.usage_chart.summary.tokens') }}
          </span>
          <span class="font-mono font-medium">
            {{
              t('settings.api_tokens.usage_chart.tooltip.total_tokens', {
                count: activeDay.totalTokens.toLocaleString(),
              })
            }}
          </span>
        </div>

        <div class="flex items-center justify-between gap-4 text-xs">
          <span class="text-subtle">
            {{ t('settings.api_tokens.usage_chart.summary.requests') }}
          </span>
          <span class="font-mono font-medium">
            {{
              t('settings.api_tokens.usage_chart.tooltip.requests', {
                count: activeDay.totalRequests,
              })
            }}
          </span>
        </div>

        <div
          v-if="currentView === 'by_token' && activeDay.breakdown.length > 0"
          class="mt-2 border-t border-border pt-2 space-y-1"
        >
          <div
            v-for="item in activeDay.breakdown"
            :key="item.tokenId"
            class="flex items-center justify-between gap-2 text-[11px]"
          >
            <div class="flex items-center gap-1.5 truncate">
              <span
                class="size-2 shrink-0 rounded-full"
                :style="{
                  backgroundColor: getTokenColor(item.tokenId, item.isRevoked),
                }"
              />
              <span class="truncate">{{ item.tokenName }}</span>
            </div>
            <span class="font-mono text-subtle shrink-0">
              {{ item.tokens.toLocaleString() }}
            </span>
          </div>
        </div>
      </div>

      <!-- Grafico SVG -->
      <div
        v-if="days.length > 0"
        class="w-full overflow-x-auto"
      >
        <svg
          class="h-56 w-full min-w-[500px]"
          :viewBox="`0 0 ${SVG_WIDTH} ${SVG_HEIGHT}`"
          preserveAspectRatio="none"
          data-test="api-tokens-usage-svg"
          @mouseleave="hoveredDayIndex = null"
        >
          <!-- Grid lines horizontais e ticks do eixo Y -->
          <g class="grid-lines">
            <g
              v-for="tick in yAxisTicks"
              :key="tick.value"
            >
              <line
                :x1="PADDING.left"
                :y1="PADDING.top + (chartHeight * tick.yPercent) / 100"
                :x2="SVG_WIDTH - PADDING.right"
                :y2="PADDING.top + (chartHeight * tick.yPercent) / 100"
                stroke="currentColor"
                class="text-border/60"
                stroke-dasharray="3,3"
                stroke-width="1"
              />
              <text
                :x="PADDING.left - 8"
                :y="PADDING.top + (chartHeight * tick.yPercent) / 100 + 4"
                text-anchor="end"
                class="fill-subtle font-mono text-[10px]"
              >
                {{ formatTokenCount(tick.value) }}
              </text>
            </g>
          </g>

          <!-- Barras verticais -->
          <g class="bars">
            <g
              v-for="(day, dIdx) in computedDayBars"
              :key="day.date"
              class="cursor-pointer transition-opacity"
              :class="{
                'opacity-100': hoveredDayIndex === null || hoveredDayIndex === dIdx,
                'opacity-40': hoveredDayIndex !== null && hoveredDayIndex !== dIdx,
              }"
              @mouseenter="hoveredDayIndex = dIdx"
            >
              <!-- Fatias empilhadas da barra -->
              <rect
                v-for="(seg, sIdx) in day.segments"
                :key="`${day.date}-${sIdx}`"
                :x="day.x"
                :y="seg.y"
                :width="barWidth"
                :height="seg.height"
                :fill="seg.color"
                rx="2"
                class="transition-all duration-150"
              />

              <!-- Alvo invisivel ampliado para facilitar hover/touch -->
              <rect
                :x="day.xCenter - slotWidth / 2"
                :y="PADDING.top"
                :width="slotWidth"
                :height="chartHeight"
                fill="transparent"
              />

              <!-- Label do dia no eixo X -->
              <text
                v-if="shouldShowDayLabel(dIdx, days.length)"
                :x="day.xCenter"
                :y="SVG_HEIGHT - 10"
                text-anchor="middle"
                class="fill-subtle font-mono text-[10px]"
              >
                {{ day.dayLabel }}
              </text>
            </g>
          </g>
        </svg>
      </div>

      <!-- Estado vazio amigavel se nao houver nenhum uso no periodo -->
      <div
        v-if="!hasUsage"
        data-test="chart-empty-state"
        class="py-6 text-center"
      >
        <p class="text-xs font-medium text-subtle">
          {{ t('settings.api_tokens.usage_chart.empty.title') }}
        </p>
        <p class="mt-0.5 text-[11px] text-muted-foreground">
          {{ t('settings.api_tokens.usage_chart.empty.description') }}
        </p>
      </div>
    </div>

    <!-- Legenda dos tokens (ativa especialmente na visao por token) -->
    <div
      v-if="currentView === 'by_token' && (stats?.tokens.length ?? 0) > 0"
      data-test="chart-tokens-legend"
      class="mt-3 flex flex-wrap items-center gap-3 border-t border-border/60 pt-3"
    >
      <span class="text-xs font-medium text-subtle">
        {{ t('settings.api_tokens.usage_chart.legend.tokens') }}:
      </span>
      <div
        v-for="token in stats?.tokens"
        :key="token.id"
        class="flex items-center gap-1.5 text-xs"
      >
        <span
          class="size-2.5 shrink-0 rounded-full"
          :style="{ backgroundColor: getTokenColor(token.id, token.isRevoked) }"
        />
        <span :class="token.isRevoked ? 'text-subtle italic' : 'text-foreground'">
          {{ token.name }}
        </span>
      </div>
    </div>
  </div>
</template>
