<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCopyFeedback } from '@/composables/useCopyFeedback';
import type { Rgba } from '@/lib/color';
import type { LayoutViewMode } from './lib/boardView';
import type { LayoutController } from './lib/controller';
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
 * A partir da F4 o corpo consome o `LayoutController` que o pai monta: o
 * workspace live regenera com o motor atual e o congelado exibe o snapshot
 * gravado. Restaurar e sugestoes so aparecem quando o modo as sustenta.
 */

const props = defineProps<{
    controller: LayoutController;
    /** Regeneracao explicita no modo congelado; ausente quando nao ha como. */
    regenerate?: (() => void) | null;
}>();

const { t } = useI18n();
const feedback = useCopyFeedback();

const picker = props.controller.picker;
const strategy = props.controller.strategy;
const backgroundText = props.controller.backgroundText;
const secondaryText = props.controller.secondaryText;
const hasSeed = props.controller.hasSeed;
const needsBackground = props.controller.needsBackground;
const backgroundInvalid = props.controller.backgroundInvalid;
const secondaryInvalid = props.controller.secondaryInvalid;
const secondarySource = props.controller.secondarySource;
const result = props.controller.result;
const seed = props.controller.seed;
const notice = props.controller.notice;
const isStale = props.controller.isStale;
const target = props.controller.target;
const editState = props.controller.editState;
const editNotice = props.controller.editNotice;
const suggestions = props.controller.suggestions;
const editable = props.controller.editable;
const pendingRegeneration = props.controller.pendingRegeneration;
const canRestoreEdits = props.controller.canRestoreEdits;
const canSuggest = props.controller.canSuggest;
const mode = props.controller.mode;
const applyColor = props.controller.applyColor;
const toggleTokenLock = props.controller.toggleTokenLock;
const acceptAdjustment = props.controller.acceptAdjustment;
const acceptAdjustmentInBothThemes =
    props.controller.acceptAdjustmentInBothThemes;
const restoreTokenEdit = props.controller.restoreTokenEdit;
const restoreThemeEdits = props.controller.restoreThemeEdits;
const restoreAllEdits = props.controller.restoreAllEdits;
const resetSecondary = props.controller.resetSecondary;

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

function requestRegeneration(): void {
    props.regenerate?.();
}
</script>

<template>
    <div
        class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[360px_minmax(0,1fr)]"
        :data-mode="mode"
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
                    v-if="result !== null && canRestoreEdits"
                    :counts="restoreCounts"
                    :total="restoreTotal"
                    :restore-theme="restoreThemeEdits"
                    :restore-all="restoreAllEdits"
                />
            </div>

            <p
                v-if="!canRestoreEdits && result !== null"
                class="text-xs text-muted-foreground"
                data-test="layout-restore-disabled"
            >
                {{ t('tools.layout-harmonizer.lifecycle.restore_disabled') }}
            </p>

            <div
                v-if="pendingRegeneration"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-500/50 bg-amber-500/10 px-3 py-2 text-sm"
                role="status"
                aria-live="polite"
                data-test="layout-pending-regeneration"
            >
                <span>
                    {{
                        t(
                            'tools.layout-harmonizer.lifecycle.pending_regeneration',
                        )
                    }}
                </span>
                <button
                    v-if="regenerate"
                    type="button"
                    class="rounded-md border border-border px-2 py-1 text-xs font-medium hover:bg-muted"
                    data-test="layout-regenerate"
                    @click="requestRegeneration"
                >
                    {{ t('tools.layout-harmonizer.lifecycle.regenerate') }}
                </button>
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
                    v-if="canSuggest"
                    v-model:target="target"
                    :suggestions="suggestions"
                    :accept="acceptAdjustment"
                    :accept-both="acceptAdjustmentInBothThemes"
                />
                <p
                    v-else
                    class="text-xs text-muted-foreground"
                    data-test="layout-suggestions-off"
                >
                    {{ t('tools.layout-harmonizer.lifecycle.suggestions_off') }}
                </p>
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
