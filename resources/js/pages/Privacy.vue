<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LegalDocument from '@/components/LegalDocument.vue';

/**
 * Politica de Privacidade, publica e indexavel (`/{locale}/privacy`).
 *
 * As props vem do servidor: o e-mail de contato sai de config/legal.php e a
 * data da revisao e formatada no idioma da pagina.
 */
const { t } = useI18n();

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
    <Head :title="t('legal.privacy.title')" />

    <LegalDocument
        namespace="privacy"
        :sections="sections"
        :contact-email="contactEmail"
        :updated-label="updatedLabel"
    />
</template>
