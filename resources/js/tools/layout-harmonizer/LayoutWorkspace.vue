<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import { useLayoutSystem } from './composables/useLayoutSystem';
import type { LayoutViewMode } from './lib/boardView';
import SeedPicker from './components/SeedPicker.vue';
import SeedSummary from './components/SeedSummary.vue';
import StrategySelect from './components/StrategySelect.vue';
import ThemeBoard from './components/ThemeBoard.vue';
import ThemeComparison from './components/ThemeComparison.vue';
import ViewModeSwitch from './components/ViewModeSwitch.vue';

/**
 * Corpo da ferramenta: entrada a esquerda, quadro a direita; no mobile tudo
 * empilhado. O quadro so existe depois de uma seed; entrada invalida mantem o
 * ultimo resultado e mostra o aviso, sem trocar tema, storage ou rota.
 */

const { t } = useI18n();
const feedback = useCopyFeedback();

const {
    picker,
    strategy,
    backgroundText,
    hasSeed,
    needsBackground,
    backgroundInvalid,
    result,
    seed,
    notice,
    isStale,
} = useLayoutSystem();

const viewMode = ref<LayoutViewMode>('compare');

const noticeText = computed(() => {
    const parts: string[] = [];

    if (notice.value !== null) {
        parts.push(
            t(
                `tools.layout-harmonizer.states.${notice.value.replace(/-/g, '_')}`,
            ),
        );
    }

    if (isStale.value) {
        parts.push(t('tools.layout-harmonizer.states.stale_board'));
    }

    return parts.join(' ');
});

const singleTitle = computed(() =>
    viewMode.value === 'dark'
        ? t('tools.layout-harmonizer.view.board_dark')
        : t('tools.layout-harmonizer.view.board_light'),
);
</script>

<template>
    <div
        class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[360px_minmax(0,1fr)]"
        data-test="layout-workspace"
    >
        <section class="space-y-3 rounded-xl border p-4">
            <h2 class="text-sm font-semibold">
                {{ t('tools.layout-harmonizer.seed.title') }}
            </h2>
            <p class="text-xs text-muted-foreground">
                {{ t('tools.layout-harmonizer.seed.description') }}
            </p>

            <SeedPicker
                v-model:background-text="backgroundText"
                :picker="picker"
                :empty="!hasSeed"
                :background-visible="needsBackground"
                :background-invalid="backgroundInvalid"
            />

            <StrategySelect v-model="strategy" />

            <SeedSummary
                v-if="seed !== null && result !== null"
                :seed="seed"
                :strategy="result.strategy"
            />
        </section>

        <div class="space-y-4">
            <ViewModeSwitch v-if="result !== null" v-model="viewMode" />

            <p
                class="min-h-5 text-sm text-destructive"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                data-test="layout-notice"
            >
                {{ noticeText }}
            </p>

            <template v-if="result !== null">
                <ThemeComparison
                    v-if="viewMode === 'compare'"
                    :light="result.themes.light"
                    :dark="result.themes.dark"
                    :copy="feedback.copy"
                />
                <ThemeBoard
                    v-else
                    :board="result.themes[viewMode]"
                    :title="singleTitle"
                    :copy="feedback.copy"
                />
            </template>

            <section
                v-else
                class="rounded-xl border border-dashed p-8 text-center"
                data-test="layout-empty"
            >
                <h2 class="text-base font-medium">
                    {{ t('tools.layout-harmonizer.states.empty_title') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('tools.layout-harmonizer.states.empty_description') }}
                </p>
            </section>

            <p
                class="min-h-5 text-sm text-muted-foreground"
                role="status"
                aria-live="polite"
                data-test="layout-feedback"
            >
                {{ feedback.message }}
            </p>
        </div>
    </div>
</template>
