<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LegalDocument from '@/components/LegalDocument.vue';

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
</script>

<template>
    <Head :title="t('legal.terms.title')" />

    <LegalDocument
        namespace="terms"
        :sections="sections"
        :contact-email="contactEmail"
        :updated-label="updatedLabel"
    />
</template>
