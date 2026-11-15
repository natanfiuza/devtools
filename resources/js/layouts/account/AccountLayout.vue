<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AnalyticsScript from '@/components/AnalyticsScript.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConsentBanner from '@/components/ConsentBanner.vue';
import ImpersonationBanner from '@/components/admin/ImpersonationBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import { useAccountNav } from '@/composables/useAccountNav';
import { useActiveSection } from '@/composables/useActiveSection';
import type { NavItem } from '@/types';

/**
 * Area autenticada (DESIGN: Painel do usuario).
 *
 * Um layout so para a conta inteira - visao geral, historico, faturas e a secao
 * Conta. Substitui a sidebar de um item do starter kit e a navegacao lateral de
 * settings, que dividiam a mesma area em duas.
 *
 * O conteudo respira em 896px: as telas da conta sao formularios e tabelas
 * curtas - o painel administrativo, que e mais largo, tem layout proprio.
 *
 * A conta e medida como o site (ADR-032): o banner e a tag sobem aqui tambem,
 * com a decisao vinda do servidor. O admin fica de fora - la nao ha banner nem
 * tag, nem com aceite.
 */
const { t } = useI18n();
const groups = useAccountNav();

/** A secao aberta e decidida sobre a lista inteira, e nao por grupo. */
const items = computed<NavItem[]>(() =>
    groups.value.flatMap((group) => group.items),
);

const { activeTitle } = useActiveSection(items, () => t('account.nav.menu'));
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <ImpersonationBanner />
            <AppSidebarHeader :title="activeTitle" />

            <!-- `AppContent` ja rende o `<main>`; aqui e so a medida. O
                 `space-y` separa as telas que tem mais de um bloco na raiz
                 (o Perfil, por exemplo, tem formulario, exportacao e
                 exclusao). -->
            <div class="w-full flex-1 px-4 py-6">
                <div class="mx-auto w-full max-w-4xl space-y-12">
                    <slot />
                </div>
            </div>
        </AppContent>

        <ConsentBanner />
        <AnalyticsScript />
        <Toaster />
    </AppShell>
</template>
