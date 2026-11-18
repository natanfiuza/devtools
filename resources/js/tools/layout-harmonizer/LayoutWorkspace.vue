<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import type { Rgba } from '@/lib/color';
import { useLayoutSystem } from './composables/useLayoutSystem';
import type { LayoutViewMode } from './lib/boardView';
import type { LayoutTheme, LayoutTokenId } from './engine';
import RestoreControls from './components/RestoreControls.vue';
import SecondaryField from './components/SecondaryField.vue';
import SeedPicker from './components/SeedPicker.vue';
import SeedSummary from './components/SeedSummary.vue';
import StrategySelect from './components/StrategySelect.vue';
import SuggestionsPanel from './components/SuggestionsPanel.vue';
import ThemeBoard from './components/ThemeBoard.vue';
import ThemeComparison from './components/ThemeComparison.vue';
import ViewModeSwitch from './components/ViewModeSwitch.vue';

/**
 * Corpo da ferramenta: entrada a esquerda, quadro a direita; no mobile tudo
 * empilhado. O quadro so existe depois de uma seed; entrada invalida mantem o
 * ultimo resultado e mostra o aviso, sem trocar tema, storage ou rota.
 *
 * Na F3 a coluna da esquerda ganhou a secundaria manual, e abaixo dos quadros
 * ficam as sugestoes de contraste com seus aceites explicitos.
 */

const { t } = useI18n();
const feedback = useCopyFeedback();

const {
    picker,
    strategy,
    backgroundText,
    secondaryText,
    hasSeed,
    needsBackground,
    backgroundInvalid,
    secondaryInvalid,
    secondarySource,
    result,
    seed,
    notice,
    isStale,
    target,
    editState,
    editNotice,
    suggestions,
    editable,
    applyColor,
    toggleTokenLock,
    acceptAdjustment,
    acceptAdjustmentInBothThemes,
    restoreTokenEdit,
    restoreThemeEdits,
    restoreAllEdits,
    resetSecondary,
} = useLayoutSystem();

const viewMode = ref<LayoutViewMode>('compare');

const singleTheme = computed<LayoutTheme>(() =>
    viewMode.value === 'dark' ? 'dark' : 'light',
);

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

const editNoticeText = computed(() =>
    editNotice.value === null
        ? ''
        : t(`tools.layout-harmonizer.edit_notice.${editNotice.value}`),
);

const singleTitle = computed(() =>
    viewMode.value === 'dark'
        ? t('tools.layout-harmonizer.view.board_dark')
        : t('tools.layout-harmonizer.view.board_light'),
);

/** Tokens com sugestao aplicavel, por tema, para o indicador da celula. */
const suggestedByTheme = computed<
    Partial<Record<LayoutTheme, readonly LayoutTokenId[]>>
>(() => {
    const light: LayoutTokenId[] = [];
    const dark: LayoutTokenId[] = [];

    for (const suggestion of suggestions.value) {
        if (suggestion.proposal === null) {
            continue;
        }

        (suggestion.theme === 'light' ? light : dark).push(suggestion.token);
    }

    return { light, dark };
});

const restoreCounts = computed<Record<LayoutTheme, number>>(() => ({
    light: countInTheme('light'),
    dark: countInTheme('dark'),
}));

const restoreTotal = computed(
    () => restoreCounts.value.light + restoreCounts.value.dark,
);

function countInTheme(theme: LayoutTheme): number {
    const overrides = editState.value.overrides[theme] ?? {};
    const locks = editState.value.locks[theme] ?? {};

    return new Set([...Object.keys(overrides), ...Object.keys(locks)]).size;
}

function applyIn(theme: LayoutTheme, token: LayoutTokenId, color: Rgba): void {
    applyColor(theme, token, color);
}

function toggleIn(theme: LayoutTheme, token: LayoutTokenId): void {
    toggleTokenLock(theme, token);
}

function restoreIn(theme: LayoutTheme, token: LayoutTokenId): void {
    restoreTokenEdit(theme, token);
}
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

            <SecondaryField
                v-model:secondary-text="secondaryText"
                :invalid="secondaryInvalid"
                :manual="secondarySource === 'manual'"
                @reset="resetSecondary"
            />

            <SeedSummary
                v-if="seed !== null && result !== null"
                :seed="seed"
                :strategy="result.strategy"
                :secondary-source="secondarySource"
            />
        </section>

        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <ViewModeSwitch v-if="result !== null" v-model="viewMode" />

                <RestoreControls
                    v-if="result !== null"
                    :counts="restoreCounts"
                    :total="restoreTotal"
                    :restore-theme="restoreThemeEdits"
                    :restore-all="restoreAllEdits"
                />
            </div>

            <p
                class="min-h-5 text-sm text-destructive"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                data-test="layout-notice"
            >
                {{ noticeText }}
            </p>

            <p
                class="min-h-5 text-sm text-muted-foreground"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                data-test="layout-edit-notice"
            >
                {{ editNoticeText }}
            </p>

            <template v-if="result !== null">
                <ThemeComparison
                    v-if="viewMode === 'compare'"
                    :light="result.themes.light"
                    :dark="result.themes.dark"
                    :copy="feedback.copy"
                    :edit-state="editState"
                    :suggested="suggestedByTheme"
                    :editable="editable"
                    :apply-color="applyIn"
                    :toggle-lock="toggleIn"
                    :restore-token="restoreIn"
                />
                <ThemeBoard
                    v-else
                    :board="result.themes[singleTheme]"
                    :title="singleTitle"
                    :copy="feedback.copy"
                    :locks="editState.locks[singleTheme] ?? {}"
                    :overrides="editState.overrides[singleTheme] ?? {}"
                    :suggested-tokens="suggestedByTheme[singleTheme] ?? []"
                    :editable="editable"
                    :apply-color="
                        (token, color) => applyIn(singleTheme, token, color)
                    "
                    :toggle-lock="(token) => toggleIn(singleTheme, token)"
                    :restore-token="(token) => restoreIn(singleTheme, token)"
                />

                <SuggestionsPanel
                    v-model:target="target"
                    :suggestions="suggestions"
                    :accept="acceptAdjustment"
                    :accept-both="acceptAdjustmentInBothThemes"
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
