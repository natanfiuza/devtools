<script setup lang="ts">
import LegalDocument from '@/components/LegalDocument.vue';
import PageHead from '@/components/PageHead.vue';

/**
 * Politica de Privacidade, publica e indexavel (`/{locale}/privacy`).
 *
 * As props vem do servidor: o e-mail de contato sai de config/legal.php e a
 * data da revisao e formatada no idioma da pagina.
 */
defineProps<{
    contactEmail: string | null;
    updatedLabel: string;
}>();

/** Ordem de leitura das secoes, igual em todos os idiomas. */
const sections = [
    'data',
    'purpose',
    'browser',
    'sharing',
    'cookies',
    'retention',
    'rights',
    'security',
    'changes',
] as const;
</script>

<template>
    <PageHead />

    <LegalDocument
        namespace="privacy"
        :sections="sections"
        :contact-email="contactEmail"
        :updated-label="updatedLabel"
    />
</template>
