<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAccountNav } from '@/composables/useAccountNav';
import { useRoutes } from '@/composables/useRoutes';
import { dashboard } from '@/routes';

/**
 * Sidebar da area autenticada.
 *
 * Uma navegacao so para a conta inteira: visao geral, historico, faturas e a
 * secao Conta (DESIGN: Painel do usuario). Os dois links fixos do starter kit
 * (Repository e Documentation, apontando para o repositorio do kit) sairam -
 * nao eram do site.
 */
const { t } = useI18n();
const groups = useAccountNav();
const routes = useRoutes();
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard.url()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                v-for="group in groups"
                :key="group.label"
                :label="group.label"
                :items="group.items"
            />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        :tooltip="t('account.nav.back_to_site')"
                    >
                        <a :href="routes.home.value">
                            <ArrowLeft />
                            <span>{{ t('account.nav.back_to_site') }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
