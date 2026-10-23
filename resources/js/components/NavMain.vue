<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

/**
 * Um grupo da navegacao lateral: titulo e itens.
 *
 * O titulo vem de fora (i18n) - antes era o texto fixo "Platform", que so
 * servia enquanto existia um grupo unico.
 */
defineProps<{
    /** Titulo do grupo, ja traduzido. */
    label: string;
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
  <SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>{{ label }}</SidebarGroupLabel>
    <SidebarMenu>
      <SidebarMenuItem
        v-for="item in items"
        :key="item.title"
      >
        <SidebarMenuButton
          as-child
          :is-active="isCurrentUrl(item.href)"
          :tooltip="item.title"
        >
          <Link :href="item.href">
            <component :is="item.icon" />
            <span>{{ item.title }}</span>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>
