<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LegalDocument from '@/components/LegalDocument.vue';
import PageHead from '@/components/PageHead.vue';

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

/** A introducao e o resumo da pagina - o mesmo no texto e no compartilhamento. */
const description = computed<string>(() =>
    t('legal.privacy.intro', { app: t('app.brand') }),
);
</script>

<template>
    <PageHead
        :title="t('legal.privacy.title')"
        :description="description"
    />

    <LegalDocument
        namespace="privacy"
        :sections="sections"
        :contact-email="contactEmail"
        :updated-label="updatedLabel"
    />
</template>
