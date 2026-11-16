<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import type { LayoutThemeBoard } from '../engine';
import ThemeBoard from './ThemeBoard.vue';

/**
 * Comparacao lado a lado: uma coluna no mobile, duas no desktop. Os dois
 * quadros sao independentes; nenhum tema do site e alterado.
 */

defineProps<{
    light: LayoutThemeBoard;
    dark: LayoutThemeBoard;
    copy: (value: string, success?: string) => Promise<boolean>;
}>();

const { t } = useI18n();
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
        />
        <ThemeBoard
            :board="dark"
            :title="t('tools.layout-harmonizer.view.board_dark')"
            :copy="copy"
        />
    </div>
</template>
