<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Rgba } from '@/lib/color';
import type { LayoutThemeBoard, LayoutTokenId } from '../engine';
import { checksByToken, groupTokens } from '../lib/boardView';
import type { LayoutOverride } from '../lib/editState';
import ContrastSummary from './ContrastSummary.vue';
import FeedbackPreview from './FeedbackPreview.vue';
import TokenGroup from './TokenGroup.vue';

/**
 * Um tema completo: os 33 tokens em cinco familias, a previa de feedback e o
 * resumo de contraste. Nao conhece o outro tema nem o tema do site.
 */

const props = withDefaults(
    defineProps<{
        board: LayoutThemeBoard;
        title: string;
        copy: (value: string, success?: string) => Promise<boolean>;
        locks?: Partial<Record<LayoutTokenId, true>>;
        overrides?: Partial<Record<LayoutTokenId, LayoutOverride>>;
        suggestedTokens?: readonly LayoutTokenId[];
        editable?: boolean;
        applyColor?: (token: LayoutTokenId, color: Rgba) => void;
        toggleLock?: (token: LayoutTokenId) => void;
        restoreToken?: (token: LayoutTokenId) => void;
    }>(),
    {
        locks: () => ({}),
        overrides: () => ({}),
        suggestedTokens: () => [],
        editable: true,
        applyColor: () => {},
        toggleLock: () => {},
        restoreToken: () => {},
    },
);

const { t } = useI18n();

const groups = computed(() => groupTokens(props.board.tokens));
const checks = computed(() => checksByToken(props.board));

function purposeOf(id: LayoutTokenId): string {
    return t(`tools.layout-harmonizer.tokens.${id}`);
}

function familyTitle(family: (typeof groups.value)[number]['family']): string {
    return t(`tools.layout-harmonizer.families.${family}`);
}
</script>

<template>
  <section
    role="region"
    :aria-label="
      t('tools.layout-harmonizer.a11y.board_section', { theme: title })
    "
    class="space-y-4 rounded-xl border p-4"
    data-test="theme-board"
  >
    <h2 class="text-base font-semibold">
      {{ title }}
    </h2>

    <TokenGroup
      v-for="group in groups"
      :key="group.family"
      :title="familyTitle(group.family)"
      :tokens="group.tokens"
      :checks-by-token="checks"
      :purpose-of="purposeOf"
      :copy="copy"
      :locks="locks"
      :overrides="overrides"
      :suggested-tokens="suggestedTokens"
      :editable="editable"
      :apply-color="applyColor"
      :toggle-lock="toggleLock"
      :restore-token="restoreToken"
    />

    <FeedbackPreview :tokens="board.tokens" />
    <ContrastSummary :checks="board.checks" />
  </section>
</template>
