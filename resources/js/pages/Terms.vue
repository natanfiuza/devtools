<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LegalDocument from '@/components/LegalDocument.vue';
import PageHead from '@/components/PageHead.vue';

/**
 * Termos de Uso, publicos e indexaveis (`/{locale}/terms`).
 */
const { t } = useI18n();

defineProps<{
    contactEmail: string | null;
    updatedLabel: string;
}>();

/** Ordem de leitura das secoes, igual em todos os idiomas. */
const sections = [
    'service',
    'account',
    'use',
    'content',
    'availability',
    'warranty',
    'changes',
    'law',
] as const;

/** A introducao e o resumo da pagina - o mesmo no texto e no compartilhamento. */
const description = computed<string>(() =>
    t('legal.terms.intro', { app: t('app.brand') }),
);
</script>

<template>
    <PageHead :title="t('legal.terms.title')" :description="description" />

    <LegalDocument
        namespace="terms"
        :sections="sections"
        :contact-email="contactEmail"
        :updated-label="updatedLabel"
    />
</template>
