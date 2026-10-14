<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CreditCard,
    LayoutGrid,
    Mail,
    ScrollText,
    Tag,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Toaster } from '@/components/ui/sonner';
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useActiveSection } from '@/composables/useActiveSection';
import { useRoutes } from '@/composables/useRoutes';
import admin from '@/routes/admin';
import type { NavItem } from '@/types';

/**
 * Painel administrativo (DESIGN: Painel administrativo).
 *
 * Layout proprio, sem o header publico: sidebar de secoes fixas e barra
 * superior com o titulo da secao, o seletor de idioma e o menu do usuario. O
 * conteudo respira num container de 1280px - o painel e mais largo que o site.
 */
const { t } = useI18n();
const page = usePage();
const routes = useRoutes();

const navItems = computed<NavItem[]>(() => [
    {
        title: t('admin.nav.overview'),
        href: admin.dashboard.url(),
        icon: LayoutGrid,
    },
    { title: t('admin.nav.users'), href: admin.users.index.url(), icon: Users },
    {
        title: t('admin.nav.subscribers'),
        href: admin.subscribers.index.url(),
        icon: Mail,
    },
    { title: t('admin.nav.plans'), href: admin.plans.index.url(), icon: Tag },
    {
        title: t('admin.nav.payments'),
        href: admin.payments.index.url(),
        icon: CreditCard,
    },
    {
        title: t('admin.nav.audit'),
        href: admin.audit.index.url(),
        icon: ScrollText,
    },
]);

/**
 * A secao aberta marca o item da sidebar (`aria-current`) e escreve o titulo da
 * barra - a regra e a mesma da area da conta (useActiveSection).
 */
const { activeHref, activeTitle } = useActiveSection(navItems, () =>
    t('admin.nav.panel'),
);
</script>

<template>
    <div class="flex min-h-screen bg-background text-foreground">
        <aside
            class="sticky top-0 hidden h-screen w-60 shrink-0 flex-col border-r border-border lg:flex"
        >
            <div class="flex h-14 items-center px-4">
                <Link
                    :href="admin.dashboard.url()"
                    class="font-mono text-sm font-medium"
                >
                    devtools<span class="text-accent"> admin</span>
                </Link>
            </div>

            <nav
                class="flex-1 space-y-1 px-2 py-2"
                :aria-label="t('admin.nav.panel')"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.title"
                    :href="item.href"
                    class="flex h-9 items-center gap-2 rounded-md px-3 text-sm"
                    :class="
                        item.href === activeHref
                            ? 'bg-accent/10 text-foreground'
                            : 'text-subtle hover:bg-muted hover:text-foreground'
                    "
                    :aria-current="
                        item.href === activeHref ? 'page' : undefined
                    "
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.title }}
                </Link>
            </nav>

            <div class="border-t border-border p-2">
                <a
                    :href="routes.home.value"
                    class="flex h-9 items-center gap-2 rounded-md px-3 text-sm text-subtle hover:bg-muted hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    {{ t('admin.nav.back_to_site') }}
                </a>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-10 flex h-14 items-center justify-between border-b border-border bg-background px-4 sm:px-6"
            >
                <h1 class="truncate text-sm font-medium">
                    {{ activeTitle }}
                </h1>

                <div class="flex items-center gap-1">
                    <a
                        :href="routes.home.value"
                        class="text-sm text-subtle hover:text-foreground lg:hidden"
                    >
                        {{ t('admin.nav.back_to_site') }}
                    </a>
                    <LocaleSwitcher />
                    <ThemeToggle />
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted"
                                data-test="admin-user-menu"
                            >
                                <UserInfo :user="page.props.auth.user" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="min-w-56">
                            <UserMenuContent :user="page.props.auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <main
                class="mx-auto w-full max-w-[1280px] flex-1 px-4 py-6 sm:px-6"
            >
                <slot />
            </main>
        </div>

        <Toaster />
    </div>
</template>
