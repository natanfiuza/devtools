<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { Rgba } from '@/lib/color';
import type { LayoutTheme, LayoutThemeBoard, LayoutTokenId } from '../engine';
import { emptyEditState, type LayoutEditState } from '../lib/editState';
import ThemeBoard from './ThemeBoard.vue';

/**
 * Comparacao lado a lado: uma coluna no mobile, duas no desktop. Os dois
 * quadros sao independentes; nenhum tema do site e alterado. Cada tema tem
 * seus proprios bloqueios, overrides e sugestoes.
 */

const props = withDefaults(
    defineProps<{
        light: LayoutThemeBoard;
        dark: LayoutThemeBoard;
        copy: (value: string, success?: string) => Promise<boolean>;
        editState?: LayoutEditState;
        suggested?: Partial<Record<LayoutTheme, readonly LayoutTokenId[]>>;
        editable?: boolean;
        applyColor?: (
            theme: LayoutTheme,
            token: LayoutTokenId,
            color: Rgba,
        ) => void;
        toggleLock?: (theme: LayoutTheme, token: LayoutTokenId) => void;
        restoreToken?: (theme: LayoutTheme, token: LayoutTokenId) => void;
    }>(),
    {
        editState: () => emptyEditState(),
        suggested: () => ({}),
        editable: true,
        applyColor: () => {},
        toggleLock: () => {},
        restoreToken: () => {},
    },
);

const { t } = useI18n();

function locks(theme: LayoutTheme) {
    return props.editState.locks[theme] ?? {};
}

function overrides(theme: LayoutTheme) {
    return props.editState.overrides[theme] ?? {};
}

function suggestedTokens(theme: LayoutTheme): readonly LayoutTokenId[] {
    return props.suggested[theme] ?? [];
}

function apply(theme: LayoutTheme, token: LayoutTokenId, color: Rgba): void {
    props.applyColor(theme, token, color);
}

function toggle(theme: LayoutTheme, token: LayoutTokenId): void {
    props.toggleLock(theme, token);
}

function restore(theme: LayoutTheme, token: LayoutTokenId): void {
    props.restoreToken(theme, token);
}
</script>

<template>
    <div
        class="grid grid-cols-1 gap-4 lg:grid-cols-2"
        data-test="theme-comparison"
    >
        <ThemeBoard
            :board="light"
            :title="t('tools.layout-harmonizer.view.board_light')"
            :copy="copy"
            :locks="locks('light')"
            :overrides="overrides('light')"
            :suggested-tokens="suggestedTokens('light')"
            :editable="editable"
            :apply-color="(token, color) => apply('light', token, color)"
            :toggle-lock="(token) => toggle('light', token)"
            :restore-token="(token) => restore('light', token)"
        />
        <ThemeBoard
            :board="dark"
            :title="t('tools.layout-harmonizer.view.board_dark')"
            :copy="copy"
            :locks="locks('dark')"
            :overrides="overrides('dark')"
            :suggested-tokens="suggestedTokens('dark')"
            :editable="editable"
            :apply-color="(token, color) => apply('dark', token, color)"
            :toggle-lock="(token) => toggle('dark', token)"
            :restore-token="(token) => restore('dark', token)"
        />
    </div>
</template>
