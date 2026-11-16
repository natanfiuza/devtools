<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import LayoutWorkspace from './LayoutWorkspace.vue';

/**
 * Corpo da ferramenta Layout Harmonizer. Cabecalho, SEO e limites vem de
 * pages/tools/Show.vue; roda inteiro no navegador (ADR-010).
 */

const { t } = useI18n();
</script>

<template>
    <div class="space-y-4" data-test="layout-harmonizer-tool">
        <p class="text-sm text-muted-foreground">
            {{ t('tools.layout-harmonizer.intro') }}
        </p>

        <LayoutWorkspace />
    </div>
</template>
