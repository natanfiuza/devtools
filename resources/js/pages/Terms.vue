<script setup lang="ts">
import LegalDocument from '@/components/LegalDocument.vue';
import PageHead from '@/components/PageHead.vue';

/**
 * Termos de Uso, publicos e indexaveis (`/{locale}/terms`).
 */
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
    <PageHead />

    <LegalDocument
        namespace="terms"
        :sections="sections"
        :contact-email="contactEmail"
        :updated-label="updatedLabel"
    />
</template>
