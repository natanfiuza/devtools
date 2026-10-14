<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

const { t } = useI18n();

// O breadcrumb so e renderizado com dois ou mais itens (AppHeader); este e o
// mesmo titulo da pagina, traduzido em settings.appearance.title.
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('settings.appearance.title')" />

    <h1 class="sr-only">{{ t('settings.appearance.title') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.appearance.title')"
            :description="t('settings.appearance.description')"
        />
        <AppearanceTabs />
    </div>
</template>
